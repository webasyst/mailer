<?php
class mailerWaTransport implements mailerExtendedTransportInterface, mailerBounceTransportInterface
{
    protected $data;
    protected $_eventDispatcher;
    protected $api;
    protected $is_test;
    protected $campaign;
    protected $spamtest_message_data;

    public function __construct($sender, $params)
    {
        $this->data = (array) $sender;
        $this->data['params'] = $params;
        $this->_eventDispatcher = new Swift_Events_SimpleEventDispatcher();
        $this->api = new mailerWaTransportServiceApi();
    }

    /**
     * Convert a list of addresses as returned from Swift: email => name
     * into list of emails in WA API format ['email' => email, 'name' => name].
     *
     * If $return_first is true then will return single first address only.
     * When input contains no addresses and $return_first is true,
     * function will return false.
     *
     * @param array $addresses_from_swift email => name
     * @param bool $return_first
     * @return array|false
     */
    protected static function convertEmails($addresses_from_swift, $return_first=false)
    {
        $result = [];
        foreach(ifempty($addresses_from_swift, []) as $email => $name) {
            $result[] = [
                'email' => $email,
                'name' => $name,
            ];
        }
        return $return_first ? reset($result) : $result;
    }

    /**
     * Part of Swift Transport interface: send the given Message.
     * All WA Mailer transports must implement beforeSendPerformed and sendPerformed events.
     * Mailer does throttling via plugins connected to sendPerformed: AntiFloodPlugin and ThrottlerPlugin.
     *
     * @param Swift_Mime_Message $message
     * @param string[] &$failedRecipients to collect failures by-reference
     * @return int
     */
    public function send(Swift_Mime_Message $message, &$failedRecipients = null)
    {
        // Do not try to send anything when WebasystID is not connected
        if (!$this->api->isConnected()) {
            return 0;
        }

        $evt = $this->_eventDispatcher->createSendEvent($this, $message);
        $this->_eventDispatcher->dispatchEvent($evt, 'beforeSendPerformed');
        if ($evt->bubbleCancelled()) {
            return 0;
        }

        $message_data = [
            'to' => self::convertEmails($message->getTo()),
            'cc' => self::convertEmails($message->getCc()),
            'bcc' => self::convertEmails($message->getBcc()),
            'from' => self::convertEmails($message->getFrom(), true),
            'reply_to' => self::convertEmails($message->getReplyTo(), true),

            'subject' => $message->getSubject(),
            'html' => $message->getBody(),
            'plaintext' => null, // see below
            'unsubscribe_url' => null, // see below
            'message_log_id' => null, // see below
            'namespace' => 'mailer_wa',

            'campaign_id' => ifset($this->campaign, 'id', null),

            // Not supported by Mailer yet
            //'attachments' => [],
            //'inline_attachments' => [],
        ];

        // Message plaintext, if exists
        foreach($message->getChildren() as $e) {
            /** @var $e Swift_Mime_MimeEntity */
            if ($e->getNestingLevel() == Swift_Mime_MimeEntity::LEVEL_TOP) {
                continue;
            }
            if ($e->getContentType() == 'text/plain') {
                $message_data['plaintext'] = $e->getBody();
                break;
            }
        }

        foreach($message->getHeaders()->getAll() as $header) {
            /** @var $header Swift_Mime_Header */
            switch($header->getFieldName()) {
                case 'List-Unsubscribe':
                    $message_data['unsubscribe_url'] = trim($header->getFieldBody(), '<>');
                    break;
                case 'X-Log-ID':
                    $value = $header->getFieldBody();
                    if (is_numeric($value) && $value > 0) {
                        $message_data['message_log_id'] = $value;
                    }
                    break;
            }
        }

        foreach(['plaintext', 'unsubscribe_url', 'message_log_id'] as $k) {
            if (!isset($message_data[$k])) {
                unset($message_data[$k]);
            }
        }

        if (!$this->spamtest_message_data) {
            $this->spamtest_message_data = $message_data;
        }

        $res = $this->api->serviceCall('EMAIL', $message_data, waNet::METHOD_POST, ['request_format' => waNet::FORMAT_JSON]);

        $status = ifset($res, 'status', null);

        if ($status == 402) {
            // Insufficient funds. Signal the app that campaign sending should be paused
            throw new mailerTransportSoftfailException(_w('Not sufficient account balance. Please top up your balance to resume the campaign.'));
        } else {
            $successfully_sent_count = count((array) $message->getTo()) + count((array) $message->getCc()) + count((array) $message->getBcc());
            if ($status >= 300) {
                $error = ifset($res, 'response', 'error', null);

                if ($error == 'invalid_recipient') {
                    $evt->setFailedRecipients(((array)$message->getTo()) + ((array)$message->getCc()) + ((array)$message->getBcc()));
                    $successfully_sent_count = 0;
                } else {
                    $this->logError("Unable to send email:\n".wa_dump_helper($message_data)."\nAPI returned:\n".wa_dump_helper($res));

                    $error_description = ifset($res, 'response', 'error_description', _w('API error'));
                    throw new mailerTransportSoftfailException(sprintf_wp('Failed to send email: %s', $error_description));
                }
            }
        }

        $evt->setResult($successfully_sent_count);
        $this->_eventDispatcher->dispatchEvent($evt, 'sendPerformed');
        return $successfully_sent_count;
    }

