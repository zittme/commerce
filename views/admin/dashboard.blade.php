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

	<div class="zmd-cards zmd-cards-sales">
		<div class="zmd-card">
			<span>{{ lang('commerce.admin_dashboard_1') }}</span>
			<strong>{{ shop_money_base($zs->today->sales) }}</strong>
			<small>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->today->orders)) }} · {{ lang('commerce.adm_yesterday') }} {{ shop_money_base($zs->yesterday->sales) }}</small>
		</div>
		<div class="zmd-card">
			<span>{{ lang('commerce.admin_dashboard_2') }}</span>
			<strong>{{ shop_money_base($zs->month->sales) }}</strong>
			<small>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->month->orders)) }} · {{ lang('commerce.adm_last_month') }} {{ shop_money_base($zs->last_month->sales) }}</small>
		</div>
	</div>
	<div class="zmd-cards zmd-cards-todo">
		<a class="zmd-card zmd-link" href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrders', 'f_status', 'pending') }}">
			<span>{{ lang('commerce.admin_dashboard_3') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->pending)) }}</strong>
			<small>{{ lang('commerce.admin_dashboard_4') }}</small>
		</a>
		<a class="zmd-card zmd-link {{ $zs->counts->to_ship > 0 ? 'is-alert' : '' }}" href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrders', 'f_ship', 'to_ship') }}">
			<span>{{ lang('commerce.admin_dashboard_5') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->to_ship)) }}</strong>
			<small>{{ lang('commerce.admin_dashboard_6') }}</small>
		</a>
		<a class="zmd-card zmd-link" href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminOrders', 'f_ship', 'shipping') }}">
			<span>{{ lang('commerce.admin_dashboard_7') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->shipping)) }}</strong>
			<small>{{ lang('commerce.admin_dashboard_8') }}</small>
		</a>
		<a class="zmd-card zmd-link {{ $zs->counts->claims > 0 ? 'is-alert' : '' }}" href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminClaims', 'f_status', 'requested') }}">
			<span>{{ lang('commerce.admin_dashboard_9') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->claims)) }}</strong>
			<small>{{ lang('commerce.admin_dashboard_10') }}</small>
		</a>
		<a class="zmd-card zmd-link {{ ($zs->counts->unanswered ?? 0) > 0 ? 'is-alert' : '' }}" href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminQna', 'f_unanswered', 'Y') }}">
			<span>{{ lang('commerce.adm_dash_unanswered') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->unanswered ?? 0)) }}</strong>
			<small>{{ lang('commerce.adm_dash_unanswered_hint') }}</small>
		</a>
		<a class="zmd-card zmd-link {{ count($low_stock_rows) > 0 ? 'is-alert' : '' }}" href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock') }}">
			<span>{{ lang('commerce.adm_low_stock_card') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format(count($low_stock_rows))) }}</strong>
			<small>{{ sprintf(lang('commerce.adm_low_stock_hint'), number_format($low_stock_default)) }}</small>
		</a>
	</div>

	{{-- 재고 부족은 바로 손봐야 하는 일이라 콘솔 첫 화면에 목록까지 펼쳐 둔다 --}}
	@if (count($low_stock_rows))
	<div class="rsva-panel">
		<h3>{{ sprintf(lang('commerce.adm_low_stock_title'), count($low_stock_rows)) }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock') }}" class="zmd-more">{{ lang('commerce.admin_menu_stock') }}</a></h3>
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stock_4') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_6') }}</th><th style="text-align:right">{{ lang('commerce.adm_low_stock_limit') }}</th><th>{{ lang('commerce.admin_stock_7') }}</th><th></th></tr></thead>
			<tbody>
				@foreach (array_slice($low_stock_rows, 0, 10) as $dl)
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
		@foreach (array_slice($low_stock_rows, 0, 10) as $dl)
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

	<div class="zmd-split">
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_dashboard_11') }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStats') }}" class="zmd-more">{{ lang('commerce.admin_dashboard_12') }}</a></h3>
			@if (empty($zs->series))
			<p class="rsva-empty">{{ lang('commerce.admin_dashboard_13') }}</p>
			@else
			<div class="zmd-chart">
				@foreach ($zs->series as $zs_row)
				<div class="zmd-bar" title="{{ $zs_row->label }} · {{ shop_money_base($zs_row->sales) }} · {{ sprintf(lang('commerce.st_unit_count'), $zs_row->orders) }}">
					<div class="zmd-bar-fill" style="height:{{ $zs_max > 0 ? max(2, (int)round($zs_row->sales / $zs_max * 100)) : 2 }}%"></div>
				</div>
				@endforeach
			</div>
			@endif
		</div>
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_dashboard_14') }} <small style="font-weight:500;color:#8b95a1">{{ lang('commerce.admin_dashboard_15') }}</small></h3>
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
		</div>
	</div>

	<div class="zmd-split" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr)">
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
	</div>

	{{-- 개설을 마친 뒤에는 체크리스트가 상단 면적을 차지하지 않게 접어 둔다 --}}
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
</div>
@else
{{-- 코어 관리자에서는 콘솔 안내만 보여준다. 운영은 전용 콘솔로 일원화 --}}
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
.zmd-card { padding: 16px 18px; border: 1px solid var(--zm-border, #e5e8eb); border-radius: 12px; background: var(--zm-surface, #fff); text-decoration: none; color: inherit; }
.zmd-card span { display: block; font-size: 12.5px; color: var(--zm-text-sub, #6b7684); }
.zmd-card strong { display: block; margin-top: 6px; font-size: 21px; }
.zmd-card small { display: block; margin-top: 4px; font-size: 12px; color: var(--zm-text-sub, #8b95a1); }
.zmd-link:hover { border-color: var(--zm-point, #2677e3); }
.zmd-card.is-alert strong { color: var(--zm-point, #2677e3); }
.zmd-split { display: grid; grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr); gap: 12px; margin: 0 0 14px; }
.zmd-split .rsva-panel { margin: 0; }
.zmd-more { float: right; font-size: 12.5px; font-weight: 500; color: var(--zm-point, #2677e3); text-decoration: none; }
.zmd-chart { display: flex; align-items: flex-end; gap: 3px; height: 150px; padding-top: 6px; }
.zmd-bar { flex: 1 1 0; min-width: 4px; height: 100%; display: flex; align-items: flex-end; }
.zmd-bar-fill { width: 100%; border-radius: 3px 3px 0 0; background: var(--zm-point, #2677e3); opacity: .8; }
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
.zmd-mini a:hover { color: var(--zm-point, #2677e3); }
/* 카드가 한 줄에 안 들어가면 3칸씩 두 줄로 나뉜다. 마지막 하나만 떨어지지 않게 한다 */
@media (max-width: 1400px) { .zmd-cards-todo { grid-template-columns: repeat(3, minmax(0, 1fr)); } }
@media (max-width: 1100px) { .zmd-cards-todo { grid-template-columns: repeat(2, minmax(0, 1fr)); } .zmd-split { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 640px) { .zmd-cards, .zmd-cards-sales, .zmd-cards-todo { grid-template-columns: minmax(0, 1fr); } }
</style>
