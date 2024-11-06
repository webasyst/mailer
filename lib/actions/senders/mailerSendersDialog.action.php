<?php

class mailerSendersDialogAction extends waViewAction
{
    protected $sender_model;
    protected $params_model;

    public function execute()
    {
        if (!mailerHelper::isAdmin()) {
            throw new waException('Access denied.', 403);
        }

        $id = waRequest::get('id');
        $this->sender_model = new mailerSenderModel();
        $sender = $this->sender_model->getById($id);

        $this->params_model = new mailerSenderParamsModel();
        $params = $this->params_model->getBySender($id);

        $params['ssl_is_set'] = extension_loaded('openssl');
        $params['php_version_ok'] = version_compare(PHP_VERSION, '5.3') >= 0;
        $params['php_version'] = PHP_VERSION;

        if ($sender) {
            $email = explode('@', $sender['email']);
            $sender['domain'] = array_pop($email);
            $sender['one_string_key'] = mailerHelper::getOneStringKey(ifset($params['dkim_pub_key']));
            $params['dkim_selector'] = mailerHelper::getDkimSelector($sender['email']);

            if ($params['type'] == 'wa' && wa()->whichUI() == '1.3') {
                throw new waException(_w('Please switch to the Webasyst 2 mode to edit the selected sender.'));
            }
        }

        $sender_types = $this->getSenderTypes($sender, $params);
        if (!isset($params['type'])) {
            $params['type'] = isset($sender_types['wa']) ? 'wa' : 'default';
        }
        $this->view->assign('sender_types', $sender_types);
        $this->view->assign('show_delete_button', $id && $sender && $this->sender_model->countAll() > 1);
        $this->view->assign('sender', $sender);
        $this->view->assign('params', $params);

        if (isset($sender_types['wa'])) {
            $api = new mailerWaTransportServiceApi();
            $waid_is_connected = $api->isConnected();
            if ($waid_is_connected) {
                $response = $api->getBalance('EMAIL');
                $status = ifset($response, 'status', null);
                if (empty($status) || $status >= 400) {
                    $notice = ifset($response, 'response', 'error_description', ifset($response, 'response', 'error', ''));
                } else {
                    $balance_amount = ifset($response, 'response', 'amount', '');
                    $balance = wa_currency_html($balance_amount, ifset($response, 'response', 'currency_id', ''));
                    $price = wa_currency_html(ifset($response, 'response', 'price', ''), ifset($response, 'response', 'currency_id', ''));
                    $free_limits = ifset($response, 'response', 'free_limits', '');

                    $response = $api->getIpWhiteList();
                    $white_list = ifset($response, 'response', 'list', []);
                    $is_allowed_ip = ifset($response, 'response', 'is_allowed_ip', true);
                    $current_ip = ifset($response, 'response', 'your_ip', '');
                }
            }

            $this->view->assign([
                'waid_is_connected' => $waid_is_connected,
                'wa_api_error'      => ifset($notice, []),
                'wa_balance'        => ifset($balance, '—'),
                'wa_price'          => ifset($price, '—'),
                'wa_free_limits'    => ifset($free_limits, []),
                'wa_white_list'     => ifset($white_list, []),
                'wa_is_allowed_ip'  => ifset($is_allowed_ip, true),
                'wa_current_ip'     => ifset($current_ip, ''),
                'wa_price'          => ifset($price, '—'),
                'wa_transport_configured' => !empty($white_list) || !empty($balance_amount),
            ]);
        }

    }

    protected function getSenderTypes($sender, $params)
    {
        $sender_types = mailerHelper::getSenderTypes();
        if (!waSystemConfig::isDebug() && $params['type'] !== 'test') {
            unset($sender_types['test']);
        }
        /* Hide wa transport without any conditions #63.5578
        if (wa()->whichUI() == '1.3' || ($params['type'] != 'wa' && $this->isWaSenderExists())) {
            unset($sender_types['wa']);
        }
        */
        unset($sender_types['wa']);


        /**
         * @since 2.1.2
         */
        $event_result = wa()->event('sender.type_settings', ref([
            'sender_id' => ifset($sender, 'id', null),
            'sender' => $sender,
            'sender_params' => $params,
            'types' => &$sender_types,
        ]));

        foreach($event_result as $plugin => $types) {
            foreach($types as $id => $type) {
                if (!isset($sender_types[$id])) {
                    if (!isset($type['name'])) {
                        $type['name'] = $id;
                    }
                    if (!isset($type['description'])) {
                        $type['description'] = '';
                    }
                    $sender_types[$id] = $type;
                }
            }
        }

        /**
         */
        wa()->event('sender.types', $sender_types);

        return $sender_types;
    }

    protected function isWaSenderExists()
    {
        $sender_ids = array_column($this->sender_model->getAll(), 'id');
        return !empty($this->params_model->getByField(['sender_id' => $sender_ids, 'name' => 'type', 'value' => 'wa']));
    }

}
