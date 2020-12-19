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
<table id="summary-tab" width="100%">
	<tr>
		<th class="header small" valign="middle">Numéro de {$carrier->name}</th>
		<th class="header small" valign="middle">Date de {$carrier->name}</th>
		<th class="header small" valign="middle">Réf. de {$carrier->name}</th>
		{if $addresses.invoice->vat_number}
			<th class="header small" valign="middle">{l s='VAT Number' d='Shop.Pdf' pdf='true'}</th>
		{/if}
	</tr>
	<tr>
		<td class="center small white">{$title|escape:'html':'UTF-8'}</td>
		<td class="center small white">{dateFormat date=$order->invoice_date full=0}</td>
		<td class="center small white">{$order->getUniqReference()}</td>
		{if $addresses.invoice->vat_number}
			<td class="center small white">
				{$addresses.invoice->vat_number}
			</td>
		{/if}
	</tr>
</table>
