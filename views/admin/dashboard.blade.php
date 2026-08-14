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

	<div class="zmd-cards">
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
		<a class="zmd-card zmd-link" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrders', 'f_status', 'pending') }}">
			<span>{{ lang('commerce.admin_dashboard_3') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->pending)) }}</strong>
			<small>{{ lang('commerce.admin_dashboard_4') }}</small>
		</a>
		<a class="zmd-card zmd-link {{ $zs->counts->to_ship > 0 ? 'is-alert' : '' }}" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrders', 'f_status', 'paid') }}">
			<span>{{ lang('commerce.admin_dashboard_5') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->to_ship)) }}</strong>
			<small>{{ lang('commerce.admin_dashboard_6') }}</small>
		</a>
		<a class="zmd-card zmd-link" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrders') }}">
			<span>{{ lang('commerce.admin_dashboard_7') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->shipping)) }}</strong>
			<small>{{ lang('commerce.admin_dashboard_8') }}</small>
		</a>
		<a class="zmd-card zmd-link {{ $zs->counts->claims > 0 ? 'is-alert' : '' }}" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminClaims') }}">
			<span>{{ lang('commerce.admin_dashboard_9') }}</span>
			<strong>{{ sprintf(lang('commerce.st_unit_count'), number_format($zs->counts->claims)) }}</strong>
			<small>{{ lang('commerce.admin_dashboard_10') }}</small>
		</a>
	</div>

	<div class="zmd-split">
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_dashboard_11') }} <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminStats') }}" class="zmd-more">{{ lang('commerce.admin_dashboard_12') }}</a></h3>
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
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $zs_item->item_srl) }}">{{ $zs_item->item_name }}</a>
					<span>{{ sprintf(lang('commerce.st_unit_ea'), number_format($zs_item->qty)) }} · {{ shop_money_base($zs_item->sales) }}</span>
				</li>
				@endforeach
			</ol>
			@endif
		</div>
	</div>

	<div class="rsva-panel">
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
.zmd-cards { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0 0 14px; }
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
@media (max-width: 1100px) { .zmd-cards { grid-template-columns: repeat(2, minmax(0, 1fr)); } .zmd-split { grid-template-columns: minmax(0, 1fr); } }
@media (max-width: 640px) { .zmd-cards { grid-template-columns: minmax(0, 1fr); } }
</style>
