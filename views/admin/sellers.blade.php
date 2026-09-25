@include('_tabs')

@php
$mk_tabs = ['' => lang('commerce.mk_all'), 'pending' => lang('commerce.mk_st_pending'), 'approved' => lang('commerce.mk_st_approved'), 'suspended' => lang('commerce.mk_st_suspended'), 'rejected' => lang('commerce.mk_st_rejected')];
$mk_base = getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'sellers');
$mk_default_text = rtrim(rtrim(number_format((float)$mk_default_rate, 2, '.', ''), '0'), '.');
@endphp

<style>
.mk-tabs { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 14px; }
.mk-tabs a { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border: 1px solid var(--zmc-line-strong, #d6d2c8); border-radius: 5px; font-size: 13px; color: var(--zmc-ink, #232a3b) !important; text-decoration: none !important; background: var(--zmc-surface, #fff); }
.mk-tabs a.is-on { background: var(--zmc-brand, #26345c); border-color: var(--zmc-brand, #26345c); color: var(--zmc-on-brand, #fff8e6) !important; }
.mk-tabs a.is-hot b { color: var(--zmc-bad, #b33a2e); }
.mk-tabs a.is-on b { color: inherit; }
.mk-list { display: flex; flex-direction: column; gap: 12px; }
.mk-card { padding: 16px 18px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); }
.mk-card-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin-bottom: 10px; }
.mk-card-head b { font-size: 15px; }
.mk-sub { font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); }
.mk-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700; background: var(--zmc-side, #efede8); }
.mk-badge.is-pending { background: var(--zmc-warn-soft, #fbefd6); color: var(--zmc-warn, #a3690c); }
.mk-badge.is-approved { background: var(--zmc-ok-soft, #e7f2e8); color: var(--zmc-ok, #3f8a54); }
.mk-badge.is-suspended, .mk-badge.is-rejected { background: var(--zmc-bad-soft, #fbe6e2); color: var(--zmc-bad, #b33a2e); }
.mk-info { display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 6px 16px; margin: 0; font-size: 13px; }
.mk-info div { display: flex; gap: 8px; min-width: 0; }
.mk-info dt { flex: none; width: 92px; color: var(--zmc-sub, #7a7f8c); }
.mk-info dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
.mk-intro { margin: 10px 0 0; padding: 10px 12px; border-radius: 5px; background: var(--zmc-bg, #f7f6f3); font-size: 13px; white-space: pre-line; }
.mk-acts { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin-top: 12px; padding-top: 12px; border-top: 1px dashed var(--zmc-line, #e6e3dc); }
.mk-acts form { display: inline-flex; gap: 6px; align-items: center; margin: 0; }
.mk-acts input[type=text], .mk-acts input[type=number] { width: auto; padding: 5px 8px !important; font-size: 13px !important; }
.mk-empty { padding: 40px 20px; text-align: center; color: var(--zmc-sub, #7a7f8c); }
</style>

<div class="rsva">
	<nav class="mk-tabs">
		@foreach ($mk_tabs as $mk_key => $mk_label)
		<a href="{{ $mk_base }}{{ $mk_key !== '' ? '&f_status=' . $mk_key : '' }}" class="{{ $mk_status === $mk_key ? 'is-on' : '' }} {{ $mk_key === 'pending' && ($mk_counts['pending'] ?? 0) > 0 ? 'is-hot' : '' }}">{{ $mk_label }} <b>{{ number_format($mk_counts[$mk_key] ?? 0) }}</b></a>
		@endforeach
		<form method="get" action="{{ getUrl('') }}" style="margin:0 0 0 auto">
			<input type="hidden" name="act" value="dispCommerceConsole" /><input type="hidden" name="p" value="sellers" />
			@if ($mk_status !== '')<input type="hidden" name="f_status" value="{{ $mk_status }}" />@endif
			<input type="search" name="q" value="{{ $mk_q }}" placeholder="{{ lang('commerce.mk_search_ph') }}" />
		</form>
	</nav>

	@if (empty($mk_sellers))
	<div class="rsva-panel"><p class="mk-empty">{{ lang('commerce.mk_empty_sellers') }}</p></div>
	@else
	<div class="mk-list">
		@foreach ($mk_sellers as $s)
		<div class="mk-card">
			<div class="mk-card-head">
				<b>{{ $s->shop_name }}</b>
				<span class="mk-badge is-{{ $s->status }}">{{ lang('commerce.mk_st_' . $s->status) }}</span>
				<span class="mk-sub">{{ $s->nick_name ?: '#' . $s->member_srl }}@if ($s->user_id) ({{ $s->user_id }})@endif · {{ lang('commerce.mk_applied_at') }} {{ $s->regdate_text }}</span>
			</div>
			<dl class="mk-info">
				<div><dt>{{ lang('commerce.mk_f_biz_name') }}</dt><dd>{{ $s->biz_name }}</dd></div>
				<div><dt>{{ lang('commerce.mk_f_ceo_name') }}</dt><dd>{{ $s->ceo_name }}</dd></div>
				<div><dt>{{ lang('commerce.mk_f_biz_no') }}</dt><dd>{{ $s->biz_no }}</dd></div>
				<div><dt>{{ lang('commerce.mk_f_mailorder_no') }}</dt><dd>{{ $s->mailorder_no }}</dd></div>
				<div><dt>{{ lang('commerce.mk_f_tel') }}</dt><dd>{{ $s->tel }}</dd></div>
				<div><dt>{{ lang('commerce.mk_f_email') }}</dt><dd>{{ $s->email }}</dd></div>
				<div><dt>{{ lang('commerce.mk_f_biz_address') }}</dt><dd>{{ $s->biz_address }}</dd></div>
				<div><dt>{{ lang('commerce.mk_f_bank') }}</dt><dd>{{ $s->bank_name }} {{ $s->bank_account }} ({{ $s->bank_holder }})</dd></div>
				<div><dt>{{ lang('commerce.mk_f_ship_fee') }}</dt><dd>{{ $s->ship_fee_text }}@if ($s->free_over_text !== '') · {{ sprintf(lang('commerce.mk_free_over'), $s->free_over_text) }}@endif</dd></div>
				<div><dt>{{ lang('commerce.mk_f_commission') }}</dt><dd>{{ $s->effective_rate }}%@if ($s->rate_text === '') <span class="mk-sub">({{ lang('commerce.mk_rate_default') }})</span>@endif</dd></div>
			</dl>
			@if (trim((string)$s->intro) !== '')<p class="mk-intro">{{ $s->intro }}</p>@endif
			@if ($s->status === 'rejected' && trim((string)$s->reject_reason) !== '')<p class="mk-sub" style="margin:8px 0 0">{{ lang('commerce.mk_reject_reason') }}: {{ $s->reject_reason }}</p>@endif
			@if ($s->carry > 0)<p class="mk-sub" style="margin:0 0 6px;color:var(--zmc-bad, #b33a2e)">{{ lang('commerce.sc_carry_balance') }} {{ $s->carry_text }}</p>@endif
			<div class="mk-acts">
				@if ($s->status !== 'approved')
				<form action="{{ getUrl('') }}" method="post" onsubmit="return confirm('{{ lang('commerce.mk_ask_approve') }}')">
					<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procCommerceAdminSellerStatus" />
					<input type="hidden" name="seller_srl" value="{{ $s->seller_srl }}" /><input type="hidden" name="status" value="approved" />
					<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.mk_do_approve') }}</button>
				</form>
				@endif
				@if ($s->status === 'pending')
				<form action="{{ getUrl('') }}" method="post">
					<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procCommerceAdminSellerStatus" />
					<input type="hidden" name="seller_srl" value="{{ $s->seller_srl }}" /><input type="hidden" name="status" value="rejected" />
					<input type="text" name="reason" maxlength="250" placeholder="{{ lang('commerce.mk_reject_reason') }}" />
					<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.mk_do_reject') }}</button>
				</form>
				@endif
				@if ($s->status === 'approved')
				<form action="{{ getUrl('') }}" method="post" onsubmit="return confirm({{ json_encode($s->carry > 0 ? sprintf(lang('commerce.sc_warn_carry_status'), $s->carry_text) : lang('commerce.mk_ask_suspend')) }})">
					<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procCommerceAdminSellerStatus" />
					<input type="hidden" name="seller_srl" value="{{ $s->seller_srl }}" /><input type="hidden" name="status" value="suspended" />@if ($s->carry > 0)<input type="hidden" name="carry_ok" value="Y" />@endif
					<input type="text" name="reason" maxlength="250" placeholder="{{ lang('commerce.mk_suspend_reason') }}" />
					<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.mk_do_suspend') }}</button>
				</form>
				<a class="rsva-btn rsva-btn-sm" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'items', 'f_seller', $s->seller_srl) }}">{{ lang('commerce.mk_see_items') }}</a>
				<a class="rsva-btn rsva-btn-sm" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'settlements', 'f_seller', $s->seller_srl) }}">{{ lang('commerce.admin_menu_settlements') }}</a>
				@endif
				<form action="{{ getUrl('') }}" method="post" style="margin-left:auto">
					<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procCommerceAdminSellerCommission" />
					<input type="hidden" name="seller_srl" value="{{ $s->seller_srl }}" />
					<label class="mk-sub" style="margin:0">{{ lang('commerce.mk_f_commission') }}</label>
					<input type="number" name="commission_rate" min="0" max="100" step="0.01" value="{{ $s->rate_text }}" placeholder="{{ $mk_default_text }}" style="width:90px" />
					<span class="mk-sub">%</span>
					<button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.mk_save') }}</button>
				</form>
			</div>
		</div>
		@endforeach
	</div>
	@include('_pagenav', ['pn' => $page_navigation])
	@endif
	<p class="mk-sub" style="margin-top:14px">{{ sprintf(lang('commerce.mk_rate_hint'), $mk_default_text) }}</p>
</div>
