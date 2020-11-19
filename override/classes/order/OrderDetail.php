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
class OrderDetail extends OrderDetailCore
{
    public function __construct($id = null, $id_lang = null, $context = null)
    {
        parent::__construct($id, $id_lang, $context);
    }

    public function updateTaxAmount($order)
    {
        $this->setContext((int) $this->id_shop);
        $address = new Address((int) $order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
        $tax_manager = TaxManagerFactory::getManager($address, (int) Product::getIdTaxRulesGroupByIdProduct((int) $this->product_id, $this->context, $this->product_attribute_id));
        $this->tax_calculator = $tax_manager->getTaxCalculator();

        return $this->saveTaxCalculator($order, true);
    }

    /**
     * Apply tax to the product.
     *
     * @param object $order
     * @param array $product
     */
    protected function setProductTax(Order $order, $product)
    {
        $this->ecotax = Tools::convertPrice((float) ($product['ecotax']), (int) ($order->id_currency));

        // Exclude VAT
        if (!Tax::excludeTaxeOption()) {
            $this->setContext((int) $product['id_shop']);
            $this->id_tax_rules_group = (int) Product::getIdTaxRulesGroupByIdProduct((int) $product['id_product'], $this->context, $product['id_product_attribute']);

            $tax_manager = TaxManagerFactory::getManager($this->vat_address, $this->id_tax_rules_group);
            $this->tax_calculator = $tax_manager->getTaxCalculator();
            $this->tax_computation_method = (int) $this->tax_calculator->computation_method;
        }

        $this->ecotax_tax_rate = 0;
        if (!empty($product['ecotax'])) {
            $this->ecotax_tax_rate = Tax::getProductEcotaxRate($order->{Configuration::get('PS_TAX_ADDRESS_TYPE')});
        }
    }

    /**
     * Set specific price of the product.
     *
     * @param object $order
     */
    protected function setSpecificPrice(Order $order, $product = null)
    {
        $this->reduction_amount = 0.00;
        $this->reduction_percent = 0.00;
        $this->reduction_amount_tax_incl = 0.00;
        $this->reduction_amount_tax_excl = 0.00;

        if ($this->specificPrice) {
            switch ($this->specificPrice['reduction_type']) {
                case 'percentage':
                    $this->reduction_percent = (float) $this->specificPrice['reduction'] * 100;

                    break;

                case 'amount':
                    $price = Tools::convertPrice($this->specificPrice['reduction'], $order->id_currency);
                    $this->reduction_amount = !$this->specificPrice['id_currency'] ? (float) $price : (float) $this->specificPrice['reduction'];
                    if ($product !== null) {
                        $this->setContext((int) $product['id_shop']);
                    }
                    $id_tax_rules = (int) Product::getIdTaxRulesGroupByIdProduct((int) $this->specificPrice['id_product'], $this->context, $product['id_product_attribute']);
                    $tax_manager = TaxManagerFactory::getManager($this->vat_address, $id_tax_rules);
                    $this->tax_calculator = $tax_manager->getTaxCalculator();

                    if ($this->specificPrice['reduction_tax']) {
                        $this->reduction_amount_tax_incl = $this->reduction_amount;
                        $this->reduction_amount_tax_excl = Tools::ps_round($this->tax_calculator->removeTaxes($this->reduction_amount), _PS_PRICE_COMPUTE_PRECISION_);
                    } else {
                        $this->reduction_amount_tax_incl = Tools::ps_round($this->tax_calculator->addTaxes($this->reduction_amount), _PS_PRICE_COMPUTE_PRECISION_);
                        $this->reduction_amount_tax_excl = $this->reduction_amount;
                    }

                    break;
            }
        }
    }

    public static function getCrossSells($id_product, $id_lang, $limit = 12)
    {
        if (!$id_product || !$id_lang) {
            return;
        }

        $front = true;
        if (!in_array(Context::getContext()->controller->controller_type, array('front', 'modulefront'))) {
            $front = false;
        }

        $orders = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
        SELECT o.id_order
        FROM ' . _DB_PREFIX_ . 'orders o
        LEFT JOIN ' . _DB_PREFIX_ . 'order_detail od ON (od.id_order = o.id_order)
        WHERE o.valid = 1 AND od.product_id = ' . (int) $id_product);

        if (count($orders)) {
            $list = '';
            foreach ($orders as $order) {
                $list .= (int) $order['id_order'] . ',';
            }
            $list = rtrim($list, ',');

            $order_products = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS('
                SELECT DISTINCT od.product_id, p.id_product, pl.name, pl.link_rewrite, p.reference, i.id_image, product_shop.show_price,
                cl.link_rewrite category, p.ean13, p.isbn, p.out_of_stock, p.id_category_default, '.__DEFAULT_IPA__.' AS id_product_attribute
                FROM ' . _DB_PREFIX_ . 'order_detail od
                LEFT JOIN ' . _DB_PREFIX_ . 'product p ON (p.id_product = od.product_id)
                ' . Shop::addSqlAssociation('product', 'p') .
                'LEFT JOIN ' . _DB_PREFIX_ . 'product_lang pl ON (pl.id_product = od.product_id' . Shop::addSqlRestrictionOnLang('pl') . ')
                LEFT JOIN ' . _DB_PREFIX_ . 'category_lang cl ON (cl.id_category = product_shop.id_category_default' . Shop::addSqlRestrictionOnLang('cl') . ')
                LEFT JOIN ' . _DB_PREFIX_ . 'image i ON (i.id_product = od.product_id)
                ' . Shop::addSqlAssociation('image', 'i', true, 'image_shop.cover=1') . '
                WHERE od.id_order IN (' . $list . ')
                    AND pl.id_lang = ' . (int) $id_lang . '
                    AND cl.id_lang = ' . (int) $id_lang . '
                    AND od.product_id != ' . (int) $id_product . '
                    AND product_shop.active = 1'
                . ($front ? ' AND product_shop.`visibility` IN ("both", "catalog")' : '') . '
                ORDER BY RAND()
                LIMIT ' . (int) $limit . '
            ', true, false);

            $tax_calc = Product::getTaxCalculationMethod();
            if (is_array($order_products)) {
                foreach ($order_products as &$order_product) {
                    $order_product['image'] = Context::getContext()->link->getImageLink(
                        $order_product['link_rewrite'],
                        (int) $order_product['product_id'] . '-' . (int) $order_product['id_image'],
                        ImageType::getFormattedName('medium')
                    );
                    $order_product['link'] = Context::getContext()->link->getProductLink(
                        (int) $order_product['product_id'],
                        $order_product['link_rewrite'],
                        $order_product['category'],
                        $order_product['ean13']
                    );
                    if ($tax_calc == 0 || $tax_calc == 2) {
                        $order_product['displayed_price'] = Product::getPriceStatic((int) $order_product['product_id'], true, null);
                    } elseif ($tax_calc == 1) {
                        $order_product['displayed_price'] = Product::getPriceStatic((int) $order_product['product_id'], false, null);
                    }
                }

                return Product::getProductsProperties($id_lang, $order_products);
            }
        }
    }

    //return the product OR product attribute whole sale price
    public function getWholeSalePrice()
    {
        $product = new Product($this->product_id);
        $wholesale_price = $product->wholesale_price;
        return $wholesale_price;
    }
}
