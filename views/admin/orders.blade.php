@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispCommerceAdminOrders" />
		<select name="f_status">
			<option value="">{{ lang('commerce.admin_orders_1') }}</option>
			@foreach (['pending'=>lang('commerce.st_log_pending'),'paid'=>lang('commerce.st_log_paid'),'cancelled'=>lang('commerce.st_order_cancelled'),'expired'=>lang('commerce.st_order_expired')] as $key => $label)
			<option value="{{ $key }}" @if($filters->status === $key) selected @endif>{{ $label }}</option>
			@endforeach
		</select>
		<input type="text" name="f_keyword" placeholder="{{ lang('commerce.admin_orders_16') }}" value="{{ $filters->keyword }}" />
		<button type="submit" class="rsva-btn">{{ lang('commerce.admin_orders_2') }}</button>
	</form>

	@if (empty($orders))
	<p class="rsva-empty">{{ lang('commerce.admin_orders_3') }}</p>
	@else
	@php $zmi_url = getUrl('', 'mid', '', 'module', 'commerce', 'act', 'dispCommerceAdminOrderInvoice'); @endphp
	<div class="zmi-bulk">
		<label><input type="checkbox" id="zmiAll" /> {{ lang('commerce.admin_orders_4') }}</label>
		<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-primary" id="zmiPrint" data-url="{{ $zmi_url }}">{{ lang('commerce.admin_orders_5') }}</button>
		<button type="button" class="rsva-btn rsva-btn-sm" id="zmiExport" data-url="{{ getUrl('', 'mid', '', 'module', 'commerce', 'act', 'dispCommerceAdminExportOrders', 'f_status', $filters->status, 'f_keyword', $filters->keyword) }}">{{ lang('commerce.admin_orders_6') }}</button>
		<span class="zmi-bulk-hint">{{ lang('commerce.admin_orders_7') }}</span>
	</div>

	<table class="rsva-table">
		<thead><tr><th style="width:34px"></th><th>{{ lang('commerce.admin_orders_8') }}</th><th>{{ lang('commerce.admin_orders_9') }}</th><th>{{ lang('commerce.admin_orders_10') }}</th><th>{{ lang('commerce.admin_orders_11') }}</th><th>{{ lang('commerce.admin_orders_12') }}</th><th>{{ lang('commerce.admin_orders_13') }}</th><th></th></tr></thead>
		<tbody>
			@foreach ($orders as $o)
			@php $os = $seller_map[(int)$o->order_srl] ?? null; @endphp
			<tr>
				<td style="text-align:center"><input type="checkbox" class="zmi-pick" value="{{ $o->order_srl }}" /></td>
				<td><strong>{{ $o->order_code }}</strong></td>
				<td>{{ $o->orderer_name }}<br /><small style="color:#9aa1ab">{{ $o->orderer_phone }}</small></td>
				<td>{{ shop_money_in($o->payment_price, $o->currency ?? 'KRW') }}</td>
				<td><span class="rsva-st {{ $o->status === 'paid' ? 'rsva-st-confirmed' : ($o->status === 'pending' ? 'rsva-st-hold' : 'rsva-st-cancelled') }}">{{ ['pending'=>lang('commerce.st_order_pending'),'paid'=>lang('commerce.st_order_paid'),'cancelled'=>lang('commerce.st_order_cancelled'),'failed'=>lang('commerce.st_order_failed'),'expired'=>lang('commerce.st_order_expired')][$o->status] ?? $o->status }}</span></td>
				<td>{{ $os ? (['pending'=>'-','paid'=>lang('commerce.st_sel_paid'),'preparing'=>lang('commerce.st_sel_preparing'),'shipping'=>lang('commerce.st_sel_shipping'),'delivered'=>lang('commerce.st_sel_delivered'),'cancelled'=>lang('commerce.st_order_cancelled'),'refunded'=>lang('commerce.st_sel_refunded')][$os->status] ?? $os->status) : '-' }}</td>
				<td><small>{{ zdate($o->regdate, 'm.d H:i') }}</small></td>
				<td>
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $o->order_srl) }}" class="rsva-btn rsva-btn-sm">{{ lang('commerce.admin_orders_14') }}</a>
					<a href="{{ getUrl('', 'mid', '', 'module', 'commerce', 'act', 'dispCommerceAdminOrderInvoice', 'order_srl', $o->order_srl) }}" class="rsva-btn rsva-btn-sm" target="_blank" rel="noopener" data-zmc-keep>{{ lang('commerce.admin_orders_15') }}</a>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>

<style>
.zmi-bulk { display: flex; align-items: center; gap: 12px; margin: 0 0 10px; font-size: 13px; }
.zmi-bulk label { display: flex; align-items: center; gap: 6px; cursor: pointer; }
.zmi-bulk-hint { color: #8b95a1; font-size: 12px; }
</style>

<script>
(function () {
	var all = document.getElementById('zmiAll');
	var btn = document.getElementById('zmiPrint');
	if (!all || !btn) return;

	function picks() { return Array.prototype.slice.call(document.querySelectorAll('.zmi-pick')); }

	all.addEventListener('change', function () {
		picks().forEach(function (el) { el.checked = all.checked; });
	});

	btn.addEventListener('click', function () {
		var srls = [];
		picks().forEach(function (el) { if (el.checked) srls.push(el.value); });
		if (!srls.length) { alert({!! json_encode(lang('commerce.adm_print_pick')) !!}); return; }
		if (srls.length > 50) { alert({!! json_encode(lang('commerce.adm_print_limit')) !!}); return; }
		var u = new URL(btn.getAttribute('data-url'), location.href);
		u.searchParams.set('order_srls', srls.join(','));
		window.open(u.toString(), '_blank', 'noopener');
	});

	var exportBtn = document.getElementById('zmiExport');
	if (exportBtn) {
		exportBtn.addEventListener('click', function () {
			var srls = [];
			picks().forEach(function (el) { if (el.checked) srls.push(el.value); });
			var u = new URL(exportBtn.getAttribute('data-url'), location.href);
			if (srls.length) { u.searchParams.set('order_srls', srls.join(',')); }
			location.href = u.toString();
		});
	}
})();
</script>
