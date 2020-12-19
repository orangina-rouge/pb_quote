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
<table id="addresses-tab" cellspacing="0" cellpadding="0">
	<tr>
		<td width="50%">{if $delivery_address}<span class="bold">{l s='Adresse des travaux' d='Shop.Pdf' pdf='true'}</span><br/><br/>
				{$delivery_address}
			{/if}
		</td>
		<td width="50%"><span class="bold">{l s='Billing Address' d='Shop.Pdf' pdf='true'}</span><br/><br/>
				{$invoice_address}
		</td>
	</tr>
</table>
