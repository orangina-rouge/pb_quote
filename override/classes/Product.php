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

define('__DEFAULT_IPA__', 1011);

class Product extends ProductCore
{
    const PIECE = 'Pièce';
    const TVA = 'TVA';
    const VAT = 'vat';
    const ROOM = 'room';

    public function __construct($id_product = null, $full = false, $id_lang = null, $id_shop = null, Context $context = null)
    {
        parent::__construct($id_product, $full, $id_lang, $id_shop, $context);
    }

    /**
     * Init computation of price display method (i.e. price should be including tax or not) for a customer.
     * If customer Id passed as null then this compute price display method with according of current group.
     * Otherwise a price display method will compute with according of a customer address (i.e. country).
     *
     * @see Group::getPriceDisplayMethod()
     *
     * @param int|null $id_customer
     */
    public static function initPricesComputation($id_customer = null)
    {
        if ((int) $id_customer > 0) {
            $customer = new Customer((int) $id_customer);
            if (!Validate::isLoadedObject($customer)) {
                die(Tools::displayError());
            }
            self::$_taxCalculationMethod = Group::getPriceDisplayMethod((int) $customer->id_default_group);
            $cur_cart = Context::getContext()->cart;
            $id_address = 0;
            if (Validate::isLoadedObject($cur_cart)) {
                $id_address = (int) $cur_cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
            }
            $address_infos = Address::getCountryAndState($id_address);

            if (self::$_taxCalculationMethod != PS_TAX_EXC
                && !empty($address_infos['vat_number'])
                && $address_infos['id_country'] != Configuration::get('VATNUMBER_COUNTRY')
                && Configuration::get('VATNUMBER_MANAGEMENT')) {
                self::$_taxCalculationMethod = PS_TAX_EXC;
            }
        } else {
            self::$_taxCalculationMethod = Group::getPriceDisplayMethod(Group::getCurrent()->id);
        }
    }

    /**
     * Returns price display method for a customer (i.e. price should be including tax or not).
     *
     * @see initPricesComputation()
     *
     * @param int|null $id_customer
     *
     * @return int Returns 0 (PS_TAX_INC) if tax should be included, otherwise 1 (PS_TAX_EXC) - tax should be excluded
     */
    public static function getTaxCalculationMethod($id_customer = null)
    {
        if (self::$_taxCalculationMethod === null || $id_customer !== null) {
            Product::initPricesComputation($id_customer);
        }

        return (int) self::$_taxCalculationMethod;
    }

    /**
     * Get the default attribute for a product.
     *
     * @return int Attributes list
     */
    public static function getDefaultAttribute($id_product, $minimum_quantity = 0, $reset = false)
    {
        return __DEFAULT_IPA__;
    }

    /**
     * For a given id_product and id_product_attribute, return available date.
     *
     * @param int $id_product
     * @param int $id_product_attribute Optional
     *
     * @return string/null
     */
    public static function getAvailableDate($id_product, $id_product_attribute = null)
    {
        return parent::getAvailableDate($id_product, null);
    }

    /**
     * @param $idVat
     * @param $idRoom
     * @param array $attribute
     * @return array
     */
    public static function setDefaultOn($idVat, $idRoom, array $attribute)
    {
        if ($idVat == 0 && $idRoom == 0) {
            $attribute['default_on'] = 1;
        } else {
            $attribute['default_on'] = null;
        }
        return $attribute;
    }

    /**
     * @param $idRoom
     * @return string
     */
    public static function getIdAttributeRoom($idRoom)
    {
        return strval(($idRoom + 1) * 10);
    }

    /**
     * @param $idVat
     * @return string
     */
    public static function getIdAttributeVat($idVat)
    {
        return strval($idVat + 1);
    }

    /**
     * @return string[]
     */
    public static function getVats($id = 1)
    {
        $vats = array();
        foreach (explode(',', Configuration::get('PB_QUOTE_VAT')) as $vat) {
            $vats[] = trim(explode(':',trim($vat))[$id]);
        }
        return $vats;
    }

    public function productAttributeExists($attributes_list, $current_product_attribute = false, Context $context = null, $all_shops = false, $return_id = false)
    {
        $id_product_attribute = self::getIdProductAttributeByIdAttributes($this->id, $attributes_list);

        if($current_product_attribute != $id_product_attribute) {
            if ($return_id) {
                return $id_product_attribute;
            } else {
                return true;
            }
        }
        return false;
    }

    /**
     * Get all available product attributes resume.
     *
     * @param int $id_lang Language id
     *
     * @return array Product attributes combinations
     */
    public function getAttributesResume($id_lang, $attribute_value_separator = ' - ', $attribute_separator = ', ')
    {
//        $results = parent::getAttributesResume($id_lang, $attribute_value_separator, $attribute_separator);
//        PrestaShopLogger::addLog(__METHOD__." ".var_export($results, true));
        $results = array();
        $rooms = self::getRooms();
        $vats = self::getVats();
        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $attribute = array();
                $attribute['id_product_attribute'] = self::computeIdProductAttribute($idRoom, $idVat);
//                $attribute['id_product'] = strval($this->id);
                $attribute['attribute_designation'] = '';
                $attribute['attribute_designation'] .= self::ROOM . $attribute_value_separator . $room ;
                $attribute['attribute_designation'] .= $attribute_separator;
                $attribute['attribute_designation'] .= self::VAT . $attribute_value_separator . $vat;
                $attribute['quantity'] = 0;
                $results[]= $attribute;
            }
        }
        foreach ($results as $row) {
            $cache_key = $row['id_product'] . '_' . $row['id_product_attribute'] . '_quantity';
            if (!Cache::isStored($cache_key)) {
                Cache::store(
                    $cache_key,
                    0
                );
            }
        }
//        PrestaShopLogger::addLog(__METHOD__." ".var_export($results, true));
        return $results;
    }

    /**
     * Get all available product attributes combinations.
     *
     * @param int $id_lang Language id
     * @param bool $groupByIdAttributeGroup
     *
     * @return array Product attributes combinations
     */
    public function getAttributeCombinations($id_lang = null, $groupByIdAttributeGroup = true)
    {
//        $results = parent::getAttributeCombinations($id_lang, $groupByIdAttributeGroup);
//        PrestaShopLogger::addLog(__METHOD__." ".var_export($results, true));
        $results = array();
        $rooms = self::getRooms();
        $vats = self::getVats();
        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $id_product_attribute = self::computeIdProductAttribute($idRoom, $idVat);

                $attribute = self::fullAttribute();
                $attribute['id_product_attribute'] = $id_product_attribute;
                $attribute['id_product'] = strval($this->id);
                $attribute['id_shop'] = strval($this->id_shop);
                $attribute['id_attribute_group'] = '2';
                $attribute['group_name'] = self::ROOM;
                $attribute['attribute_name'] = $room;
                $attribute['id_attribute'] = self::getIdAttributeRoom($idRoom);
                $attribute = self::setDefaultOn($idVat, $idRoom, $attribute);
                $results[] = $attribute;

                $attribute = self::fullAttribute();
                $attribute['id_product_attribute'] = $id_product_attribute;
                $attribute['id_product'] = strval($this->id);
                $attribute['id_shop'] = strval($this->id_shop);
                $attribute['id_attribute_group'] = '3';
                $attribute['group_name'] = self::VAT;
                $attribute['attribute_name'] = $vat;
                $attribute['id_attribute'] = self::getIdAttributeVat($idVat);
                $attribute = self::setDefaultOn($idVat, $idRoom, $attribute);
                $results[] = $attribute;
            }
        }
        foreach ($results as $row) {
            $cache_key = $row['id_product'] . '_' . $row['id_product_attribute'] . '_quantity';
            if (!Cache::isStored($cache_key)) {
                Cache::store(
                    $cache_key,
                    0
                );
            }
        }
