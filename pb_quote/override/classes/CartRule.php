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
class CartRule extends CartRuleCore
{
    /**
     * Checks if the products chosen by the customer are usable with the cart rule.
     *
     * @param \Cart $cart
     * @param bool $returnProducts [default=false]
     *                             If true, this method will return an array of eligible products.
     *                             Otherwise, it returns TRUE on success and string|false on errors (depending on the value of $displayError)
     * @param bool $displayError [default=false]
     *                           If true, this method will return an error message instead of FALSE on errors.
     *                           Otherwise, it returns FALSE on errors
     * @param bool $alreadyInCart
     *
     * @return array|bool|string
     *
     * @throws PrestaShopDatabaseException
     */
    public function checkProductRestrictionsFromCart(Cart $cart, $returnProducts = false, $displayError = true, $alreadyInCart = false)
    {
        $selected_products = array();

        // Check if the products chosen by the customer are usable with the cart rule
        if ($this->product_restriction) {
            $product_rule_groups = $this->getProductRuleGroups();
            foreach ($product_rule_groups as $id_product_rule_group => $product_rule_group) {
                $eligible_products_list = array();
                if (isset($cart) && is_object($cart) && is_array($products = $cart->getProducts())) {
                    foreach ($products as $product) {
                        $eligible_products_list[] = (int) $product['id_product'] . '-' . (int) $product['id_product_attribute'];
                    }
                }
                if (!count($eligible_products_list)) {
                    return (!$displayError) ? false : $this->trans('You cannot use this voucher in an empty cart', array(), 'Shop.Notifications.Error');
                }
                $product_rules = $this->getProductRules($id_product_rule_group);
                $countRulesProduct = count($product_rules);
                $condition = 0;
                foreach ($product_rules as $product_rule) {
                    switch ($product_rule['type']) {
                        case 'attributes':
                            $cart_attributes = Db::getInstance()->executeS('
							SELECT cp.quantity, cp.`id_product`, pac.`id_attribute`, cp.`id_product_attribute`
							FROM `' . _DB_PREFIX_ . 'cart_product` cp
							LEFT JOIN `' . _DB_PREFIX_ . 'product_attribute_combination` pac ON cp.id_product_attribute = pac.id_product_attribute
							WHERE cp.`id_cart` = ' . (int) $cart->id . '
							AND cp.`id_product` IN (' . implode(',', array_map('intval', $eligible_products_list)) . ')
							AND cp.id_product_attribute > 0');
                            $count_matching_products = 0;
                            $matching_products_list = array();
                            foreach ($cart_attributes as $cart_attribute) {
                                if (in_array($cart_attribute['id_attribute'], $product_rule['values'])) {
                                    $count_matching_products += $cart_attribute['quantity'];
                                    if (
                                        $alreadyInCart
                                        && $this->gift_product == $cart_attribute['id_product']
                                        && $this->gift_product_attribute == $cart_attribute['id_product_attribute']) {
                                        --$count_matching_products;
                                    }
                                    $matching_products_list[] = $cart_attribute['id_product'] . '-' . $cart_attribute['id_product_attribute'];
                                }
                            }
                            if ($count_matching_products < $product_rule_group['quantity']) {
                                if ($countRulesProduct === 1) {
                                    return (!$displayError) ? false : $this->trans('You cannot use this voucher with these products', array(), 'Shop.Notifications.Error');
                                } else {
                                    ++$condition;

                                    break;
                                }
                            }
                            $eligible_products_list = $this->filterProducts($eligible_products_list, $matching_products_list, $product_rule['type']);

                            break;
                        case 'products':
                            $cart_products = Db::getInstance()->executeS('
							SELECT cp.quantity, cp.`id_product`
							FROM `' . _DB_PREFIX_ . 'cart_product` cp
							WHERE cp.`id_cart` = ' . (int) $cart->id . '
							AND cp.`id_product` IN (' . implode(',', array_map('intval', $eligible_products_list)) . ')');
                            $count_matching_products = 0;
                            $matching_products_list = array();
                            foreach ($cart_products as $cart_product) {
                                if (in_array($cart_product['id_product'], $product_rule['values'])) {
                                    $count_matching_products += $cart_product['quantity'];
                                    if ($alreadyInCart && $this->gift_product == $cart_product['id_product']) {
                                        --$count_matching_products;
                                    }
                                    $matching_products_list[] = $cart_product['id_product'] . '-0';
                                }
                            }
                            if ($count_matching_products < $product_rule_group['quantity']) {
                                if ($countRulesProduct === 1) {
                                    return (!$displayError) ? false : $this->trans('You cannot use this voucher with these products', array(), 'Shop.Notifications.Error');
                                } else {
                                    ++$condition;

                                    break;
                                }
                            }
                            $eligible_products_list = $this->filterProducts($eligible_products_list, $matching_products_list, $product_rule['type']);

                            break;
                        case 'categories':
                            $cart_categories = Db::getInstance()->executeS('
							SELECT cp.quantity, cp.`id_product`, cp.`id_product_attribute`, catp.`id_category`
							FROM `' . _DB_PREFIX_ . 'cart_product` cp
							LEFT JOIN `' . _DB_PREFIX_ . 'category_product` catp ON cp.id_product = catp.id_product
							WHERE cp.`id_cart` = ' . (int) $cart->id . '
							AND cp.`id_product` IN (' . implode(',', array_map('intval', $eligible_products_list)) . ')
							AND cp.`id_product` <> ' . (int) $this->gift_product);
                            $count_matching_products = 0;
                            $matching_products_list = array();
                            foreach ($cart_categories as $cart_category) {
                                if (in_array($cart_category['id_category'], $product_rule['values'])
                                    /*
                                     * We also check that the product is not already in the matching product list,
                                     * because there are doubles in the query results (when the product is in multiple categories)
                                     */
                                    && !in_array($cart_category['id_product'] . '-' . $cart_category['id_product_attribute'], $matching_products_list)) {
                                    $count_matching_products += $cart_category['quantity'];
                                    $matching_products_list[] = $cart_category['id_product'] . '-' . $cart_category['id_product_attribute'];
                                }
                            }
                            if ($count_matching_products < $product_rule_group['quantity']) {
                                if ($countRulesProduct === 1) {
                                    return (!$displayError) ? false : $this->trans('You cannot use this voucher with these products', array(), 'Shop.Notifications.Error');
                                } else {
                                    ++$condition;

                                    break;
                                }
                            }
                            // Attribute id is not important for this filter in the global list, so the ids are replaced by 0
                            foreach ($matching_products_list as &$matching_product) {
                                $matching_product = preg_replace('/^([0-9]+)-[0-9]+$/', '$1-0', $matching_product);
                            }
                            $eligible_products_list = $this->filterProducts($eligible_products_list, $matching_products_list, $product_rule['type']);

                            break;
                        case 'manufacturers':
                            $cart_manufacturers = Db::getInstance()->executeS('
							SELECT cp.quantity, cp.`id_product`, p.`id_manufacturer`
							FROM `' . _DB_PREFIX_ . 'cart_product` cp
							LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON cp.id_product = p.id_product
							WHERE cp.`id_cart` = ' . (int) $cart->id . '
							AND cp.`id_product` IN (' . implode(',', array_map('intval', $eligible_products_list)) . ')');
                            $count_matching_products = 0;
                            $matching_products_list = array();
                            foreach ($cart_manufacturers as $cart_manufacturer) {
                                if (in_array($cart_manufacturer['id_manufacturer'], $product_rule['values'])) {
                                    $count_matching_products += $cart_manufacturer['quantity'];
                                    $matching_products_list[] = $cart_manufacturer['id_product'] . '-0';
                                }
                            }
                            if ($count_matching_products < $product_rule_group['quantity']) {
                                if ($countRulesProduct === 1) {
                                    return (!$displayError) ? false : $this->trans('You cannot use this voucher with these products', array(), 'Shop.Notifications.Error');
                                } else {
                                    ++$condition;

                                    break;
                                }
                            }
                            $eligible_products_list = $this->filterProducts($eligible_products_list, $matching_products_list, $product_rule['type']);

                            break;
                        case 'suppliers':
                            $cart_suppliers = Db::getInstance()->executeS('
							SELECT cp.quantity, cp.`id_product`, p.`id_supplier`
							FROM `' . _DB_PREFIX_ . 'cart_product` cp
							LEFT JOIN `' . _DB_PREFIX_ . 'product` p ON cp.id_product = p.id_product
							WHERE cp.`id_cart` = ' . (int) $cart->id . '
							AND cp.`id_product` IN (' . implode(',', array_map('intval', $eligible_products_list)) . ')');
                            $count_matching_products = 0;
                            $matching_products_list = array();
                            foreach ($cart_suppliers as $cart_supplier) {
                                if (in_array($cart_supplier['id_supplier'], $product_rule['values'])) {
                                    $count_matching_products += $cart_supplier['quantity'];
                                    $matching_products_list[] = $cart_supplier['id_product'] . '-0';
                                }
                            }
                            if ($count_matching_products < $product_rule_group['quantity']) {
                                if ($countRulesProduct === 1) {
                                    return (!$displayError) ? false : $this->trans('You cannot use this voucher with these products', array(), 'Shop.Notifications.Error');
                                } else {
                                    ++$condition;

                                    break;
                                }
                            }
                            $eligible_products_list = $this->filterProducts($eligible_products_list, $matching_products_list, $product_rule['type']);

                            break;
                    }
                    if (!count($eligible_products_list)) {
                        if ($countRulesProduct === 1) {
                            return (!$displayError) ? false : $this->trans('You cannot use this voucher with these products', array(), 'Shop.Notifications.Error');
                        }
                    }
                }
                if ($countRulesProduct !== 1 && $condition == $countRulesProduct) {
                    return (!$displayError) ? false : $this->trans('You cannot use this voucher with these products', array(), 'Shop.Notifications.Error');
                }
                $selected_products = array_merge($selected_products, $eligible_products_list);
            }
        }
        if ($returnProducts) {
            return $selected_products;
        }

        return (!$displayError) ? true : false;
    }
}
