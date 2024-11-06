<?php

/**
 * Campaign editor, settings customization.
 *
 * Shows campaign settings form, and accepts submit from it.
 * After submit, if everything is OK, prepares the campaign for sending and marks it as being sent.
 *
 * See mailerCampaignsSendController and mailerMessage->send() for what happens next.
 */
class mailerCampaignsSettingsAction extends waViewAction
{
    protected $sender_model;
    protected $sender_params_model;
    protected $app_settings_model;
    protected $waid_is_connected = null;
    protected $wa_transport_api;
    protected $wa_transport_balance_response = null;

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

        // Prepare google-analytics defaults
//        if (empty($params['google_analytics'])) {
//            // If previous campaign had google analytics turned on, then turn it on by default
//            $sql = "SELECT MAX(m.id)
//                    FROM mailer_message AS m
//                        JOIN mailer_message_params AS mp
//                            ON mp.message_id=m.id
//                    WHERE m.status > 0
//                        AND mp.name='google_analytics'";
//            $ga_message_id = $mm->query($sql)->fetchField();
//
//            if ($ga_message_id) {
//                $sql = "SELECT MAX(id) FROM mailer_message WHERE status > 0";
//                if ($ga_message_id == $mm->query($sql)->fetchField()) {
//                    $params['google_analytics'] = 1;
//                }
//            }
//        }
        if (empty($params['google_analytics_utm_source'])) {
            $params['google_analytics_utm_source'] = 'newsletter';
        }
        if (empty($params['google_analytics_utm_medium'])) {
            $params['google_analytics_utm_medium'] = 'email';
        }
        if (empty($params['google_analytics_utm_campaign'])) {
            $params['google_analytics_utm_campaign'] = strtolower(waLocale::transliterate($campaign['subject']));
            $params['google_analytics_utm_campaign'] = preg_replace('~[^a-z0-9]+~u', '_', preg_replace('~[`\'"]~', '', $params['google_analytics_utm_campaign']));
            $params['google_analytics_utm_campaign'] = trim($params['google_analytics_utm_campaign'], '_');
//            if (strlen($params['google_analytics_utm_campaign']) <= 5) {
//                $params['google_analytics_utm_campaign'] = '';
//            }
        }

        // List of possible senders
        $sm = $this->getSenderModel();
        $senders = $sm->getAll('id');

        // Create the default sender if no senders exist
        if (!$senders && $this->createDefaultSender()) {
            $senders = $sm->getAll('id');
        }

        // Fill senders params
        $spm = $this->getSenderParamsModel();
        $sender_params = $spm->getAll();
        $senders = array_map(function ($sender) use ($sender_params) {
            return array_merge(
                $sender,
                (array) array_reduce(
                    array_filter($sender_params, function ($el) use ($sender) {
                        return $el['sender_id'] == ifset($sender, 'id', '');
                    }),
                    function ($result, $el) {
                        $result[$el['name']] = $el['value'];
                        return $result;
                    }
                )
            );
        }, (array) $senders);

        if (!$this->isWaidConnected() || wa()->whichUI() == '1.3') {
            // Remove Webasyst Transport sender in case of absent connection to Webasyst ID or UI 1.3
            $senders = array_filter($senders, function($el) {
                return $el['type'] != 'wa';
            });
        } elseif (!in_array('wa', waUtils::getFieldValues($senders, 'type'), true)) {
            // Create Webasyst Transport sender in case of good conditions
            $wa_sender = $this->createWaSender();
            if (!empty($wa_sender)) {
                array_unshift($senders, $wa_sender);
            }
        } else {
            // Set Webasyst Transport sender to first place in list
            usort($senders, function($a, $b) {
                if ($a['type'] == 'wa') {
                    return -1;
                }
                if ($b['type'] == 'wa') {
                    return 1;
                }
                return 0;
            });
        }

        

        // List of return-paths
        $rpm = new mailerReturnPathModel();
        $return_paths = $rpm->select('id, email')->fetchAll();

