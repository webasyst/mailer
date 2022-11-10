<?php

/**
 * Returns HTML to insert into CampaignsSettings page
 * when user clicks the 'Send to selected recipients' link.
 * Validates campaign and either show error messages, or a button to proceed.
 * When user clicks the button, a POST is sent to this controller again,
 * and the sending starts.
 */
class mailerCampaignsPresendAction extends waViewAction
{
    public function execute()
    {
        $campaign_id = waRequest::get('campaign_id', 0, 'int');
        if (!$campaign_id) {
            throw new waException('No campaign id given.', 404);
        }

        // Campaign data
        $mm = new mailerMessageModel();
        $campaign = $mm->getById($campaign_id);
        if (!$campaign) {
            throw new waException('Campaign not found.', 404);
        }
        if ($campaign['status'] != mailerMessageModel::STATUS_DRAFT && $campaign['status'] != mailerMessageModel::STATUS_PENDING) {
            echo "<script>window.location.hash = '#/campaigns/report/{$campaign_id}/';</script>";
            exit;
        }

        // Access control
        if (mailerHelper::campaignAccess($campaign) < 2) {
            throw new waException('Access denied.', 403);
        }

        // Campaign params
        $mpm = new mailerMessageParamsModel();
        $params = $mpm->getByMessage($campaign_id);

        // Start sending the campaign if POST came and validation passes.
        $errormsg = self::localValidate($campaign, $params);
        if (waRequest::post('send') && !$errormsg) {
            $errormsg = self::eventValidate($campaign, $params);
            if (!$errormsg) {
                try {
                    mailerHelper::prepareRecipients($campaign, $params);
                    // check schedule campaigns (we should 'prepare' Recipients, check events, but not send campaign)
                    if ($campaign['status'] != mailerMessageModel::STATUS_PENDING) {
                        echo "<script>window.location.hash='#/campaigns/report/{$campaign_id}/';</script>";
                        exit;
                    }
                } catch (waException $e) {
                    $errormsg = array(_w('Unable to prepare recipients list. Another user attempted to start sending this campaign?'));
                }
            }
        }

        $return_path_ok = true;
        if (!$errormsg) {
            // This breaks smarty vars assigned before it
            $return_path_ok = $this->isReturnPathOk($campaign, $params);
        }

        $last_cron_time = wa()->getSetting('last_cron_time', 0);
        $last_cron_time = wa_is_int($last_cron_time) && $last_cron_time >= 0 ? $last_cron_time : 0;

        $estimated_duration_str = null;
        $estimated_duration_sec = $this->getEstimatedDuration($campaign['sender_id'], ifset($params, 'speed_limit', null), ifset($params, 'recipients_count', 0));
        if ($estimated_duration_sec) {
            $estimated_duration_str = self::getAge($estimated_duration_sec);
        }

        $this->view->assign('errormsg', $errormsg);
        $this->view->assign('cron_command', '<code>php '.wa()->getConfig()->getRootPath().'/cli.php mailer send</code><br><br><code>php '.wa()->getConfig()->getRootPath().'/cli.php mailer check</code>');
        $this->view->assign('cron_ok', $last_cron_time + 3600*2 > time());
        $this->view->assign('last_cron_time', $last_cron_time);
        $this->view->assign('return_path_ok', $return_path_ok);
        $this->view->assign('unique_recipients', $params['recipients_count']);
        $this->view->assign('routing_ok', !!wa()->getRouteUrl('mailer', true));
        $this->view->assign('estimated_duration', $estimated_duration_str);
        $this->view->assign('need_from_replace', ifset($params['need_from_replace'], false));
        $this->view->assign('scheduled', $campaign['status'] == mailerMessageModel::STATUS_PENDING );
        $this->view->assign('scheduled_time', $campaign['send_datetime'] );
    }

