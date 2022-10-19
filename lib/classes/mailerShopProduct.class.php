<?php

class mailerShopProduct
{
    public static function pasteProduct(&$campaign, $product_ids)
    {
        if (empty($campaign)) {
            return $campaign;
        }
        if (wa()->appExists('shop')) {
            wa('shop');
            $campaign['count_products'] = (int) $campaign['count_products'];
            $products = (new shopProductsCollection($product_ids))->getProducts('*, images');
            $root_url = wa()->getConfig()->getRootUrl(true);
            $product_blocks = self::getProductBlock($campaign['rebody']);
            $rebody_patterns = [
                '#(<re-image[^<>]*src=")/wa-apps/mailer/img/placeholder\.svg\?{\$PRODUCT_IMAGE_URL}("[^<>]*?data-tag="product_image">)#',
                '#{\$PRODUCT_NAME}#',
                '#{\$PRODUCT_PRICE}#'
            ];
            if (count($product_ids) <= $campaign['count_products']) {
                /* без клонирования блоков */
                foreach ($products as $product) {
                    $img = reset($product['images']);
                    $img_url = $root_url.shopImage::getUrl($img, '200x0');
                    $rebody_replacement = [
                        "$1$img_url$2",
                        ifempty($product, 'name', ''),
                        wa_currency($product['price'], $product['currency'])
                    ];
                    array_shift($product_blocks);
                    $campaign['rebody'] = preg_replace($rebody_patterns, $rebody_replacement, $campaign['rebody'], 1);
                }

                /* удаление незаполненных блоков */
                foreach ($product_blocks as $product_block) {
                    $campaign['rebody'] = str_replace($product_block, '', $campaign['rebody']);
                }
                unset($product_blocks);
            } else {
                /* с клонированием блоков */
                $i = 0;
                $overflow = '';
                $last_block = array_pop($product_blocks);
                foreach ($products as $product) {
                    $i++;
                    $img = reset($product['images']);
                    $img_url = $root_url.shopImage::getUrl($img, '200x0');
                    $rebody_replacement = [
                        "$1$img_url$2",
                        ifempty($product, 'name', ''),
                        wa_currency($product['price'], $product['currency'])
                    ];
                    if ($i < $campaign['count_products']) {
                        $campaign['rebody'] = preg_replace($rebody_patterns, $rebody_replacement, $campaign['rebody'], 1);
                    } else {
                        /** заполнение (клонирование) последнего блока */
                        $overflow .= preg_replace($rebody_patterns, $rebody_replacement, $last_block);
                    }
                }
                $campaign['rebody'] = str_replace($last_block, $overflow, $campaign['rebody']);
            }
            wa('mailer');
        }

        return $campaign;
    }

    private static function getProductBlock($rebody = '')
    {
        $pattern_block = '#<re-block[^<>]*data-tag="product"[^<>]*>.*?</re-block>#';
        preg_match_all($pattern_block, $rebody, $reblocks, PREG_PATTERN_ORDER);

        return ifset($reblocks, 0, []);
    }
}