//        PrestaShopLogger::addLog(__METHOD__." ".var_export($results, true));
        return $results;
    }

    public static function computeIdProductAttribute($idRoom, $idVat) {
        return strval(1011 + (10 * $idRoom) + $idVat);
    }

    public static function computeIdVat($id_product_attribute) {
        return ($id_product_attribute % 10) - 1;
    }

    public static function computeIdRoom($id_product_attribute) {
        return (int)(($id_product_attribute - ($id_product_attribute % 10) - 1010) / 10);
    }

    /**
     * Get product attribute combination by id_product_attribute.
     *
     * @param int $id_product_attribute
     * @param int $id_lang Language id
     *
     * @return array Product attribute combination by id_product_attribute
     */
    public function getAttributeCombinationsById($id_product_attribute, $id_lang, $groupByIdAttributeGroup = true)
    {
        $res = parent::getAttributeCombinationsById($id_product_attribute, $id_lang, $groupByIdAttributeGroup);
        PrestaShopLogger::addLog(__METHOD__." $id_product_attribute, $id_lang, $groupByIdAttributeGroup ".var_export($res, true));

        if( ((int)$id_product_attribute) < __DEFAULT_IPA__) {
            $id_product_attribute = __DEFAULT_IPA__;
        }
        $idVat = self::computeIdVat((int)$id_product_attribute);
        $idRoom = self::computeIdRoom((int)$id_product_attribute);
        $res = array();
        $rooms = self::getRooms();
        $vats = self::getVats();
        
        $attribute = self::fullAttribute();
        $attribute['id_product_attribute'] = strval($id_product_attribute);
        $attribute['id_attribute'] = self::getIdAttributeRoom($idRoom);
        $attribute['id_attribute_group'] = '2';
        $attribute['id_product'] = strval($this->id);
        $attribute['id_shop'] = strval($this->id_shop);
        $attribute['attribute_name'] = $rooms[$idRoom];
        $attribute['group_name'] = self::ROOM;
        $attribute['public_group_name'] = self::PIECE;
        $attribute['position'] = strval($idRoom);
        $attribute = self::setDefaultOn($idVat, $idRoom, $attribute);
        $res[]= $attribute;

        $attribute = self::fullAttribute();
        $attribute['id_product_attribute'] = strval($id_product_attribute);
        $attribute['id_attribute'] = self::getIdAttributeVat($idVat);
        $attribute['id_attribute_group'] = '3';
        $attribute['id_product'] = strval($this->id);
        $attribute['id_shop'] = strval($this->id_shop);
        $attribute['position'] = strval($idVat);
        $attribute['attribute_name'] = $vats[$idVat];
        $attribute['group_name'] = self::VAT;
        $attribute['public_group_name'] = self::TVA;
        $attribute = self::setDefaultOn($idVat, $idRoom, $attribute);
        $res[]= $attribute;

        foreach ($res as $row) {
            $cache_key = $row['id_product'] . '_' . $row['id_product_attribute'] . '_quantity';
            if (!Cache::isStored($cache_key)) {
                Cache::store(
                    $cache_key,
                    0
                );
            }
        }
        PrestaShopLogger::addLog(__METHOD__." $id_product_attribute, $id_lang, $groupByIdAttributeGroup ".var_export($res, true));
        return $res;
    }

    public function getCombinationImages($id_lang)
    {
        return false;
    }

    public static function getCombinationImageById($id_product_attribute, $id_lang)
    {
        return false;
    }

    /**
     * Check if product has attributes combinations.
     *
     * @return int Attributes combinations number
     */
    public function hasAttributes()
    {
//        $results = parent::hasAttributes();
//        PrestaShopLogger::addLog(__METHOD__." ".var_export($results, true));
        $results = 0;
        $rooms = self::getRooms();
        $vats = self::getVats();
        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $results++;
            }
        }