    /** Local validation: check basic campaign properties. */
    public static function localValidate($campaign, &$params)
    {
        $errormsg = array();
        if (!trim($campaign['body'])) {
            $errormsg[] = _w('No message body.');
        }
        if (!trim($campaign['subject'])) {
            $errormsg[] = _w('No message subject.');
        }
        $validator = new waEmailValidator();
        if (!trim($campaign['from_email'])) {
            $errormsg[] = _w('No sender email address specified.');
        } elseif (!$validator->isValid($campaign['from_email'])) {
            $errormsg[] = _w('Invalid sender email address.');
        }
        if (!empty($campaign['reply_to']) && !$validator->isValid($campaign['reply_to'])) {
            $errormsg[] = _w('Invalid Reply-to email address.');
        }

        // Check if there are recipients selected
        $action = waRequest::post('send') ? null : 'UpdateDraftRecipientsTable'; // if we actually dont send - will update draft recipients table and count
        $params['recipients_count'] = mailerHelper::countUniqueRecipients($campaign, $params, null, $errors, $action);
        if ($params['recipients_count'] <= 0) {
            $errormsg[] = _w('No recipients selected.');
        }

        // Check if this campaign has more recipients than it is allowed
        $max_recipients_count = wa('mailer')->getConfig()->getOption('max_recipients_count');
        if ($max_recipients_count && $max_recipients_count < $params['recipients_count']) {
            $errormsg[] = _w('Maximum recipients limit has been exceeded:').' '.$max_recipients_count;
        }

        // Being paranoid: check that wa-data and wa-cache are available for writing
        foreach(array(wa()->getDataPath('', false, 'mailer'), waConfig::get('wa_path_cache')) as $path) {
            if (!file_exists($path)) {
                @waFiles::create($path);
            }
            if (!is_writable($path)) {
                $errormsg[] = sprintf_wp('%s is not writable', $path);
            }
        }

        // Check if daily limit exceeded
        $max_recipients_daily = wa('mailer')->getConfig()->getOption('max_recipients_daily');
        if ($max_recipients_daily) {
            $mlm = new mailerMessageLogModel();
            if ($max_recipients_daily < $mlm->countSentToday() + $params['recipients_count']) {
                $errormsg[] = _w('Maximum recipients daily limit has been exceeded:').' '.$max_recipients_daily;
            }
        }

        // Check if WA transport is available if selected for this campaign,
        // and balance exceeds campaign cost
        $sender_params_model = new mailerSenderParamsModel();
        $sender_params = $sender_params_model->getBySender($campaign['sender_id']);
        if (ifset($sender_params, 'type', '') == 'wa') {
            $api = new mailerWaTransportServiceApi();
            if (!$api->isConnected()) {
                $errormsg[] = sprintf_wp(
                    '<a href="%s">Connect to Webasyst ID</a> to use the Webasyst sender server.',
                    wa()->getConfig()->getBackendUrl(true).'webasyst/settings/waid/'
                );
            } elseif ($params['recipients_count'] > 0) {
                try {
                    $sufficient_balance = $api->balanceCheckService('EMAIL', [
                        'count' => $params['recipients_count'],
                    ], $result);
                    if (!$sufficient_balance) {
                        $current_balance = wa_currency_html($result['response']['current_balance'], $result['response']['currency_id']);
                        $campaign_cost = wa_currency_html($result['response']['amount'], $result['response']['currency_id']);
                        $errormsg[] = join("\n<br>\n", [
                            _w('Not sufficient account balance to complete the campaign.'),
                            sprintf_wp(
                                'Current account balance: <strong>%s</strong>, sending cost: <strong>%s</strong>.',
                                $current_balance,
                                $campaign_cost
                            ),
                            _w('Please to up your balance.'),
                            '<span class="small">' . sprintf_wp('Use the “%s” button above on this page.', _w('Top up balance')) . '</span>',
                        ]);
                    } else {
                        $check_from_email = $api->serviceCall('SENDERCHECK', [
                            'from_email' => $campaign['from_email'],
                            'from_name' => $campaign['from_name'],
                            'reply_to_email' => $campaign['reply_to'],
                        ]);
                        if (ifset($check_from_email, 'response', 'need_replace', null)) {
                            $params['need_from_replace'] = $check_from_email['response'];
                            $params['need_from_replace']['original_from_email'] = $campaign['from_email'];
                        }
                    }
                } catch (waException $e) {
                    // should never happen
                    $errormsg[] = $e->getMessage();
                }
            }
        }

        return $errormsg;
    }

    /** Allows plugins to validate campaign before sending. */
    public static function eventValidate($campaign_or_id, $params=null)
    {

        if (is_array($campaign_or_id)) {
            $campaign = $campaign_or_id;
        } else {
            $mm = new mailerMessageModel();
            $campaign = $mm->getById($campaign_or_id);
        }
        if ($params === null) {
            $mpm = new mailerMessageParamsModel();
            $params = $mpm->getByMessage($campaign['id']);
        }

        $sender_params_model = new mailerSenderParamsModel();
        $sender_params = $sender_params_model->getBySender($campaign['sender_id']);

        /**@/**
         * @event campaign.validate
         *
         * Allows to validate and cancel campaign before sending
         *
         * @param array[string]array $params['campaign'] input: row from mailer_message
         * @param array[string]array $params['params'] input: campaign params from mailer_message_params, key => value
         * @param array[string]array $params['sender_params'] input: sender params from mailer_sender_params, key => value
         * @param array[string]array $params['errors'] output: list of error message strings to show to user
         * @return void
         */
        $evt_params = array(
            'campaign' => &$campaign,           // INPUT
            'params' => &$params,               // INPUT
            'sender_params' => &$sender_params, // INPUT
            'errors' => array(),                // OUTPUT
        );
        wa()->event('campaign.validate', $evt_params);
        return (array) $evt_params['errors'];
    }

