<?php

/**
 * Campaign editor, subject and body of the message.
 */
class mailerCampaignsStep1Action extends waViewAction
{
    public function execute()
    {
        $campaign_id = waRequest::get('campaign_id', 0, 'int');
        $product_ids = waRequest::get('products', [], waRequest::TYPE_ARRAY_INT);
        $campaign = null;
        $mm = new mailerMessageModel();
        if ($campaign_id) {
            $campaign = $mm->getById($campaign_id);
        }

        if (!$campaign) {
            if (!mailerHelper::isAuthor()) {
                throw new waException('Access denied.', 403);
            }
            $template_id = waRequest::get('template_id', 0, 'int');
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
                $campaign['id'] = $mm->insert($campaign);
                $campaign['just_created'] = true;

                if ($params) {
                    $mpm->save($campaign['id'], $params);
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
                $this->setTemplate('campaigns/CampaignsStep1ReadOnly.html', true);
            }
        }

        // When campaign contains files, deletion confirmation must mention them, so check them out.
        $contains_files = false;
        $files = @scandir(wa()->getDataPath('files/'.$campaign_id, true, 'mailer'));
        if ($files && count($files) > 2) {
            $contains_files = true;
        }

        if (!empty($product_ids)) {
            $mailer_shop = new mailerShopProduct($campaign, $product_ids);
            $campaign = array_merge($campaign, $mailer_shop->getTemplate());
            waRequest::setParam('id', $campaign['id']);
            waRequest::setParam('data', $campaign);

            /** сохраняем рассылку с подставленными данными продуктов */
            (new mailerCampaignsSaveController())->execute();
        }

        mailerHelper::assignCampaignSidebarVars($this->view, $campaign);
        $this->view->assign('contains_files', $contains_files);
        $this->view->assign('campaign', $campaign);
        $this->view->assign('lang', substr(wa()->getLocale(), 0, 2));
    }
}