//        PrestaShopLogger::addLog(__METHOD__." ".var_export($results, true));
        return $results;
    }

    /**
     * Get new products.
     *
     * @param int $id_lang Language id
     * @param int $pageNumber Start from (optional)
     * @param int $nbProducts Number of products to return (optional)
     *
     * @return array New products
     */
    public static function getNewProducts($id_lang, $page_number = 0, $nb_products = 10, $count = false, $order_by = null, $order_way = null, Context $context = null)
    {
        $now = date('Y-m-d') . ' 00:00:00';
        if (!$context) {
            $context = Context::getContext();
        }

        $front = true;
        if (!in_array($context->controller->controller_type, array('front', 'modulefront'))) {
            $front = false;
        }

        if ($page_number < 1) {
            $page_number = 1;
        }
        if ($nb_products < 1) {
            $nb_products = 10;
        }
        if (empty($order_by) || $order_by == 'position') {
            $order_by = 'date_add';
        }
        if (empty($order_way)) {
            $order_way = 'DESC';
        }
        if ($order_by == 'id_product' || $order_by == 'price' || $order_by == 'date_add' || $order_by == 'date_upd') {
            $order_by_prefix = 'product_shop';
        } elseif ($order_by == 'name') {
            $order_by_prefix = 'pl';
        }
        if (!Validate::isOrderBy($order_by) || !Validate::isOrderWay($order_way)) {
            die(Tools::displayError());
        }

        $sql_groups = '';
        if (Group::isFeatureActive()) {
            $groups = FrontController::getCurrentCustomerGroups();
            $sql_groups = ' AND EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp
                JOIN `' . _DB_PREFIX_ . 'category_group` cg ON (cp.id_category = cg.id_category AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '= ' . (int) Configuration::get('PS_UNIDENTIFIED_GROUP')) . ')
                WHERE cp.`id_product` = p.`id_product`)';
        }

        if (strpos($order_by, '.') > 0) {
            $order_by = explode('.', $order_by);
            $order_by_prefix = $order_by[0];
            $order_by = $order_by[1];
        }

        $nb_days_new_product = (int) Configuration::get('PS_NB_DAYS_NEW_PRODUCT');

        if ($count) {
            $sql = 'SELECT COUNT(p.`id_product`) AS nb
                    FROM `' . _DB_PREFIX_ . 'product` p
                    ' . Shop::addSqlAssociation('product', 'p') . '
                    WHERE product_shop.`active` = 1
                    AND product_shop.`date_add` > "' . date('Y-m-d', strtotime('-' . $nb_days_new_product . ' DAY')) . '"
                    ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
                    ' . $sql_groups;

            return (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        }
        $sql = new DbQuery();
        $sql->select(
            'p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, pl.`description`, pl.`description_short`, pl.`link_rewrite`, pl.`meta_description`,
            pl.`meta_keywords`, pl.`meta_title`, pl.`name`, pl.`available_now`, pl.`available_later`, image_shop.`id_image` id_image, il.`legend`, m.`name` AS manufacturer_name,
            (DATEDIFF(product_shop.`date_add`,
                DATE_SUB(
                    "' . $now . '",
                    INTERVAL ' . $nb_days_new_product . ' DAY
                )
            ) > 0) as new'
        );

        $sql->from('product', 'p');
        $sql->join(Shop::addSqlAssociation('product', 'p'));
        $sql->leftJoin(
            'product_lang',
            'pl',
            '
            p.`id_product` = pl.`id_product`
            AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('pl')
        );
        $sql->leftJoin('image_shop', 'image_shop', 'image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int) $context->shop->id);
        $sql->leftJoin('image_lang', 'il', 'image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) $id_lang);
        $sql->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');

        $sql->where('product_shop.`active` = 1');
        if ($front) {
            $sql->where('product_shop.`visibility` IN ("both", "catalog")');
        }
        $sql->where('product_shop.`date_add` > "' . date('Y-m-d', strtotime('-' . $nb_days_new_product . ' DAY')) . '"');
        if (Group::isFeatureActive()) {
            $groups = FrontController::getCurrentCustomerGroups();
            $sql->where('EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp
                JOIN `' . _DB_PREFIX_ . 'category_group` cg ON (cp.id_category = cg.id_category AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Configuration::get('PS_UNIDENTIFIED_GROUP')) . ')
                WHERE cp.`id_product` = p.`id_product`)');
        }

        $sql->orderBy((isset($order_by_prefix) ? pSQL($order_by_prefix) . '.' : '') . '`' . pSQL($order_by) . '` ' . pSQL($order_way));
        $sql->limit($nb_products, (int) (($page_number - 1) * $nb_products));

        if (Combination::isFeatureActive()) {
            $sql->select('product_attribute_shop.minimal_quantity AS product_attribute_minimal_quantity, '.__DEFAULT_IPA__.' AS id_product_attribute');
            $sql->leftJoin('product_attribute_shop', 'product_attribute_shop', 'p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int) $context->shop->id);
        }
        $sql->join(Product::sqlStock('p', null));

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if (!$result) {
            return false;
        }

        if ($order_by == 'price') {
            Tools::orderbyPrice($result, $order_way);
        }
        $products_ids = array();
        foreach ($result as $row) {
            $products_ids[] = $row['id_product'];
        }
        // Thus you can avoid one query per product, because there will be only one query for all the products of the cart
        Product::cacheFrontFeatures($products_ids, $id_lang);

        return Product::getProductsProperties((int) $id_lang, $result);
    }

    /**
     * Get a random special.
     *
     * @param int $id_lang Language id
     *
     * @return array Special
     */
    public static function getRandomSpecial($id_lang, $beginning = false, $ending = false, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $front = true;
        if (!in_array($context->controller->controller_type, array('front', 'modulefront'))) {
            $front = false;
        }

        $current_date = date('Y-m-d H:i:00');
        $product_reductions = Product::_getProductIdByDate((!$beginning ? $current_date : $beginning), (!$ending ? $current_date : $ending), $context, true);

        if ($product_reductions) {
            $ids_products = '';
            foreach ($product_reductions as $product_reduction) {
                $ids_products .= '(' . (int) $product_reduction['id_product'] . ',' . ($product_reduction['id_product_attribute'] ? (int) $product_reduction['id_product_attribute'] : __DEFAULT_IPA__) . '),';
            }

            $ids_products = rtrim($ids_products, ',');
            Db::getInstance()->execute('CREATE TEMPORARY TABLE `' . _DB_PREFIX_ . 'product_reductions` (id_product INT UNSIGNED NOT NULL DEFAULT 0, id_product_attribute INT UNSIGNED NOT NULL DEFAULT 0) ENGINE=MEMORY', false);
            if ($ids_products) {
                Db::getInstance()->execute('INSERT INTO `' . _DB_PREFIX_ . 'product_reductions` VALUES ' . $ids_products, false);
            }

            $groups = FrontController::getCurrentCustomerGroups();
            $sql_groups = ' AND EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp
                JOIN `' . _DB_PREFIX_ . 'category_group` cg ON (cp.id_category = cg.id_category AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Configuration::get('PS_UNIDENTIFIED_GROUP')) . ')
                WHERE cp.`id_product` = p.`id_product`)';

            // Please keep 2 distinct queries because RAND() is an awful way to achieve this result
            $sql = 'SELECT product_shop.id_product, '.__DEFAULT_IPA__.' AS id_product_attribute
                    FROM
                    `' . _DB_PREFIX_ . 'product_reductions` pr,
                    `' . _DB_PREFIX_ . 'product` p
                    ' . Shop::addSqlAssociation('product', 'p') . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop
                        ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int) $context->shop->id . ')
                    WHERE p.id_product=pr.id_product AND (pr.id_product_attribute = 0 OR product_attribute_shop.id_product_attribute = pr.id_product_attribute) AND product_shop.`active` = 1
                        ' . $sql_groups . '
                    ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
                    ORDER BY RAND()';

            $result = Db::getInstance()->getRow($sql);

            Db::getInstance()->execute('DROP TEMPORARY TABLE `' . _DB_PREFIX_ . 'product_reductions`', false);

            if (!$id_product = $result['id_product']) {
                return false;
            }

            // no group by needed : there's only one attribute with cover=1 for a given id_product + shop
            $sql = 'SELECT p.*, product_shop.*, stock.`out_of_stock` out_of_stock, pl.`description`, pl.`description_short`,
                        pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`, pl.`available_now`, pl.`available_later`,
                        p.`ean13`, p.`isbn`, p.`upc`, image_shop.`id_image` id_image, il.`legend`,
                        DATEDIFF(product_shop.`date_add`, DATE_SUB("' . date('Y-m-d') . ' 00:00:00",
                        INTERVAL ' . (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . '
                            DAY)) > 0 AS new
                    FROM `' . _DB_PREFIX_ . 'product` p
                    LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                        p.`id_product` = pl.`id_product`
                        AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('pl') . '
                    )
                    ' . Shop::addSqlAssociation('product', 'p') . '
                    LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop
                        ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int) $context->shop->id . ')
                    LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) $id_lang . ')
                    ' . Product::sqlStock('p', null) . '
                    WHERE p.id_product = ' . (int) $id_product;

            $row = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);
            if (!$row) {
                return false;
            }

            $row['id_product_attribute'] = (int) $result['id_product_attribute'];

            return Product::getProductProperties($id_lang, $row);
        } else {
            return false;
        }
    }

    /**
     * Get prices drop.
     *
     * @param int $id_lang Language id
     * @param int $pageNumber Start from (optional)
     * @param int $nbProducts Number of products to return (optional)
     * @param bool $count Only in order to get total number (optional)
     *
     * @return array Prices drop
     */
    public static function getPricesDrop(
        $id_lang,
        $page_number = 0,
        $nb_products = 10,
        $count = false,
        $order_by = null,
        $order_way = null,
        $beginning = false,
        $ending = false,
        Context $context = null
    ) {
        if (!Validate::isBool($count)) {
            die(Tools::displayError());
        }

        if (!$context) {
            $context = Context::getContext();
        }
        if ($page_number < 1) {
            $page_number = 1;
        }
        if ($nb_products < 1) {
            $nb_products = 10;
        }
        if (empty($order_by) || $order_by == 'position') {
            $order_by = 'price';
        }
        if (empty($order_way)) {
            $order_way = 'DESC';
        }
        if ($order_by == 'id_product' || $order_by == 'price' || $order_by == 'date_add' || $order_by == 'date_upd') {
            $order_by_prefix = 'product_shop';
        } elseif ($order_by == 'name') {
            $order_by_prefix = 'pl';
        }
        if (!Validate::isOrderBy($order_by) || !Validate::isOrderWay($order_way)) {
            die(Tools::displayError());
        }
        $current_date = date('Y-m-d H:i:00');
        $ids_product = Product::_getProductIdByDate((!$beginning ? $current_date : $beginning), (!$ending ? $current_date : $ending), $context);

        $tab_id_product = array();
        foreach ($ids_product as $product) {
            if (is_array($product)) {
                $tab_id_product[] = (int) $product['id_product'];
            } else {
                $tab_id_product[] = (int) $product;
            }
        }

        $front = true;
        if (!in_array($context->controller->controller_type, array('front', 'modulefront'))) {
            $front = false;
        }

        $sql_groups = '';
        if (Group::isFeatureActive()) {
            $groups = FrontController::getCurrentCustomerGroups();
            $sql_groups = ' AND EXISTS(SELECT 1 FROM `' . _DB_PREFIX_ . 'category_product` cp
                JOIN `' . _DB_PREFIX_ . 'category_group` cg ON (cp.id_category = cg.id_category AND cg.`id_group` ' . (count($groups) ? 'IN (' . implode(',', $groups) . ')' : '=' . (int) Configuration::get('PS_UNIDENTIFIED_GROUP')) . ')
                WHERE cp.`id_product` = p.`id_product`)';
        }

        if ($count) {
            return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue('
            SELECT COUNT(DISTINCT p.`id_product`)
            FROM `' . _DB_PREFIX_ . 'product` p
            ' . Shop::addSqlAssociation('product', 'p') . '
            WHERE product_shop.`active` = 1
            AND product_shop.`show_price` = 1
            ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
            ' . ((!$beginning && !$ending) ? 'AND p.`id_product` IN(' . ((is_array($tab_id_product) && count($tab_id_product)) ? implode(', ', $tab_id_product) : 0) . ')' : '') . '
            ' . $sql_groups);
        }

        if (strpos($order_by, '.') > 0) {
            $order_by = explode('.', $order_by);
            $order_by = pSQL($order_by[0]) . '.`' . pSQL($order_by[1]) . '`';
        }

        $sql = '
        SELECT
            p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, pl.`description`, pl.`description_short`, pl.`available_now`, pl.`available_later`,
            '.__DEFAULT_IPA__.' AS id_product_attribute,
            pl.`link_rewrite`, pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`,
            pl.`name`, image_shop.`id_image` id_image, il.`legend`, m.`name` AS manufacturer_name,
            DATEDIFF(
                p.`date_add`,
                DATE_SUB(
                    "' . date('Y-m-d') . ' 00:00:00",
                    INTERVAL ' . (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY
                )
            ) > 0 AS new
        FROM `' . _DB_PREFIX_ . 'product` p
        ' . Shop::addSqlAssociation('product', 'p') . '
        LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop
            ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int) $context->shop->id . ')
        ' . Product::sqlStock('p', null, false, $context->shop) . '
        LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
            p.`id_product` = pl.`id_product`
            AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('pl') . '
        )
        LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop
            ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int) $context->shop->id . ')
        LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) $id_lang . ')
        LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON (m.`id_manufacturer` = p.`id_manufacturer`)
        WHERE product_shop.`active` = 1
        AND product_shop.`show_price` = 1
        ' . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
        ' . ((!$beginning && !$ending) ? ' AND p.`id_product` IN (' . ((is_array($tab_id_product) && count($tab_id_product)) ? implode(', ', $tab_id_product) : 0) . ')' : '') . '
        ' . $sql_groups . '
        ORDER BY ' . (isset($order_by_prefix) ? pSQL($order_by_prefix) . '.' : '') . pSQL($order_by) . ' ' . pSQL($order_way) . '
        LIMIT ' . (int) (($page_number - 1) * $nb_products) . ', ' . (int) $nb_products;

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if (!$result) {
            return false;
        }

        if ($order_by == 'price') {
            Tools::orderbyPrice($result, $order_way);
        }

        return Product::getProductsProperties($id_lang, $result);
    }

    /**
     * Returns product price.
     *
     * @param int $id_product Product id
     * @param bool $usetax With taxes or not (optional)
     * @param int|null $id_product_attribute product attribute id (optional).
     *                                       If set to false, do not apply the combination price impact.
     *                                       NULL does apply the default combination price impact
     * @param int $decimals Number of decimals (optional)
     * @param int|null $divisor Useful when paying many time without fees (optional)
     * @param bool $only_reduc Returns only the reduction amount
     * @param bool $usereduc Set if the returned amount will include reduction
     * @param int $quantity Required for quantity discount application (default value: 1)
     * @param bool $force_associated_tax DEPRECATED - NOT USED Force to apply the associated tax.
     *                                   Only works when the parameter $usetax is true
     * @param int|null $id_customer Customer ID (for customer group reduction)
     * @param int|null $id_cart Cart ID. Required when the cookie is not accessible
     *                          (e.g., inside a payment module, a cron task...)
     * @param int|null $id_address Customer address ID. Required for price (tax included)
     *                             calculation regarding the guest localization
     * @param null $specific_price_output If a specific price applies regarding the previous parameters,
     *                                    this variable is filled with the corresponding SpecificPrice object
     * @param bool $with_ecotax insert ecotax in price output
     * @param bool $use_group_reduction
     * @param Context $context
     * @param bool $use_customer_price
     *
     * @return float Product price
     */
    public static function getPriceStatic(
        $id_product,
        $usetax = true,
        $id_product_attribute = null,
        $decimals = 6,
        $divisor = null,
        $only_reduc = false,
        $usereduc = true,
        $quantity = 1,
        $force_associated_tax = false,
        $id_customer = null,
        $id_cart = null,
        $id_address = null,
        &$specific_price_output = null,
        $with_ecotax = true,
        $use_group_reduction = true,
        Context $context = null,
        $use_customer_price = true,
        $id_customization = null
    ) {
        if (!$context) {
            $context = Context::getContext();
        }

        $cur_cart = $context->cart;

        if ($divisor !== null) {
            Tools::displayParameterAsDeprecated('divisor');
        }

        if (!Validate::isBool($usetax) || !Validate::isUnsignedId($id_product)) {
            die(Tools::displayError());
        }

        // Initializations
        $id_group = null;
        if ($id_customer) {
            $id_group = Customer::getDefaultGroupId((int) $id_customer);
        }
        if (!$id_group) {
            $id_group = (int) Group::getCurrent()->id;
        }

        // If there is cart in context or if the specified id_cart is different from the context cart id
        if (!is_object($cur_cart) || (Validate::isUnsignedInt($id_cart) && $id_cart && $cur_cart->id != $id_cart)) {
            /*
            * When a user (e.g., guest, customer, Google...) is on PrestaShop, he has already its cart as the global (see /init.php)
            * When a non-user calls directly this method (e.g., payment module...) is on PrestaShop, he does not have already it BUT knows the cart ID
            * When called from the back office, cart ID can be inexistant
            */
            if (!$id_cart && !isset($context->employee)) {
                die(Tools::displayError());
            }
            $cur_cart = new Cart($id_cart);
            // Store cart in context to avoid multiple instantiations in BO
            if (!Validate::isLoadedObject($context->cart)) {
                $context->cart = $cur_cart;
            }
        }

        $cart_quantity = 0;
        if ((int) $id_cart) {
            $cache_id = 'Product::getPriceStatic_' . (int) $id_product . '-' . (int) $id_cart;
            if (!Cache::isStored($cache_id) || ($cart_quantity = Cache::retrieve($cache_id) != (int) $quantity)) {
                $sql = 'SELECT SUM(`quantity`)
                FROM `' . _DB_PREFIX_ . 'cart_product`
                WHERE `id_product` = ' . (int) $id_product . '
                AND `id_cart` = ' . (int) $id_cart;
                $cart_quantity = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
                Cache::store($cache_id, $cart_quantity);
            } else {
                $cart_quantity = Cache::retrieve($cache_id);
            }
        }

        $id_currency = Validate::isLoadedObject($context->currency) ? (int) $context->currency->id : (int) Configuration::get('PS_CURRENCY_DEFAULT');

        if (!$id_address && Validate::isLoadedObject($cur_cart)) {
            $id_address = $cur_cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
        }

        // retrieve address informations
        $address = Address::initialize($id_address, true);
        $id_country = (int) $address->id_country;
        $id_state = (int) $address->id_state;
        $zipcode = $address->postcode;

        if (Tax::excludeTaxeOption()) {
            $usetax = false;
        }

        if ($usetax != false
            && !empty($address->vat_number)
            && $address->id_country != Configuration::get('VATNUMBER_COUNTRY')
            && Configuration::get('VATNUMBER_MANAGEMENT')) {
            $usetax = false;
        }

        if (null === $id_customer && Validate::isLoadedObject($context->customer)) {
            $id_customer = $context->customer->id;
        }

        $return = Product::priceCalculation(
            $context->shop->id,
            $id_product,
            $id_product_attribute,
            $id_country,
            $id_state,
            $zipcode,
            $id_currency,
            $id_group,
            $quantity,
            $usetax,
            $decimals,
            $only_reduc,
            $usereduc,
            $with_ecotax,
            $specific_price_output,
            $use_group_reduction,
            $id_customer,
            $use_customer_price,
            $id_cart,
            $cart_quantity,
            $id_customization
        );

        return $return;
    }

    /**
     * Price calculation / Get product price.
     *
     * @param int $id_shop Shop id
     * @param int $id_product Product id
     * @param int $id_product_attribute Product attribute id
     * @param int $id_country Country id
     * @param int $id_state State id
     * @param string $zipcode
     * @param int $id_currency Currency id
     * @param int $id_group Group id
     * @param int $quantity Quantity Required for Specific prices : quantity discount application
     * @param bool $use_tax with (1) or without (0) tax
     * @param int $decimals Number of decimals returned
     * @param bool $only_reduc Returns only the reduction amount
     * @param bool $use_reduc Set if the returned amount will include reduction
     * @param bool $with_ecotax insert ecotax in price output
     * @param null $specific_price If a specific price applies regarding the previous parameters,
     *                             this variable is filled with the corresponding SpecificPrice object
     * @param bool $use_group_reduction
     * @param int $id_customer
     * @param bool $use_customer_price
     * @param int $id_cart
     * @param int $real_quantity
     *
     * @return float Product price
     **/
    public static function priceCalculation(
        $id_shop,
        $id_product,
        $id_product_attribute,
        $id_country,
        $id_state,
        $zipcode,
        $id_currency,
        $id_group,
        $quantity,
        $use_tax,
        $decimals,
        $only_reduc,
        $use_reduc,
        $with_ecotax,
        &$specific_price,
        $use_group_reduction,
        $id_customer = 0,
        $use_customer_price = true,
        $id_cart = 0,
        $real_quantity = 0,
        $id_customization = 0
    ) {
//        PrestaShopLogger::addLog(__METHOD__." $id_shop, $id_product, $id_product_attribute, $id_country, $id_state, $zipcode, $id_currency, $id_group, $quantity, $use_tax, $decimals, $only_reduc, $use_reduc, $with_ecotax, $specific_price, $use_group_reduction, $id_customer, $use_customer_price, $id_cart, $real_quantity, $id_customization ");
        static $address = null;
        static $context = null;

        if ($context == null) {
            $context = Context::getContext()->cloneContext();
        }

        if ($address === null) {
            if (is_object($context->cart) && $context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')} != null) {
                $id_address = $context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')};
                $address = new Address($id_address);
            } else {
                $address = new Address();
            }
        }

        if ($id_shop !== null && $context->shop->id != (int) $id_shop) {
            $context->shop = new Shop((int) $id_shop);
        }

        if (!$use_customer_price) {
            $id_customer = 0;
        }

        if ($id_product_attribute === null) {
            $id_product_attribute = Product::getDefaultAttribute($id_product);
        }

        $cache_id = (int) $id_product . '-' . (int) $id_shop . '-' . (int) $id_currency . '-' . (int) $id_country . '-' . $id_state . '-' . $zipcode . '-' . (int) $id_group .
            '-' . (int) $quantity . '-' . (int) $id_product_attribute . '-' . (int) $id_customization .
            '-' . (int) $with_ecotax . '-' . (int) $id_customer . '-' . (int) $use_group_reduction . '-' . (int) $id_cart . '-' . (int) $real_quantity .
            '-' . ($only_reduc ? '1' : '0') . '-' . ($use_reduc ? '1' : '0') . '-' . ($use_tax ? '1' : '0') . '-' . (int) $decimals;

        // reference parameter is filled before any returns
        $specific_price = SpecificPrice::getSpecificPrice(
            (int) $id_product,
            $id_shop,
            $id_currency,
            $id_country,
            $id_group,
            $quantity,
            $id_product_attribute,
            $id_customer,
            $id_cart,
            $real_quantity
        );

        if (isset(self::$_prices[$cache_id])) {
//            PrestaShopLogger::addLog(__METHOD__." returns cache ".var_export(self::$_prices[$cache_id], true));
            return self::$_prices[$cache_id];
        }

        // fetch price & attribute price
        $cache_id_2 = $id_product . '-' . $id_shop;
        if (!isset(self::$_pricesLevel2[$cache_id_2])) {
            $sql = new DbQuery();
            $sql->select('product_shop.`price`, product_shop.`ecotax`');
            $sql->from('product', 'p');
            $sql->innerJoin('product_shop', 'product_shop', '(product_shop.id_product=p.id_product AND product_shop.id_shop = ' . (int) $id_shop . ')');
            $sql->where('p.`id_product` = ' . (int) $id_product);

            $res = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            if (is_array($res) && count($res)) {
                foreach ($res as $row) {
                    $array_tmp = array(
                        'price' => $row['price'],
                        'ecotax' => $row['ecotax'],
                        'attribute_price' => (isset($row['attribute_price']) ? $row['attribute_price'] : null),
                    );
                    $id_product_attributes = self::getProductAttributesIds($id_product);
                    foreach($id_product_attributes as $row2) {
                        $tmp_id_product_attribute = (int) $row2['id_product_attribute'];
                        if($tmp_id_product_attribute == __DEFAULT_IPA__) {
                            self::$_pricesLevel2[$cache_id_2][0] = $array_tmp;
                        }
                        self::$_pricesLevel2[$cache_id_2][$tmp_id_product_attribute] = $array_tmp;
                    }
                }
            }
        }

        if (!isset(self::$_pricesLevel2[$cache_id_2][(int) $id_product_attribute])) {
//            PrestaShopLogger::addLog(__METHOD__." returns (empty)");
            return;
        }

        $result = self::$_pricesLevel2[$cache_id_2][(int) $id_product_attribute];

        if (!$specific_price || $specific_price['price'] < 0) {
            $price = (float) $result['price'];
        } else {
            $price = (float) $specific_price['price'];
        }
        // convert only if the specific price is in the default currency (id_currency = 0)
        if (!$specific_price || !($specific_price['price'] >= 0 && $specific_price['id_currency'])) {
            $price = Tools::convertPrice($price, $id_currency);

            if (isset($specific_price['price']) && $specific_price['price'] >= 0) {
                $specific_price['price'] = $price;
            }
        }

        // Attribute price
        if (is_array($result) && (!$specific_price || !$specific_price['id_product_attribute'] || $specific_price['price'] < 0)) {
            $attribute_price = Tools::convertPrice($result['attribute_price'] !== null ? (float) $result['attribute_price'] : 0, $id_currency);
            // If you want the default combination, please use NULL value instead
            if ($id_product_attribute !== false) {
                $price += $attribute_price;
            }
        }

        // Customization price
        if ((int) $id_customization) {
            $price += Tools::convertPrice(Customization::getCustomizationPrice($id_customization), $id_currency);
        }

        // Tax
        $address->id_country = $id_country;
        $address->id_state = $id_state;
        $address->postcode = $zipcode;

        $tax_manager = TaxManagerFactory::getManager($address, Product::getIdTaxRulesGroupByIdProduct((int) $id_product, $context, $id_product_attribute));
        $product_tax_calculator = $tax_manager->getTaxCalculator();

        // Add Tax
        if ($use_tax) {
            $price = $product_tax_calculator->addTaxes($price);
        }

        // Eco Tax
        if (($result['ecotax'] || isset($result['attribute_ecotax'])) && $with_ecotax) {
            $ecotax = $result['ecotax'];
            if (isset($result['attribute_ecotax']) && $result['attribute_ecotax'] > 0) {
                $ecotax = $result['attribute_ecotax'];
            }

            if ($id_currency) {
                $ecotax = Tools::convertPrice($ecotax, $id_currency);
            }
            if ($use_tax) {
                static $psEcotaxTaxRulesGroupId = null;
                if ($psEcotaxTaxRulesGroupId === null) {
                    $psEcotaxTaxRulesGroupId = (int) Configuration::get('PS_ECOTAX_TAX_RULES_GROUP_ID');
                }
                // reinit the tax manager for ecotax handling
                $tax_manager = TaxManagerFactory::getManager(
                    $address,
                    $psEcotaxTaxRulesGroupId
                );
                $ecotax_tax_calculator = $tax_manager->getTaxCalculator();
                $price += $ecotax_tax_calculator->addTaxes($ecotax);
            } else {
                $price += $ecotax;
            }
        }

        // Reduction
        $specific_price_reduction = 0;
        if (($only_reduc || $use_reduc) && $specific_price) {
            if ($specific_price['reduction_type'] == 'amount') {
                $reduction_amount = $specific_price['reduction'];

                if (!$specific_price['id_currency']) {
                    $reduction_amount = Tools::convertPrice($reduction_amount, $id_currency);
                }

                $specific_price_reduction = $reduction_amount;

                // Adjust taxes if required

                if (!$use_tax && $specific_price['reduction_tax']) {
                    $specific_price_reduction = $product_tax_calculator->removeTaxes($specific_price_reduction);
                }
                if ($use_tax && !$specific_price['reduction_tax']) {
                    $specific_price_reduction = $product_tax_calculator->addTaxes($specific_price_reduction);
                }
            } else {
                $specific_price_reduction = $price * $specific_price['reduction'];
            }
        }

        if ($use_reduc) {
            $price -= $specific_price_reduction;
        }

        // Group reduction
        if ($use_group_reduction) {
            $reduction_from_category = GroupReduction::getValueForProduct($id_product, $id_group);
            if ($reduction_from_category !== false) {
                $group_reduction = $price * (float) $reduction_from_category;
            } else { // apply group reduction if there is no group reduction for this category
                $group_reduction = (($reduc = Group::getReductionByIdGroup($id_group)) != 0) ? ($price * $reduc / 100) : 0;
            }

            $price -= $group_reduction;
        }

        if ($only_reduc) {
//            PrestaShopLogger::addLog(__METHOD__." returns only_reduc ".var_export(Tools::ps_round($specific_price_reduction, $decimals), true));
            return Tools::ps_round($specific_price_reduction, $decimals);
        }

        $price = Tools::ps_round($price, $decimals);

        if ($price < 0) {
            $price = 0;
        }

        self::$_prices[$cache_id] = $price;
//        PrestaShopLogger::addLog(__METHOD__." returns (end) ".var_export(self::$_prices[$cache_id], true));
        return self::$_prices[$cache_id];
    }

    /**
     * Get product price
     * Same as static function getPriceStatic, no need to specify product id.
     *
     * @param bool $tax With taxes or not (optional)
     * @param int $id_product_attribute Product attribute id (optional)
     * @param int $decimals Number of decimals (optional)
     * @param int $divisor Util when paying many time without fees (optional)
     *
     * @return float Product price in euros
     */
    public function getPrice(
        $tax = true,
        $id_product_attribute = null,
        $decimals = 6,
        $divisor = null,
        $only_reduc = false,
        $usereduc = true,
        $quantity = 1
    ) {
        return Product::getPriceStatic((int) $this->id, $tax, $id_product_attribute, $decimals, $divisor, $only_reduc, $usereduc, $quantity);
    }

    public function getPublicPrice(
        $tax = true,
        $id_product_attribute = null,
        $decimals = 6,
        $divisor = null,
        $only_reduc = false,
        $usereduc = true,
        $quantity = 1
    ) {
        $specific_price_output = null;

        return Product::getPriceStatic(
            (int) $this->id,
            $tax,
            $id_product_attribute,
            $decimals,
            $divisor,
            $only_reduc,
            $usereduc,
            $quantity,
            false,
            null,
            null,
            null,
            $specific_price_output,
            true,
            true,
            null,
            false
        );
    }

    public function getIdProductAttributeMostExpensive()
    {
        return strval(__DEFAULT_IPA__);
    }

    public function getDefaultIdProductAttribute()
    {
        return strval(__DEFAULT_IPA__);
    }

    /**
     * Get available product quantities (this method already have decreased products in cart).
     *
     * @param int $idProduct Product id
     * @param int $idProductAttribute Product attribute id (optional)
     * @param bool|null $cacheIsPack
     * @param Cart|null $cart
     * @param int $idCustomization Product customization id (optional)
     *
     * @return int Available quantities
     */
    public static function getQuantity(
        $idProduct,
        $idProductAttribute = null,
        $cacheIsPack = null,
        Cart $cart = null,
        $idCustomization = null
    ) {
        if (Pack::isPack((int) $idProduct)) {
            return Pack::getQuantity($idProduct, null, $cacheIsPack, $cart, $idCustomization);
        }
        $availableQuantity = StockAvailable::getQuantityAvailableByProduct($idProduct);
        $nbProductInCart = 0;

        if (!empty($cart)) {
            $cartProduct = $cart->getProductQuantity($idProduct, 0, $idCustomization);

            if (!empty($cartProduct['deep_quantity'])) {
                $nbProductInCart = $cartProduct['deep_quantity'];
            }
        }

        // @since 1.5.0
        return $availableQuantity - $nbProductInCart;
    }

    /**
     * Create JOIN query with 'stock_available' table.
     *
     * @param string $productAlias Alias of product table
     * @param string|int $productAttribute If string : alias of PA table ; if int : value of PA ; if null : nothing about PA
     * @param bool $innerJoin LEFT JOIN or INNER JOIN
     * @param Shop $shop
     *
     * @return string
     */
    public static function sqlStock($product_alias, $product_attribute = null, $inner_join = false, Shop $shop = null)
    {
        $id_shop = ($shop !== null ? (int) $shop->id : null);
        $sql = (($inner_join) ? ' INNER ' : ' LEFT ')
            . 'JOIN ' . _DB_PREFIX_ . 'stock_available stock
            ON (stock.id_product = `' . bqSQL($product_alias) . '`.id_product';

        $sql .= StockAvailable::addSqlShopRestriction(null, $id_shop, 'stock') . ' )';

        return $sql;
    }

    /**
     * Check product availability.
     *
     * @param int $qty Quantity desired
     *
     * @return bool True if product is available with this quantity, false otherwise
     */
    public function checkQty($qty)
    {
        if ($this->isAvailableWhenOutOfStock(StockAvailable::outOfStock($this->id))) {
            return true;
        }
        $availableQuantity = StockAvailable::getQuantityAvailableByProduct($this->id);

        return $qty <= $availableQuantity;
    }

    /**
     * Check if there is no default attribute and create it if not.
     */
    public function checkDefaultAttributes()
    {
        return true;
    }

    public static function getAttributesColorList(array $products, $have_stock = true)
    {
        if (!count($products)) {
            return array();
        }

        $id_lang = Context::getContext()->language->id;

        $check_stock = !Configuration::get('PS_DISP_UNAVAILABLE_ATTR');
        if (!$res = Db::getInstance()->executeS(
            '
            SELECT pa.`id_product`, a.`color`, pac.`id_product_attribute`, ' . ($check_stock ? 'SUM(IF(stock.`quantity` > 0, 1, 0))' : '0') . ' qty, a.`id_attribute`, al.`name`, IF(color = "", a.id_attribute, color) group_by
            FROM `' . _DB_PREFIX_ . 'product_attribute` pa
            ' . Shop::addSqlAssociation('product_attribute', 'pa') .
            ($check_stock ? Product::sqlStock('pa', 'pa') : '') . '
            JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON (pac.`id_product_attribute` = product_attribute_shop.`id_product_attribute`)
            JOIN `' . _DB_PREFIX_ . 'attribute` a ON (a.`id_attribute` = pac.`id_attribute`)
            JOIN `' . _DB_PREFIX_ . 'attribute_lang` al ON (a.`id_attribute` = al.`id_attribute` AND al.`id_lang` = ' . (int) $id_lang . ')
            JOIN `' . _DB_PREFIX_ . 'attribute_group` ag ON (a.id_attribute_group = ag.`id_attribute_group`)
            WHERE pa.`id_product` IN (' . implode(',', array_map('intval', $products)) . ') AND ag.`is_color_group` = 1
            GROUP BY pa.`id_product`, a.`id_attribute`, `group_by`
            ' . ($check_stock ? 'HAVING qty > 0' : '') . '
            ORDER BY a.`position` ASC;'
            )
        ) {
            return false;
        }

        $colors = array();
        foreach ($res as $row) {
            $row['texture'] = '';

            if (Tools::isEmpty($row['color']) && !@filemtime(_PS_COL_IMG_DIR_ . $row['id_attribute'] . '.jpg')) {
                continue;
            } elseif (Tools::isEmpty($row['color']) && @filemtime(_PS_COL_IMG_DIR_ . $row['id_attribute'] . '.jpg')) {
                $row['texture'] = _THEME_COL_DIR_ . $row['id_attribute'] . '.jpg';
            }

            $colors[(int) $row['id_product']][] = array('id_product_attribute' => (int) $row['id_product_attribute'], 'color' => $row['color'], 'texture' => $row['texture'], 'id_product' => $row['id_product'], 'name' => $row['name'], 'id_attribute' => $row['id_attribute']);
        }

        return $colors;
    }

    /**
     * Get all available attribute groups.
     *
     * @param int $id_lang Language id
     *
     * @return array Attribute groups
     */
    public function getAttributesGroups($id_lang)
    {
//        $results = parent::getAttributesGroups($id_lang);
//        PrestaShopLogger::addLog(__METHOD__." $id_lang ".var_export($results, true));
        $results = array();
        $rooms = self::getRooms();
        $vats = self::getVats();

        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $attribute = self::baseAttributeGroups();;
                $attribute['id_product_attribute'] = self::computeIdProductAttribute($idRoom, $idVat);
                $attribute['id_product'] = strval($this->id);
                $attribute['id_shop'] = strval($this->id_shop);
                $attribute['id_attribute'] = self::getIdAttributeRoom($idRoom);
                $attribute['id_attribute_group'] = '2';
                $attribute['attribute_name'] = $room;
                $attribute['group_name'] = self::ROOM;
                $attribute['public_group_name'] = self::PIECE;
                $attribute = self::setDefaultOn($idVat, $idRoom, $attribute);

                $results[]= $attribute;
            }
        }

        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $attribute = self::baseAttributeGroups();;
                $attribute['id_product_attribute'] = self::computeIdProductAttribute($idRoom, $idVat);
                $attribute['id_product'] = strval($this->id);
                $attribute['id_shop'] = strval($this->id_shop);
                $attribute['id_attribute'] = self::getIdAttributeVat($idVat);
                $attribute['id_attribute_group'] = '3';
                $attribute['attribute_name'] = $vat;
                $attribute['group_name'] = self::VAT;
                $attribute['public_group_name'] = self::TVA;
                $attribute = self::setDefaultOn($idVat, $idRoom, $attribute);

                $results[]= $attribute;
            }
        }
