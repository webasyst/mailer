<?php

class mailerShopProduct
{
    private $product_ids;
    private $body;
    private $rebody;
    private $type_id;
    private $routing;

    public function __construct($campaign, $product_ids)
    {
        $this->product_ids = ifset($product_ids, []);
        $this->body = ifset($campaign, 'body', '');
        $this->rebody = ifset($campaign, 'rebody', '');
        $this->setStorefront();
    }

    /**
     * @return void
     * @throws waException
     */
    private function setStorefront()
    {
        $this->routing = wa()->getRouting();
        $domain_routes = $this->routing->getByApp('shop');
        foreach ($domain_routes as $domain => $routes) {
            foreach ($routes as $route) {
                /** берем первую попавшую витрину */
                $this->routing->setRoute($route, $domain);
                $this->type_id = ifempty($route['type_id'], []);
                if ($this->type_id) {
                    $this->type_id = array_map('intval', $this->type_id);
                }
                break 2;
            }
        }
    }

    public function getTemplate()
    {
        if (wa()->appExists('shop')) {
            $this->pasteProduct();
        }

        return [
            'body'   => $this->body,
            'rebody' => $this->rebody
        ];
    }

    /**
     * Вставка в шаблоны информации о товарах
     *
     * @return void
     * @throws waException
     */
    private function pasteProduct()
    {
        wa('shop');
        $products = (new shopProductsCollection($this->product_ids))->getProducts('*, images, frontend_url');
        $product_blocks = $this->getProductBlock();
        $product_reblocks = $this->getProductReblock();

        // ?? count $product_blocks === count $product_reblocks
        foreach ($this->product_ids as $product_id) {
            if ($product_id < 1 && empty($products[$product_id])) {
                continue;
            }
            $product = $products[$product_id];
            $product_block = array_shift($product_blocks);
            $product_reblock = array_shift($product_reblocks);
            if (empty($product_block) || empty($product_reblock)) {
                break;
            }
            $body_block = $this->pasteVariables($product, $product_block);
            $rebody_block = $this->pasteVariables($product, $product_reblock);
            $this->body = $this->strReplaceOnce($product_block, $body_block, $this->body);
            $this->rebody = $this->strReplaceOnce($product_reblock, $rebody_block, $this->rebody);
        }
        wa('mailer');
    }

    /**
     * Поиск и подстановка текста в первом вхождении
     *
     * @param $search
     * @param $replace
     * @param $text
     * @return string
     */
    private function strReplaceOnce($search, $replace, $text) {
        $pos = mb_strpos($text, $search);
        if ($pos !== false) {
            $txt = mb_substr($text, 0, $pos).$replace.mb_substr($text, $pos + mb_strlen($search));
        } else {
            $txt = $text;
        }

        return $txt;
    }

    /**
     * Подсчет количества блоков в шаблоне под товары
     *
     * @param $rebody
     * @return int
     */
    public static function getCountProducts($rebody)
    {
        $rebody = ifempty($rebody, '');
        preg_match_all('#<[^<>]+SHOP_SCRIPT_PRODUCT_BLOCK[^<>]+>#', $rebody, $product_image, PREG_SET_ORDER);

        return count($product_image);
    }

    /**
     * Получение из HTML шаблона блоков для товаров
     *
     * @return array
     */
    private function getProductBlock()
    {
        $pattern_block = '#
            <td[^<>]+class="[^<>"\']*SHOP_SCRIPT_PRODUCT_BLOCK[^<>"\']*"[^<>]*>
                \s*<table[^<>]*>.*?</table>\s*
            </td>
            #xs';
        preg_match_all($pattern_block, $this->body, $blocks, PREG_PATTERN_ORDER);

        return ifset($blocks, 0, []);
    }

    /**
     * Получение из Визуального шаблона блоков для товара
     *
     * @return array
     */
    private function getProductReblock()
    {
        $pattern_reblock = '#
            <([^<>\s]+)[^<>]*class=["\'][^<>"\']*SHOP_SCRIPT_PRODUCT_BLOCK[^<>"\']*["\'][^<>]*>
                .*?
            </\1>
            #xs';
        preg_match_all($pattern_reblock, $this->rebody, $reblocks, PREG_PATTERN_ORDER);

        return ifset($reblocks, 0, []);
    }

    /**
     * Вставка переменных товара в HTML или визуальный блок шаблона
     *
     * @param $product
     * @param $body_block
     * @return string
     * @throws waException
     */
    private function pasteVariables($product, $body_block)
    {
        /** image */
        $body_block = $this->imagePaste($product, $body_block);

        $vars = [
            '#%TEST_PATTERN%#'      => '<h4>You should not see this text</h4>',
            '#%PRODUCT_URL%#'       => '',
            '#%PRODUCT_NAME%#'      => ifempty($product, 'name', ''),
            '#%PRODUCT_PRICE%#'     => wa_currency($product['price'], $product['currency']),
            '#%PRODUCT_DESC%#'      => ifset($product, 'summary', ''),
            '#%PRODUCT_PRICE_OLD%#' => '',
        ];

        if ($this->isAvailableProduct($product)) {
            /** если товар доступен на витрине */
            $vars['#%PRODUCT_URL%#'] = $this->getProductUrl($product);
        } else {
            /**
             * Товар НЕ доступен => ампутируем ссылку из блока
             * !!! ВАЖНА последовательность регулярных выражений,
             * чтобы обработать сперва отсутствующие ссылки товара,
             * иначе потеряется переменная {$PRODUCT_URL}
             */
            $vars = ['#<a[^<>]+href=[\'"]%PRODUCT_URL%[\'"][^<>]*>%PRODUCT_NAME%</a>#' => '%PRODUCT_NAME%'] + $vars;
        }
        if (!empty($product['compare_price'])) {
            $vars['#%PRODUCT_PRICE_OLD%#'] = wa_currency($product['compare_price'], $product['currency']);
        }

        foreach ($vars as $pattern => $var) {
            $body_block = preg_replace($pattern, $var, $body_block);
        }

        return $body_block;
    }

    /**
     * Вставка изображения товара в HTML или визуальный блок шаблона
     *
     * @param $product
     * @param $block
     * @return string
     * @throws waException
     */
    private function imagePaste($product, $block)
    {
        $img = reset($product['images']);
        $root_url = wa()->getConfig()->getRootUrl(true);
        $img_url = ($img === false ? '/wa-apps/mailer/img/placeholder.svg' : shopImage::getUrl($img, '200x0'));
        $img_url = rtrim($root_url, '/').$img_url;
        $pattern = '#(<(?:img|re-image)[^<>]*src=")/wa-apps/mailer/img/placeholder\.svg\?%PRODUCT_IMAGE_URL%("[^<>]*?>)#';

        return preg_replace($pattern, "$1$img_url$2", $block, 1);
    }

    private function isAvailableProduct($product)
    {
        $check_status = isset($product['status']) && (int) $product['status'] > -1;
        $check_type = empty($this->type_id) || (!empty($product['type_id']) && in_array((int) $product['type_id'], $this->type_id));

        return $check_type && $check_status;
    }

    /**
     * Формирование ссылки товара на витрине магазина
     *
     * @param $product
     * @return string
     */
    private function getProductUrl($product = [])
    {
        $params = [
            'product_url' => ifempty($product, 'url', '')
        ];
        $url = $this->routing->getUrl('shop/frontend/product', $params, true);

        return waIdna::dec($url);
    }
}
