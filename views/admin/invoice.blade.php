@php
	$biz = $shop_config;
	$zmi_status = ['pending'=>lang('commerce.st_order_pending'),'paid'=>lang('commerce.st_order_paid'),'cancelled'=>lang('commerce.st_order_cancelled'),'failed'=>lang('commerce.st_order_failed'),'expired'=>lang('commerce.st_order_expired')];
@endphp

<div class="zmi-page">
	<div class="zmi-toolbar">
		@if (count($invoices) > 1)<span class="zmi-count">{{ sprintf(lang('commerce.st_unit_count'), count($invoices)) }}</span>@endif
		<button type="button" class="zmi-btn zmi-btn-primary" onclick="window.print()">{{ lang('commerce.admin_invoice_1') }}</button>
		<button type="button" class="zmi-btn" onclick="window.close()">{{ lang('commerce.admin_invoice_2') }}</button>
	</div>

	@foreach ($invoices as $inv)
	@php
		$order = $inv->order;
		$order_items = $inv->items;
		$order_address = $inv->address;
		$discount = (int)$order->discount_total;
		$credit = (int)$order->credit_used;
		$tax = $inv->tax;
		$show_tax = $tax->enabled;
		$ship = null;
	@endphp
	{{-- 템플릿 @php 안에서는 루프를 쓰지 않는다 (템플릿 v2 규약) --}}
	@foreach ($inv->sellers as $os)
		@if ($ship === null && $os->shipping_invoice)
		@php $ship = $os; @endphp
		@endif
	@endforeach
	<div class="zmi-sheet">
		<div class="zmi-head">
			<h1>{{ lang('commerce.admin_invoice_3') }}</h1>
			<div class="zmi-code">
				<div><b>{{ $order->order_code }}</b></div>
				<div>{{ substr((string)$order->regdate, 0, 4) }}-{{ substr((string)$order->regdate, 4, 2) }}-{{ substr((string)$order->regdate, 6, 2) }}</div>
			</div>
		</div>

		@if ($biz->biz_name || $biz->biz_number || $biz->biz_address || $biz->biz_tel)
		<table class="zmi-tbl zmi-biz">
			<tbody>
				@if ($biz->biz_name || $biz->biz_ceo)
				<tr>
					<th>{{ lang('commerce.admin_invoice_4') }}</th><td>{{ $biz->biz_name }}</td>
					<th>{{ lang('commerce.admin_invoice_5') }}</th><td>{{ $biz->biz_ceo }}</td>
				</tr>
				@endif
				@if ($biz->biz_number || $biz->biz_tel)
				<tr>
					<th>{{ lang('commerce.admin_invoice_6') }}</th><td>{{ $biz->biz_number }}</td>
					<th>{{ lang('commerce.admin_invoice_7') }}</th><td>{{ $biz->biz_tel }}</td>
				</tr>
				@endif
				@if ($biz->biz_address)
				<tr><th>{{ lang('commerce.admin_invoice_8') }}</th><td colspan="3">{{ $biz->biz_address }}</td></tr>
				@endif
			</tbody>
		</table>
		@endif

		<div class="zmi-cols">
			<div class="zmi-col">
				<h2>{{ lang('commerce.admin_invoice_9') }}</h2>
				<table class="zmi-tbl">
					<tbody>
						<tr><th>{{ lang('commerce.admin_invoice_10') }}</th><td>{{ $order->order_code }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_11') }}</th><td>{{ $zmi_status[$order->status] ?? $order->status }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_12') }}</th><td>{{ $order->orderer_name }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_13') }}</th><td>{{ $order->orderer_phone }}</td></tr>
						@if ($order->orderer_email)
						<tr><th>{{ lang('commerce.admin_invoice_14') }}</th><td>{{ $order->orderer_email }}</td></tr>
						@endif
					</tbody>
				</table>
			</div>
			<div class="zmi-col">
				<h2>{{ lang('commerce.admin_invoice_15') }}</h2>
				<table class="zmi-tbl">
					<tbody>
						@if ($order_address)
						@if (($order_address->country ?? 'KR') !== 'KR')
						<tr><th>{{ lang('commerce.admin_invoice_16') }}</th><td>{{ $inv->country_name }}</td></tr>
						@endif
						<tr><th>{{ lang('commerce.admin_invoice_17') }}</th><td>{{ $order_address->receiver_name }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_13') }}</th><td>{{ $inv->phone_text }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_8') }}</th><td>{{ $inv->address_text }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_18') }}</th><td>{{ $order_address->delivery_memo }}</td></tr>
						@else
						<tr><td colspan="2">{{ lang('commerce.admin_invoice_19') }}</td></tr>
						@endif
						@if ($ship)
						<tr><th>{{ lang('commerce.admin_invoice_20') }}</th><td>{{ $ship->shipping_company }} {{ $ship->shipping_invoice }}</td></tr>
						@endif
					</tbody>
				</table>
			</div>
		</div>

		<h2>{{ lang('commerce.adm_invoice_items') }} @if ($show_tax && $tax->zero_rated)<span class="zmi-tag">{{ lang('commerce.admin_invoice_21') }}</span>@endif</h2>
		<table class="zmi-tbl zmi-items">
			<thead>
				<tr>
					<th style="width:34px">No</th>
					<th>{{ lang('commerce.admin_invoice_22') }}</th>
					@php $zmi_has_sku = false; @endphp
					@foreach ($order_items as $zmi_sku_row)
					@php $zmi_has_sku = $zmi_has_sku || trim((string)($zmi_sku_row->sku ?? '')) !== ''; @endphp
					@endforeach
					@if ($zmi_has_sku)<th style="width:110px">SKU</th>@endif
					<th style="width:52px">{{ lang('commerce.admin_invoice_23') }}</th>
					<th style="width:88px">{{ lang('commerce.admin_invoice_24') }}</th>
					@if ($show_tax)
					<th style="width:96px">{{ lang('commerce.admin_invoice_25') }}</th>
					<th style="width:80px">{{ lang('commerce.admin_invoice_26') }}</th>
					@endif
					<th style="width:100px">{{ lang('commerce.admin_invoice_27') }}</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($order_items as $i => $oi)
				<tr>
					<td class="zmi-c">{{ $i + 1 }}</td>
					<td>
						{{ $oi->item_name }}@if (($oi->tax_type ?? 'taxable') === 'free')<span class="zmi-tag">{{ lang('commerce.admin_invoice_28') }}</span>@endif
						@if ($oi->option_name)<span class="zmi-opt">{{ $oi->option_name }}</span>@endif
					</td>
					@if ($zmi_has_sku)<td class="zmi-c">{{ $oi->sku ?? '' }}</td>@endif
					<td class="zmi-c">{{ $oi->qty }}</td>
					<td class="zmi-r">{{ shop_money_in($oi->price, $order->currency ?? 'KRW') }}</td>
					@if ($show_tax)
					<td class="zmi-r">{{ shop_money_in($tax->lines[$i]->supply, $order->currency ?? 'KRW') }}</td>
					<td class="zmi-r">{{ $tax->lines[$i]->free ? '-' : shop_money_in($tax->lines[$i]->vat, $order->currency ?? 'KRW') }}</td>
					@endif
					<td class="zmi-r">{{ shop_money_in($oi->subtotal, $order->currency ?? 'KRW') }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>

		<table class="zmi-tbl zmi-sum">
			<tbody>
				@if ($show_tax)
				<tr><th>{{ lang('commerce.admin_invoice_29') }}</th><td class="zmi-r">{{ shop_money_in($tax->taxable_supply, $order->currency ?? 'KRW') }}</td></tr>
				@if ($tax->free_supply > 0)
				<tr><th>{{ $tax->zero_rated ? lang('commerce.adm_supply_zero') : lang('commerce.adm_supply_free') }}</th><td class="zmi-r">{{ shop_money_in($tax->free_supply, $order->currency ?? 'KRW') }}</td></tr>
				@endif
				<tr><th>{{ lang('commerce.admin_invoice_30') }}</th><td class="zmi-r">{{ shop_money_in($tax->delivery_supply, $order->currency ?? 'KRW') }}</td></tr>
				<tr><th>{{ lang('commerce.admin_invoice_31') }}</th><td class="zmi-r">{{ shop_money_in($tax->total_vat, $order->currency ?? 'KRW') }}</td></tr>
				@else
				<tr><th>{{ lang('commerce.admin_invoice_32') }}</th><td class="zmi-r">{{ shop_money_in($order->item_total, $order->currency ?? 'KRW') }}</td></tr>
				<tr><th>{{ lang('commerce.admin_invoice_33') }}</th><td class="zmi-r">{{ shop_money_in($order->delivery_fee_total, $order->currency ?? 'KRW') }}</td></tr>
				@endif
				@if ($discount > 0)
				<tr><th>{{ lang('commerce.admin_invoice_34') }}</th><td class="zmi-r">-{{ shop_money_in($discount, $order->currency ?? 'KRW') }}</td></tr>
				@endif
				@if ($credit > 0)
				<tr><th>{{ lang('commerce.admin_invoice_35') }}</th><td class="zmi-r">-{{ shop_money_in($credit, $order->currency ?? 'KRW') }}</td></tr>
				@endif
				<tr class="zmi-total"><th>{{ lang('commerce.admin_invoice_36') }}</th><td class="zmi-r">{{ shop_money_in($order->payment_price, $order->currency ?? 'KRW') }}</td></tr>
			</tbody>
		</table>
		@if ($show_tax && !$tax->zero_rated)
		<p class="zmi-taxnote">{{ sprintf($tax->included ? lang('commerce.adm_tax_note_incl') : lang('commerce.adm_tax_note_excl'), (int)round($tax->rate * 100)) }}</p>
		@endif

		@if ($order->memo)
		<div class="zmi-memo"><b>{{ lang('commerce.admin_invoice_37') }}</b> {{ $order->memo }}</div>
		@endif

		@if ($biz->biz_note)
		<div class="zmi-note">{{ $biz->biz_note }}</div>
		@endif
	</div>
	@endforeach
</div>

<style>
.zmi-page { background: #f2f4f6; min-height: 100vh; padding: 20px 0 60px; font-family: Pretendard, -apple-system, BlinkMacSystemFont, "Segoe UI", "Malgun Gothic", sans-serif; color: #1d2433; }
.zmi-toolbar { width: 190mm; max-width: calc(100% - 24px); margin: 0 auto 14px; display: flex; gap: 8px; align-items: center; justify-content: flex-end; }
.zmi-count { margin-right: auto; font-size: 13px; color: #4b5563; }
.zmi-btn { padding: 9px 18px; border: 1px solid #d9dee5; border-radius: 8px; background: #fff; color: #1d2433; font-family: inherit; font-size: 14px; cursor: pointer; }
.zmi-btn-primary { border-color: #2677e3; background: #2677e3; color: #fff; font-weight: 700; }
.zmi-sheet { width: 190mm; max-width: calc(100% - 24px); margin: 0 auto 18px; padding: 16mm 14mm; background: #fff; box-shadow: 0 10px 30px -16px rgba(16,24,40,.35); box-sizing: border-box; }
.zmi-head { display: flex; align-items: flex-end; justify-content: space-between; border-bottom: 2px solid #1d2433; padding-bottom: 10px; margin-bottom: 16px; }
.zmi-head h1 { margin: 0; font-size: 26px; letter-spacing: 6px; }
.zmi-code { text-align: right; font-size: 12.5px; color: #4b5563; line-height: 1.7; }
.zmi-sheet h2 { margin: 18px 0 6px; font-size: 13.5px; font-weight: 700; }
.zmi-tbl { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.zmi-tbl th, .zmi-tbl td { border: 1px solid #d9dee5; padding: 6px 9px; text-align: left; vertical-align: middle; line-height: 1.5; }
.zmi-tbl th { background: #f7f9fa; font-weight: 600; color: #4b5563; width: 84px; white-space: nowrap; }
.zmi-biz th { width: 104px; }
.zmi-cols { display: flex; gap: 12px; }
.zmi-col { flex: 1; min-width: 0; }
.zmi-items thead th { text-align: center; background: #f7f9fa; width: auto; }
.zmi-items td { height: 26px; }
.zmi-tag { display: inline-block; margin-left: 5px; padding: 1px 6px; border: 1px solid #d9dee5; border-radius: 4px; font-size: 10.5px; font-weight: 600; color: #4b5563; vertical-align: middle; }
.zmi-taxnote { margin: 8px 0 0; text-align: right; font-size: 11.5px; color: #6b7684; }
.zmi-opt { display: block; margin-top: 2px; font-size: 11.5px; color: #6b7684; }
.zmi-c { text-align: center; }
.zmi-r { text-align: right; }
.zmi-sum { width: 300px; margin-left: auto; margin-top: 10px; }
.zmi-sum th { width: 130px; }
.zmi-total th, .zmi-total td { background: #f7f9fa; font-size: 14px; font-weight: 700; color: #1d2433; }
.zmi-memo { margin-top: 14px; padding: 9px 11px; border: 1px dashed #d9dee5; font-size: 12.5px; line-height: 1.6; }
.zmi-note { margin-top: 16px; padding-top: 10px; border-top: 1px solid #e5e8eb; font-size: 11.5px; color: #6b7684; line-height: 1.7; white-space: pre-line; }

@media print {
	@page { size: A4; margin: 12mm; }
	.zmi-page { background: #fff; padding: 0; }
	.zmi-toolbar { display: none; }
	.zmi-sheet { width: auto; max-width: none; margin: 0; padding: 0; box-shadow: none; break-after: page; page-break-after: always; }
	.zmi-sheet:last-child { break-after: auto; page-break-after: auto; }
	.zmi-tbl th { background: #f7f9fa !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>
