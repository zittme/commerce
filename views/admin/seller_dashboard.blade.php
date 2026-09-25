@include('_tabs')

<div class="rsva">
	@if (empty($sc_me->shop_id))
	<div class="rsva-panel" style="border-color:var(--zmc-mark, #e3a92f)">
		<h3>{{ lang('commerce.sc_need_shop_id') }}</h3>
		<p style="margin:0 0 12px;font-size:13.5px">{{ lang('commerce.sc_need_shop_id_desc') }}</p>
		<a class="rsva-btn rsva-btn-primary" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter', 'p', 'seller_profile') }}">{{ lang('commerce.sc_menu_shop_info') }}</a>
	</div>
	@endif

	<div class="rsva-cards">
		<a class="rsva-card" style="text-decoration:none" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter', 'p', 'shipping', 'st', 'paid') }}"><b>{{ number_format($sc_stats->today) }}</b><span>{{ lang('commerce.sc_stat_today') }}</span></a>
		<a class="rsva-card" style="text-decoration:none" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter', 'p', 'shipping', 'st', 'paid') }}"><b>{{ number_format($sc_stats->to_confirm) }}</b><span>{{ lang('commerce.sc_stat_to_confirm') }}</span></a>
		<a class="rsva-card" style="text-decoration:none" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter', 'p', 'shipping', 'st', 'preparing') }}"><b>{{ number_format($sc_stats->to_ship) }}</b><span>{{ lang('commerce.sc_stat_to_ship') }}</span></a>
		<a class="rsva-card" style="text-decoration:none" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter', 'p', 'shipping', 'st', 'shipping') }}"><b>{{ number_format($sc_stats->shipping) }}</b><span>{{ lang('commerce.sc_stat_shipping') }}</span></a>
		<div class="rsva-card"><b>{{ number_format($sc_stats->claims) }}</b><span>{{ lang('commerce.sc_stat_claims') }}</span></div>
		<div class="rsva-card"><b>{{ number_format($sc_stats->items_on) }}</b><span>{{ lang('commerce.sc_stat_items_on') }}</span></div>
	</div>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.sc_settle_title') }}</h3>
		<div class="rsva-form-grid">
			<div><label>{{ lang('commerce.sc_settle_expected') }}</label><div style="font-size:20px;font-weight:800">{{ $sc_stats->expected_text }}</div><small>{{ lang('commerce.sc_settle_expected_desc') }}</small></div>
			<div><label>{{ lang('commerce.sc_settle_ready') }}</label><div style="font-size:20px;font-weight:800">{{ $sc_stats->ready_text }}</div><small>{{ lang('commerce.sc_settle_ready_desc') }}</small></div>
		</div>
		<p style="margin:14px 0 0"><a class="rsva-btn" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceSellerCenter', 'p', 'settlements') }}">{{ lang('commerce.admin_menu_settlements') }}</a></p>
	</div>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.sc_recent') }}</h3>
		@if (count($sc_recent))
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.mk_col_order') }}</th><th>{{ lang('commerce.mk_col_status') }}</th><th>{{ lang('commerce.mk_col_sales') }}</th><th>{{ lang('commerce.sc_col_date') }}</th></tr></thead>
			<tbody>
				@foreach ($sc_recent as $sc_row)
				<tr>
					<td>{{ $sc_row->order_code }}</td>
					<td><span class="rsva-st rsva-st-{{ $sc_row->status }}">{{ lang('commerce.st_sel_' . $sc_row->status) }}</span></td>
					<td>{{ $sc_row->amount_text }}</td>
					<td>{{ $sc_row->regdate_text }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@else
		<div class="rsva-empty">{{ lang('commerce.sc_recent_empty') }}</div>
		@endif
	</div>
</div>
