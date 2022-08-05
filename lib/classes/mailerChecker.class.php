<?php

class mailerChecker
{
    /**
     * @var mailerMessageLogModel
     */
    protected $log_model;

    public function __construct($options = array())
    {
        $this->log_model = new mailerMessageLogModel();
        $this->options = $options;
        if (!isset($this->options['limit'])) {
            $this->options['limit'] = wa()->getEnv() === 'cli' ? 2000 : 100;
        }
    }

    protected function getLockFile()
    {
        return wa()->getDataPath('lock/checker.lock', false, 'mailer');
    }

    public function check($id = false, $sender_id = false)
    {
        $filename = $this->getLockFile();
        touch($filename);
        @chmod($filename, 0666);
        $x = @fopen($filename, "r+");
        if (!$x || !flock($x, LOCK_EX|LOCK_NB)) {
            fclose($x);
            return;
        }

        $return_paths = array();
        $rpm = new mailerReturnPathModel();
        if ($id) {
            if (is_numeric($id)) {
                $row = $rpm->getById($id);
            } else {
                $row = $rpm->getByField('email', $id);
            }

            if ($row && $rpm->isActive($row)) {
                $return_paths = array($row);
            }
        } else if ($sender_id) {
            $return_paths = [];
        } else {
            $return_paths = $rpm->getActive();
        }

        $this->checkExtendedTransports($sender_id);

        if ($return_paths) {
            /**
             * @event return_path.check
             *
             * Mailer is about to start checking return-path mailboxes for bounced mail
             *
             * @param array[string]array $params['return_paths'] list of rows from mailer_return_path
             * @return void
             */
            $evt_params = array(
                'return_paths' => &$return_paths,
            );
            wa()->event('return_path.check', $evt_params);

            foreach ($return_paths as $row) {
                try {
                    $this->checkMail($row);
                    $rpm->logError($row['id'], null);
                } catch (Exception $e) {
                    $rpm->logError($row['id'], $e->getMessage());
                }
            }
        }

        flock($x, LOCK_UN);
        fclose($x);
    }

    protected $last_mail_reader = null;
    public function closeMailReader()
    {
        if ($this->last_mail_reader && method_exists($this->last_mail_reader, 'close')) {
            try {
                echo "Closing...\n";
                $this->last_mail_reader->close();
                echo 'Closed!';
            } catch (Exception $e) {
                echo 'Exception while closing!';
            }
        }
    }

    protected function checkMail($return_path)
    {
        $mail_reader = new waMailPOP3($return_path); // new waMailPOP3Tester();
        // get STAT for inbox
        $n = $mail_reader->count();
        // no new emails
        if (!$n || !$n[0]) {
            return false;
        }

        // Make sure the connection will be closed even when something really bad happens.
        // This ensures that messages we mark for deletion get actually deleted.
        $this->last_mail_reader = $mail_reader;
        register_shutdown_function(array($this, 'closeMailReader'));

        // decode all messages
        $mail_decode = new waMailDecode();
        $temp_path = wa()->getTempPath('mailcheck', 'mailer');
        $source_save_path = wa()->getDataPath('mailcheck/'.date('Y-m').'/', false, 'mailer');
        $pattern = '/X-Log-ID:[\s\t]*([0-9]+)/is';
        $processed_count = 0;
        for ($i = 1; $i <= $n[0]; $i++) {
            $uniq_id = isset($ids[$i]) ? $ids[$i].'.'.$return_path['server'] : uniqid(true);
            $mail_path = $temp_path.'/'.$uniq_id;
            if (file_exists($mail_path)) {
                continue;
            }
            // create temp folder for message
            waFiles::create($mail_path);
            // save message to local
            $mail_reader->get($i, $mail_path.'/mail.eml');

            try {
                $mail = $mail_decode->decode($mail_path.'/mail.eml');
            } catch (Exception $e) {
                // Save message for debugging
                $dest = $source_save_path.'error-parsing/'.basename($mail_path);
                if (file_exists($dest)) {
                    waFiles::delete($dest);
                }
                waFiles::move($mail_path, $dest);
                waFiles::delete($mail_path); // being paranoid
                $mail_reader->delete($i);
                continue;
            }

            // Get $log_id from email text
            $log_id = false;
            if ($mail['headers']['to']) {
                $to = explode('@', $mail['headers']['to'][0]['email'], 2);
                $to = explode('+', $to[0], 2);
                if (isset($to[1]) && wa_is_int($to[1])) {
                    $log_id = $to[1];
                }
            }
            if (!$log_id && isset($mail['message/rfc822'])) {
                if (preg_match($pattern, $mail['message/rfc822'], $match)) {
                    $log_id = $match[1];
                }
            }
            if (!$log_id && isset($mail['text/plain'])) {
                if (preg_match($pattern, $mail['text/plain'], $match)) {
                    $log_id = $match[1];
                }
            }

            if ($log_id) {

                $full_error_text = $this->getError($mail);
                list($error_type, $error_fatal) = self::classifyBounce($full_error_text);

                // Mark recipient as bounced
                $this->log_model->setStatus($log_id, -2, $full_error_text, $error_type, $error_fatal);

                // Save message for debugging
                if (waSystemConfig::isDebug()) {
                    $dest = $source_save_path.$log_id;
                    if (file_exists($dest)) {
                        waFiles::delete($dest);
                    }
                    waFiles::move($mail_path, $dest);
                }
            } else {
                // Save message for debugging
                if (waSystemConfig::isDebug()) {
                    $dest = $source_save_path.'error-no-log-id/'.basename($mail_path);
                    if (file_exists($dest)) {
                        waFiles::delete($dest);
                    }
                    waFiles::move($mail_path, $dest);
                }
            }

            waFiles::delete($mail_path);
            $mail_reader->delete($i);
            $processed_count++;
            if (!empty($this->options['limit']) && $processed_count >= $this->options['limit']) {
                break;
            }
        }
        $mail_reader->close();
        $this->last_mail_reader = null;
    }

