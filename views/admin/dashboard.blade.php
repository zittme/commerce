@include('_tabs')

@if (Context::get('zmc_console'))
<div class="rsva">
	@php
		$zmc_cl = Context::get('shop_checklist') ?: [];
		$zmc_done = (int)Context::get('shop_checklist_done');
		$zs = Context::get('shop_stats');
		$zs_max = 0;
	@endphp
	@foreach ($zs->series as $zs_row)
	@if ($zs_row->sales > $zs_max) @php $zs_max = $zs_row->sales; @endphp @endif
	@endforeach

	@php
	$zd_map = [];
	foreach ($zs->series as $zs_row) { $zd_map[(string)$zs_row->bucket] = $zs_row; }
	$zd_days = [];
	for ($zd_i = 29; $zd_i >= 0; $zd_i--)
	{
		$zd_b = date('Ymd', strtotime('-' . $zd_i . ' days'));
		$zd_days[] = $zd_map[$zd_b] ?? (object)['bucket' => $zd_b, 'label' => date('n/j', strtotime($zd_b)), 'orders' => 0, 'sales' => 0];
	}
	$zd_sum30 = 0;
	foreach ($zd_days as $zd_d) { $zd_sum30 += (int)$zd_d->sales; }
	$zd_todo = [
		[lang('commerce.admin_dashboard_5'), (int)$zs->counts->to_ship, getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrders', 'f_ship', 'to_ship'), 'orders'],
		[lang('commerce.admin_dashboard_9'), (int)$zs->counts->claims, getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminClaims', 'f_status', 'requested'), 'claims'],
		[lang('commerce.adm_dash_unanswered'), (int)($zs->counts->unanswered ?? 0), getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminQna', 'f_unanswered', 'Y'), 'qna'],
		[lang('commerce.adm_low_stock_card'), count($low_stock_rows), getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock'), 'items'],
		[lang('commerce.admin_dashboard_3'), (int)$zs->counts->pending, getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrders', 'f_status', 'pending'), 'orders'],
		[lang('commerce.admin_dashboard_7'), (int)$zs->counts->shipping, getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrders', 'f_ship', 'shipping'), 'orders'],
	];
	$zd_todo_keep = [];
	foreach ($zd_todo as $zd_t) { if (Zittme\Modules\Commerce\Models\Staff::can($zd_t[3])) { $zd_todo_keep[] = $zd_t; } }
	$zd_hot = count($zd_todo_keep) < count($zd_todo) ? count($zd_todo_keep) : 4;
	$zd_todo = $zd_todo_keep;
	$zd_busy = 0;
	foreach (array_slice($zd_todo, 0, $zd_hot) as $zd_t) { $zd_busy += $zd_t[1]; }
	@endphp

	@if (count($zd_todo))
	<section class="zmd-todo" aria-label="{{ lang('commerce.dash_todo') }}">
		<h3>{{ lang('commerce.dash_todo') }} <small>{{ $zd_busy > 0 ? sprintf(lang('commerce.dash_todo_count'), $zd_busy) : lang('commerce.dash_todo_none') }}</small></h3>
		<div class="zmd-todo-row">
			@foreach ($zd_todo as $zd_i => $zd_t)
			<a class="zmd-todo-item {{ $zd_t[1] > 0 && $zd_i < $zd_hot ? 'is-on' : '' }}" href="{{ $zd_t[2] }}"><b>{{ number_format($zd_t[1]) }}</b><span>{{ $zd_t[0] }}</span></a>
			@endforeach
		</div>
	</section>
	@endif

	@if (Zittme\Modules\Commerce\Models\Staff::can('stats'))
	<div class="zmd-row">
		<section class="rsva-panel zmd-sales">
			<div class="zmd-sales-nums">
				<div><span>{{ lang('commerce.admin_dashboard_1') }}</span><strong>{{ shop_money_base($zs->today->sales) }}</strong><small>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->today->orders)) }} · {{ lang('commerce.adm_yesterday') }} {{ shop_money_base($zs->yesterday->sales) }}</small></div>
				<div><span>{{ lang('commerce.admin_dashboard_2') }}</span><strong>{{ shop_money_base($zs->month->sales) }}</strong><small>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->month->orders)) }} · {{ lang('commerce.adm_last_month') }} {{ shop_money_base($zs->last_month->sales) }}</small></div>
				<div><span>{{ lang('commerce.dash_30days') }}</span><strong>{{ shop_money_base($zd_sum30) }}</strong><small><a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStats') }}">{{ lang('commerce.admin_dashboard_12') }}</a></small></div>
			</div>
			<div class="zmd-chart" role="img" aria-label="{{ lang('commerce.dash_30days') }}">
				@foreach ($zd_days as $zd_d)
				<div class="zmd-bar {{ (int)$zd_d->sales > 0 ? '' : 'is-zero' }}" title="{{ $zd_d->label }} · {{ shop_money_base((int)$zd_d->sales) }} · {{ sprintf(lang('commerce.st_unit_count'), (int)$zd_d->orders) }}"><i style="height:{{ $zs_max > 0 && (int)$zd_d->sales > 0 ? max(4, (int)round($zd_d->sales / $zs_max * 100)) : 0 }}%"></i></div>
				@endforeach
			</div>
			@if ($zd_sum30 === 0)<p class="zmd-chart-empty">{{ lang('commerce.admin_dashboard_13') }}</p>@endif
			<div class="zmd-axis"><span>{{ $zd_days[0]->label }}</span><span>{{ lang('commerce.dash_today') }}</span></div>
		</section>
		<section class="rsva-panel">
			<h3>{{ lang('commerce.admin_dashboard_14') }} <small>{{ lang('commerce.admin_dashboard_15') }}</small></h3>
			@if (empty($zs->top_items))
			<p class="rsva-empty">{{ lang('commerce.admin_dashboard_16') }}</p>
			@else
			<ol class="zmd-top">
				@foreach ($zs->top_items as $zs_item)
				<li>
					<a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $zs_item->item_srl) }}">{{ $zs_item->item_name }}</a>
					<span>{{ sprintf(lang('commerce.st_unit_ea'), number_format($zs_item->qty)) }} · {{ shop_money_base($zs_item->sales) }}</span>
				</li>
				@endforeach
			</ol>
			@endif
		</section>
	</div>

	@endif

	@if (count($low_stock_rows) && Zittme\Modules\Commerce\Models\Staff::can('items'))
	<div class="rsva-panel">
		<h3>{{ sprintf(lang('commerce.adm_low_stock_title'), count($low_stock_rows)) }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock') }}" class="zmd-more">{{ lang('commerce.admin_menu_stock') }}</a></h3>
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stock_4') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_6') }}</th><th style="text-align:right">{{ lang('commerce.adm_low_stock_limit') }}</th><th>{{ lang('commerce.admin_stock_7') }}</th><th></th></tr></thead>
			<tbody>
				@foreach (array_slice($low_stock_rows, 0, 5) as $dl)
				@php $dl_fid = 'zmdLow' . (int)$dl->item_srl . '_' . (int)$dl->option_srl; @endphp
				<tr>
					<td>{{ $dl->label }}</td>
					<td style="text-align:right;font-weight:700">{{ number_format((int)$dl->stock) }}</td>
					<td style="text-align:right;color:#8b95a1">{{ number_format((int)$dl->limit_qty) }}</td>
					<td><input type="number" name="qty" form="{{ $dl_fid }}" min="1" value="10" style="width:80px" /></td>
					<td><button type="submit" form="{{ $dl_fid }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.adm_low_stock_restock') }}</button></td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@foreach (array_slice($low_stock_rows, 0, 5) as $dl)
		<form id="zmdLow{{ (int)$dl->item_srl }}_{{ (int)$dl->option_srl }}" action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminStockAdjust" />
			<input type="hidden" name="item_srl" value="{{ (int)$dl->item_srl }}" />
			<input type="hidden" name="option_srl" value="{{ (int)$dl->option_srl }}" />
			<input type="hidden" name="adjust_type" value="in" />
			<input type="hidden" name="memo" value="{{ lang('commerce.adm_low_stock_restock') }}" />
			<input type="hidden" name="success_return_url" value="{{ getNotEncodedUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminDashboard') }}" />
		</form>
		@endforeach
	</div>
	@endif

	<div class="zmd-split" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)">
		@if (Zittme\Modules\Commerce\Models\Staff::can('orders'))
		<div class="rsva-panel">
			<h3>{{ lang('commerce.adm_dash_recent_orders') }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrders') }}" class="zmd-more">{{ lang('commerce.admin_menu_orders') }}</a></h3>
			@if (empty($recent_orders))
			<p class="rsva-empty">{{ lang('commerce.admin_orders_3') }}</p>
			@else
			<table class="zmd-mini">
				<thead><tr><th>{{ lang('commerce.admin_orders_8') }}</th><th>{{ lang('commerce.admin_orders_9') }}</th><th>{{ lang('commerce.admin_orders_10') }}</th><th>{{ lang('commerce.admin_orders_11') }}</th><th>{{ lang('commerce.admin_orders_13') }}</th></tr></thead>
				<tbody>
					@foreach ($recent_orders as $ro)
					<tr>
						<td><a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $ro->order_srl) }}">{{ $ro->order_code }}</a></td>
						<td>{{ $ro->orderer_name }}</td>
						<td>{{ shop_money_in($ro->payment_price, $ro->currency ?? 'KRW') }}</td>
						<td><span class="rsva-st {{ $ro->status === 'paid' ? 'rsva-st-confirmed' : ($ro->status === 'pending' ? 'rsva-st-hold' : 'rsva-st-cancelled') }}">{{ ['pending'=>lang('commerce.st_order_pending'),'paid'=>lang('commerce.st_order_paid'),'cancelled'=>lang('commerce.st_order_cancelled'),'failed'=>lang('commerce.st_order_failed'),'expired'=>lang('commerce.st_order_expired')][$ro->status] ?? $ro->status }}</span></td>
						<td><small>{{ zdate($ro->regdate, 'm.d H:i') }}</small></td>
					</tr>
					@endforeach
				</tbody>
			</table>
			@endif
		</div>
		@endif
		@if (Zittme\Modules\Commerce\Models\Staff::can('claims'))
		<div class="rsva-panel">
			<h3>{{ lang('commerce.adm_dash_recent_claims') }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminClaims') }}" class="zmd-more">{{ lang('commerce.admin_menu_claims') }}</a></h3>
			@if (empty($recent_claims))
			<p class="rsva-empty">{{ lang('commerce.admin_claims_6') }}</p>
			@else
			<table class="zmd-mini">
				<thead><tr><th>{{ lang('commerce.admin_claims_7') }}</th><th>{{ lang('commerce.admin_claims_8') }}</th><th>{{ lang('commerce.admin_claims_10') }}</th><th>{{ lang('commerce.admin_orders_13') }}</th></tr></thead>
				<tbody>
					@foreach ($recent_claims as $rc)
					@php $rco = $recent_claim_orders[(int)$rc->order_srl] ?? null; @endphp
					<tr>
						<td>@if ($rco)<a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $rco->order_srl) }}">{{ $rco->order_code }}</a>@else -@endif</td>
						<td>{{ ['cancel'=>lang('commerce.st_claim_cancel'),'return'=>lang('commerce.st_claim_return'),'exchange'=>lang('commerce.st_claim_exchange')][$rc->claim_type] ?? $rc->claim_type }}</td>
						<td><span class="rsva-st {{ $rc->status === 'requested' ? 'rsva-st-hold' : '' }}">{{ ['requested'=>lang('commerce.st_claim_request'),'rejected'=>lang('commerce.st_claim_rejected'),'done'=>lang('commerce.st_claim_done')][$rc->status] ?? $rc->status }}</span></td>
						<td><small>{{ zdate($rc->regdate, 'm.d H:i') }}</small></td>
					</tr>
					@endforeach
				</tbody>
			</table>
			@endif
		</div>
		@endif
	</div>

	@if (Zittme\Modules\Commerce\Models\Staff::can('@sub'))
	<div class="rsva-panel" @if (count($zmc_cl) && $zmc_done >= count($zmc_cl)) hidden @endif>
		<h3 style="display:flex;align-items:center;gap:10px">{{ lang('commerce.admin_dashboard_17') }}
			<span style="font-size:12.5px;font-weight:600;color:#6b7684">{{ sprintf(lang('commerce.adm_checklist_done'), $zmc_done, count($zmc_cl)) }}</span>
		</h3>
		<div style="height:6px;border-radius:99px;background:#eef1f5;margin:6px 0 14px;overflow:hidden">
			<div style="height:100%;width:{{ count($zmc_cl) ? round($zmc_done / count($zmc_cl) * 100) : 0 }}%;background:#2677e3;border-radius:99px"></div>
		</div>
		<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px">
			@foreach ($zmc_cl as $zmc_step)
			<a href="{{ $zmc_step->url }}" style="display:block;padding:12px 14px;border:1px solid {{ $zmc_step->done ? '#cfe3fb' : '#e3e6eb' }};border-radius:10px;background:{{ $zmc_step->done ? '#f2f8ff' : '#fff' }};text-decoration:none;color:inherit">
				<div style="display:flex;align-items:center;gap:7px;font-size:13.5px;font-weight:700;color:{{ $zmc_step->done ? '#2677e3' : '#333d4b' }}">
					@if ($zmc_step->done)
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" fill="#2677e3"/><path d="M8 12.5l2.6 2.6L16 9.5" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
					@else
					<svg width="15" height="15" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="9" stroke="#c6ccd4" stroke-width="2"/></svg>
					@endif
					{{ $zmc_step->title }}
				</div>
				<p style="margin:6px 0 0;font-size:12.5px;color:#6b7684;line-height:1.6">{{ $zmc_step->hint }}</p>
			</a>
			@endforeach
		</div>
		<p style="margin:12px 0 0;font-size:12.5px;color:#8b95a1">{{ lang('commerce.admin_dashboard_18') }} <strong>/{{ $shop_mid }}</strong> {{ lang('commerce.admin_dashboard_19') }}</p>
	</div>
	@endif

	@php
	$dash_info = ModuleModel::getModuleInfoXml('commerce');
	$dash_author = ($dash_info && !empty($dash_info->author)) ? $dash_info->author[0] : null;
	$dash_site = $dash_author && $dash_author->homepage ? $dash_author->homepage : 'https://zitt.me';
	@endphp
	<div class="rsva-panel dash-support">
		<div class="dash-support-main">
			<p>{{ lang('commerce.adm_support_text') }}</p>
			<div class="dash-support-acts">
				<a href="https://zitt.me/issue" target="_blank" rel="noopener" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.adm_support_issue') }}</a>
				<a href="https://open.kakao.com/o/gpRz8Vyi" target="_blank" rel="noopener" class="rsva-btn rsva-btn-sm">{{ lang('commerce.adm_support_kakao') }}</a>
			</div>
		</div>
		<dl class="dash-support-info">
			<div><dt>{{ lang('commerce.dash_info_module') }}</dt><dd>{{ lang('commerce.admin_console_title') }} · {{ $dash_info->title ?? 'Commerce' }} <b>v{{ $dash_info->version ?? '' }}</b>@if (!empty($dash_info->date)) <small>({{ zdate($dash_info->date, 'Y.m.d') }})</small>@endif</dd></div>
			<div><dt>{{ lang('commerce.dash_info_author') }}</dt><dd>{{ $dash_author->name ?? 'ZZAN Studio' }}</dd></div>
			<div><dt>{{ lang('commerce.dash_info_site') }}</dt><dd><a href="{{ $dash_site }}" target="_blank" rel="noopener">{{ preg_replace('#^https?://#', '', rtrim($dash_site, '/')) }}</a></dd></div>
			@if ($dash_author && $dash_author->email_address)
			<div><dt>{{ lang('commerce.dash_info_mail') }}</dt><dd><a href="mailto:{{ $dash_author->email_address }}">{{ $dash_author->email_address }}</a></dd></div>
			@endif
		</dl>
	</div>
	<style>
	.dash-support-main { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px; }
	.dash-support-main p { margin: 0; font-size: 13.5px; color: var(--zmc-sub, #6b7684); }
	.dash-support-acts { display: flex; gap: 8px; flex-wrap: wrap; }
	.dash-support-acts a { text-decoration: none !important; }
	.dash-support-info { display: flex; flex-wrap: wrap; gap: 6px 28px; margin: 14px 0 0; padding: 12px 0 0; border-top: 1px solid var(--zmc-line, #e6e3dc); font-size: 12.5px; }
	.dash-support-info div { display: flex; gap: 8px; }
	.dash-support-info dt { color: var(--zmc-sub, #7a7f8c); }
	.dash-support-info dd { margin: 0; color: var(--zmc-ink, #232a3b); }
	.dash-support-info dd small { color: var(--zmc-sub, #7a7f8c); }
	.dash-support-info a { color: var(--zmc-brand, #26345c); }
	</style>
</div>
@else
<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_dashboard_20') }}</h3>
		<p style="margin:0;font-size:13px;color:#6b7684;line-height:1.8">
			{{ lang('commerce.admin_dashboard_21') }} <strong>{{ lang('commerce.admin_dashboard_22') }}</strong>{{ lang('commerce.admin_dashboard_23') }} <strong>/{{ $shop_mid }}</strong> {{ lang('commerce.admin_dashboard_19') }}
		</p>
	</div>
</div>
@endif

<style>
.zmd-cards { display: grid; gap: 12px; margin: 0 0 12px; }
.zmd-cards-sales { grid-template-columns: repeat(2, minmax(0, 1fr)); }
.zmd-cards-todo { grid-template-columns: repeat(6, minmax(0, 1fr)); margin-bottom: 14px; }
.zmd-card { padding: 16px 18px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: 12px; background: var(--zm-surface, #fff); text-decoration: none; color: inherit; }
.zmd-card span { display: block; font-size: 12.5px; color: var(--zm-text-sub, #6b7684); }
.zmd-card strong { display: block; margin-top: 6px; font-size: 21px; }
.zmd-card small { display: block; margin-top: 4px; font-size: 12px; color: var(--zm-text-sub, #8b95a1); }
.zmd-link:hover { border-color: var(--zmc-brand, #26345c); }
.zmd-card.is-alert strong { color: var(--zmc-brand, #26345c); }
.zmd-split { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 12px; margin: 0 0 14px; }
.zmd-split .rsva-panel { margin: 0; }
.zmd-more { float: right; font-size: 12.5px; font-weight: 500; color: var(--zmc-brand, #26345c); text-decoration: none; }
.zmd-chart { display: flex; align-items: flex-end; gap: 3px; height: 150px; padding-top: 6px; }
.zmd-bar { flex: 1 1 0; min-width: 4px; height: 100%; display: flex; align-items: flex-end; }
.zmd-bar-fill { width: 100%; border-radius: 3px 3px 0 0; background: var(--zmc-brand, #26345c); opacity: .8; }
.zmd-bar:hover .zmd-bar-fill { opacity: 1; }
.zmd-top { margin: 0; padding: 0 0 0 20px; font-size: 13px; line-height: 1.9; }
.zmd-top li a { color: inherit; text-decoration: none; }
.zmd-top li a:hover { text-decoration: underline; }
.zmd-top li span { display: block; font-size: 12px; color: var(--zm-text-sub, #8b95a1); }
.zmd-mini { width: 100%; border-collapse: collapse; font-size: 12.5px; }
.zmd-mini th { padding: 7px 8px; font-size: 12px; font-weight: 600; color: #8b95a1; text-align: left; border-bottom: 1px solid #eef1f5; }
.zmd-mini td { padding: 8px; border-bottom: 1px solid #f4f6f9; vertical-align: middle; }
.zmd-mini tr:last-child td { border-bottom: 0; }
.zmd-mini a { color: inherit; text-decoration: none; font-weight: 700; }
.zmd-mini a:hover { color: var(--zmc-brand, #26345c); }
@media (max-width: 1400px) { .zmd-cards-todo { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1100px) { .zmd-cards-todo { grid-template-columns: repeat(2, minmax(0, 1fr)); } .zmd-split { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 640px) { .zmd-cards, .zmd-cards-sales, .zmd-cards-todo { grid-template-columns: minmax(0, 1fr); } }
.zmd-todo { margin: 0 0 16px; }
.zmd-todo h3 { margin: 0 0 10px; font-size: 16px; font-weight: 800; }
.zmd-todo h3 small, .rsva-panel h3 small { margin-left: 6px; font-size: 13px; font-weight: 500; color: var(--zmc-sub, #7a7f8c); }
.zmd-todo-row { display: grid; grid-template-columns: repeat(6, minmax(0, 1fr)); border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); overflow: hidden; }
.zmd-todo-item { display: flex; flex-direction: column; gap: 2px; padding: 14px 16px; text-decoration: none !important; color: var(--zmc-sub, #7a7f8c) !important; border-left: 1px solid var(--zmc-line, #e6e3dc); }
.zmd-todo-item:first-child { border-left: 0; }
.zmd-todo-item b { font-size: 22px; font-weight: 800; color: var(--zmc-sub, #7a7f8c); font-variant-numeric: tabular-nums; }
.zmd-todo-item span { font-size: 13px; }
.zmd-todo-item.is-on { background: var(--zmc-brand-soft, #fbf1d6); color: var(--zmc-ink, #232a3b) !important; box-shadow: inset 0 3px 0 var(--zmc-mark, #e3a92f); }
.zmd-todo-item.is-on b { color: var(--zmc-ink, #232a3b); }
.zmd-todo-item:hover { background: var(--zmc-hover, #e9e6df); }
.zmd-row { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: 16px; margin: 0 0 16px; }
.zmd-row .rsva-panel { margin: 0; }
.zmd-sales-nums { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
.zmd-sales-nums span { display: block; font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); }
.zmd-sales-nums strong { display: block; margin-top: 4px; font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; }
.zmd-sales-nums small { display: block; margin-top: 2px; font-size: 12px; }
.zmd-sales .zmd-chart { height: 120px; gap: 4px; border-bottom: 1px solid var(--zmc-line-strong, #d6d2c8); }
.zmd-sales .zmd-bar i { display: block; width: 100%; border-radius: 2px 2px 0 0; background: var(--zmc-brand, #26345c); }
.zmd-sales .zmd-bar:last-child i { background: var(--zmc-mark, #e3a92f); }
.zmd-sales .zmd-bar:hover i { opacity: .8; }
.zmd-chart-empty { margin: -70px 0 50px; text-align: center; font-size: 13px; color: var(--zmc-sub, #7a7f8c); }
.zmd-axis { display: flex; justify-content: space-between; margin-top: 6px; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
@media (max-width: 1200px) { .zmd-todo-row { grid-template-columns: repeat(3, minmax(0, 1fr)); } .zmd-todo-item:nth-child(4) { border-left: 0; } .zmd-todo-item:nth-child(n+4) { border-top: 1px solid var(--zmc-line, #e6e3dc); } .zmd-row { grid-template-columns: 1fr; } }
</style>
