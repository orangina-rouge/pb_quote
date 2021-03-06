{**
 * 2020 point-barre.com
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    tenshy
 * @copyright 2020 point-barre.com
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 *}
<table class="product" width="100%" cellpadding="4" cellspacing="0">

	<thead>
	<tr>
		<th class="product header small" width="{$layout.reference.width}%">{l s='Reference' d='Shop.Pdf' pdf='true'}</th>
		<th class="product header small" width="{$layout.product.width}%">{l s='Product' d='Shop.Pdf' pdf='true'}</th>
		<th class="product header small" width="{$layout.tax_code.width}%">{l s='Tax Rate' d='Shop.Pdf' pdf='true'}</th>

		{if isset($layout.before_discount)}
			<th class="product header small" width="{$layout.unit_price_tax_excl.width}%">{l s='Base price' d='Shop.Pdf' pdf='true'} <br /> {l s='(Tax excl.)' d='Shop.Pdf' pdf='true'}</th>
		{/if}

		<th class="product header-right small" width="{$layout.unit_price_tax_excl.width}%">{l s='Unit Price' d='Shop.Pdf' pdf='true'} <br /> {l s='(Tax excl.)' d='Shop.Pdf' pdf='true'}</th>
		<th class="product header small" width="{$layout.quantity.width}%">{l s='Qty' d='Shop.Pdf' pdf='true'}</th>
		<th class="product header-right small" width="{$layout.total_tax_excl.width}%">{l s='Total' d='Shop.Pdf' pdf='true'} <br /> {l s='(Tax excl.)' d='Shop.Pdf' pdf='true'}</th>
	</tr>
	</thead>

	<tbody>

	<!-- PRODUCTS -->
	{foreach $order_details_per_room as $room => $room_order_details}
        <tr class="product color_line_even">
            {if isset($layout.before_discount)}
                <td class="product center" colspan="7">
            {else}
                <td class="product center" colspan="6">
            {/if}
                {$room}
            </td>
        </tr>
        {foreach $room_order_details as $order_detail}
            {cycle values=["color_line_even", "color_line_odd"] assign=bgcolor_class}
            <tr class="product {$bgcolor_class}">

                <td class="product center">
                    {$order_detail.product_reference}
                </td>
                <td class="product left">
                    {if $display_product_images}
                        <table width="100%">
                            <tr>
                                <td width="15%">
                                    {if isset($order_detail.image) && $order_detail.image->id}
                                        {$order_detail.image_tag}
                                    {/if}
                                </td>
                                <td width="5%">&nbsp;</td>
                                <td width="80%">
                                    {$order_detail.product_name}
                                </td>
                            </tr>
                        </table>
                    {else}
                        {$order_detail.product_name}
                    {/if}

                </td>
                <td class="product center">
                    {$order_detail.order_detail_tax_label}
                </td>

                {if isset($layout.before_discount)}
                    <td class="product center">
                        {if isset($order_detail.unit_price_tax_excl_before_specific_price)}
                            {displayPrice currency=$order->id_currency price=$order_detail.unit_price_tax_excl_before_specific_price}
                        {else}
                            --
                        {/if}
                    </td>
                {/if}

                <td class="product right">
                    {displayPrice currency=$order->id_currency price=$order_detail.unit_price_tax_excl_including_ecotax}
                    {if $order_detail.ecotax_tax_excl > 0}
                        <br>
                        <small>{{displayPrice currency=$order->id_currency price=$order_detail.ecotax_tax_excl}|string_format:{l s='ecotax: %s' d='Shop.Pdf' pdf='true'}}</small>
                    {/if}
                </td>
                <td class="product center">
                    {$order_detail.product_quantity}
                </td>
                <td  class="product right">
                    {displayPrice currency=$order->id_currency price=$order_detail.total_price_tax_excl_including_ecotax}
                </td>
            </tr>
        {/foreach}
	{/foreach}
	<!-- END PRODUCTS -->

	<!-- CART RULES -->

	{assign var="shipping_discount_tax_incl" value="0"}
	{foreach from=$cart_rules item=cart_rule name="cart_rules_loop"}
		{if $smarty.foreach.cart_rules_loop.first}
		<tr class="discount">
			<th class="header" colspan="{$layout._colCount}">
				{l s='Discounts' d='Shop.Pdf' pdf='true'}
			</th>
		</tr>
		{/if}
		<tr class="discount">
			<td class="white right" colspan="{$layout._colCount - 1}">
				{$cart_rule.name}
			</td>
			<td class="right white">
				- {displayPrice currency=$order->id_currency price=$cart_rule.value_tax_excl}
			</td>
		</tr>
	{/foreach}

	</tbody>

</table>
