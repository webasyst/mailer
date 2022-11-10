<?php

/**
 * List of templates.
 */
class mailerTemplatesAction extends waViewAction
{
    public function execute()
    {
        if (!mailerHelper::isAdmin()) {
            throw new waException('Access denied.', 403);
        }
        $template_model = new mailerTemplateModel();
        $templates = $template_model->getTemplates(($this->whichUI() === '1.3'));

        foreach($templates as &$v) {
            if (!trim($v['subject'])) {
                $v['subject'] = _w('<no subject>');
            }
            $v['image'] = mailerHelper::getTemplatePreviewUrl($v['id']);
            if (!$v['image']) {
                $v['preview_content'] = $v['body'];
            }
        }
        unset($v);

        $shop = null;
        if (wa()->appExists('shop')) {
            $tab = waRequest::get('tab', '');
            foreach($templates as $key => &$tmp) {
                switch ($tab) {
                    case 'shop':
                        if ($tmp['count_products'] == 0) {
                            unset($templates[$key]);
                        }
                        break;
                    default:
                        if ($tmp['count_products'] > 0) {
                            unset($templates[$key]);
                        }
                }
            }
            $shop = wa()->getConfig()->getBackendUrl(true).'shop/?action=products';
        }

        $this->view->assign('templates', $templates);
        $this->view->assign('shop', $shop);
    }
}