    protected function getError($mail)
    {
        if (isset($mail['message/delivery-status'])) {
            $error = $mail['message/delivery-status'];
        } else {
            $error = $mail['text/plain'];
            $error = preg_replace("/-{2,8}\s+Original\s+message\s+-{2,8}.*/is", "", $error);
        }
        return $error;
    }

    protected function getSendersWithExtendedTransport($sender_id=false)
    {
        $sender_model = new mailerSenderModel();
        $sender_params_model = new mailerSenderParamsModel();

        // Load senders
        if ($sender_id) {
            $sender = $sender_model->getById($sender_id);
            if (!$sender) {
                return;
            }
            $senders = [$sender_id => $sender];
        } else {
            // Built in extended transports
            $senders = $sender_model->getByType('wa');

            // Extended transports from plugins
            $senders += $sender_model->getSendersWithUnknownType();
        }
        $sender_params_model->loadForSenders($senders);

        // Load sender transports
        foreach($senders as $sender_id => &$sender) {
            $type = $sender['params']['type'];
            if ($type == 'wa') {
                $transport = new mailerWaTransport($sender, $sender['params']);
            } else {
                $transport = mailerSimpleMessage::getPluginTransport($sender, $sender['params']);
                if (!$transport || !($transport instanceof mailerBounceTransportInterface)) {
                    unset($senders[$sender_id]);
                    continue;
                }
            }
            $sender['transport'] = $transport;
        }
        unset($sender);

        return $senders;
    }

    protected function checkExtendedTransports($sender_id=false)
    {
        $senders = $this->getSendersWithExtendedTransport($sender_id);
        if (!$senders) {
            return;
        }

        /**
         * @event extended_transport.check
         *
         * Mailer is about to start checking extended transports for bounced mail
         *
         * @param array[string]array $params['return_paths'] list of rows from mailer_return_path
         * @return void
         */
        $evt_params = array(
            'senders' => &$senders,
        );
        wa()->event('extended_transport.check', $evt_params);
        register_shutdown_function(array($this, 'closeTransport'));

        foreach($senders as $sender_id => $sender) {
            if (empty($sender['transport']) || !($sender['transport'] instanceof mailerBounceTransportInterface)) {
                continue;
            }
            $this->last_extended_transport = $sender['transport'];
            $bounces = $sender['transport']->getBounces($this->options['limit']);
            $this->last_extended_transport = null;
            if (!is_array($bounces)) {
                continue;
            }

            foreach($bounces as $log_id => $bounce) {
                if (is_array($bounce)) {
                    $full_error_text = ifset($bounce, 'error_text', '');
                    $error_type = ifset($bounce, 'error_type', null);
                    $error_fatal = !empty($bounce['is_fatal']);
                } else {
                    $full_error_text = $bounce;
                    $error_type = null;
                    $error_fatal = false;
                }

                if (!isset($error_type)) {
                    list($error_type, $error_fatal) = self::classifyBounce($full_error_text);
                }

                // Mark recipient as bounced
                $this->log_model->setStatus($log_id, mailerMessageLogModel::STATUS_NOT_DELIVERED, $full_error_text, $error_type, $error_fatal);
            }
        }
    }

    // If this bounce can be classified, do so
    public static function classifyBounce($full_error_text)
    {
        static $bounce_types = null;
        if ($bounce_types === null) {
            $bounce_types = wa('mailer')->getConfig()->getBounceTypes();
        }

        $error_type = null;
        $error_fatal = false; // When failed to parse a bounce, count it as non-fatal

        foreach ($bounce_types as $type => $bt) {
            if (preg_match($bt['regex'], $full_error_text)) {
                $error_type = $type;
                $error_fatal = $bt['fatal'];
                break;
            }
        }

        return array($error_type, $error_fatal);
    }

    protected $last_extended_transport = null;
    public function closeTransport()
    {
        if ($this->last_extended_transport) {
            try {
                echo "Closing transport...\n";
                $this->last_extended_transport->bounceCheckerShutdown();
                echo 'Closed!';
            } catch (Exception $e) {
                echo 'Exception while closing transport!';
            }
        }
    }
}

/** Debugging helper. Emulates waMailPOP3. */
class waMailPOP3Tester
{
    protected $messages = array(false);

    public function __construct() {
        $this->messages = array(false);
        for ($i = 1000; $i < 10000; $i++) {
            $this->messages[] = "X-Log-ID: 4291\nKinda some text here. Not even in proper email format: it will still parse nicely because of X-Log-ID.\nUnrouteable address. Naturally. And mailbox is full, too.";
        }
    }

    public function count() {
        return array(count($this->messages)-1);
    }

    public function get($i, $file) {
        if (!isset($this->messages[$i])) {
            return;
        }
        file_put_contents($file, $this->messages[$i]);
    }

    public function delete($i) {
        // Nothing to do...
    }

    public function close() {
        // Nothing to do...
    }
}

