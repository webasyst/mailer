<?php

class mailerSendersSaveController extends waJsonController
{
    public function execute()
    {
        if (!mailerHelper::isAdmin()) {
            throw new waException('Access denied.', 403);
        }

        // Save sender data
        $id = waRequest::post('id');
        $data = waRequest::post('data');
        $this->validateArray($data);
        unset($data['id']);

        // Sender params
        $params = waRequest::post('params', array());
        $this->validateArray($params);

        if ($params['type'] == 'wa') {
            if (!(new mailerWaTransportServiceApi)->isConnected()) {
                $this->errors = array(
                    '' => sprintf_wp(
                        '<a href="%s">Connect to Webasyst ID</a> to use the Webasyst sender server.',
                        wa()->getConfig()->getBackendUrl(true)."webasyst/settings/waid/"
                    ),
                );
                return;
            }
        }

        // Validate email
        if (empty($data['email'])) {
            if ($params['type'] != 'wa') {
                // Email required for non Webasyst Transport
                $this->errors = array(
                    'data[email]' => _w('Email is required'),
                );
                return;
            }
        } else {
            $ev = new waEmailValidator();
            if (!$ev->isValid($data['email'])) {
                $this->errors = array(
                    'data[email]' => _w('Invalid Email address'),
                );
                return;
            }
        }

        $spm = new mailerSenderParamsModel();

        // Try to connect using given settings
        if ($params['type'] == 'smtp') {
            if (!empty($params['login']) && empty($params['password']) && $id) {
                $p = $spm->getById($id);
                $params['password'] = $p['password'];
            }

            try {
                $transport = Swift_SmtpTransport::newInstance(ifempty($params['server']), ifempty($params['port']));
                if (!empty($params['login'])) {
                    $transport->setUsername($params['login']);
                    if (!empty($params['password'])) {
                        $transport->setPassword($params['password']);
                    }
                }
                if (!empty($params['encryption'])) {
                    // Check if SSL is supported
                    // (have to do it here because Swift tends to echo warnings that break our JSON)
                    if (!defined('OPENSSL_VERSION_NUMBER')) {
                        throw new waException(_w('Encryption requires OpenSSL PHP module to be installed.'));
                    }
                    $transport->setEncryption($params['encryption']);
                }
                $transport->start();
                $transport->stop();
            } catch (Exception $e) {
                $this->errors = self::fixWindowsErrormsg($e->getMessage());
                if (!$this->errors || json_encode($this->errors) === 'null') {
                    waLog::log('mailer: Error checking SMTP during transport validation: '.$this->errors);
                    $this->errors = _w('Unknown error');
                }
                $this->errors = array('' => _w('Error checking mail transport:').' '.$this->errors);
                return;
            }
        } else if ($params['type'] == 'sendmail') {
            try {
                if (!empty($params['command'])) {
                    $command = str_replace(' -t', ' -bs', $params['command']);
                    $transport = Swift_SendmailTransport::newInstance($command);
                } else {
                    $transport = Swift_SendmailTransport::newInstance();
                }
                $transport->start();
                $transport->stop();
            } catch (Exception $e) {
                $this->errors = self::fixWindowsErrormsg($e->getMessage());
                if (!$this->errors || json_encode($this->errors) === 'null') {
                    waLog::log('mailer: Error checking Sendmail during transport validation: '.$this->errors);
                    $this->errors = _w('Unknown error');
                }
                $this->errors = array('' => _w('Error checking mail transport:').' '.$this->errors);
                return;
            }
        }

        $sender_model = new mailerSenderModel();
        if ($id) {
            $sender = $sender_model->getById($id);
            if (!$sender) {
                throw new waException('Sender not found', 404);
            }
            $sender_params = $spm->getBySender($id);
        } else {
            $sender = $sender_model->getEmptyRow();
            $sender_params = [];
        }

        // If specified email is used in Webasyst Transport sender
        // remove this email from Webasyst Transport sender data to allow save it in new sender
        $check_sender_email_unique = $sender_model->getByField('email', $data['email']);
        if (!empty($check_sender_email_unique) && $check_sender_email_unique['id'] != $id) {
            if (!empty($spm->getByField(['sender_id' => $check_sender_email_unique['id'], 'name' => 'type', 'value' => 'wa']))) {
                $sender_model->updateById($check_sender_email_unique['id'], ['email' => '']);
            }
        }

        /**
         * Allows to check and validate sender settings.
         *
         * Return array of error strings.
         * Keys are field names as set in name attribute in DOM.
         * If field is not found in DOM, generic error will be shown below the form.
         * Values are strings containing human-readable error description.
         *
         * @return array field name => human-readable error description
         * @since 2.1.2
         */
        $before_save_result = wa('mailer')->event('sender.before_save', ref([
            'sender_id' => $id,
            'sender' => $sender,
            'sender_params' => $sender_params,
            'sender_update' => &$data,
            'sender_params_update' => &$params,
        ]));

        foreach($before_save_result as $plugin_id => $errors) {
            if (is_array($errors)) {
                $this->errors += $errors;
            }
        }

        if ($this->errors) {
            return;
        }

        if ($params['type'] == 'wa') {
            // Force no_return_path param for WES transport
            $params['no_return_path'] = '1';
            // Save From address in params for WES transport
            $params['from'] = $data['email'];
            $data['email'] = '';
        }

        try {
            if ($id) {
                $sender_model->updateById($id, $data);
            } else {
                $id = $sender_model->insert($data);
            }
        } catch (waDbException $e) {
            if (false !== strpos($e->getMessage(), 'Duplicate entry')) {
                $this->errors = array(
                    'data[email]' => _w('Sender Email must be unique'),
                );
            } else {
                $this->errors = array('' => $e->getMessage());
            }
            return;
        }

        // Save sender params
        $spm->save($id, $params);
        $this->response = $id;

        /**
         * @since 2.1.2
         */
        wa('mailer')->event('sender.save', ref([
            'sender_id' => $id,
            'sender' => array_intersect_key($data + $sender, $sender),
            'sender_params' => $params,
            'sender_update' => $data,
            'sender_params_update' => $params,
        ]));

    }

    /**
     * Error messages on windows hostings are known to return messages encoded in non-UTF8.
     * This breaks json_encode() later and results in error message being null.
     * This function does its best to decode such messages into UTF-8.
     */
    protected static function fixWindowsErrormsg($errormsg)
    {
        if ($errormsg && json_encode($errormsg) === 'null') {
            $e = @iconv('windows-1251', 'utf-8//IGNORE', $errormsg);
            if ($e && json_encode($e) !== 'null') {
                $errormsg = $e;
            }
        }
        return $errormsg;
    }

    protected function validateArray(&$arr)
    {
        if (!is_array($arr)) {
            throw new waException('Bad POST parameters.');
        }
        foreach($arr as $k => &$v) {
            if ($k != 'password') {
                $v = trim($v);
            }
        }
    }
}

