<?php

/**
 * TODO: delete it latter
 * !!! No real reason for this class to be separate. Should combine it into mailerMessage eventually.
 */
class mailerSimpleMessage
{
    protected $data;
    protected $vars;

    public function __construct($data)
    {
        $this->data = $data;
    }

    /**
     * @return Swift_MailTransport|Swift_SendmailTransport|Swift_SmtpTransport
     */
    protected function getTransport()
    {
        if ($this->data['sender_id']) {
            $sender_model = new mailerSenderModel();
            $sender = $sender_model->getById($this->data['sender_id']);
            if (!$sender) {
                $sender = $sender_model->getEmptyRow();
                $sender['id'] = $this->data['sender_id'];
            }
            $params_model = new mailerSenderParamsModel();
            $params = $params_model->getBySender($this->data['sender_id']);
            if (!isset($params['type'])) {
                $params['type'] = 'mail';
            }
            switch ($params['type']) {
                case 'mail':
                    if (!empty($params['options'])) {
                        return mailerPhpMailTransport::newInstance($params['options']);
                    } else {
                        return mailerPhpMailTransport::newInstance();
                    }
                case 'sendmail':
                    if (empty($params['command'])) {
                        return Swift_SendmailTransport::newInstance();
                    } else {
                        return Swift_SendmailTransport::newInstance($params['command']);
                    }
                case 'smtp':
                    $transport = Swift_SmtpTransport::newInstance($params['server'], $params['port']);
                    if (isset($params['login'])) {
                        $transport->setUsername($params['login']);
                        $transport->setPassword($params['password']);
                    }
                    if (isset($params['encryption'])) {
                        $transport->setEncryption($params['encryption']);
                    }
                    return $transport;
                case 'test':
                    return mailerTestTransport::newInstance();
                case 'default':
                    return $this->getDefaultTransport();
                case 'wa':
                    return new mailerWaTransport($sender, $params);
                default:
                    $result = self::getPluginTransport($sender, $params);
                    if ($result) {
                        return $result;
                    }
            }
        }
        return $this->getDefaultTransport();
    }

    public static function getPluginTransport($sender, $sender_params)
    {
        $params = array(
            'type' => $sender_params['type'],
            'sender_id' => $sender['id'],
            'sender' => $sender,
            'sender_params' => $sender_params,
        );
        $result = wa()->event('sender.transport', $params);
        foreach(ifempty($result, []) as $transport) {
            if ($transport && $transport instanceof Swift_Transport) {
                return $transport;
            }
        }
    }

    protected function getDefaultTransport()
    {
        $result = waMail::getTransportByEmail('default');
        if ($result instanceof Swift_MailTransport) {
            $result = mailerPhpMailTransport::newInstance($result->getExtraParams());
        }
        return $result;
    }

    /**
     * @return Swift_Message
     */
    protected function getMessage()
    {
        $m = Swift_Message::newInstance()
            ->setCharset('utf-8')
            ->setEncoder(new Swift_Mime_ContentEncoder_Base64ContentEncoder())
            ->setFrom($this->encodeEmail($this->data['from_email']), $this->data['from_name'])
            ->setSubject($this->data['subject']);
        return $m;
    }

    // Used in FrontendSubscribe to send confirmation email when client subscribes via form.
    // Sends confirmation using default system transport.
    public function send()
    {
        $args = func_get_args();
        $email = $args[0];
        $name = $args[1];
        $vars = isset($args[2]) ? $args[2] : array();

        $mailer = Swift_Mailer::newInstance($this->getTransport());
        // get message
        $message = $this->getMessage();

        $message->setTo($email, $name);
        if ($vars) {
            $message->setBody(str_replace(array_keys($vars), array_values($vars), $this->data['body']),
                'text/html', 'utf-8');
        } else {
            $message->setBody($this->data['body'], 'text/html', 'utf-8');
        }
        // generate new message-ID
        $message->generateId();
        $mailer->send($message);
    }

    protected function encodeEmail($email)
    {
        if ($email && is_string($email) && !preg_match("/^[a-z0-9~@+:\[\]\.-]+$/ui", $email)) {
            $email = waIdna::enc($email);
        }
        return $email;
    }
}
