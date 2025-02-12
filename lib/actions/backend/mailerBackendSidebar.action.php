<?php
/**
 * Backend sidebar HTML to use in layout or request via XHR.
 */
class mailerBackendSidebarAction extends waViewAction
{
    protected $waid_is_connected = null;
    protected $wa_transport_api;
    protected $wa_transport_balance_response = null;

    public function execute()
    {
        // Filter drafts by access rights
        $access_sql = '';
        if (!mailerHelper::isInspector()) {
            $access_sql = ' AND create_contact_id='.wa()->getUser()->getId();
        }

        // List of drafts
        $mm = new mailerMessageModel();
        $drafts = $mm->select('*')->where('is_template=0 AND status IN (i:draft, i:pending)'.$access_sql, array('draft' => mailerMessageModel::STATUS_DRAFT, 'pending' => mailerMessageModel::STATUS_PENDING))->order('id DESC')->fetchAll('id');
        foreach($drafts as &$d) {
            $d['pic_src'] = '';
            if (!empty($d['create_contact_id'])) {
                try {
                    $d['pic_src'] = wao(new waContact($d['create_contact_id']))->getPhoto(20);
                } catch (Exception $e) {}
            }
        }
        unset($d);

        $this->view->assign('drafts', $drafts);

        // Count total number of sent messages and number of currently sending
        $mm = new mailerMessageModel();
        $cnt = $mm->countSent();

        // Plugin blocks
        $plugin_blocks = array(/*
            block_id => array(
                'id' => block_id
                'html' => ...
            )
        */);
        foreach(wa()->event('sidebar.blocks') as $app_id => $one_or_more_blocks) {
            if (!isset($one_or_more_blocks['html'])) {
                $i = '';
                foreach($one_or_more_blocks as $block) {
                    $key = isset($block['id']) ? $block['id'] : $app_id.$i;
                    $plugin_blocks[$key] = $block;
                    $i++;
                }
            } else {
                $key = isset($one_or_more_blocks['id']) ? $one_or_more_blocks['id'] : $app_id;
                $plugin_blocks[$key] = $one_or_more_blocks;
            }
        }

        $templates_count = $subscribers_count = $unsubscribers_count = $undeliverable_count = 0;
        if (mailerHelper::isAdmin()) {
            $tm = new mailerTemplateModel();
            $templates_count = $tm->countAll(($this->whichUI() === '1.3'));

            $sm = new mailerSubscriberModel();
            $subscribers_count = $sm->countListView('');

            $um = new mailerUnsubscriberModel();
            $unsubscribers_count = $um->countAll();

            $sql = "SELECT COUNT(*)
                    FROM wa_contact_emails AS ce
                        JOIN wa_contact AS c
                            ON c.id=ce.contact_id
                    WHERE ce.status='unavailable'";
            $undeliverable_count = $um->query($sql)->fetchField();
        }

        $this->view->assign('plugin_blocks', $plugin_blocks);
        $this->view->assign('total_sent', $cnt['total_sent']);
        $this->view->assign('sending_count', $cnt['sending_count']);
        $this->view->assign('templates_count', $templates_count);
        $this->view->assign('subscribers_count', $subscribers_count);
        $this->view->assign('unsubscribers_count', $unsubscribers_count);
        $this->view->assign('undeliverable_count', $undeliverable_count);

        $this->assignWaTransportBlock();
    }

    private function assignWaTransportBlock()
    {
        try {
            $wa_service_api = new waServicesApi();
            $waid_is_connected = $wa_service_api->isConnected();
        } catch (Throwable $e) {
            $waid_is_connected = false;
        }

        $this->view->assign('waid_is_connected', $waid_is_connected);
        if (!$waid_is_connected) {
            return;
        }

        $balance_amount = 0;
        $res = $wa_service_api->getBalance(waServicesApi::EMAIL_MESSAGE_SERVICE);

        $status = ifset($res, 'status', null);
        if (empty($status) || $status >= 400) {
            $api_error = ifset($res, 'response', 'error_description', ifset($res, 'response', 'error', ''));
        } else {
            $balance_amount = ifset($res, 'response', 'amount', 0);
            $currency_id = ifset($res, 'response', 'currency_id', '');
            $balance = $this->formatAmount($balance_amount, $currency_id);

            $price_value = ifset($res, 'response', 'price', 0);
            if ($balance_amount > 0 && $price_value > 0) {
                $messages_count = intval(floor($balance_amount / $price_value));
            }
            $remaining_free_calls = ifempty($res, 'response', 'remaining_free_calls', []);
            $remaining_pack = ifset($remaining_free_calls, 'pack', 0);
            unset($remaining_free_calls['pack']);
        }

        $this->view->assign([
            'wa_api_error'     => ifset($api_error),
            'wa_is_positive_balance' => $balance_amount > 0,
            'wa_balance'        => ifset($balance, '—'),
            'wa_total'   => ifset($messages_count, 0)
                            + ifset($remaining_free_calls, 'total', 0)
                            + ifset($remaining_pack, 0),
            ]);
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

    protected function formatAmount($amount, $currency_id)
    {
        $precision = strpos(strrev(strval($amount)), '.');
        $format = ($precision > 1) ? '%'.$precision : '%0';
        $amount_str = waCurrency::format($format, $amount, $currency_id);
        return $currency_id === 'RUB' ? $amount_str . ' <span class="ruble">₽</span>' : '$' . $amount_str;
    }
}
