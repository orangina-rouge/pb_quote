<?php
/**
 * 2020 point-barre.com
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 *
 * @author    tenshy
 * @copyright 2020 point-barre.com
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

class Link extends LinkCore
{
    /**
     * Constructor (initialization only).
     */
    public function __construct($protocolLink = null, $protocolContent = null)
    {
        parent::__construct($protocolLink, $protocolContent);
    }

    /**
     * Create a link to a product.
     *
     * @param mixed $product Product object (can be an ID product, but deprecated)
     * @param string $alias
     * @param string $category
     * @param string $ean13
     * @param int $idLang
     * @param int $idShop (since 1.5.0) ID shop need to be used when we generate a product link for a product in a cart
     * @param int $ipa ID product attribute
     *
     * @return string
     */
    public function getProductLink(
        $product,
        $alias = null,
        $category = null,
        $ean13 = null,
        $idLang = null,
        $idShop = null,
        $ipa = null,
        $force_routes = false,
        $relativeProtocol = false,
        $addAnchor = false,
        $extraParams = array()
    ) {
        $dispatcher = Dispatcher::getInstance();

        if (!$idLang) {
            $idLang = Context::getContext()->language->id;
        }

        $url = $this->getBaseLink($idShop, null, $relativeProtocol) . $this->getLangLink($idLang, null, $idShop);

        // Set available keywords
        $params = array();

        if (!is_object($product)) {
            if (is_array($product) && isset($product['id_product'])) {
                $params['id'] = $product['id_product'];
            } elseif ((int) $product) {
                $params['id'] = $product;
            } else {
                throw new PrestaShopException('Invalid product vars');
            }
        } else {
            $params['id'] = $product->id;
        }

        //Attribute equal to 0 or empty is useless, so we force it to null so that it won't be inserted in query parameters
        if (empty($ipa)) {
            $ipa = __DEFAULT_IPA__;
        }
        $params['id_product_attribute'] = $ipa;
        if (!$alias) {
            $product = $this->getProductObject($product, $idLang, $idShop);
        }
        $params['rewrite'] = (!$alias) ? $product->getFieldByLang('link_rewrite') : $alias;
        if (!$ean13) {
            $product = $this->getProductObject($product, $idLang, $idShop);
        }
        $params['ean13'] = (!$ean13) ? $product->ean13 : $ean13;
        if ($dispatcher->hasKeyword('product_rule', $idLang, 'meta_keywords', $idShop)) {
            $product = $this->getProductObject($product, $idLang, $idShop);
            $params['meta_keywords'] = Tools::str2url($product->getFieldByLang('meta_keywords'));
        }
        if ($dispatcher->hasKeyword('product_rule', $idLang, 'meta_title', $idShop)) {
            $product = $this->getProductObject($product, $idLang, $idShop);
            $params['meta_title'] = Tools::str2url($product->getFieldByLang('meta_title'));
        }

        if ($dispatcher->hasKeyword('product_rule', $idLang, 'manufacturer', $idShop)) {
            $product = $this->getProductObject($product, $idLang, $idShop);
            $params['manufacturer'] = Tools::str2url($product->isFullyLoaded ? $product->manufacturer_name : Manufacturer::getNameById($product->id_manufacturer));
        }

        if ($dispatcher->hasKeyword('product_rule', $idLang, 'supplier', $idShop)) {
            $product = $this->getProductObject($product, $idLang, $idShop);
            $params['supplier'] = Tools::str2url($product->isFullyLoaded ? $product->supplier_name : Supplier::getNameById($product->id_supplier));
        }

        if ($dispatcher->hasKeyword('product_rule', $idLang, 'price', $idShop)) {
            $product = $this->getProductObject($product, $idLang, $idShop);
            $params['price'] = $product->isFullyLoaded ? $product->price : Product::getPriceStatic($product->id, false, null, 6, null, false, true, 1, false, null, null, null, $product->specificPrice);
        }

        if ($dispatcher->hasKeyword('product_rule', $idLang, 'tags', $idShop)) {
            $product = $this->getProductObject($product, $idLang, $idShop);
            $params['tags'] = Tools::str2url($product->getTags($idLang));
        }

        if ($dispatcher->hasKeyword('product_rule', $idLang, 'category', $idShop)) {
            if (!$category) {
                $product = $this->getProductObject($product, $idLang, $idShop);
            }
            $params['category'] = (!$category) ? $product->category : $category;
        }

        if ($dispatcher->hasKeyword('product_rule', $idLang, 'reference', $idShop)) {
            $product = $this->getProductObject($product, $idLang, $idShop);
            $params['reference'] = Tools::str2url($product->reference);
        }

        if ($dispatcher->hasKeyword('product_rule', $idLang, 'categories', $idShop)) {
            $product = $this->getProductObject($product, $idLang, $idShop);
            $params['category'] = (!$category) ? $product->category : $category;
            $cats = array();
            foreach ($product->getParentCategories($idLang) as $cat) {
                if (!in_array($cat['id_category'], Link::$category_disable_rewrite)) {
                    //remove root and home category from the URL
                    $cats[] = $cat['link_rewrite'];
                }
            }
            $params['categories'] = implode('/', $cats);
        }
        if ($ipa) {
            $product = $this->getProductObject($product, $idLang, $idShop);
        }
        $anchor = $ipa ? $product->getAnchor((int) $ipa, (bool) $addAnchor) : '';

        return $url . $dispatcher->createUrl('product_rule', $idLang, array_merge($params, $extraParams), $force_routes, $anchor, $idShop);
    }
}
