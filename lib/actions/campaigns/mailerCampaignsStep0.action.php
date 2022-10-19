<?php

/**
 * New campaign page. Suggests a list of templates to choose from.
 */
class mailerCampaignsStep0Action extends waViewAction
{
    public function execute()
    {
        // Sccess control
        if (!mailerHelper::isAuthor()) {
            throw new waException('Access denied.', 403);
        }

        // List of templates
        $tm = new mailerTemplateModel();
        $templates = $tm->getTemplates(($this->whichUI() === '1.3'));

        if (!$templates) {
            // When there are no templates, open next step immidiately,
            // creating new campaign from scratch.
            $this->redirect('?module=campaigns&action=step1');
        }

        foreach ($templates as $key => &$v) {
            if ((int) $v['count_products'] > 0) {
                unset($templates[$key]);
                continue;
            } elseif (!trim($v['subject'])) {
                $v['subject'] = _w('<no subject>');
            }
            $v['image'] = mailerHelper::getTemplatePreviewUrl($v['id']);
            if (!$v['image']) {
                $v['preview_content'] = $v['body'];
            }
        }
        unset($v);

        $this->view->assign('templates', $templates);
    }
}

