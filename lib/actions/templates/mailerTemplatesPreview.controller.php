<?php

class mailerTemplatesPreviewController extends waJsonController
{
    public function execute()
    {
        $user_rights = wa()->getUser()->getRights('mailer');
        if (empty($user_rights)) {
            throw new waException(_w('Access denied.'), 403);
        }

        $previews = [];
        $product_ids = waRequest::post('product_ids', [], waRequest::TYPE_ARRAY_INT);
        if ($product_ids && wa()->appExists('shop')) {
            $tm = new mailerTemplateModel();
            $templates = $tm->getTemplates();
            foreach ($templates as $template) {
                if ((int) ifempty($template, 'count_products', 0) > 0) {
                    $mailer_shop = new mailerShopProduct($template, $product_ids);
                    $template = array_merge($template, $mailer_shop->getTemplate());
                    $previews[] = $template;
                }
            }
        }

        $this->response = $previews;
    }
}