        // Create the default return-path, if possible to deduce it from system mail config
        if (!$return_paths) {
            $mail_config = wa()->getConfig()->getConfigFile('mail');
            if (!empty($mail_config) && !empty($mail_config['default'])) {
                $mail_config = $mail_config['default'];
                $rp = array(
                    'ssl' => 0,
                );
                foreach(array(
                    'login' => 'login',
                    'password' => 'password',
                    'server' => 'pop3_host',
                    'port' => 'pop3_port',
                    'ssl' => 'pop3_ssl',
                    'email' => 'login',
                ) as $rp_key => $conf_key) {
                    if (isset($mail_config[$conf_key])) {
                        $rp[$rp_key] = $mail_config[$conf_key];
                    }
                }
                if (count($rp) >= 6) {
                    $rp_id = $rpm->insert($rp);
                    $return_paths[] = array(
                        'id' => $rp_id,
                        'email' => $rp['email'],
                    );
                }
            }
        }

        // Make sure campaign sender exists before calling plugin hook
        if (!empty($campaign['sender_id']) && empty($senders[$campaign['sender_id']])) {
            $campaign['sender_id'] = 0;
        }

        // Allow plugins to modify data before rendering settings page
        $campaign_settings_event = wa('mailer')->event('campaign.settings', ref([
            'campaign' => &$campaign,
            'params' => &$params,
            'senders' => &$senders,
            'return_paths' => &$return_paths,
        ]));

        $wa_sender = $this->getWaSender($senders);
        if (empty($campaign['sender_id'])) {
            $last_sender_id = $this->getAppSettingsModel()->get('mailer', 'last_sender_id');
            if (!empty($last_sender_id)) {
                $campaign['sender_id'] = $last_sender_id;
            } elseif (!empty($wa_sender)) {
                $campaign['sender_id'] = $wa_sender['id'];
            }
        }

        // Current selected campaign sender for template
        $current_sender = [];
        if (!empty($campaign['sender_id'])) {
            $current_sender = array_pop(ref(array_filter($senders, function($el) use ($campaign) {
                return $el['id'] == $campaign['sender_id'];
            })));
            if (empty($current_sender)) {
                $campaign['sender_id'] = 0;
            }
        }

        mailerHelper::assignCampaignSidebarVars($this->view, $campaign, $params);
//        $params['action'] = 'NameAndCountRecipients'; // __not__ update table with draft recipients because we just open page, only count nonunique recipients
        $this->view->assign([
            'campaign_settings_event' => $campaign_settings_event,
            'sender_types' => mailerHelper::getSenderTypes(),
            'return_paths' => $return_paths,
            'campaign' => $campaign,
            'senders' => $senders,
            'params' => $params,
            'sending_speed_values' => wa('mailer')->getConfig()->getAvailableSpeeds(),
            'current_sender' => $current_sender,
//            'unique_recipients' => mailerHelper::countRecipients($campaign, $params),
            'account_name' => wa()->accountName(),
        ]);

        if ($this->isWaidConnected() && in_array('wa', waUtils::getFieldValues($senders, 'type'), true)) {
            $response = $this->getWaTransportBalanceResponse();
            $status = ifset($response, 'status', null);
            if (empty($status) || $status >= 400) {
                $notice = ifset($response, 'response', 'error_description', ifset($response, 'response', 'error', ''));
            } else {
                $balance_amount = ifset($response, 'response', 'amount', 0);
                $currency_id = ifset($response, 'response', 'currency_id', '');
                $price_value = ifset($response, 'response', 'price', 0);
                $free_limits = ifset($response, 'response', 'free_limits', []);
                $remaining_free_calls = ifset($response, 'response', 'remaining_free_calls', []);

                $precision = strpos(strrev(strval($price_value)), '.');
                $format = ($precision > 2) ? '%'.$precision.'t{s}' : '%{s}';

                $balance = wa_currency_html($balance_amount, $currency_id, $format);
                $price = wa_currency_html($price_value, $currency_id, $format);

                if ($balance_amount > 0 && $price_value > 0) {
                    $letters_count = intval(floor($balance_amount / $price_value));
                    // TODO: не стоит ли тут добавить остаток бесплатного лимита?
                }

                $response = $this->getWaTransportApi()->getIpWhiteList();
                $white_list = ifset($response, 'response', 'list', []);
                $is_allowed_ip = ifset($response, 'response', 'is_allowed_ip', true);
                $current_ip = ifset($response, 'response', 'your_ip', '');
            }
        }

