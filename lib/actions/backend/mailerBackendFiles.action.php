<?php

class mailerBackendFilesAction extends waViewAction {
    public function execute() {
        $campaign_id = waRequest::get('campaign_id', 0, waRequest::TYPE_INT);

        $campaign = null;
        $mm = new mailerMessageModel();
        if ($campaign_id) {
            $campaign = $mm->getById($campaign_id);
        }
        $last_campaign = $mm->getListView(false, 0, 1, 'datetime', true);

        if (!$campaign) {
            if (!mailerHelper::isAuthor()) {
                throw new waException('Access denied.', 403);
            }
            $template_id = waRequest::get('template_id', 0, waRequest::TYPE_INT);
            $tm = new mailerTemplateModel();
            if ($template_id) {
                $campaign = $tm->getById($template_id);
                $campaign['id'] = '';
            }
            if ($campaign && count($campaign) > 1) {
                // Create campaign right now when template is specified
                $mpm = new mailerMessageParamsModel();
                $params = $mpm->getByMessage($campaign['id']);

                unset($campaign['id']);
                $campaign['create_datetime'] = date("Y-m-d H:i:s");
                $campaign['create_contact_id'] = $this->getUser()->getId();
                $campaign['is_template'] = 0;
                $campaign['status'] = 0;
                //$campaign['id'] = $mm->insert($campaign);
                $campaign['just_created'] = true;

                if ($params) {
                    //$mpm->save($campaign['id'], $params);
                }

                mailerHelper::copyMessageFiles($campaign['id'], $campaign);
            } else {
                // No template specified: create from scratch
                $campaign = $tm->getEmptyTemplate();
            }
        } else {
            // Access control
            $access = mailerHelper::campaignAccess($campaign);
            if ($access < 1) {
                throw new waException('Access denied.', 403);
            }
            if (($campaign['status'] != mailerMessageModel::STATUS_DRAFT && $campaign['status'] != mailerMessageModel::STATUS_PENDING) || $access < 2) {
               // $this->setTemplate('campaigns/CampaignsStep1ReadOnly.html', true);
            }
        }

        $files = waFiles::listdir(wa()->getDataPath('files/'.$campaign_id, true, 'mailer'));
        $campaign_files = [];
        if ($files) {
            foreach ($files as $file) {
                switch (waFiles::extension($file)) {
                    case 'jpg':
                    case 'jpeg':
                    case 'jpe':
                    case 'png':
                    case 'apng':
                    case 'gif':
                    case 'bmp':
                    case 'svg':
                    case 'webp':
                        $is_image = true;
                        break;
                    default:
                        $is_image = false;
                }
                $campaign_files[] = [
                    'name' => $file,
                    'is_image' => $is_image,
                    'url' => wa()->getDataUrl('files/'.$campaign_id, true, 'mailer').'/'.$file
                ];
            }
        }

        $this->view->assign('campaign_files', $campaign_files);
        $this->view->assign('campaign_id', $campaign_id);
    }
}