    /** Returns false if there's a problem connecting to this campaign's return path */
    protected function isReturnPathOk($campaign)
    {
        if (empty($campaign['return_path'])) {
            return array(
                'status'=>true
            );
        }

        $rpm = new mailerReturnPathModel();
        $rp = $rpm->getByEmail($campaign['return_path']);
        if (!$rp) {
            return array(
                'status'=>false,
                'reason'=>1,
                'return-path'=>$campaign['return_path']
            );
        }

        // Check if SSL is supported
        if (!defined('OPENSSL_VERSION_NUMBER') && !empty($data['ssl'])) {
            return array(
                'status'=>false,
                'reason'=>2,
                'return-path'=>$campaign['return_path']
            );
        }

        // Check cache in session
        $status = wa()->getStorage()->get('mailer_rp_status_'.$rp['id']);
        if (isset($status)) {
            return array(
                'return-path'=>$campaign['return_path'],
                'reason'=>wa()->getStorage()->get('mailer_rp_reason_'.$rp['id']),
                'status'=>$status
            );
        }

        // Try to connect using given settings
        try {
            $mail_reader = new waMailPOP3($rp);
            $mail_reader->count();
        } catch (Exception $e) {
            wa()->getStorage()->set('mailer_rp_status_'.$rp['id'], false);
            wa()->getStorage()->set('mailer_rp_reason_'.$rp['id'], 3);
            return array(
                'return-path'=>$campaign['return_path'],
                'status'=>false,
                'reason'=>3
            );
        }

        // check return-path from smtp sender
        $mm = new mailerMessage($campaign);
        if ($mm->testReturnPathSmtpSender() === false) {
            wa()->getStorage()->set('mailer_rp_status_'.$rp['id'], false);
            wa()->getStorage()->set('mailer_rp_reason_'.$rp['id'], 4);
            return array(
                'status'=>false,
                'reason'=>4,
                'return-path'=>$campaign['return_path']
            );
        }

        wa()->getStorage()->set('mailer_rp_status_'.$rp['id'], true);
        wa()->getStorage()->del('mailer_rp_reason_'.$rp['id']);
        return array(
            'return-path'=>$campaign['return_path'],
            'status'=>true
        );
    }

    protected function getEstimatedDuration($sender_id, $speed_limit, $recipients_count)
    {
        if ($speed_limit > 0) {
            return $recipients_count * 3600 / $speed_limit;
        }

        // Get several finished campaigns that use this sender
        $m = new waModel();
        $sql = "SELECT id, send_datetime, finished_datetime, mp1.value AS recipients_count, mp2.value AS fake_send_timestamp, mp3.value AS speed_limit
                FROM mailer_message AS m
                    JOIN mailer_message_params AS mp1
                        ON mp1.message_id=m.id
                            AND mp1.name='recipients_count'
                    LEFT JOIN mailer_message_params AS mp2
                        ON mp2.message_id=m.id
                            AND mp2.name='fake_send_timestamp'
                    LEFT JOIN mailer_message_params AS mp3
                        ON mp3.message_id=m.id
                            AND mp3.name='speed_limit'
                WHERE m.sender_id=?
                    AND m.send_datetime IS NOT NULL
                    AND m.finished_datetime IS NOT NULL
                HAVING speed_limit IS NULL
                ORDER BY m.id DESC
                LIMIT 5";
        $rows = $m->query($sql, [$sender_id]);

        // Calculate total sent count and total sending time of finished campaigns
        $total_sending_time = 0;
        $total_recipients_count = 0;
        foreach($rows as $row) {
            $total_recipients_count += $row['recipients_count'];
            $start_ts = ifempty($row['fake_send_timestamp'], strtotime($row['send_datetime']));
            $end_ts = strtotime($row['finished_datetime']);
            $total_sending_time += $end_ts - $start_ts;
        }

        if ($total_recipients_count > 0) {
            return $recipients_count*$total_sending_time / $total_recipients_count;
        } else {
            return null;
        }
    }

    public static function getAge($fullseconds)
    {
        if($fullseconds < 60) {
            return _w('less than a minute');
        } elseif($fullseconds < 60 * 60) {
            return _w('about %d minute', 'about %d minutes', round(($fullseconds) / 60));
        } else {
            $minutes = round($fullseconds / 60) % 60;
            $hours = floor($fullseconds / (60*60));

            $result = _w('about %d hour', 'about %d hours', $hours);
            if ($minutes) {
                $result .= ' ' . _w('%d minute', '%d minutes', $minutes);
            }

            return $result;
        }
    }
}
