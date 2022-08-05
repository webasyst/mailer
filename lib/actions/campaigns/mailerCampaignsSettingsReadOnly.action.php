<?php

/**
 * For campaign sent or being sent, show its parameters.
 */
class mailerCampaignsSettingsReadOnlyAction extends waViewAction
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
        if ($campaign['status'] <= 0 || $campaign['status'] == mailerMessageModel::STATUS_PENDING) {
            echo "<script>window.location.hash = '#/campaigns/send/{$campaign_id}/';</script>";
            exit;
        }

        // Access control
        if (mailerHelper::campaignAccess($campaign) < 1) {
            throw new waException('Access denied.', 403);
        }

        // Campaign params
        $mpm = new mailerMessageParamsModel();
        $params = $mpm->getByMessage($campaign_id);

        $ms = new mailerSenderModel();
        $sender = $ms->getById($campaign['sender_id']);
        if (!$sender) {
            $sender = $ms->getEmptyRow();
            $sender_params = ['type' => ''];
        } else {
            $msp = new mailerSenderParamsModel();
            $sender_params = $msp->getBySender($sender['id']);
        }

        // if we save sender params in campaigns_params table
        $params_changed = false;
        foreach (['reply_to', 'type', 'server', 'port'] as $sender_param) {
            $saved_sender_param = 'sender_' . $sender_param;
            if (isset($params[$saved_sender_param])) {
                if ($params_changed == false) {
                    $sender_params = array();
                    $params_changed = true;
                }
                $sender_params[$sender_param] = $params[$saved_sender_param];
            }
        }

        $wa_report_data = null;
        if ($sender_params['type'] === 'wa') {
            $transport = new mailerWaTransport($sender, $sender_params);
            $wa_report_data = $transport->getCampaignReport($campaign_id);
        }

        $mrp = new mailerReturnPathModel();
        $return_path = $mrp->getByField('email', $campaign['return_path']);

        mailerHelper::assignCampaignSidebarVars($this->view, $campaign, $params);
        $this->view->assign('campaign', $campaign);
        $this->view->assign('params', $params);
        $this->view->assign('sender', $sender);
        $this->view->assign('sender_params', $sender_params);
        $this->view->assign('return_path', $return_path);
        $this->view->assign('wa_report_data', $wa_report_data);
        $this->view->assign('sender_types', mailerHelper::getSenderTypes());
    }
}

