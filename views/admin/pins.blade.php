@include('_tabs')

@php
$pn_base = getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'pins');
$pn_join = strpos($pn_base, '?') === false ? '?' : '&amp;';
$pn_names = [];
foreach ($pin_items as $pi) { $pn_names[(int)$pi->item_srl] = $pi->item_name; }
$pn_st_label = ['stock' => lang('commerce.pin_st_stock'), 'assigned' => lang('commerce.pin_st_assigned'), 'void' => lang('commerce.pin_st_void')];
@endphp

<style>
.pn-sum { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 12px; margin: 0 0 16px; }
.pn-card { display: block; padding: 14px 16px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); text-decoration: none !important; color: inherit !important; }
.pn-card.is-on { border-color: var(--zmc-brand, #26345c); box-shadow: inset 0 0 0 1px var(--zmc-brand, #26345c); }
.pn-card b { display: block; font-size: 14px; margin-bottom: 8px; color: var(--zmc-ink, #232a3b); }
.pn-card dl { display: grid; grid-template-columns: repeat(4, 1fr); gap: 4px; margin: 0; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
.pn-card dd { margin: 0; font-size: 16px; font-weight: 700; color: var(--zmc-ink, #232a3b); font-variant-numeric: tabular-nums; }
.pn-card dd.is-low { color: #b3261e; }
.pn-wrap { display: grid; grid-template-columns: minmax(0, 1fr) 360px; gap: 20px; align-items: start; }
.pn-bar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; margin: 0 0 10px; }
.pn-bar select, .pn-bar input { height: 34px !important; padding: 0 10px !important; font-size: 13px !important; line-height: normal !important; }
.pn-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.pn-table th { padding: 9px 10px; border-bottom: 1px solid var(--zmc-line-strong, #d6d2c8); background: var(--zmc-side, #efede8); font-size: 12px; font-weight: 600; color: var(--zmc-sub, #7a7f8c); text-align: left; white-space: nowrap; }
.pn-table td { padding: 9px 10px; border-bottom: 1px solid var(--zmc-line, #e6e3dc); vertical-align: middle; }
.pn-pin { font-family: ui-monospace, Consolas, monospace; letter-spacing: .04em; }
.pn-st { display: inline-block; padding: 2px 7px; border-radius: 4px; font-size: 11.5px; font-weight: 700; background: var(--zmc-side, #efede8); }
.pn-st.s-stock { background: #e7f4ec; color: #1d7a45; }
.pn-st.s-assigned { background: #eef1f8; color: #26345c; }
.pn-st.s-void { background: #fdecea; color: #b3261e; }
.pn-sub { display: block; font-size: 11.5px; color: var(--zmc-sub, #7a7f8c); }
.pn-form { position: sticky; top: 16px; display: flex; flex-direction: column; gap: 10px; }
.pn-form h3 { margin: 0; font-size: 14.5px; }
.pn-form textarea { width: 100%; min-height: 200px; box-sizing: border-box; font-family: ui-monospace, Consolas, monospace; font-size: 13px; }
.pn-form select, .pn-form input[type=text] { width: 100%; box-sizing: border-box; }
.pn-hint { margin: 0; font-size: 12px; line-height: 1.6; color: var(--zmc-sub, #7a7f8c); }
.pn-empty { padding: 36px 20px; text-align: center; color: var(--zmc-sub, #7a7f8c); }
.pn-pager { display: flex; justify-content: center; gap: 4px; margin: 12px 0; }
.pn-pager a, .pn-pager span { min-width: 30px; padding: 5px 8px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: 4px; text-align: center; font-size: 12.5px; text-decoration: none !important; color: var(--zmc-ink, #232a3b) !important; background: var(--zmc-surface, #fff); }
.pn-pager span { background: var(--zmc-brand, #26345c); color: #fff !important; }
@media (max-width: 1100px) { .pn-wrap { grid-template-columns: 1fr; } .pn-form { position: static; } }
</style>

<div class="rsva">
	@if (empty($pin_items))
	<div class="rsva-panel"><p class="pn-empty">{{ lang('commerce.pin_no_items') }}</p></div>
	@else
	<div class="pn-sum">
		@foreach ($pin_items as $pi)
		@php $pc = $pin_counts[(int)$pi->item_srl] ?? ['stock' => 0, 'assigned' => 0, 'revealed' => 0, 'void' => 0]; @endphp
		<a class="pn-card {{ (int)$pin_filters->item === (int)$pi->item_srl ? 'is-on' : '' }}" href="{{ $pn_base }}{{ $pn_join }}f_item={{ $pi->item_srl }}">
			<b>{{ $pi->item_name }}</b>
			<dl>
				<div><dt>{{ lang('commerce.pin_st_stock') }}</dt><dd class="{{ $pc['stock'] < 10 ? 'is-low' : '' }}">{{ number_format($pc['stock']) }}</dd></div>
				<div><dt>{{ lang('commerce.pin_st_assigned') }}</dt><dd>{{ number_format($pc['assigned']) }}</dd></div>
				<div><dt>{{ lang('commerce.pin_st_revealed') }}</dt><dd>{{ number_format($pc['revealed']) }}</dd></div>
				<div><dt>{{ lang('commerce.pin_st_void') }}</dt><dd>{{ number_format($pc['void']) }}</dd></div>
			</dl>
		</a>
		@endforeach
	</div>

	<div class="pn-wrap">
		<div>
			<form class="pn-bar" method="get" action="{{ getUrl('') }}">
				<input type="hidden" name="act" value="dispCommerceConsole" /><input type="hidden" name="p" value="pins" />
				<select name="f_item">
					<option value="">{{ lang('commerce.pin_all_items') }}</option>
					@foreach ($pin_items as $pi)<option value="{{ $pi->item_srl }}" @if ((int)$pin_filters->item === (int)$pi->item_srl) selected @endif>{{ $pi->item_name }}</option>@endforeach
				</select>
				<select name="f_status">
					<option value="">{{ lang('commerce.pin_all_status') }}</option>
					@foreach (['stock', 'assigned', 'revealed', 'void'] as $pn_s)<option value="{{ $pn_s }}" @if ($pin_filters->status === $pn_s) selected @endif>{{ lang('commerce.pin_st_' . $pn_s) }}</option>@endforeach
				</select>
				<input type="search" name="f_q" value="{{ $pin_filters->q }}" placeholder="{{ lang('commerce.pin_search_ph') }}" />
				<button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.au_filter') }}</button>
				<span style="flex:1"></span>
				<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger" id="pnVoid" disabled>{{ lang('commerce.pin_void_selected') }}</button>
			</form>
			<div class="rsva-panel" style="padding:0;overflow-x:auto">
				@if (empty($pin_rows))
				<p class="pn-empty">{{ lang('commerce.pin_list_empty') }}</p>
				@else
				<table class="pn-table">
					<thead><tr><th style="width:30px"></th><th>PIN</th><th>{{ lang('commerce.ts_col_item') }}</th><th>{{ lang('commerce.ts_col_state') }}</th><th>{{ lang('commerce.pin_expire') }}</th><th>{{ lang('commerce.sh_col_order') }}</th><th>{{ lang('commerce.pin_col_dates') }}</th></tr></thead>
					<tbody>
					@foreach ($pin_rows as $pr)
					<tr>
						<td>@if ($pr->status === 'stock')<input type="checkbox" class="pn-chk" value="{{ $pr->pin_srl }}" aria-label="PIN {{ $pr->pin_tail }}" />@endif</td>
						<td class="pn-pin">****-{{ $pr->pin_tail }}@if ($pr->memo)<span class="pn-sub">{{ $pr->memo }}</span>@endif</td>
						<td>{{ $pn_names[(int)$pr->item_srl] ?? '#' . $pr->item_srl }}</td>
						<td><span class="pn-st s-{{ $pr->status }}">{{ $pn_st_label[$pr->status] ?? $pr->status }}</span>@if ($pr->revealed_date)<span class="pn-sub">{{ lang('commerce.pin_st_revealed') }}</span>@endif</td>
						<td class="pn-sub" style="font-size:12.5px">{{ $pr->expire_date ? zdate($pr->expire_date . '000000', 'Y.m.d') : '-' }}</td>
						<td>@if ($pr->order_code)<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $pr->order_srl) }}">{{ $pr->order_code }}</a><span class="pn-sub">{{ $pr->orderer_name }}</span>@else - @endif</td>
						<td class="pn-sub" style="font-size:12px">{{ lang('commerce.pin_col_reg') }} {{ zdate($pr->regdate, 'm.d') }}@if ($pr->assigned_date) · {{ lang('commerce.pin_st_assigned') }} {{ zdate($pr->assigned_date, 'm.d H:i') }}@endif @if ($pr->revealed_date) · {{ lang('commerce.pin_st_revealed') }} {{ zdate($pr->revealed_date, 'm.d H:i') }}@endif</td>
					</tr>
					@endforeach
					</tbody>
				</table>
				@endif
			</div>
			@if ($pin_pages > 1)
			<nav class="pn-pager">
				@for ($pn_i = max(1, $pin_page - 5); $pn_i <= min($pin_pages, $pin_page + 5); $pn_i++)
				@if ($pn_i === $pin_page)<span>{{ $pn_i }}</span>@else<a href="{{ $pn_base }}{{ $pn_join }}f_item={{ $pin_filters->item }}&amp;f_status={{ $pin_filters->status }}&amp;f_q={{ urlencode($pin_filters->q) }}&amp;page={{ $pn_i }}">{{ $pn_i }}</a>@endif
				@endfor
			</nav>
			@endif
		</div>

		<form class="rsva-panel pn-form" id="pnForm">
			<h3>{{ lang('commerce.pin_add') }}</h3>
			<select name="item_srl" required>
				@foreach ($pin_items as $pi)<option value="{{ $pi->item_srl }}" @if ((int)$pin_filters->item === (int)$pi->item_srl) selected @endif>{{ $pi->item_name }}</option>@endforeach
			</select>
			<textarea name="pins" id="pnText" placeholder="{{ lang('commerce.pin_add_ph') }}" spellcheck="false" autocomplete="off"></textarea>
			<div style="display:flex;gap:8px;align-items:center">
				<button type="button" class="rsva-btn rsva-btn-sm" id="pnFileBtn">{{ lang('commerce.pin_load_csv') }}</button>
				<input type="file" id="pnFile" accept=".csv,.txt,text/csv,text/plain" hidden />
				<span class="pn-hint" id="pnLines"></span>
			</div>
			<input type="text" name="memo" maxlength="120" placeholder="{{ lang('commerce.pin_memo_ph') }}" />
			<p class="pn-hint">{{ lang('commerce.pin_add_hint') }}</p>
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.pin_add_btn') }}</button>
		</form>
	</div>
	@endif
</div>

<script>
(function () {
	var form = document.getElementById('pnForm');
	if (!form) return;
	var text = document.getElementById('pnText'), lines = document.getElementById('pnLines');
	var T = {
		lines: {!! json_encode(lang('commerce.pin_lines')) !!},
		done: {!! json_encode(lang('commerce.pin_add_done')) !!},
		voidAsk: {!! json_encode(lang('commerce.pin_void_ask')) !!}
	};
	function count() { var n = text.value.split(/\r?\n/).filter(function (l) { return l.trim() !== ''; }).length; lines.textContent = n ? T.lines.replace('%d', n) : ''; }
	text.addEventListener('input', count);
	document.getElementById('pnFileBtn').addEventListener('click', function () { document.getElementById('pnFile').click(); });
	document.getElementById('pnFile').addEventListener('change', function () {
		var f = this.files && this.files[0]; if (!f) return;
		var r = new FileReader();
		r.onload = function () { text.value = (text.value ? text.value.replace(/\s*$/, '\n') : '') + String(r.result).replace(/^﻿/, ''); count(); };
		r.readAsText(f, 'utf-8'); this.value = '';
	});
	form.addEventListener('submit', function (e) {
		e.preventDefault();
		exec_json('commerce.procCommerceAdminAddPins', { item_srl: form.item_srl.value, pins: text.value, memo: form.memo.value }, function (res) {
			alert(T.done.replace('%1', res.added).replace('%2', res.dup).replace('%3', res.bad));
			text.value = ''; location.reload();
		});
	});
	var voidBtn = document.getElementById('pnVoid');
	function picked() { return [].map.call(document.querySelectorAll('.pn-chk:checked'), function (c) { return c.value; }); }
	document.querySelectorAll('.pn-chk').forEach(function (c) { c.addEventListener('change', function () { voidBtn.disabled = !picked().length; }); });
	voidBtn.addEventListener('click', function () {
		var srls = picked(); if (!srls.length || !confirm(T.voidAsk.replace('%d', srls.length))) return;
		exec_json('commerce.procCommerceAdminVoidPins', { pin_srls: JSON.stringify(srls) }, function () { location.reload(); });
	});
})();
</script>