//        PrestaShopLogger::addLog(__METHOD__." $id_lang ".var_export($results, true));
        return $results;
    }

    /**
     * Get product accessories.
     *
     * @param int $id_lang Language id
     *
     * @return array Product accessories
     */
    public function getAccessories($id_lang, $active = true)
    {
        $sql = 'SELECT p.*, product_shop.*, stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity, pl.`description`, pl.`description_short`, pl.`link_rewrite`,
                    pl.`meta_description`, pl.`meta_keywords`, pl.`meta_title`, pl.`name`, pl.`available_now`, pl.`available_later`,
                    image_shop.`id_image` id_image, il.`legend`, m.`name` as manufacturer_name, cl.`name` AS category_default, '.__DEFAULT_IPA__.' AS id_product_attribute,
                    DATEDIFF(
                        p.`date_add`,
                        DATE_SUB(
                            "' . date('Y-m-d') . ' 00:00:00",
                            INTERVAL ' . (Validate::isUnsignedInt(Configuration::get('PS_NB_DAYS_NEW_PRODUCT')) ? Configuration::get('PS_NB_DAYS_NEW_PRODUCT') : 20) . ' DAY
                        )
                    ) > 0 AS new
                FROM `' . _DB_PREFIX_ . 'accessory`
                LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = `id_product_2`
                ' . Shop::addSqlAssociation('product', 'p') . '
                LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_shop` product_attribute_shop
                    ON (p.`id_product` = product_attribute_shop.`id_product` AND product_attribute_shop.`default_on` = 1 AND product_attribute_shop.id_shop=' . (int) $this->id_shop . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON (
                    p.`id_product` = pl.`id_product`
                    AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('pl') . '
                )
                LEFT JOIN `' . _DB_PREFIX_ . 'category_lang` cl ON (
                    product_shop.`id_category_default` = cl.`id_category`
                    AND cl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('cl') . '
                )
                LEFT JOIN `' . _DB_PREFIX_ . 'image_shop` image_shop
                    ON (image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int) $this->id_shop . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'image_lang` il ON (image_shop.`id_image` = il.`id_image` AND il.`id_lang` = ' . (int) $id_lang . ')
                LEFT JOIN `' . _DB_PREFIX_ . 'manufacturer` m ON (p.`id_manufacturer`= m.`id_manufacturer`)
                ' . Product::sqlStock('p', 0) . '
                WHERE `id_product_1` = ' . (int) $this->id .
                ($active ? ' AND product_shop.`active` = 1 AND product_shop.`visibility` != \'none\'' : '') . '
                GROUP BY product_shop.id_product';

        if (!$result = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql)) {
            return array();
        }

        foreach ($result as $k => &$row) {
            if (!Product::checkAccessStatic((int) $row['id_product'], false)) {
                unset($result[$k]);

                continue;
            } else {
                $row['id_product_attribute'] = Product::getDefaultAttribute((int) $row['id_product']);
            }
        }

        return $this->getProductsProperties($id_lang, $result);
    }

    /**
     * Admin panel product search.
     *
     * @param int $id_lang Language id
     * @param string $query Search query
     *
     * @return array Matching products
     */
    public static function searchByName($id_lang, $query, Context $context = null)
    {
        if (!$context) {
            $context = Context::getContext();
        }

        $sql = new DbQuery();
        $sql->select('p.`id_product`, pl.`name`, p.`ean13`, p.`isbn`, p.`upc`, p.`active`, p.`reference`, m.`name` AS manufacturer_name, stock.`quantity`, product_shop.advanced_stock_management, p.`customizable`');
        $sql->from('product', 'p');
        $sql->join(Shop::addSqlAssociation('product', 'p'));
        $sql->leftJoin(
            'product_lang',
            'pl',
            'p.`id_product` = pl.`id_product`
            AND pl.`id_lang` = ' . (int) $id_lang . Shop::addSqlRestrictionOnLang('pl')
        );
        $sql->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');

        $where = 'pl.`name` LIKE \'%' . pSQL($query) . '%\'
        OR p.`ean13` LIKE \'%' . pSQL($query) . '%\'
        OR p.`isbn` LIKE \'%' . pSQL($query) . '%\'
        OR p.`upc` LIKE \'%' . pSQL($query) . '%\'
        OR p.`reference` LIKE \'%' . pSQL($query) . '%\'
        OR p.`supplier_reference` LIKE \'%' . pSQL($query) . '%\'
        OR EXISTS(SELECT * FROM `' . _DB_PREFIX_ . 'product_supplier` sp WHERE sp.`id_product` = p.`id_product` AND `product_supplier_reference` LIKE \'%' . pSQL($query) . '%\')';

        $sql->orderBy('pl.`name` ASC');

        $sql->where($where);
        $sql->join(Product::sqlStock('p', 0));

        $result = Db::getInstance()->executeS($sql);

        if (!$result) {
            return false;
        }

        $results_array = array();
        foreach ($result as $row) {
            $row['price_tax_incl'] = Product::getPriceStatic($row['id_product'], true, null, 2);
            $row['price_tax_excl'] = Product::getPriceStatic($row['id_product'], false, null, 2);
            $results_array[] = $row;
        }

        return $results_array;
    }

    /**
     * Duplicate attributes when duplicating a product.
     *
     * @param int $id_product_old Old product id
     * @param int $id_product_new New product id
     */
    public static function duplicateAttributes($id_product_old, $id_product_new)
    {
        return true;
    }

    public static function getAttributesImpacts($id_product)
    {
        return array();
    }

    /**
     * Get product attribute image associations.
     *
     * @param int $id_product_attribute
     *
     * @return array
     */
    public static function _getAttributeImageAssociations($id_product_attribute)
    {
        return array();
    }

    public static function getProductProperties($id_lang, $row, Context $context = null)
    {
        Hook::exec('actionGetProductPropertiesBefore', [
            'id_lang' => $id_lang,
            'product' => &$row,
            'context' => $context,
        ]);

        if (!$row['id_product']) {
            return false;
        }

        if ($context == null) {
            $context = Context::getContext();
        }

        $id_product_attribute = $row['id_product_attribute'] = (!empty($row['id_product_attribute']) ? (int) $row['id_product_attribute'] : __DEFAULT_IPA__);

        // Product::getDefaultAttribute is only called if id_product_attribute is missing from the SQL query at the origin of it:
        // consider adding it in order to avoid unnecessary queries
        $row['allow_oosp'] = Product::isAvailableWhenOutOfStock($row['out_of_stock']);
        if ($id_product_attribute === null
            && ((isset($row['cache_default_attribute']) && ($ipa_default = $row['cache_default_attribute']) !== null)
                || ($ipa_default = Product::getDefaultAttribute($row['id_product'], !$row['allow_oosp'])))) {
            $id_product_attribute = $row['id_product_attribute'] = $ipa_default;
        }

        // Tax
        $usetax = !Tax::excludeTaxeOption();

        $cache_key = $row['id_product'] . '-' . $id_product_attribute . '-' . $id_lang . '-' . (int) $usetax;
        if (isset($row['id_product_pack'])) {
            $cache_key .= '-pack' . $row['id_product_pack'];
        }

        if (isset(self::$productPropertiesCache[$cache_key])) {
            return array_merge($row, self::$productPropertiesCache[$cache_key]);
        }

        // Datas
        $row['category'] = Category::getLinkRewrite((int) $row['id_category_default'], (int) $id_lang);
        $row['category_name'] = Db::getInstance()->getValue('SELECT name FROM ' . _DB_PREFIX_ . 'category_lang WHERE id_shop = ' . (int) $context->shop->id . ' AND id_lang = ' . (int) $id_lang . ' AND id_category = ' . (int) $row['id_category_default']);
        $row['link'] = $context->link->getProductLink((int) $row['id_product'], $row['link_rewrite'], $row['category'], $row['ean13'], null, null, __DEFAULT_IPA__);

        $row['attribute_price'] = 0;

        if (isset($row['quantity_wanted'])) {
            // 'quantity_wanted' may very well be zero even if set
            $quantity = max((int) $row['minimal_quantity'], (int) $row['quantity_wanted']);
        } elseif (isset($row['cart_quantity'])) {
            $quantity = max((int) $row['minimal_quantity'], (int) $row['cart_quantity']);
        } else {
            $quantity = (int) $row['minimal_quantity'];
        }

        $row['price_tax_exc'] = Product::getPriceStatic(
            (int) $row['id_product'],
            false,
            $id_product_attribute,
            (self::$_taxCalculationMethod == PS_TAX_EXC ? 2 : 6),
            null,
            false,
            true,
            $quantity
        );

        if (self::$_taxCalculationMethod == PS_TAX_EXC) {
            $row['price_tax_exc'] = Tools::ps_round($row['price_tax_exc'], 2);
            $row['price'] = Product::getPriceStatic(
                (int) $row['id_product'],
                true,
                $id_product_attribute,
                6,
                null,
                false,
                true,
                $quantity
            );
            $row['price_without_reduction'] =
            $row['price_without_reduction_without_tax'] = Product::getPriceStatic(
                (int) $row['id_product'],
                false,
                $id_product_attribute,
                2,
                null,
                false,
                false,
                $quantity
            );
        } else {
            $row['price'] = Tools::ps_round(
                Product::getPriceStatic(
                    (int) $row['id_product'],
                    true,
                    $id_product_attribute,
                    6,
                    null,
                    false,
                    true,
                    $quantity
                ),
                (int) Configuration::get('PS_PRICE_DISPLAY_PRECISION')
            );
            $row['price_without_reduction'] = Product::getPriceStatic(
                (int) $row['id_product'],
                true,
                $id_product_attribute,
                6,
                null,
                false,
                false,
                $quantity
            );
            $row['price_without_reduction_without_tax'] = Product::getPriceStatic(
                (int) $row['id_product'],
                false,
                $id_product_attribute,
                6,
                null,
                false,
                false,
                $quantity
            );
        }

        $row['reduction'] = Product::getPriceStatic(
            (int) $row['id_product'],
            (bool) $usetax,
            $id_product_attribute,
            6,
            null,
            true,
            true,
            $quantity,
            true,
            null,
            null,
            null,
            $specific_prices
        );

        $row['reduction_without_tax'] = Product::getPriceStatic(
            (int) $row['id_product'],
            false,
            $id_product_attribute,
            6,
            null,
            true,
            true,
            $quantity,
            true,
            null,
            null,
            null,
            $specific_prices
        );

        $row['specific_prices'] = $specific_prices;

        $row['quantity'] = Product::getQuantity(
            (int) $row['id_product'],
            0,
            isset($row['cache_is_pack']) ? $row['cache_is_pack'] : null,
            $context->cart
        );

        $row['quantity_all_versions'] = $row['quantity'];

        if ($row['id_product_attribute']) {
            $row['quantity'] = Product::getQuantity(
                (int) $row['id_product'],
                $id_product_attribute,
                isset($row['cache_is_pack']) ? $row['cache_is_pack'] : null,
                $context->cart
            );

            $row['available_date'] = Product::getAvailableDate(
                (int) $row['id_product'],
                $id_product_attribute
            );
        }

        $row['id_image'] = Product::defineProductImage($row, $id_lang);
        $row['features'] = Product::getFrontFeaturesStatic((int) $id_lang, $row['id_product']);

        $row['attachments'] = array();
        if (!isset($row['cache_has_attachments']) || $row['cache_has_attachments']) {
            $row['attachments'] = Product::getAttachmentsStatic((int) $id_lang, $row['id_product']);
        }

        $row['virtual'] = ((!isset($row['is_virtual']) || $row['is_virtual']) ? 1 : 0);

        // Pack management
        $row['pack'] = (!isset($row['cache_is_pack']) ? Pack::isPack($row['id_product']) : (int) $row['cache_is_pack']);
        $row['packItems'] = $row['pack'] ? Pack::getItemTable($row['id_product'], $id_lang) : array();
        $row['nopackprice'] = $row['pack'] ? Pack::noPackPrice($row['id_product']) : 0;

        if ($row['pack'] && !Pack::isInStock($row['id_product'], $quantity, $context->cart)) {
            $row['quantity'] = 0;
        }

        $row['customization_required'] = false;
        if (isset($row['customizable']) && $row['customizable'] && Customization::isFeatureActive()) {
            if (count(Product::getRequiredCustomizableFieldsStatic((int) $row['id_product']))) {
                $row['customization_required'] = true;
            }
        }

        $attributes = Product::getAttributesParams($row['id_product'], $row['id_product_attribute']);

        foreach ($attributes as $attribute) {
            $row['attributes'][$attribute['id_attribute_group']] = $attribute;
        }

        $row = Product::getTaxesInformations($row, $context);

        $row['ecotax_rate'] = (float) Tax::getProductEcotaxRate($context->cart->{Configuration::get('PS_TAX_ADDRESS_TYPE')});

        Hook::exec('actionGetProductPropertiesAfter', [
            'id_lang' => $id_lang,
            'product' => &$row,
            'context' => $context,
        ]);



        if (0 != $row['unit_price_ratio']) {
            $unitPrice = ($row['price_tax_exc'] / $row['unit_price_ratio']);
            $row['unit_price_ratio'] = $row['price_tax_exc'] / $unitPrice;
        }

        $row['unit_price'] = ($row['unit_price_ratio'] != 0 ? $row['price'] / $row['unit_price_ratio'] : 0);

        self::$productPropertiesCache[$cache_key] = $row;

        return self::$productPropertiesCache[$cache_key];
    }

    public static function getTaxesInformations($row, Context $context = null)
    {
        static $address = null;

        if ($context === null) {
            $context = Context::getContext();
        }
        if ($address === null) {
            $address = new Address();
        }

        $address->id_country = (int) $context->country->id;
        $address->id_state = 0;
        $address->postcode = 0;

        $tax_manager = TaxManagerFactory::getManager($address, Product::getIdTaxRulesGroupByIdProduct((int) $row['id_product'], $context, (int) $row['id_product_attribute']));
        $row['rate'] = $tax_manager->getTaxCalculator()->getTotalRate();
        $row['tax_name'] = $tax_manager->getTaxCalculator()->getTaxesName();

        return $row;
    }

    public static function getProductsProperties($id_lang, $query_result)
    {
        $results_array = array();

        if (is_array($query_result)) {
            foreach ($query_result as $row) {
                if ($row2 = Product::getProductProperties($id_lang, $row)) {
                    $results_array[] = $row2;
                }
            }
        }

        return $results_array;
    }

    public function getIdTaxRulesGroup()
    {
        return $this->id_tax_rules_group;
    }

    public static function getIdTaxRulesGroupByIdProduct($id_product, Context $context = null, $id_product_attribute = __DEFAULT_IPA__)
    {
        if (!$context) {
            $context = Context::getContext();
        }
        $key = 'product_id_tax_rules_group_' . (int) $id_product . '_' . $id_product_attribute . '_'. (int) $context->shop->id;
        if (!Cache::isStored($key)) {
            $id_tax_rules_groups = self::getVats(0);
            $idVat = self::computeIdVat((int)$id_product_attribute);

            $result = $id_tax_rules_groups[$idVat];
            Cache::store($key, (int) $result);

            return (int) $result;
        }

        return Cache::retrieve($key);
    }

    /**
     * Returns tax rate.
     *
     * @param Address|null $address
     *
     * @return float The total taxes rate applied to the product
     */
    public function getTaxesRate(Address $address = null)
    {
        if (!$address || !$address->id_country) {
            $address = Address::initialize();
        }

        $tax_manager = TaxManagerFactory::getManager($address, self::getIdTaxRulesGroupByIdProduct($this->id));
        $tax_calculator = $tax_manager->getTaxCalculator();

        return $tax_calculator->getTotalRate();
    }

    /**
     * Get all product attributes ids.
     *
     * @since 1.5.0
     *
     * @param int $id_product the id of the product
     *
     * @return array product attribute id list
     */
    public static function getProductAttributesIds($id_product, $shop_only = false)
    {
//        $results = parent::getProductAttributesIds($id_product, $shop_only);
//        PrestaShopLogger::addLog(__METHOD__." $id_product, $shop_only ".var_export($results, true));
        $results = array();
        $rooms = self::getRooms();
        $vats = self::getVats();
        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $attribute = array();
                $attribute['id_product_attribute'] = self::computeIdProductAttribute($idRoom, $idVat);
                $results[]= $attribute;
            }
        }
