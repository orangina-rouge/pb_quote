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

class Cart extends CartCore
{

    /**
     * CartCore constructor.
     *
     * @param int|null $id Cart ID
     *                     null = new Cart
     * @param int|null $idLang Language ID
     *                         null = Language ID of current Context
     */
    public function __construct($id = null, $idLang = null)
    {
        parent::__construct($id, $idLang);
    }

    /**
     * Return cart products.
     *
     * @param bool $refresh
     * @param bool $id_product
     * @param int $id_country
     * @param bool $fullInfos
     *
     * @return array Products
     */
    public function getProducts($refresh = false, $id_product = false, $id_country = null, $fullInfos = true)
    {
        if (!$this->id) {
            return array();
        }
        // Product cache must be strictly compared to NULL, or else an empty cart will add dozens of queries
        if ($this->_products !== null && !$refresh) {
            // Return product row with specified ID if it exists
            if (is_int($id_product)) {
                foreach ($this->_products as $product) {
                    if ($product['id_product'] == $id_product) {
                        return array($product);
                    }
                }

                return array();
            }

            return $this->_products;
        }

        // Build query
        $sql = new DbQuery();

        // Build SELECT
        $sql->select('cp.`id_product_attribute`, cp.`id_product`, cp.`quantity` AS cart_quantity, cp.id_shop, cp.`id_customization`, pl.`name`, p.`is_virtual`,
                        pl.`description_short`, pl.`available_now`, pl.`available_later`, product_shop.`id_category_default`, p.`id_supplier`,
                        p.`id_manufacturer`, m.`name` AS manufacturer_name, product_shop.`on_sale`, product_shop.`ecotax`, product_shop.`additional_shipping_cost`,
                        product_shop.`available_for_order`, product_shop.`show_price`, product_shop.`price`, product_shop.`active`, product_shop.`unity`, product_shop.`unit_price_ratio`,
                        stock.`quantity` AS quantity_available, p.`width`, p.`height`, p.`depth`, stock.`out_of_stock`, p.`weight`,
                        p.`available_date`, p.`date_add`, p.`date_upd`, IFNULL(stock.quantity, 0) as quantity, pl.`link_rewrite`, cl.`link_rewrite` AS category,
                        CONCAT(LPAD(cp.`id_product`, 10, 0), LPAD(IFNULL(cp.`id_product_attribute`, 0), 10, 0), IFNULL(cp.`id_address_delivery`, 0), IFNULL(cp.`id_customization`, 0)) AS unique_id, cp.id_address_delivery,
                        product_shop.advanced_stock_management, ps.product_supplier_reference supplier_reference');

        // Build FROM
        $sql->from('cart_product', 'cp');

        // Build JOIN
        $sql->leftJoin('product', 'p', 'p.`id_product` = cp.`id_product`');
        $sql->innerJoin('product_shop', 'product_shop', '(product_shop.`id_shop` = cp.`id_shop` AND product_shop.`id_product` = p.`id_product`)');
        $sql->leftJoin(
            'product_lang',
            'pl',
            'p.`id_product` = pl.`id_product`
            AND pl.`id_lang` = ' . (int) $this->id_lang . Shop::addSqlRestrictionOnLang('pl', 'cp.id_shop')
        );

        $sql->leftJoin(
            'category_lang',
            'cl',
            'product_shop.`id_category_default` = cl.`id_category`
            AND cl.`id_lang` = ' . (int) $this->id_lang . Shop::addSqlRestrictionOnLang('cl', 'cp.id_shop')
        );

        $sql->leftJoin('product_supplier', 'ps', 'ps.`id_product` = cp.`id_product` AND ps.`id_supplier` = p.`id_supplier`');
        $sql->leftJoin('manufacturer', 'm', 'm.`id_manufacturer` = p.`id_manufacturer`');

        // @todo test if everything is ok, then refactorise call of this method
        $sql->join(Product::sqlStock('cp', 'cp'));

        // Build WHERE clauses
        $sql->where('cp.`id_cart` = ' . (int) $this->id);
        if ($id_product) {
            $sql->where('cp.`id_product` = ' . (int) $id_product);
        }
        $sql->where('p.`id_product` IS NOT NULL');

        // Build ORDER BY
        $sql->orderBy('cp.`date_add`, cp.`id_product`, cp.`id_product_attribute` ASC');

        if (Customization::isFeatureActive()) {
            $sql->select('cu.`id_customization`, cu.`quantity` AS customization_quantity');
            $sql->leftJoin(
                'customization',
                'cu',
                'p.`id_product` = cu.`id_product` AND cp.`id_product_attribute` = cu.`id_product_attribute` AND cp.`id_customization` = cu.`id_customization` AND cu.`id_cart` = ' . (int) $this->id
            );
            $sql->groupBy('cp.`id_product_attribute`, cp.`id_product`, cp.`id_shop`, cp.`id_customization`');
        } else {
            $sql->select('NULL AS customization_quantity, NULL AS id_customization');
        }

        $sql->select('
            0 AS price_attribute, 0 AS ecotax_attr,
            p.`reference` AS reference,
            p.`weight` AS weight_attribute,
            p.`ean13` AS ean13,
            p.`isbn` AS isbn,
            p.`upc` AS upc,
            product_shop.`minimal_quantity` AS minimal_quantity,
            product_shop.`wholesale_price` AS wholesale_price
        ');

        $sql->select('image_shop.`id_image` id_image, il.`legend`');
        $sql->leftJoin('image_shop', 'image_shop', 'image_shop.`id_product` = p.`id_product` AND image_shop.cover=1 AND image_shop.id_shop=' . (int) $this->id_shop);
        $sql->leftJoin('image_lang', 'il', 'il.`id_image` = image_shop.`id_image` AND il.`id_lang` = ' . (int) $this->id_lang);

        $result = Db::getInstance()->executeS($sql);
        PrestaShopLogger::addLog(__METHOD__." ".var_export($result, true));

        // Reset the cache before the following return, or else an empty cart will add dozens of queries
        $products_ids = array();
        $pa_ids = array();
        if ($result) {
            foreach ($result as $key => $row) {
                $products_ids[] = $row['id_product'];
                $pa_ids[] = $row['id_product_attribute'];
                $specific_price = SpecificPrice::getSpecificPrice($row['id_product'], $this->id_shop, $this->id_currency, $id_country, $this->id_shop_group, $row['cart_quantity'], 0, $this->id_customer, $this->id);
                if ($specific_price) {
                    $reduction_type_row = array('reduction_type' => $specific_price['reduction_type']);
                } else {
                    $reduction_type_row = array('reduction_type' => 0);
                }

                $result[$key] = array_merge($row, $reduction_type_row);
            }
        }
        // Thus you can avoid one query per product, because there will be only one query for all the products of the cart
        Product::cacheProductsFeatures($products_ids);
        self::cacheSomeAttributesLists($pa_ids, $this->id_lang);

        $this->_products = array();
        if (empty($result)) {
            return array();
        }

        if ($fullInfos) {
            $ecotax_rate = (float) Tax::getProductEcotaxRate($this->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
            $apply_eco_tax = Product::$_taxCalculationMethod == PS_TAX_INC && (int) Configuration::get('PS_TAX');
            $cart_shop_context = Context::getContext()->cloneContext();

            $gifts = $this->getCartRules(CartRule::FILTER_ACTION_GIFT);
            $givenAwayProductsIds = array();

            if ($this->shouldSplitGiftProductsQuantity && count($gifts) > 0) {
                foreach ($gifts as $gift) {
                    foreach ($result as $rowIndex => $row) {
                        if (!array_key_exists('is_gift', $result[$rowIndex])) {
                            $result[$rowIndex]['is_gift'] = false;
                        }

                        if (
                            $row['id_product'] == $gift['gift_product'] &&
                            $row['id_product_attribute'] == $gift['gift_product_attribute']
                        ) {
                            $row['is_gift'] = true;
                            $result[$rowIndex] = $row;
                        }
                    }

                    $index = $gift['gift_product'] . '-' . $gift['gift_product_attribute'];
                    if (!array_key_exists($index, $givenAwayProductsIds)) {
                        $givenAwayProductsIds[$index] = 1;
                    } else {
                        ++$givenAwayProductsIds[$index];
                    }
                }
            }

            foreach ($result as &$row) {
                if (!array_key_exists('is_gift', $row)) {
                    $row['is_gift'] = false;
                }

                $additionalRow = Product::getProductProperties((int) $this->id_lang, $row);
                $row['reduction'] = $additionalRow['reduction'];
                $row['reduction_without_tax'] = $additionalRow['reduction_without_tax'];
                $row['price_without_reduction'] = $additionalRow['price_without_reduction'];
                $row['specific_prices'] = $additionalRow['specific_prices'];
                unset($additionalRow);

                $givenAwayQuantity = 0;
                $giftIndex = $row['id_product'] . '-' . $row['id_product_attribute'];
                if ($row['is_gift'] && array_key_exists($giftIndex, $givenAwayProductsIds)) {
                    $givenAwayQuantity = $givenAwayProductsIds[$giftIndex];
                }

                if (!$row['is_gift'] || (int) $row['cart_quantity'] === $givenAwayQuantity) {
                    $row = $this->applyProductCalculations($row, $cart_shop_context);
                } else {
                    // Separate products given away from those manually added to cart
                    $this->_products[] = $this->applyProductCalculations($row, $cart_shop_context, $givenAwayQuantity);
                    unset($row['is_gift']);
                    $row = $this->applyProductCalculations(
                        $row,
                        $cart_shop_context,
                        $row['cart_quantity'] - $givenAwayQuantity
                    );
                }

                $this->_products[] = $row;
            }
        } else {
            $this->_products = $result;
        }

        return $this->_products;
    }

    /**
     * @param $row
     * @param $shopContext
     * @param $productQuantity
     *
     * @return mixed
     */
    protected function applyProductCalculations($row, $shopContext, $productQuantity = null)
    {
        if (null === $productQuantity) {
            $productQuantity = (int) $row['cart_quantity'];
        }

        if (isset($row['ecotax_attr']) && $row['ecotax_attr'] > 0) {
            $row['ecotax'] = (float) $row['ecotax_attr'];
        }

        $row['stock_quantity'] = (int) $row['quantity'];
        // for compatibility with 1.2 themes
        $row['quantity'] = $productQuantity;

        // get the customization weight impact
        $customization_weight = Customization::getCustomizationWeight($row['id_customization']);

        if (isset($row['id_product_attribute']) && (int) $row['id_product_attribute'] && isset($row['weight_attribute'])) {
            $row['weight_attribute'] += $customization_weight;
            $row['weight'] = (float) $row['weight_attribute'];
        } else {
            $row['weight'] += $customization_weight;
        }

        if (Configuration::get('PS_TAX_ADDRESS_TYPE') == 'id_address_invoice') {
            $address_id = (int) $this->id_address_invoice;
        } else {
            $address_id = (int) $row['id_address_delivery'];
        }
        if (!Address::addressExists($address_id)) {
            $address_id = null;
        }

        if ($shopContext->shop->id != $row['id_shop']) {
            $shopContext->shop = new Shop((int) $row['id_shop']);
        }

        $address = Address::initialize($address_id, true);
        $id_tax_rules_group = Product::getIdTaxRulesGroupByIdProduct((int) $row['id_product'], $shopContext, (int) $row['id_product_attribute']);
        $tax_calculator = TaxManagerFactory::getManager($address, $id_tax_rules_group)->getTaxCalculator();

        $specific_price_output = null;

        $row['price_without_reduction'] = Product::getPriceStatic(
            (int) $row['id_product'],
            true,
            isset($row['id_product_attribute']) ? (int) $row['id_product_attribute'] : null,
            6,
            null,
            false,
            false,
            $productQuantity,
            false,
            (int) $this->id_customer ? (int) $this->id_customer : null,
            (int) $this->id,
            $address_id,
            $specific_price_output,
            true,
            true,
            $shopContext,
            true,
            $row['id_customization']
        );

        $row['price_without_reduction_without_tax'] = Product::getPriceStatic(
            (int) $row['id_product'],
            false,
            isset($row['id_product_attribute']) ? (int) $row['id_product_attribute'] : null,
            6,
            null,
            false,
            false,
            $productQuantity,
            false,
            (int) $this->id_customer ? (int) $this->id_customer : null,
            (int) $this->id,
            $address_id,
            $specific_price_output,
            true,
            true,
            $shopContext,
            true,
            $row['id_customization']
        );

        $row['price_with_reduction'] = Product::getPriceStatic(
            (int) $row['id_product'],
            true,
            isset($row['id_product_attribute']) ? (int) $row['id_product_attribute'] : null,
            6,
            null,
            false,
            true,
            $productQuantity,
            false,
            (int) $this->id_customer ? (int) $this->id_customer : null,
            (int) $this->id,
            $address_id,
            $specific_price_output,
            true,
            true,
            $shopContext,
            true,
            $row['id_customization']
        );

        $row['price'] = $row['price_with_reduction_without_tax'] = Product::getPriceStatic(
            (int) $row['id_product'],
            false,
            isset($row['id_product_attribute']) ? (int) $row['id_product_attribute'] : null,
            6,
            null,
            false,
            true,
            $productQuantity,
            false,
            (int) $this->id_customer ? (int) $this->id_customer : null,
            (int) $this->id,
            $address_id,
            $specific_price_output,
            true,
            true,
            $shopContext,
            true,
            $row['id_customization']
        );

        switch (Configuration::get('PS_ROUND_TYPE')) {
            case Order::ROUND_TOTAL:
                $row['total'] = $row['price_with_reduction_without_tax'] * $productQuantity;
                $row['total_wt'] = $row['price_with_reduction'] * $productQuantity;

                break;
            case Order::ROUND_LINE:
                $row['total'] = Tools::ps_round(
                    $row['price_with_reduction_without_tax'] * $productQuantity,
                    _PS_PRICE_COMPUTE_PRECISION_
                );
                $row['total_wt'] = Tools::ps_round(
                    $row['price_with_reduction'] * $productQuantity,
                    _PS_PRICE_COMPUTE_PRECISION_
                );

                break;

            case Order::ROUND_ITEM:
            default:
                $row['total'] = Tools::ps_round(
                        $row['price_with_reduction_without_tax'],
                        _PS_PRICE_COMPUTE_PRECISION_
                    ) * $productQuantity;
                $row['total_wt'] = Tools::ps_round(
                        $row['price_with_reduction'],
                        _PS_PRICE_COMPUTE_PRECISION_
                    ) * $productQuantity;

                break;
        }

        $row['price_wt'] = $row['price_with_reduction'];
        $row['description_short'] = Tools::nl2br($row['description_short']);

        // check if a image associated with the attribute exists
        if ($row['id_product_attribute']) {
            $row2 = Image::getBestImageAttribute($row['id_shop'], $this->id_lang, $row['id_product'], $row['id_product_attribute']);
            if ($row2) {
                $row = array_merge($row, $row2);
            }
        }

        $row['reduction_applies'] = ($specific_price_output && (float) $specific_price_output['reduction']);
        $row['quantity_discount_applies'] = ($specific_price_output && $productQuantity >= (int) $specific_price_output['from_quantity']);
        $row['id_image'] = Product::defineProductImage($row, $this->id_lang);
        $row['allow_oosp'] = Product::isAvailableWhenOutOfStock($row['out_of_stock']);
        $row['features'] = Product::getFeaturesStatic((int) $row['id_product']);

        if (array_key_exists($row['id_product_attribute'] . '-' . $this->id_lang, self::$_attributesLists)) {
            $row = array_merge($row, self::$_attributesLists[$row['id_product_attribute'] . '-' . $this->id_lang]);
        }

        return Product::getTaxesInformations($row, $shopContext);
    }

    /**
     * Check if product quantities in Cart are available.
     *
     * @param bool $returnProductOnFailure Return the first found product with not enough quantity
     *
     * @return bool|array If all products are in stock: true; if not: either false or an array
     *                    containing the first found product which is not in stock in the
     *                    requested amount
     */
    public function checkQuantities($returnProductOnFailure = false)
    {
        if (Configuration::isCatalogMode() && !defined('_PS_ADMIN_DIR_')) {
            return false;
        }

        foreach ($this->getProducts() as $product) {
            if (
                !$this->allow_seperated_package &&
                !$product['allow_oosp'] &&
                StockAvailable::dependsOnStock($product['id_product']) &&
                $product['advanced_stock_management'] &&
                (bool) Context::getContext()->customer->isLogged() &&
                ($delivery = $this->getDeliveryOption()) &&
                !empty($delivery)
            ) {
                $product['stock_quantity'] = StockManager::getStockByCarrier(
                    (int) $product['id_product'],
                    0,
                    $delivery
                );
            }

            if (
                !$product['active'] ||
                !$product['available_for_order'] ||
                (!$product['allow_oosp'] && $product['stock_quantity'] < $product['cart_quantity'])
            ) {
                return $returnProductOnFailure ? $product : false;
            }

            if (!$product['allow_oosp']) {
                $productQuantity = Product::getQuantity(
                    $product['id_product'],
                    $product['id_product_attribute'],
                    null,
                    $this,
                    $product['id_customization']
                );
                if ($productQuantity < 0) {
                    return $returnProductOnFailure ? $product : false;
                }
            }
        }

        return true;
    }

    /**
     * Get products grouped by package and by addresses to be sent individualy (one package = one shipping cost).
     *
     * @return array array(
     *               0 => array( // First address
     *               0 => array(  // First package
     *               'product_list' => array(...),
     *               'carrier_list' => array(...),
     *               'id_warehouse' => array(...),
     *               ),
     *               ),
     *               );
     *
     * @todo Add avaibility check
     */
    public function getPackageList($flush = false)
    {
        $cache_key = (int) $this->id . '_' . (int) $this->id_address_delivery;
        if (isset(static::$cachePackageList[$cache_key]) && static::$cachePackageList[$cache_key] !== false && !$flush) {
            return static::$cachePackageList[$cache_key];
        }

        $product_list = $this->getProducts($flush);
        // Step 1 : Get product informations (warehouse_list and carrier_list), count warehouse
        // Determine the best warehouse to determine the packages
        // For that we count the number of time we can use a warehouse for a specific delivery address
        $warehouse_count_by_address = array();

        $stock_management_active = Configuration::get('PS_ADVANCED_STOCK_MANAGEMENT');

        foreach ($product_list as &$product) {
            if ((int) $product['id_address_delivery'] == 0) {
                $product['id_address_delivery'] = (int) $this->id_address_delivery;
            }

            if (!isset($warehouse_count_by_address[$product['id_address_delivery']])) {
                $warehouse_count_by_address[$product['id_address_delivery']] = array();
            }

            $product['warehouse_list'] = array();

            if ($stock_management_active &&
                (int) $product['advanced_stock_management'] == 1) {
                $warehouse_list = Warehouse::getProductWarehouseList($product['id_product'], 0, $this->id_shop);
                if (count($warehouse_list) == 0) {
                    $warehouse_list = Warehouse::getProductWarehouseList($product['id_product'], 0);
                }
                // Does the product is in stock ?
                // If yes, get only warehouse where the product is in stock

                $warehouse_in_stock = array();
                $manager = StockManagerFactory::getManager();

                foreach ($warehouse_list as $key => $warehouse) {
                    $product_real_quantities = $manager->getProductRealQuantities(
                        $product['id_product'],
                        0,
                        array($warehouse['id_warehouse']),
                        true
                    );

                    if ($product_real_quantities > 0 || Pack::isPack((int) $product['id_product'])) {
                        $warehouse_in_stock[] = $warehouse;
                    }
                }

                if (!empty($warehouse_in_stock)) {
                    $warehouse_list = $warehouse_in_stock;
                    $product['in_stock'] = true;
                } else {
                    $product['in_stock'] = false;
                }
            } else {
                //simulate default warehouse
                $warehouse_list = array(0 => array('id_warehouse' => 0));
                $product['in_stock'] = StockAvailable::getQuantityAvailableByProduct($product['id_product'], 0) > 0;
            }

            foreach ($warehouse_list as $warehouse) {
                $product['warehouse_list'][$warehouse['id_warehouse']] = $warehouse['id_warehouse'];
                if (!isset($warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']])) {
                    $warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']] = 0;
                }

                ++$warehouse_count_by_address[$product['id_address_delivery']][$warehouse['id_warehouse']];
            }
        }
        unset($product);

        arsort($warehouse_count_by_address);

        // Step 2 : Group product by warehouse
        $grouped_by_warehouse = array();

        foreach ($product_list as &$product) {
            if (!isset($grouped_by_warehouse[$product['id_address_delivery']])) {
                $grouped_by_warehouse[$product['id_address_delivery']] = array(
                    'in_stock' => array(),
                    'out_of_stock' => array(),
                );
            }

            $product['carrier_list'] = array();
            $id_warehouse = 0;
            foreach ($warehouse_count_by_address[$product['id_address_delivery']] as $id_war => $val) {
                if (array_key_exists((int) $id_war, $product['warehouse_list'])) {
                    $product['carrier_list'] = array_replace($product['carrier_list'], Carrier::getAvailableCarrierList(new Product($product['id_product']), $id_war, $product['id_address_delivery'], null, $this));
                    if (!$id_warehouse) {
                        $id_warehouse = (int) $id_war;
                    }
                }
            }

            if (!isset($grouped_by_warehouse[$product['id_address_delivery']]['in_stock'][$id_warehouse])) {
                $grouped_by_warehouse[$product['id_address_delivery']]['in_stock'][$id_warehouse] = array();
                $grouped_by_warehouse[$product['id_address_delivery']]['out_of_stock'][$id_warehouse] = array();
            }

            if (!$this->allow_seperated_package) {
                $key = 'in_stock';
            } else {
                $key = $product['in_stock'] ? 'in_stock' : 'out_of_stock';
                $product_quantity_in_stock = StockAvailable::getQuantityAvailableByProduct($product['id_product'], 0);
                if ($product['in_stock'] && $product['cart_quantity'] > $product_quantity_in_stock) {
                    $out_stock_part = $product['cart_quantity'] - $product_quantity_in_stock;
                    $product_bis = $product;
                    $product_bis['cart_quantity'] = $out_stock_part;
                    $product_bis['in_stock'] = 0;
                    $product['cart_quantity'] -= $out_stock_part;
                    $grouped_by_warehouse[$product['id_address_delivery']]['out_of_stock'][$id_warehouse][] = $product_bis;
                }
            }

            if (empty($product['carrier_list'])) {
                $product['carrier_list'] = array(0 => 0);
            }

            $grouped_by_warehouse[$product['id_address_delivery']][$key][$id_warehouse][] = $product;
        }
        unset($product);

        // Step 3 : grouped product from grouped_by_warehouse by available carriers
        $grouped_by_carriers = array();
        foreach ($grouped_by_warehouse as $id_address_delivery => $products_in_stock_list) {
            if (!isset($grouped_by_carriers[$id_address_delivery])) {
                $grouped_by_carriers[$id_address_delivery] = array(
                    'in_stock' => array(),
                    'out_of_stock' => array(),
                );
            }
            foreach ($products_in_stock_list as $key => $warehouse_list) {
                if (!isset($grouped_by_carriers[$id_address_delivery][$key])) {
                    $grouped_by_carriers[$id_address_delivery][$key] = array();
                }
                foreach ($warehouse_list as $id_warehouse => $product_list) {
                    if (!isset($grouped_by_carriers[$id_address_delivery][$key][$id_warehouse])) {
                        $grouped_by_carriers[$id_address_delivery][$key][$id_warehouse] = array();
                    }
                    foreach ($product_list as $product) {
                        $package_carriers_key = implode(',', $product['carrier_list']);

                        if (!isset($grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key])) {
                            $grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key] = array(
                                'product_list' => array(),
                                'carrier_list' => $product['carrier_list'],
                                'warehouse_list' => $product['warehouse_list'],
                            );
                        }

                        $grouped_by_carriers[$id_address_delivery][$key][$id_warehouse][$package_carriers_key]['product_list'][] = $product;
                    }
                }
            }
        }

        $package_list = array();
        // Step 4 : merge product from grouped_by_carriers into $package to minimize the number of package
        foreach ($grouped_by_carriers as $id_address_delivery => $products_in_stock_list) {
            if (!isset($package_list[$id_address_delivery])) {
                $package_list[$id_address_delivery] = array(
                    'in_stock' => array(),
                    'out_of_stock' => array(),
                );
            }

            foreach ($products_in_stock_list as $key => $warehouse_list) {
                if (!isset($package_list[$id_address_delivery][$key])) {
                    $package_list[$id_address_delivery][$key] = array();
                }
                // Count occurance of each carriers to minimize the number of packages
                $carrier_count = array();
                foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers) {
                    foreach ($products_grouped_by_carriers as $data) {
                        foreach ($data['carrier_list'] as $id_carrier) {
                            if (!isset($carrier_count[$id_carrier])) {
                                $carrier_count[$id_carrier] = 0;
                            }
                            ++$carrier_count[$id_carrier];
                        }
                    }
                }
                arsort($carrier_count);
                foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers) {
                    if (!isset($package_list[$id_address_delivery][$key][$id_warehouse])) {
                        $package_list[$id_address_delivery][$key][$id_warehouse] = array();
                    }
                    foreach ($products_grouped_by_carriers as $data) {
                        foreach ($carrier_count as $id_carrier => $rate) {
                            if (array_key_exists($id_carrier, $data['carrier_list'])) {
                                if (!isset($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier])) {
                                    $package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier] = array(
                                        'carrier_list' => $data['carrier_list'],
                                        'warehouse_list' => $data['warehouse_list'],
                                        'product_list' => array(),
                                    );
                                }
                                $package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['carrier_list'] =
                                    array_intersect($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['carrier_list'], $data['carrier_list']);
                                $package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['product_list'] =
                                    array_merge($package_list[$id_address_delivery][$key][$id_warehouse][$id_carrier]['product_list'], $data['product_list']);

                                break;
                            }
                        }
                    }
                }
            }
        }

        // Step 5 : Reduce depth of $package_list
        $final_package_list = array();
        foreach ($package_list as $id_address_delivery => $products_in_stock_list) {
            if (!isset($final_package_list[$id_address_delivery])) {
                $final_package_list[$id_address_delivery] = array();
            }

            foreach ($products_in_stock_list as $key => $warehouse_list) {
                foreach ($warehouse_list as $id_warehouse => $products_grouped_by_carriers) {
                    foreach ($products_grouped_by_carriers as $data) {
                        $final_package_list[$id_address_delivery][] = array(
                            'product_list' => $data['product_list'],
                            'carrier_list' => $data['carrier_list'],
                            'warehouse_list' => $data['warehouse_list'],
                            'id_warehouse' => $id_warehouse,
                        );
                    }
                }
            }
        }

        static::$cachePackageList[$cache_key] = $final_package_list;

        return $final_package_list;
    }

    public static function cacheSomeAttributesLists($ipa_list, $id_lang)
    {
        $pa_implode = array();
        $separator = Configuration::get('PS_ATTRIBUTE_ANCHOR_SEPARATOR');

        foreach ($ipa_list as $id_product_attribute) {
            if ((int) $id_product_attribute && !array_key_exists($id_product_attribute . '-' . $id_lang, self::$_attributesLists)) {
                $pa_implode[] = (int) $id_product_attribute;
                self::$_attributesLists[(int) $id_product_attribute . '-' . $id_lang] = array('attributes' => '', 'attributes_small' => '');
            }
        }

        if (!count($pa_implode)) {
            return;
        }

        $results = array();
        $rooms = Product::getRooms();
        $vats = Product::getVats();

        foreach ($rooms as $idRoom => $room) {
            foreach ($vats as $idVat => $vat) {
                $idProductAttribute = Product::computeIdProductAttribute($idRoom, $idVat);
                if(in_array((int)$idProductAttribute, $pa_implode)) {
                    $attribute = Product::baseAttributeGroups();;
                    $attribute['id_product_attribute'] = $idProductAttribute;
                    $attribute['attribute_name'] = $room;
                    $attribute['public_group_name'] = Product::PIECE;
                    $results[] = $attribute;

                    $attribute = Product::baseAttributeGroups();
                    $attribute['id_product_attribute'] = $idProductAttribute;
                    $attribute['attribute_name'] = $vat;
                    $attribute['public_group_name'] = Product::TVA;
                    $results[]= $attribute;

                }
            }
        }

        $result = $results;

        foreach ($result as $row) {
            self::$_attributesLists[$row['id_product_attribute'] . '-' . $id_lang]['attributes'] .= $row['public_group_name'] . ' : ' . $row['attribute_name'] . $separator . ' ';
            self::$_attributesLists[$row['id_product_attribute'] . '-' . $id_lang]['attributes_small'] .= $row['attribute_name'] . $separator . ' ';
        }

        foreach ($pa_implode as $id_product_attribute) {
            self::$_attributesLists[$id_product_attribute . '-' . $id_lang]['attributes'] = rtrim(
                self::$_attributesLists[$id_product_attribute . '-' . $id_lang]['attributes'],
                $separator . ' '
            );

            self::$_attributesLists[$id_product_attribute . '-' . $id_lang]['attributes_small'] = rtrim(
                self::$_attributesLists[$id_product_attribute . '-' . $id_lang]['attributes_small'],
                $separator . ' '
            );
        }
    }

    /**
     * Update Product quantity.
     *
     * @param int $quantity Quantity to add (or substract)
     * @param int $id_product Product ID
     * @param int|null $id_product_attribute Attribute ID if needed
     * @param int|false $id_customization Customization ID
     * @param string $operator Indicate if quantity must be increased or decreased
     * @param int $id_address_delivery Delivery Address ID
     * @param Shop|null $shop
     * @param bool $auto_add_cart_rule
     * @param bool $skipAvailabilityCheckOutOfStock
     *
     * @return bool Whether the quantity has been successfully updated
     */
    public function updateQty(
        $quantity,
        $id_product,
        $id_product_attribute = null,
        $id_customization = false,
        $operator = 'up',
        $id_address_delivery = 0,
        Shop $shop = null,
        $auto_add_cart_rule = true,
        $skipAvailabilityCheckOutOfStock = false
    ) {
        PrestaShopLogger::addLog(__METHOD__." in");
        if (!$shop) {
            $shop = Context::getContext()->shop;
        }

        if (Validate::isLoadedObject(Context::getContext()->customer)) {
            if ($id_address_delivery == 0 && (int) $this->id_address_delivery) {
                // The $id_address_delivery is null, use the cart delivery address
                $id_address_delivery = $this->id_address_delivery;
            } elseif ($id_address_delivery == 0) {
                // The $id_address_delivery is null, get the default customer address
                $id_address_delivery = (int) Address::getFirstCustomerAddressId(
                    (int) Context::getContext()->customer->id
                );
            } elseif (!Customer::customerHasAddress(Context::getContext()->customer->id, $id_address_delivery)) {
                // The $id_address_delivery must be linked with customer
                $id_address_delivery = 0;
            }
        } else {
            $id_address_delivery = 0;
        }

        $quantity = (int) $quantity;
        $id_product = (int) $id_product;
        $id_product_attribute = (int) $id_product_attribute;
        $product = new Product($id_product, false, Configuration::get('PS_LANG_DEFAULT'), $shop->id);

        $minimal_quantity = (int) $product->minimal_quantity;

        if (!Validate::isLoadedObject($product)) {
            die(Tools::displayError());
        }

        if (isset(self::$_nbProducts[$this->id])) {
            unset(self::$_nbProducts[$this->id]);
        }

        if (isset(self::$_totalWeight[$this->id])) {
            unset(self::$_totalWeight[$this->id]);
        }

        $data = array(
            'cart' => $this,
            'product' => $product,
            'id_product_attribute' => $id_product_attribute,
            'id_customization' => $id_customization,
            'quantity' => $quantity,
            'operator' => $operator,
            'id_address_delivery' => $id_address_delivery,
            'shop' => $shop,
            'auto_add_cart_rule' => $auto_add_cart_rule,
        );

        /* @deprecated deprecated since 1.6.1.1 */
        // Hook::exec('actionBeforeCartUpdateQty', $data);
        Hook::exec('actionCartUpdateQuantityBefore', $data);

        if ((int) $quantity <= 0) {
            PrestaShopLogger::addLog(__METHOD__.' $quantity <= 0 delete');
            return $this->deleteProduct($id_product, $id_product_attribute, (int) $id_customization);
        }

        if (!$product->available_for_order
            || (
                Configuration::isCatalogMode()
                && !defined('_PS_ADMIN_DIR_')
            )
        ) {
            PrestaShopLogger::addLog(__METHOD__.' !$product->available_for_order false');
            return false;
        }

        /* Check if the product is already in the cart */
        $cartProductQuantity = $this->getProductQuantity(
            $id_product,
            $id_product_attribute,
            (int) $id_customization,
            (int) $id_address_delivery
        );

        /* Update quantity if product already exist */
        if (!empty($cartProductQuantity['quantity'])) {
            $productQuantity = Product::getQuantity($id_product, $id_product_attribute, null, $this);
            $availableOutOfStock = Product::isAvailableWhenOutOfStock($product->out_of_stock);

            if ($operator == 'up') {
                $updateQuantity = '+ ' . $quantity;
                $newProductQuantity = $productQuantity - $quantity;

                if ($newProductQuantity < 0 && !$availableOutOfStock && !$skipAvailabilityCheckOutOfStock) {
                    PrestaShopLogger::addLog(__METHOD__.' newProductQuantity < 0 && !$availableOutOfStock && !$skipAvailabilityCheckOutOfStock false');
                    return false;
                }
            } elseif ($operator == 'down') {
                $cartFirstLevelProductQuantity = $this->getProductQuantity(
                    (int) $id_product,
                    (int) $id_product_attribute,
                    $id_customization
                );
                $updateQuantity = '- ' . $quantity;
                $newProductQuantity = $productQuantity + $quantity;

                if ($cartFirstLevelProductQuantity['quantity'] <= 1
                    || $cartProductQuantity['quantity'] - $quantity <= 0
                ) {
                    PrestaShopLogger::addLog(__METHOD__.' delete');
                    return $this->deleteProduct((int) $id_product, (int) $id_product_attribute, (int) $id_customization);
                }
            } else {
                PrestaShopLogger::addLog(__METHOD__.' op (false)');
                return false;
            }

            Db::getInstance()->execute(
                'UPDATE `' . _DB_PREFIX_ . 'cart_product`
                    SET `quantity` = `quantity` ' . $updateQuantity . '
                    WHERE `id_product` = ' . (int) $id_product .
                ' AND `id_customization` = ' . (int) $id_customization .
                (!empty($id_product_attribute) ? ' AND `id_product_attribute` = ' . (int) $id_product_attribute : '') . '
                    AND `id_cart` = ' . (int) $this->id . (Configuration::get('PS_ALLOW_MULTISHIPPING') && $this->isMultiAddressDelivery() ? ' AND `id_address_delivery` = ' . (int) $id_address_delivery : '') . '
                    LIMIT 1'
            );
        } elseif ($operator == 'up') {
            /* Add product to the cart */

            $sql = 'SELECT stock.out_of_stock, IFNULL(stock.quantity, 0) as quantity
                        FROM ' . _DB_PREFIX_ . 'product p
                        ' . Product::sqlStock('p', $id_product_attribute, true, $shop) . '
                        WHERE p.id_product = ' . $id_product;

            $result2 = Db::getInstance()->getRow($sql);

            // Quantity for product pack
            if (Pack::isPack($id_product)) {
                $result2['quantity'] = Pack::getQuantity($id_product, $id_product_attribute, null, $this);
            }

            if (!Product::isAvailableWhenOutOfStock((int) $result2['out_of_stock']) && !$skipAvailabilityCheckOutOfStock) {
                if ((int) $quantity > $result2['quantity']) {
                    PrestaShopLogger::addLog(__METHOD__.' (int) $quantity > $result2["quantity"] false ');
                    return false;
                }
            }

            if ((int) $quantity < $minimal_quantity) {
                PrestaShopLogger::addLog(__METHOD__.' -1');
                return -1;
            }

            $result_add = Db::getInstance()->insert('cart_product', array(
                'id_product' => (int) $id_product,
                'id_product_attribute' => (int) $id_product_attribute,
                'id_cart' => (int) $this->id,
                'id_address_delivery' => (int) $id_address_delivery,
                'id_shop' => $shop->id,
                'quantity' => (int) $quantity,
                'date_add' => date('Y-m-d H:i:s'),
                'id_customization' => (int) $id_customization,
            ));

            if (!$result_add) {
                PrestaShopLogger::addLog(__METHOD__." out false");
                return false;
            }
        }

        // refresh cache of self::_products
        $this->_products = $this->getProducts(true);
        $this->update();
        $context = Context::getContext()->cloneContext();
        $context->cart = $this;
        Cache::clean('getContextualValue_*');
        CartRule::autoRemoveFromCart();
        if ($auto_add_cart_rule) {
            CartRule::autoAddToCart($context);
        }

        if ($product->customizable) {
            PrestaShopLogger::addLog(__METHOD__." custom");
            return $this->_updateCustomizationQuantity(
                (int) $quantity,
                (int) $id_customization,
                (int) $id_product,
                (int) $id_product_attribute,
                (int) $id_address_delivery,
                $operator
            );
        }
        PrestaShopLogger::addLog(__METHOD__." true");
        return true;
    }

    /**
     * @param $withTaxes
     * @param $product
     * @param $virtualContext
     *
     * @return int
     */
    protected function findTaxRulesGroupId($withTaxes, $product, $virtualContext)
    {
        if ($withTaxes) {
            $taxRulesGroupId = Product::getIdTaxRulesGroupByIdProduct((int) $product['id_product'], $virtualContext, (int) $product['id_product_attribute']);

            $addressId = $this->getProductAddressId($product);
            $address = $this->addressFactory->findOrCreate($addressId, true);

            // Refresh cache and execute tax manager factory hook
            TaxManagerFactory::getManager($address, $taxRulesGroupId)->getTaxCalculator();
        } else {
            $taxRulesGroupId = 0;
        }

        return $taxRulesGroupId;
    }

    /**
     * @param int $productId
     */
    protected function updateProductWeight($productId)
    {
        $productId = (int) $productId;

        $weight_product_with_attribute = 0;

        $weight_product_without_attribute = Db::getInstance()->getValue('
            SELECT SUM(p.`weight` * cp.`quantity`) as nb
            FROM `' . _DB_PREFIX_ . 'cart_product` cp
            LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON (cp.`id_product` = p.`id_product`)
            WHERE (cp.`id_product_attribute` IS NULL OR cp.`id_product_attribute` = 0)
            AND cp.`id_cart` = ' . $productId);

        $weight_cart_customizations = Db::getInstance()->getValue('
            SELECT SUM(cd.`weight` * c.`quantity`) FROM `' . _DB_PREFIX_ . 'customization` c
            LEFT JOIN `' . _DB_PREFIX_ . 'customized_data` cd ON (c.`id_customization` = cd.`id_customization`)
            WHERE c.`in_cart` = 1 AND c.`id_cart` = ' . $productId);

        self::$_totalWeight[$productId] = round(
            (float) $weight_product_with_attribute +
            (float) $weight_product_without_attribute +
            (float) $weight_cart_customizations,
            6
        );
    }
}
