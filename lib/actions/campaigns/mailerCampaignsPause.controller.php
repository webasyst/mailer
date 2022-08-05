<?php

/**
 * Pause given campaign that is currently sending or resume paused campaign.
 */
class mailerCampaignsPauseController extends waJsonController
{
    public function execute()
    {
        $id = waRequest::request('id', 0, 'int');
        if (!$id) {
            return;
        }

        $mm = new mailerMessageModel();
        $campaign = $mm->getById($id);
        if (!$campaign) {
            return;
        }

        if (mailerHelper::campaignAccess($campaign) < 2) {
            return;
        }

        $mpm = new mailerMessageParamsModel();
        $params = $mpm->getByMessage($id);

        if (waRequest::request('pause')) {
            if ($campaign['status'] != mailerMessageModel::STATUS_SENDING) {
                return;
            }

            $mm->updateById($id, array('status' => mailerMessageModel::STATUS_SENDING_PAUSED));

            // Calculate total sending time and save in message params
            $send_datetime = ifempty($params['fake_send_timestamp'], strtotime($campaign['send_datetime']));
            $mpm->save($id, array('total_sending_time' => time() - $send_datetime + 5), array('fake_send_timestamp'));

            /**@/**
             * @event campaign.pause
             *
             * Campaign just switched to PAUSED state
             *
             * @param array[string]array $params['campaign'] row from mailer_message
             * @param array[string]array $params['params'] campaign params from mailer_message_params, key => value
             * @return void
             */
            $evt_params = array(
                'campaign' => $campaign,
                'params' => $params,
            );
            wa()->event('campaign.pause', $evt_params);

        } else if (waRequest::request('resume')) {
            if ($campaign['status'] != mailerMessageModel::STATUS_SENDING_PAUSED) {
                return;
            }

            $sender_params_model = new mailerSenderParamsModel();
            $sender_params = $sender_params_model->getBySender($campaign['sender_id']);
            $errormsg = [];

            /**@/**
             * @event campaign.before_resume
             *
             * @param array[string]array $params['campaign'] input: row from mailer_message
             * @param array[string]array $params['params'] input: campaign params from mailer_message_params, key => value
             * @param array[string]array $params['sender_params'] input: sender params from mailer_sender_params, key => value
             * @param array[string]array $params['errors'] output: list of error message strings to show to user
             * @return void
             */
            $evt_params = array(
                'campaign' => &$campaign,               // INPUT
                'params' => &$params,                   // INPUT
                'sender_params' => &$sender_params,     // INPUT
                'errors' => &$errormsg,                 // OUTPUT
            );
            wa()->event('campaign.before_resume', $evt_params);

            if (ifset($sender_params, 'type', '') == 'wa') {
                $api = new mailerWaTransportServiceApi();
                if (!$api->isConnected()) {
                    $errormsg[] = sprintf_wp(
                        '<a href="%s">Connect to Webasyst ID</a> to use the Webasyst sender server.',
                        wa()->getConfig()->getBackendUrl(true).'webasyst/settings/waid/'
                    );
                } else {

                    $message_log_model = new mailerMessageLogModel();
                    $count_remaining_recipients = $message_log_model->countByField([
                        'message_id' => $campaign['id'],
                        'status' => mailerMessageLogModel::STATUS_AWAITING,
                    ]);

                    try {
                        $sufficient_balance = $api->balanceCheckService('EMAIL', [
                            'count' => $count_remaining_recipients,
                        ], $result);
                        if (!$sufficient_balance) {
                            // !!! use wa_currency_html() ?..
                            $current_balance = wa_currency($result['response']['current_balance'], $result['response']['currency_id']);
                            $campaign_cost = wa_currency_html($result['response']['amount'], $result['response']['currency_id']);
                            $error_text = join("\n<br>\n", [
                                _w('Not sufficient account balance to continue the campaign.'),
                                sprintf_wp(
                                    'Current account balance: <strong>%s</strong>, sending cost: <strong>%s</strong>.',
                                    $current_balance,
                                    $campaign_cost
                                ),
                                _w('Add funds to your balance in the sender settings.'), // !!! когда рассылка на паузе, как пользователю пополнить счёт отправителя?..
                            ]);
                            $errormsg[] = strip_tags($error_text); // !!! strip_tags() потому что фронт сейчас показывает это тупо alert()'ом - наверное, это надо будет поменять
                        }
                    } catch (waException $e) {
                        // should never happen
                        $errormsg[] = $e->getMessage();
                    }
                }
            }

            if ($errormsg) {
                $this->errors = $errormsg;
                return;
            }

            // Calculate fake sending start datetime for estimated time
            $total_sending_time = ifempty($params['total_sending_time'], 0);
            $mpm->save($id, array('fake_send_timestamp' => time() - $total_sending_time), array('total_sending_time', 'sending_softfail_description'));
            $mm->updateById($id, array('status' => mailerMessageModel::STATUS_SENDING));

            /**@/**
             * @event campaign.resume
             *
             * Campaign just switched to SENDING state after being paused
             *
             * @param array[string]array $params['campaign'] row from mailer_message
             * @param array[string]array $params['params'] campaign params from mailer_message_params, key => value
             * @return void
             */
            $evt_params = array(
                'campaign' => $campaign,
                'params' => $params,
                'sender_params' => $sender_params,
            );
            wa()->event('campaign.resume', $evt_params);
        }
    }
}