//        PrestaShopLogger::addLog(__METHOD__." $id_product, $shop_only ".var_export($results, true));
        return $results;
    }

    /**
     * Get label by lang and value by lang too.
     *
     * @param int $id_product
     * @param int $product_attribute_id
     *
     * @return array
     */
    public static function getAttributesParams($id_product, $id_product_attribute)
    {
//        $results = parent::getAttributesParams($id_product, $id_product_attribute);
//        PrestaShopLogger::addLog(__METHOD__." $id_product, $id_product_attribute ".var_export($results, true));
        $results = array();
        if( ((int)$id_product_attribute) < __DEFAULT_IPA__) {
            $id_product_attribute = __DEFAULT_IPA__;
        }
        $idVat = self::computeIdVat((int)$id_product_attribute);
        $idRoom = self::computeIdRoom((int)$id_product_attribute);
        $rooms = self::getRooms();
        $vats = self::getVats();

        $attribute = self::baseAttribute();
        $attribute['id_attribute'] = self::getIdAttributeRoom($idRoom);
        $attribute['id_attribute_group'] = '2';
        $attribute['name'] = $rooms[$idRoom];
        $attribute['group'] = self::ROOM;
        $results[]= $attribute;

        $attribute = self::baseAttribute();
        $attribute['id_attribute'] = self::getIdAttributeVat($idVat);
        $attribute['id_attribute_group'] = '3';
        $attribute['name'] = $vats[$idVat];
        $attribute['group'] = self::VAT;
        $results[]= $attribute;

        $id_lang = (int) Context::getContext()->language->id;
        $cache_id = 'Product::getAttributesParams_' . (int) $id_product . '-' . (int) $id_product_attribute . '-' . (int) $id_lang;
        Cache::store($cache_id, $results);
//        PrestaShopLogger::addLog(__METHOD__." $id_product, $id_product_attribute ".var_export($results, true));
        return $results;
    }

    /**
     * @param int $id_product
     */
    public static function getAttributesInformationsByProduct($id_product)
    {
//        $results = parent::getAttributesInformationsByProduct($id_product);
//        PrestaShopLogger::addLog(__METHOD__." $id_product ".var_export($results, true));
        $results = array();
        $rooms = self::getRooms();
        $vats = self::getVats();
        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $attribute = self::baseAttribute();
                $attribute['id_attribute'] = self::getIdAttributeRoom($idRoom);
                $attribute['id_attribute_group'] = '2';
                $attribute['attribute'] = $room;
                $attribute['group'] = self::ROOM;
                $results[]= $attribute;

                $attribute = self::baseAttribute();
                $attribute['id_attribute'] = self::getIdAttributeVat($idVat);
                $attribute['id_attribute_group'] = '3';
                $attribute['attribute'] = $vat;
                $attribute['group'] = self::VAT;
                $results[]= $attribute;
            }

        }