    /**
     * Part of Swift Transport interface: registers event listeners.
     */
    public function registerPlugin(Swift_Events_EventListener $plugin)
    {
        $this->_eventDispatcher->bindEventListener($plugin);
    }

    /**
     * Part of Swift Transport interface: checks if a sending session is active.
     * e.g. for SMTP this tells if connected to server
     */
    public function isStarted()
    {
        return true;
    }

    /**
     * Part of Swift Transport interface: starts a sending session.
     * e.g. for SMTP this connects to server
     */
    public function start()
    {
    }

    /**
     * Part of Swift Transport interface: stops a sending session.
     * e.g. for SMTP this disconnects from server
     */
    public function stop()
    {
    }

    public function getBounces($total_limit)
    {
        if (!is_numeric($total_limit) || $total_limit <= 0 || $total_limit > 2000) {
            $total_limit = 2000;
        }
        try {
            $res = $this->api->serviceCall('BOUNCE', [
                'namespace' => 'mailer_wa',
                'limit' => $total_limit,
            ]);
        } catch (Exception $e) {
            $error_message = $e->getMessage().' ('.$e->getCode().')';
        }
        if (ifset($res, 'status', null) != 200) {
            $this->logError('Unable to fetch bounce emails: '.ifset($error_message, '')."\n".wa_dump_helper($res));
            return;
        }
        if (empty($res['response']) || !is_array($res['response'])) {
            return;
        }

        $result = [];
        while ($res['response']) {
            $batch = array_splice($res['response'], 0, 100);
            $ids = [];
            foreach($batch as $bounce) {
                $ids[] = $bounce['message_id'];
                if (!empty($bounce['log_id'])) {
                    $result[$bounce['log_id']] = $bounce['content'];

                    /*
                    $result[$bounce['log_id']] = [
                        'is_fatal' => $bounce['is_fatal'],
                        'error_type' => $bounce['type'],
                        'error_text' => $bounce['content'],
                    ];
                    */
                }
            }

            $this->api->serviceCall('BOUNCE', [
                'method' => 'delete',
                'namespace' => 'mailer_wa',
                'message_ids' => $ids,
            ], waNet::METHOD_POST);
        }

        return $result;
    }

    public function bounceCheckerShutdown()
    {
    }

    protected function logError($text)
    {
        waLog::log($text, "mailer/wa/error.log");
    }

    /**
     * Called once before sending a bunch of messages.
     */
    public function setCampaign($campaign, $is_test=false)
    {
        $this->campaign = $campaign;
        $this->is_test = $is_test;
        $this->spamtest_message_data = null;
    }

    public function getCampaignReport($campaign_id)
    {
        try {
            $res = $this->api->serviceCall('EMAIL_REPORT', [
                'namespace' => 'mailer_wa',
                'campaign_id' => $campaign_id,
            ]);
        } catch (Exception $e) {
            $error_message = $e->getMessage().' ('.$e->getCode().')';
        }
        if (ifset($res, 'status', null) != 200) {
            $this->logError('Unable to fetch report data: '.ifset($error_message, '')."\n".wa_dump_helper($res));
            return;
        }
        if (empty($res['response']) || !is_array($res['response'])) {
            return;
        }
        return $res['response'];
    }

    /**
     * Called after sending a bunch of test messages before campaign is started.
     * Returns HTML to show to user, explaining results of the test, if necessary.
     * @return string
     */
    public function testSendingReport()
    {
        // TODO implement SPAMCHECK service call
        return '';
    }
}
