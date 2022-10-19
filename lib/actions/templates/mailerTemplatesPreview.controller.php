<?php

class mailerTemplatesPreviewController extends waJsonController
{
    public function execute()
    {
        $user_rights = wa()->getUser()->getRights('mailer');
        if (empty($user_rights)) {
            throw new waException(_w('Access denied'), 403);
        }

        $previews = [];
        $product_ids = waRequest::post('product_ids', [], 'array');
        if ($product_ids && wa()->appExists('shop')) {
            $previews = [];
            $tm = new mailerTemplateModel();
            $templates = $tm->getTemplates();
            foreach ($templates as $template) {
                if ((int) ifempty($template, 'count_products', 0) !== 0) {
                    $previews[] = $template;
                }
            }
        }

        $this->response = $previews;
    }
}