//        PrestaShopLogger::addLog(__METHOD__." $id_product ".var_export($results, true));
        return $results;
    }

    /**
     * @return bool
     */
    public function hasCombinations()
    {
        if (null === $this->id || 0 >= $this->id) {
            return false;
        }
        $attributes = self::getAttributesInformationsByProduct($this->id);

        return !empty($attributes);
    }

    /**
     * Get an id_product_attribute by an id_product and one or more
     * id_attribute.
     *
     * e.g: id_product 8 with id_attribute 4 (size medium) and
     * id_attribute 5 (color blue) returns id_product_attribute 9 which
     * is the dress size medium and color blue.
     *
     * @param int $idProduct
     * @param int|int[] $idAttributes
     * @param bool $findBest
     *
     * @return int
     *
     * @throws PrestaShopException
     */
    public static function getIdProductAttributeByIdAttributes($idProduct, $idAttributes, $findBest = false)
    {
        if (!is_array($idAttributes) && is_numeric($idAttributes)) {
            $idAttributes = array((int) $idAttributes);
        }

        if (!is_array($idAttributes) || empty($idAttributes)) {
            throw new PrestaShopException(
                sprintf(
                    'Invalid parameter $idAttributes with value: "%s"',
                    print_r($idAttributes, true)
                )
            );
        }

        $idProductAttribute = 1000;
        foreach($idAttributes as $idAttribute) {
            $idProductAttribute += (int)$idAttribute;
        }

        return $idProductAttribute;
    }

    /**
     * Get the combination url anchor of the product.
     *
     * @param int $id_product_attribute
     *
     * @return string
     */
    public function getAnchor($id_product_attribute, $with_id = false)
    {
        $attributes = Product::getAttributesParams($this->id, $id_product_attribute);
        $anchor = '#';
        $sep = Configuration::get('PS_ATTRIBUTE_ANCHOR_SEPARATOR');
        foreach ($attributes as &$a) {
            foreach ($a as &$b) {
                $b = str_replace($sep, '_', Tools::link_rewrite($b));
            }
            $anchor .= '/' . ($with_id && isset($a['id_attribute']) && $a['id_attribute'] ? (int) $a['id_attribute'] . $sep : '') . $a['group'] . $sep . $a['name'];
        }

        return $anchor;
    }

    /**
     * Gets the name of a given product, in the given lang.
     *
     * @since 1.5.0
     *
     * @param int $id_product
     * @param int $id_product_attribute Optional
     * @param int $id_lang Optional
     *
     * @return string
     */
    public static function getProductName($id_product, $id_product_attribute = null, $id_lang = null)
    {
        $rooms = self::getRooms();
        $vats = self::getVats();

        if( !$id_product_attribute || ((int)$id_product_attribute) < __DEFAULT_IPA__) {
            $id_product_attribute = __DEFAULT_IPA__;
        }
        $idVat = self::computeIdVat((int)$id_product_attribute);
        $idRoom = self::computeIdRoom((int)$id_product_attribute);
        $name = ' : ';
        $name .= self::ROOM.' - '.$rooms[$idRoom];
        $name .= ', ';
        $name .= self::VAT.' - '.$vats[$idVat];
        return parent::getProductName($id_product, null, $id_lang) . $name;
    }

    /**
     * For a given product, returns its real quantity.
     *
     * @since 1.5.0
     *
     * @param int $id_product
     * @param int $id_product_attribute
     * @param int $id_warehouse
     * @param int $id_shop
     *
     * @return int real_quantity
     */
    public static function getRealQuantity($id_product, $id_product_attribute = 0, $id_warehouse = 0, $id_shop = null)
    {
        static $manager = null;

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && null === $manager) {
            $manager = StockManagerFactory::getManager();
        }

        if (Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT') && Product::usesAdvancedStockManagement($id_product) &&
            StockAvailable::dependsOnStock($id_product, $id_shop)) {
            return $manager->getProductRealQuantities($id_product, 0, $id_warehouse, true);
        } else {
            return StockAvailable::getQuantityAvailableByProduct($id_product, null, $id_shop);
        }
    }

    public function hasAttributesInOtherShops()
    {
        return false;
    }

    public function isColorUnavailable($id_attribute, $id_shop)
    {
        return array();
    }

    /**
     * @return array
     */
    public static function baseAttribute()
    {
        $attribute = array();
        $attribute['reference'] = '';
        $attribute['ean13'] = '';
        $attribute['isbn'] = '';
        $attribute['upc'] = '';
        return $attribute;
    }

    /**
     * @return array
     */
    public static function fullAttribute()
    {
        $attribute = self::baseAttribute();
        $attribute['supplier_reference'] = '';
        $attribute['location'] = '';
        $attribute['wholesale_price'] = 0.000000;
        $attribute['price'] = 0.000000;
        $attribute['ecotax'] = 0.000000;
        $attribute['quantity'] = 0;
        $attribute['weight'] = 0.000000;
        $attribute['unit_price_impact'] = 0.000000;
        $attribute['minimal_quantity'] = '1';
        $attribute['low_stock_threshold'] = null;
        $attribute['low_stock_alert'] = '0';
        $attribute['available_date'] = '0000-00-00';
        $attribute['is_color_group'] = '0';

        return $attribute;
    }

    /**
     * @return array
     */
    public static function baseAttributeGroups()
    {
        $attribute = array();
        $attribute['reference'] = '';
        $attribute['price'] = 0.000000;
        $attribute['ecotax'] = 0.000000;
        $attribute['quantity'] = 0;
        $attribute['weight'] = 0.000000;
        $attribute['unit_price_impact'] = 0.000000;
        $attribute['minimal_quantity'] = '1';
        $attribute['available_date'] = 0000-00-00;
        $attribute['is_color_group'] = '0';
        $attribute['group_type'] = 'select';
        $attribute['attribute_color'] = '';

        return $attribute;
    }

    /**
     * @return string[]
     */
    public static function getRooms()
    {
        $rooms = array();
        foreach (explode(',', Configuration::get('PB_QUOTE_ROOMS')) as $room) {
            $rooms[] = trim($room);
        }
        return $rooms;
    }
}