        $this->view->assign([
            'waid_is_connected' => $this->isWaidConnected(),
            'wa_api_error'      => ifset($notice, []),
            'wa_balance'        => ifset($balance, '—'),
            'wa_price'          => ifset($price, '—'),
            'wa_free_limits'    => ifset($free_limits, []),
            'wa_remaining_free_calls' => ifset($remaining_free_calls, []),
            'wa_letters_count'  => ifset($letters_count, 0),
            'wa_white_list'     => ifset($white_list, []),
            'wa_is_allowed_ip'  => ifset($is_allowed_ip, true),
            'wa_current_ip'     => ifset($current_ip, ''),
            'wa_sender'         => ifset($wa_sender, []),
        ]);
    }

    protected function getWaSender($senders)
    {
        return array_pop(ref(array_filter($senders, function($el) {
            return $el['type'] == 'wa';
        })));
    }

    protected function createDefaultSender()
    {
        if (!empty($this->createWaSender())) {
            return true;
        }
        $email = $this->getAppSettingsModel()->get('webasyst', 'sender');
        if (!empty($email)) {
            $id = $this->getSenderModel()->insert([
                'name' => wa()->accountName(),
                'email' => $email,
            ]);
            $this->getSenderParamsModel()->save($id, [ 'type' => 'default' ]);
            return true;
        }
        return false;
    }

    protected function createWaSender()
    {
        if (wa()->whichUI() == '1.3' || !$this->isWaidConnected()) {
            return false;
        }

        $email = $this->getAppSettingsModel()->get('webasyst', 'sender');
        if (!empty($email)) {
            $sender_check_result = $this->getWaTransportApi()->serviceCall('SENDERCHECK', [ 'from_email' => $email ]);
            if (ifset($sender_check_result, 'response', 'need_replace', null)) {
                $email = '';
            }
        }

        $data = [
            'name' => wa()->accountName(),
            'email' => '',
        ];
        $params = [
            'type' => 'wa',
            'no_return_path' => '1',
            'from' => $email
        ];
        $data['id'] = $this->getSenderModel()->insert($data);
        $this->getSenderParamsModel()->save($data['id'], $params);
        return array_merge($data, $params);
    }

    protected function getWaTransportApi()
    {
        if (empty($this->wa_transport_api)) {
            $this->wa_transport_api = new mailerWaTransportServiceApi();
        }
        return $this->wa_transport_api;
    }

    protected function isWaidConnected()
    {
        if ($this->waid_is_connected === null) {
            $api = $this->getWaTransportApi();
            $this->waid_is_connected = $api->isConnected();
        }
        return $this->waid_is_connected;
    }

    protected function getWaTransportBalanceResponse()
    {
        if ($this->wa_transport_balance_response === null && $this->isWaidConnected()) {
            $this->wa_transport_balance_response = $this->getWaTransportApi()->getBalance('EMAIL');
        }
        return $this->wa_transport_balance_response;
    }

    protected function getSenderModel()
    {
        if (empty($this->sender_model)) {
            $this->sender_model = new mailerSenderModel();
        }
        return $this->sender_model;
    }

    protected function getSenderParamsModel()
    {
        if (empty($this->sender_params_model)) {
            $this->sender_params_model = new mailerSenderParamsModel();
        }
        return $this->sender_params_model;
    }

    protected function getAppSettingsModel()
    {
        if (empty($this->app_settings_model)) {
            $this->app_settings_model = new waAppSettingsModel();
        }
        return $this->app_settings_model;
    }
}
