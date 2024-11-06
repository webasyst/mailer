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

        if ($this->isWaidConnected()) {
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

                $balance = $this->formatAmount($balance_amount, $currency_id);
                $price = $this->formatAmount($price_value, $currency_id);

                if ($balance_amount > 0 && $price_value > 0) {
                    $letters_count = intval(floor($balance_amount / $price_value));
                    if ($letters_count > 10000) {
                        $letters_count = sprintf_wp('%s messages', intval($letters_count / 1000) . 'K');
                    } elseif ($letters_count > 0) {
                        $letters_count = _w('%d message', '%d messages', $letters_count);
                    } else {
                        $letters_count = '';
                    }
                    // TODO: не стоит ли тут добавить остаток бесплатного лимита?
                }
            }
        }
        $this->view->assign([
            'waid_is_connected' => $this->isWaidConnected(),
            'wa_api_error'      => ifset($notice, []),
            'wa_balance'        => ifset($balance, '—'),
            'wa_price'          => ifset($price, '—'),
            'wa_free_limits'    => ifset($free_limits, []),
            'wa_remaining_free_calls' => ifset($remaining_free_calls, []),
            'wa_letters_count'  => ifset($letters_count, null),
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

    protected function getWaTransportBalanceResponse()
    {
        if ($this->wa_transport_balance_response === null && $this->isWaidConnected()) {
            $this->wa_transport_balance_response = $this->getWaTransportApi()->getBalance('EMAIL');
        }
        return $this->wa_transport_balance_response;
    }

    protected function formatAmount($amount, $currency_id)
    {
        $precision = strpos(strrev(strval($amount)), '.');
        $format = ($precision > 1) ? '%'.$precision : '%0';
        $amount_str = waCurrency::format($format, $amount, $currency_id);
        return $currency_id === 'RUB' ? $amount_str . ' <span class="ruble">₽</span>' : '$' . $amount_str;
    }
}

