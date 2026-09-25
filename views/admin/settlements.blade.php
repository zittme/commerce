@include('_tabs')

@php
$mk_base = getUrl('', 'module', '', 'mid', '', 'act', $zmc_entry ?? 'dispCommerceConsole', 'p', 'settlements');
$mk_csv_list = getUrl('', 'module', 'commerce', 'mid', '', 'act', 'dispCommerceAdminExportSettlement', 'f_seller', $mk_f_seller ?: '', 'f_status', $mk_f_status ?: '');
@endphp

<style>
.mk-sub { font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); }
.mk-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700; }
.mk-badge.is-ready { background: var(--zmc-warn-soft, #fbefd6); color: var(--zmc-warn, #a3690c); }
.mk-badge.is-paid { background: var(--zmc-ok-soft, #e7f2e8); color: var(--zmc-ok, #3f8a54); }
.mk-num { text-align: right; white-space: nowrap; font-variant-numeric: tabular-nums; }
.mk-strong { font-weight: 700; }
.mk-acts { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
.mk-acts form { display: inline-flex; gap: 6px; margin: 0; }
.mk-acts a { text-decoration: none !important; }
.mk-empty { padding: 32px 20px; text-align: center; color: var(--zmc-sub, #7a7f8c); }
.mk-note { margin: 0 0 14px; font-size: 13px; line-height: 1.6; color: var(--zmc-sub, #7a7f8c); }
</style>

<div class="rsva">
	@if (count($mk_carries ?? []))
	<div class="rsva-panel">
		<h3>{{ lang('commerce.sc_carry_title') }}</h3>
		<p class="mk-note">{{ lang('commerce.sc_carry_desc') }}</p>
		<table class="rsva-table">
			<tbody>
				@foreach ($mk_carries as $mk_c)
				<tr><td>{{ $mk_c->shop_name }}</td><td class="mk-num mk-strong">-{{ $mk_c->amount_text }}</td></tr>
				@endforeach
			</tbody>
		</table>
	</div>
	@endif
	@if (!$mk_is_seller)
	<div class="rsva-panel">
		<h3>{{ lang('commerce.mk_make_title') }}</h3>
		<p class="mk-note">{{ lang('commerce.mk_make_desc') }}</p>
		<form method="get" action="{{ getUrl('') }}" class="rsva-inline">
			<input type="hidden" name="act" value="{{ $zmc_entry ?? 'dispCommerceConsole' }}" /><input type="hidden" name="p" value="settlements" /><input type="hidden" name="preview" value="Y" />
			<div><label>{{ lang('commerce.mk_from') }}</label><input type="date" name="from" value="{{ $mk_from }}" /></div>
			<div><label>{{ lang('commerce.mk_to') }}</label><input type="date" name="to" value="{{ $mk_to }}" /></div>
			<div><label>{{ lang('commerce.mk_col_seller') }}</label>
				<select name="f_seller">
					<option value="">{{ lang('commerce.mk_all_sellers') }}</option>
					@foreach ($mk_seller_names as $mk_sid => $mk_sname)
					<option value="{{ $mk_sid }}" @if ((int)$mk_f_seller === (int)$mk_sid) selected @endif>{{ $mk_sname }}</option>
					@endforeach
				</select>
			</div>
			<div><button type="submit" class="rsva-btn">{{ lang('commerce.mk_do_preview') }}</button></div>
		</form>

		@if ($mk_preview_on)
		@if (empty($mk_preview))
		<p class="mk-empty">{{ lang('commerce.mk_msg_nothing') }}</p>
		@else
		<table class="rsva-table" style="margin-top:14px">
			<thead><tr><th>{{ lang('commerce.mk_col_seller') }}</th><th class="mk-num">{{ lang('commerce.mk_col_orders') }}</th><th class="mk-num">{{ lang('commerce.mk_col_sales') }}</th><th class="mk-num">{{ lang('commerce.mk_col_delivery') }}</th><th class="mk-num">{{ lang('commerce.mk_col_refund') }}</th><th class="mk-num">{{ lang('commerce.mk_col_commission') }}</th><th class="mk-num">{{ lang('commerce.mk_col_settle') }}</th></tr></thead>
			<tbody>
			@foreach ($mk_preview as $pv)
			<tr><td>{{ $pv->shop_name }}</td><td class="mk-num">{{ number_format($pv->order_count) }}</td><td class="mk-num">{{ $pv->item_total_text }}</td><td class="mk-num">{{ $pv->delivery_total_text }}</td><td class="mk-num">-{{ $pv->refund_total_text }}</td><td class="mk-num">-{{ $pv->commission_total_text }}</td><td class="mk-num mk-strong">{{ $pv->settle_amount_text }}@if ((int)($pv->carry_in ?? 0) > 0)<span class="mk-sub" style="display:block">{{ lang('commerce.sc_carry_in_line') }} -{{ $pv->carry_in_text }}</span>@endif @if ((int)($pv->carry_out ?? 0) > 0)<span class="mk-sub" style="display:block">{{ lang('commerce.sc_carry_out_line') }} {{ $pv->carry_out_text }}</span>@endif</td></tr>
			@endforeach
			</tbody>
		</table>
		<form action="{{ getUrl('') }}" method="post" style="margin-top:12px" onsubmit="return confirm('{{ lang('commerce.mk_ask_create') }}')">
			<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procCommerceAdminCreateSettlement" />
			<input type="hidden" name="from" value="{{ $mk_from }}" /><input type="hidden" name="to" value="{{ $mk_to }}" /><input type="hidden" name="seller_srl" value="{{ $mk_f_seller }}" />
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.mk_do_create') }}</button>
		</form>
		@endif
		@endif
	</div>
	@else
	<p class="mk-note">{{ lang('commerce.mk_seller_settle_desc') }}</p>
	@endif

	@if ($mk_detail)
	<div class="rsva-panel">
		<h3>{{ sprintf(lang('commerce.mk_detail_title'), $mk_detail->settlement_srl) }}</h3>
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.mk_col_order') }}</th><th>{{ lang('commerce.mk_col_delivered') }}</th><th class="mk-num">{{ lang('commerce.mk_col_sales') }}</th><th class="mk-num">{{ lang('commerce.mk_col_delivery') }}</th><th class="mk-num">{{ lang('commerce.mk_col_refund') }}</th><th class="mk-num">{{ lang('commerce.mk_col_commission') }}</th><th class="mk-num">{{ lang('commerce.mk_col_settle') }}</th></tr></thead>
			<tbody>
			@foreach ($mk_detail_lines as $ln)
			<tr><td>{{ $ln->order_code }}</td><td>{{ $ln->delivered_text }}</td><td class="mk-num">{{ $ln->item_total_text }}</td><td class="mk-num">{{ $ln->delivery_text }}</td><td class="mk-num">-{{ $ln->refund_text }}</td><td class="mk-num">-{{ $ln->commission_text }}</td><td class="mk-num mk-strong">{{ $ln->settle_text }}</td></tr>
			@endforeach
			</tbody>
		</table>
		<p style="margin:12px 0 0"><a class="rsva-btn rsva-btn-sm" data-zmc-keep href="{{ getUrl('', 'module', 'commerce', 'mid', '', 'act', 'dispCommerceAdminExportSettlement', 'settlement_srl', $mk_detail->settlement_srl) }}">{{ lang('commerce.mk_csv') }}</a> <a class="rsva-btn rsva-btn-sm" href="{{ getUrl('settlement_srl', '') }}">{{ lang('commerce.mk_close') }}</a></p>
	</div>
	@endif

	<div class="rsva-filter">
		<form method="get" action="{{ getUrl('') }}" class="rsva-inline" style="margin:0">
			<input type="hidden" name="act" value="{{ $zmc_entry ?? 'dispCommerceConsole' }}" /><input type="hidden" name="p" value="settlements" />
			@if (!$mk_is_seller)
			<select name="f_seller" onchange="this.form.submit()">
				<option value="">{{ lang('commerce.mk_all_sellers') }}</option>
				@foreach ($mk_seller_names as $mk_sid => $mk_sname)
				<option value="{{ $mk_sid }}" @if ((int)$mk_f_seller === (int)$mk_sid) selected @endif>{{ $mk_sname }}</option>
				@endforeach
			</select>
			@endif
			<select name="f_status" onchange="this.form.submit()">
				<option value="">{{ lang('commerce.mk_all') }}</option>
				<option value="ready" @if ($mk_f_status === 'ready') selected @endif>{{ lang('commerce.mk_st_ready') }}</option>
				<option value="paid" @if ($mk_f_status === 'paid') selected @endif>{{ lang('commerce.mk_st_paid') }}</option>
			</select>
		</form>
		<a class="rsva-btn rsva-btn-sm" data-zmc-keep href="{{ $mk_csv_list }}" style="margin-left:auto">{{ lang('commerce.mk_csv') }}</a>
	</div>

	@if (empty($mk_settlements))
	<div class="rsva-panel"><p class="mk-empty">{{ lang('commerce.mk_empty_settlements') }}</p></div>
	@else
	<table class="rsva-table">
		<thead><tr>
			<th>{{ lang('commerce.mk_col_no') }}</th>
			@if (!$mk_is_seller)<th>{{ lang('commerce.mk_col_seller') }}</th>@endif
			<th>{{ lang('commerce.mk_col_period') }}</th>
			<th class="mk-num">{{ lang('commerce.mk_col_orders') }}</th>
			<th class="mk-num">{{ lang('commerce.mk_col_sales') }}</th>
			<th class="mk-num">{{ lang('commerce.mk_col_commission') }}</th>
			<th class="mk-num">{{ lang('commerce.mk_col_settle') }}</th>
			<th>{{ lang('commerce.mk_col_status') }}</th>
			<th></th>
		</tr></thead>
		<tbody>
		@foreach ($mk_settlements as $st)
		<tr>
			<td>{{ $st->settlement_srl }}</td>
			@if (!$mk_is_seller)<td>{{ $st->shop_name }}<span class="mk-sub" style="display:block">{{ $st->bank_name }} {{ $st->bank_account }} {{ $st->bank_holder }}</span></td>@endif
			<td>{{ $st->period_text }}</td>
			<td class="mk-num">{{ number_format((int)$st->order_count) }}</td>
			<td class="mk-num">{{ $st->item_total_text }}<span class="mk-sub" style="display:block">+{{ $st->delivery_total_text }} / -{{ $st->refund_total_text }}</span></td>
			<td class="mk-num">-{{ $st->commission_total_text }}</td>
			<td class="mk-num mk-strong">{{ $st->settle_amount_text }}@if ((int)($st->carry_in ?? 0) > 0)<span class="mk-sub" style="display:block">{{ lang('commerce.sc_carry_in_line') }} -{{ $st->carry_in_text }}</span>@endif @if ((int)($st->carry_out ?? 0) > 0)<span class="mk-sub" style="display:block">{{ lang('commerce.sc_carry_out_line') }} {{ $st->carry_out_text }}</span>@endif</td>
			<td><span class="mk-badge is-{{ $st->status }}">{{ lang('commerce.mk_st_' . $st->status) }}</span>@if ($st->paid_text !== '')<span class="mk-sub" style="display:block">{{ $st->paid_text }}</span>@endif @if (trim((string)$st->memo) !== '')<span class="mk-sub" style="display:block">{{ $st->memo }}</span>@endif</td>
			<td>
				<div class="mk-acts">
					<a class="rsva-btn rsva-btn-sm" href="{{ getUrl('settlement_srl', $st->settlement_srl) }}">{{ lang('commerce.mk_detail') }}</a>
					@if (!$mk_is_seller && $st->status === 'ready')
					<form action="{{ getUrl('') }}" method="post" onsubmit="return confirm('{{ lang('commerce.mk_ask_paid') }}')">
						<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procCommerceAdminSettlementPaid" />
						<input type="hidden" name="settlement_srl" value="{{ $st->settlement_srl }}" />
						<input type="text" name="memo" maxlength="250" placeholder="{{ lang('commerce.mk_paid_memo') }}" style="width:130px" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.mk_do_paid') }}</button>
					</form>
					<form action="{{ getUrl('') }}" method="post" onsubmit="return confirm('{{ lang('commerce.mk_ask_cancel') }}')">
						<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procCommerceAdminCancelSettlement" />
						<input type="hidden" name="settlement_srl" value="{{ $st->settlement_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.mk_do_cancel') }}</button>
					</form>
					@endif
				</div>
			</td>
		</tr>
		@endforeach
		</tbody>
	</table>
	@include('_pagenav', ['pn' => $page_navigation])
	@endif
</div>
