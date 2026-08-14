@include('_tabs')

@php
	$st_tabs = ['period' => lang('commerce.adm_stats_period'), 'item' => lang('commerce.adm_stats_item'), 'region' => lang('commerce.adm_stats_region')];
	$st_base = ['module', 'admin', 'act', 'dispCommerceAdminStats'];
	$st_max = 0;
@endphp
@if ($st_tab === 'period')
	@foreach ($st_rows as $r)
	@if ($r->sales > $st_max) @php $st_max = $r->sales; @endphp @endif
	@endforeach
@endif

<div class="rsva">
	<div class="zms-tabs">
		@foreach ($st_tabs as $key => $label)
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminStats', 't', $key, 'from', $st_from, 'to', $st_to, 'unit', $st_unit) }}" class="{{ $st_tab === $key ? 'is-on' : '' }}">{{ $label }}</a>
		@endforeach
	</div>

	<form action="{{ getUrl('') }}" method="get" class="rsva-filter zms-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispCommerceAdminStats" />
		<input type="hidden" name="t" value="{{ $st_tab }}" />
		<label>{{ lang('commerce.admin_stats_1') }}</label>
		<input type="date" name="from" value="{{ substr($st_from, 0, 4) }}-{{ substr($st_from, 4, 2) }}-{{ substr($st_from, 6, 2) }}" />
		<span>~</span>
		<input type="date" name="to" value="{{ substr($st_to, 0, 4) }}-{{ substr($st_to, 4, 2) }}-{{ substr($st_to, 6, 2) }}" />
		@if ($st_tab === 'period')
		<select name="unit">
			@foreach ($st_units as $key => $label)
			<option value="{{ $key }}" @if($st_unit === $key) selected @endif>{{ sprintf(lang('commerce.adm_stats_unit'), $label) }}</option>
			@endforeach
		</select>
		@endif
		<button type="submit" class="rsva-btn">{{ lang('commerce.admin_stats_2') }}</button>
		<a href="{{ getUrl('', 'mid', '', 'module', 'commerce', 'act', 'dispCommerceAdminExportStats', 't', $st_tab, 'from', $st_from, 'to', $st_to, 'unit', $st_unit) }}" class="rsva-btn" data-zmc-keep>{{ lang('commerce.admin_stats_3') }}</a>
	</form>

	<div class="zms-cards">
		<div class="zms-card"><span>{{ lang('commerce.admin_stats_4') }}</span><strong>{{ shop_money_base($st_summary->sales) }}</strong></div>
		<div class="zms-card"><span>{{ lang('commerce.admin_stats_5') }}</span><strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($st_summary->orders)) }}</strong></div>
		<div class="zms-card"><span>{{ lang('commerce.admin_stats_6') }}</span><strong>{{ shop_money_base($st_summary->average) }}</strong></div>
		<div class="zms-card zms-card-minus"><span>{{ lang('commerce.admin_stats_7') }}</span><strong>{{ shop_money_base($st_summary->cancelled_sales) }}</strong><small>{{ sprintf(lang('commerce.st_unit_count'), number_format($st_summary->cancelled_orders)) }}</small></div>
	</div>

	<div class="rsva-panel">
		@if (empty($st_rows))
		<p class="rsva-empty">{{ lang('commerce.admin_stats_8') }}</p>
		@else

		@if ($st_tab === 'period')
		<div class="zms-chart">
			@foreach ($st_rows as $r)
			<div class="zms-bar" title="{{ $r->label }} · {{ shop_money_base($r->sales) }} · {{ sprintf(lang('commerce.st_unit_count'), $r->orders) }}">
				<div class="zms-bar-fill" style="height:{{ $st_max > 0 ? max(2, (int)round($r->sales / $st_max * 100)) : 2 }}%"></div>
				<span class="zms-bar-label">{{ $r->label }}</span>
			</div>
			@endforeach
		</div>
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stats_1') }}</th><th style="width:120px">{{ lang('commerce.admin_stats_5') }}</th><th style="width:180px">{{ lang('commerce.admin_stats_4') }}</th></tr></thead>
			<tbody>
				@foreach ($st_rows as $r)
				<tr><td>{{ $r->label }}</td><td>{{ sprintf(lang('commerce.st_unit_count'), number_format($r->orders)) }}</td><td>{{ shop_money_base($r->sales) }}</td></tr>
				@endforeach
			</tbody>
		</table>

		@elseif ($st_tab === 'item')
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stats_9') }}</th><th style="width:110px">{{ lang('commerce.admin_stats_10') }}</th><th style="width:110px">{{ lang('commerce.admin_stats_11') }}</th><th style="width:180px">{{ lang('commerce.admin_stats_4') }}</th></tr></thead>
			<tbody>
				@foreach ($st_rows as $r)
				<tr>
					<td><a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $r->item_srl) }}">{{ $r->item_name }}</a></td>
					<td>{{ number_format($r->qty) }}</td>
					<td>{{ number_format($r->orders) }}</td>
					<td>{{ shop_money_base($r->sales) }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>

		@else
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stats_12') }}</th><th style="width:120px">{{ lang('commerce.admin_stats_11') }}</th><th style="width:180px">{{ lang('commerce.admin_stats_4') }}</th></tr></thead>
			<tbody>
				@foreach ($st_rows as $r)
				<tr><td>{{ $r->region }}</td><td>{{ sprintf(lang('commerce.st_unit_count'), number_format($r->orders)) }}</td><td>{{ shop_money_base($r->sales) }}</td></tr>
				@endforeach
			</tbody>
		</table>
		<p class="rsva-hint">{{ lang('commerce.admin_stats_13') }}</p>
		@endif

		@endif
	</div>
</div>

<style>
.zms-tabs { display: flex; gap: 6px; margin: 0 0 14px; }
.zms-tabs a { padding: 8px 16px; border: 1px solid var(--zm-border, #e5e8eb); border-radius: 999px; font-size: 13.5px; color: var(--zm-text-sub, #6b7684); text-decoration: none; }
.zms-tabs a.is-on { border-color: var(--zm-point, #2677e3); background: var(--zm-point, #2677e3); color: #fff; font-weight: 700; }
.zms-filter { align-items: center; gap: 8px; }
.zms-filter label { font-size: 13px; color: var(--zm-text-sub, #6b7684); }
.zms-cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; margin: 0 0 14px; }
.zms-card { padding: 16px 18px; border: 1px solid var(--zm-border, #e5e8eb); border-radius: 12px; background: var(--zm-surface, #fff); }
.zms-card span { display: block; font-size: 12.5px; color: var(--zm-text-sub, #6b7684); }
.zms-card strong { display: block; margin-top: 6px; font-size: 20px; }
.zms-card small { display: block; margin-top: 2px; font-size: 12px; color: var(--zm-text-sub, #6b7684); }
.zms-card-minus strong { color: #d64545; }
.zms-chart { display: flex; align-items: flex-end; gap: 3px; height: 180px; margin: 0 0 18px; padding: 0 2px 26px; border-bottom: 1px solid var(--zm-border, #e5e8eb); overflow-x: auto; }
.zms-bar { position: relative; flex: 1 0 14px; min-width: 14px; height: 100%; display: flex; align-items: flex-end; }
.zms-bar-fill { width: 100%; border-radius: 3px 3px 0 0; background: var(--zm-point, #2677e3); opacity: .85; }
.zms-bar:hover .zms-bar-fill { opacity: 1; }
.zms-bar-label { position: absolute; left: 50%; bottom: -22px; transform: translateX(-50%) rotate(-45deg); transform-origin: center; font-size: 10px; white-space: nowrap; color: var(--zm-text-sub, #6b7684); }
@media (max-width: 900px) {
	.zms-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); }
	.zms-bar-label { display: none; }
}
</style>
