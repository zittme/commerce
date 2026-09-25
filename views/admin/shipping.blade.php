@include('_tabs')

@php
$sh_tabs = ['paid' => lang('commerce.sh_tab_paid'), 'preparing' => lang('commerce.sh_tab_preparing'), 'shipping' => lang('commerce.sh_tab_shipping'), 'delivered' => lang('commerce.sh_tab_delivered')];
$sh_carriers = \Zittme\Modules\Commerce\Models\Courier::names();
$sh_direct = lang('commerce.shop_ship_direct');
$sh_editable = in_array($ship_tab, ['paid', 'preparing', 'shipping'], true);
$sh_ask_json = json_encode(['confirm' => lang('commerce.sh_ask_confirm'), 'ship' => lang('commerce.sh_ask_ship'), 'reinvoice' => lang('commerce.sh_ask_reinvoice'), 'deliver' => lang('commerce.sh_ask_deliver')], JSON_UNESCAPED_UNICODE);
$sh_base = getUrl('', 'module', '', 'mid', '', 'act', $zmc_entry ?? 'dispCommerceConsole', 'p', 'shipping');
@endphp

<style>
.sh-tabs { display: flex; gap: 22px; margin: 0 0 14px; border-bottom: 1px solid var(--zmc-line, #e6e3dc); overflow-x: auto; overflow-y: hidden; scrollbar-width: none; }
.sh-tabs a { flex: none; margin-bottom: -1px; padding: 0 0 10px; border-bottom: 2px solid transparent; font-size: 14px; font-weight: 600; color: var(--zmc-sub, #7a7f8c) !important; text-decoration: none !important; }
.sh-tabs a b { margin-left: 6px; font-variant-numeric: tabular-nums; }
.sh-tabs a.is-hot b { display: inline-block; min-width: 18px; padding: 0 6px; border-radius: 9px; background: var(--zmc-mark, #e3a92f); color: #fff; font-size: 12px; line-height: 18px; text-align: center; }
.sh-tabs a.is-on { color: var(--zmc-ink, #232a3b) !important; border-color: var(--zmc-brand, #26345c); }
.sh-bar { position: sticky; top: 0; z-index: 6; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; padding: 10px 12px; margin: 0 0 10px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); }
.sh-bar .sh-sel { font-size: 13px; color: var(--zmc-sub, #7a7f8c); min-width: 64px; }
.sh-bar .sh-sel b { color: var(--zmc-ink, #232a3b); }
.sh-bar .sh-sep { width: 1px; height: 22px; background: var(--zmc-line, #e6e3dc); }
.sh-bar .sh-grow { flex: 1; }
.sh-bar select, .sh-bar input[type=search] { height: 34px !important; padding: 0 10px !important; line-height: normal !important; font: inherit; font-size: 13px !important; }
.sh-bar select { min-width: 140px; padding-right: 28px !important; }
.sh-bar input[type=search] { width: 200px; }
.sh-wrap { overflow-x: auto; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); }
.sh-table { width: 100%; min-width: 1120px; border-collapse: collapse; font-size: 13px; }
.sh-table th { position: sticky; top: 0; padding: 9px 10px; border-bottom: 1px solid var(--zmc-line-strong, #d6d2c8); background: var(--zmc-side, #efede8); font-size: 12px; font-weight: 600; color: var(--zmc-sub, #7a7f8c); text-align: left; white-space: nowrap; }
.sh-table td { padding: 9px 10px; border-bottom: 1px solid var(--zmc-line, #e6e3dc); vertical-align: top; }
.sh-table tr:last-child td { border-bottom: 0; }
.sh-table tr.is-checked td { background: var(--zmc-brand-soft, #fbf1d6); }
.sh-table tr.is-miss td { background: #fdecea; }
.sh-table .c-chk { width: 34px; text-align: center; }
.sh-table .c-chk input { width: 16px; height: 16px; accent-color: var(--zmc-brand, #26345c); }
.sh-code a { font-weight: 600; color: var(--zmc-ink, #232a3b) !important; font-variant-numeric: tabular-nums; }
.sh-sub { display: block; margin-top: 2px; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
.sh-item { display: flex; gap: 8px; min-width: 200px; max-width: 280px; }
.sh-item i { flex: none; width: 36px; height: 36px; border-radius: 4px; background: var(--zmc-side, #efede8) center / cover no-repeat; }
.sh-item b { display: block; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.sh-addr { min-width: 220px; max-width: 320px; line-height: 1.5; }
.sh-memo { display: inline-block; margin-top: 3px; padding: 1px 6px; border-radius: 3px; background: #fff4d6; color: #7a5a10; font-size: 12px; }
.sh-in { display: flex; gap: 6px; }
.sh-in select { width: 140px; height: 34px !important; padding: 0 28px 0 10px !important; line-height: normal !important; font: inherit; font-size: 13px !important; }
.sh-in input { width: 150px; height: 34px !important; padding: 0 10px !important; font-size: 13px; font-variant-numeric: tabular-nums; }
.sh-in input:disabled { background: var(--zmc-side, #efede8); }
.sh-done { font-size: 12.5px; }
.sh-empty { padding: 50px 20px; text-align: center; color: var(--zmc-sub, #7a7f8c); font-size: 14px; }
.sh-toast { position: fixed; left: 50%; bottom: 28px; z-index: 50; transform: translateX(-50%); padding: 11px 18px; border-radius: 6px; background: var(--zmc-ink, #232a3b); color: #fff; font-size: 13.5px; box-shadow: 0 8px 24px rgba(0, 0, 0, .2); }
.sh-hint { margin: 8px 2px 0; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
</style>

<div class="rsva">
	<nav class="sh-tabs">
		@foreach ($sh_tabs as $sh_key => $sh_label)
		<a href="{{ getUrl('', 'module', '', 'mid', '', 'act', $zmc_entry ?? 'dispCommerceConsole', 'p', 'shipping', 'st', $sh_key, 'f_seller', ($ship_seller ?? 0) > 0 && ($seller_mode ?? '') === 'operator' ? $ship_seller : '') }}" class="{{ $ship_tab === $sh_key ? 'is-on' : '' }} {{ in_array($sh_key, ['paid', 'preparing'], true) && $ship_counts[$sh_key] > 0 ? 'is-hot' : '' }}">{{ $sh_label }}<b>{{ number_format($ship_counts[$sh_key]) }}</b></a>
		@endforeach
	</nav>

	<div class="sh-bar">
		@if ($sh_editable)
		<label class="sh-sel"><input type="checkbox" id="shAll" aria-label="{{ lang('commerce.select_all') }}" /> <span id="shSel">0</span></label>
		<span class="sh-sep"></span>
		@if ($ship_tab !== 'shipping')
		<select id="shBulkCarrier" aria-label="{{ lang('commerce.sh_carrier') }}">
			@foreach ($sh_carriers as $sh_c)<option>{{ $sh_c }}</option>@endforeach
			<option value="__direct">{{ $sh_direct }}</option>
		</select>
		<button type="button" class="rsva-btn rsva-btn-sm" id="shApplyCarrier">{{ lang('commerce.sh_apply_carrier') }}</button>
		<span class="sh-sep"></span>
		@endif
		@if ($ship_tab === 'paid')
		<button type="button" class="rsva-btn" data-act="confirm">{{ lang('commerce.sh_act_confirm') }}</button>
		@endif
		@if ($ship_tab === 'paid' || $ship_tab === 'preparing')
		<button type="button" class="rsva-btn rsva-btn-primary" data-act="ship">{{ lang('commerce.sh_act_ship') }}</button>
		@endif
		@if ($ship_tab === 'shipping')
		<button type="button" class="rsva-btn" data-act="reinvoice">{{ lang('commerce.sh_act_reinvoice') }}</button>
		@if (empty($zmc_seller_center))<button type="button" class="rsva-btn rsva-btn-primary" data-act="deliver">{{ lang('commerce.sh_act_deliver') }}</button>@endif
		@endif
		@endif
		<span class="sh-grow"></span>
		<form method="get" action="{{ getUrl('') }}" style="margin:0">
			<input type="hidden" name="act" value="{{ $zmc_entry ?? 'dispCommerceConsole' }}" /><input type="hidden" name="p" value="shipping" /><input type="hidden" name="st" value="{{ $ship_tab }}" />
			@if (($seller_mode ?? '') === 'operator')
			<select name="f_seller" onchange="this.form.submit()" aria-label="{{ lang('commerce.mk_filter_seller') }}">
				<option value="">{{ lang('commerce.mk_all_sellers') }}</option>
				@foreach ($seller_names as $sh_sid => $sh_sname)
				<option value="{{ $sh_sid }}" @if ((int)$ship_seller === (int)$sh_sid) selected @endif>{{ $sh_sname }}</option>
				@endforeach
			</select>
			@endif
			<input type="search" name="q" value="{{ $ship_q }}" placeholder="{{ lang('commerce.sh_search_ph') }}" />
		</form>
		<button type="button" class="rsva-btn rsva-btn-sm" id="shCsvOut" @if (empty($ship_rows)) disabled @endif>{{ lang('commerce.sh_csv_out') }}</button>
		@if ($ship_tab === 'paid' || $ship_tab === 'preparing')
		<button type="button" class="rsva-btn rsva-btn-sm" id="shCsvIn">{{ lang('commerce.sh_csv_in') }}</button>
		<input type="file" id="shCsvFile" accept=".csv,text/csv" hidden />
		@endif
	</div>

	@if (empty($ship_rows))
	<div class="sh-wrap"><p class="sh-empty">{{ lang('commerce.sh_empty_' . $ship_tab) }}</p></div>
	@else
	<div class="sh-wrap">
		<table class="sh-table" id="shTable">
			<thead><tr>
				@if ($sh_editable)<th class="c-chk"></th>@endif
				<th>{{ lang('commerce.sh_col_order') }}</th>
				<th>{{ lang('commerce.sh_col_item') }}</th>
				<th>{{ lang('commerce.sh_col_to') }}</th>
				<th>{{ lang('commerce.sh_col_addr') }}</th>
				<th>{{ $ship_tab === 'delivered' ? lang('commerce.sh_col_done') : lang('commerce.sh_col_invoice') }}</th>
			</tr></thead>
			<tbody>
			@foreach ($ship_rows as $r)
			@php
			$sh_first = $r->items[0] ?? null;
			$sh_more = count($r->items) - 1;
			$sh_qty = 0;
			foreach ($r->items as $sh_it) { $sh_qty += (int)$sh_it->qty; }
			$sh_a = $r->address;
			$sh_addr = $sh_a ? trim(($sh_a->zipcode ? '(' . $sh_a->zipcode . ') ' : '') . $sh_a->address1 . ' ' . $sh_a->address2 . (($sh_a->country ?? 'KR') !== 'KR' ? ' · ' . $sh_a->city . ' ' . $sh_a->state . ' ' . $sh_a->country : '')) : '';
			$sh_is_direct = $r->shipping_company === $sh_direct;
			$sh_items_text = $sh_first ? $sh_first->item_name . ($sh_first->option_name ? ' / ' . $sh_first->option_name : '') . ' ×' . $sh_first->qty . ($sh_more > 0 ? ' ' . sprintf(lang('commerce.sh_more_items'), $sh_more) : '') : '';
			@endphp
			<tr data-order="{{ $r->order_srl }}" data-os="{{ $r->order_seller_srl }}" data-code="{{ $r->order_code }}" data-to="{{ $sh_a->receiver_name ?? '' }}" data-phone="{{ $sh_a->receiver_phone ?? '' }}" data-zip="{{ $sh_a->zipcode ?? '' }}" data-addr="{{ $sh_a ? trim($sh_a->address1 . ' ' . $sh_a->address2) : '' }}" data-memo="{{ $sh_a->delivery_memo ?? '' }}" data-items="{{ $sh_items_text }}" data-qty="{{ $sh_qty }}">
				@if ($sh_editable)<td class="c-chk"><input type="checkbox" class="sh-chk" aria-label="{{ $r->order_code }}" /></td>@endif
				<td class="sh-code">
					@if (($seller_mode ?? '') === 'seller')<b>{{ $r->order_code }}</b>@else<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminOrderView', 'order_srl', $r->order_srl) }}">{{ $r->order_code }}</a>@endif
					<span class="sh-sub">{{ zdate($r->paid_date ?: $r->regdate, 'm.d H:i') }} · {{ $r->orderer_name }}</span>
					@if (($seller_mode ?? '') === 'operator')<span class="sh-sub">{{ $seller_names[(int)$r->seller_srl] ?? lang('commerce.mk_direct') }}</span>@endif
				</td>
				<td>
					<div class="sh-item">
						<i @if ($sh_first && $sh_first->thumb) style="background-image:url('{{ $sh_first->thumb }}')" @endif></i>
						<div style="min-width:0">
							<b>{{ $sh_first->item_name ?? '' }}</b>
							<span class="sh-sub">@if ($sh_first && $sh_first->option_name){{ $sh_first->option_name }} · @endif{{ sprintf(lang('commerce.st_unit_ea'), $sh_first->qty ?? 0) }}@if ($sh_more > 0) · {{ sprintf(lang('commerce.sh_more_items'), $sh_more) }}@endif</span>
						</div>
					</div>
				</td>
				<td style="white-space:nowrap">{{ $sh_a->receiver_name ?? '' }}<span class="sh-sub">{{ $sh_a->receiver_phone ?? '' }}</span></td>
				<td class="sh-addr">{{ $sh_addr }}@if (!empty($sh_a->delivery_memo))<br /><span class="sh-memo">{{ $sh_a->delivery_memo }}</span>@endif</td>
				<td>
					@if ($ship_tab === 'delivered')
					<span class="sh-done">{{ $r->shipping_company }} {{ $r->shipping_invoice }}<span class="sh-sub">{{ lang('commerce.sh_delivered_at') }} {{ $r->delivered_date ? zdate($r->delivered_date, 'm.d H:i') : '' }}</span></span>
					@else
					<div class="sh-in">
						<select class="sh-carrier" aria-label="{{ lang('commerce.sh_carrier') }}">
							<option value="">{{ lang('commerce.sh_carrier') }}</option>
							@foreach ($sh_carriers as $sh_c)<option @if ($r->shipping_company === $sh_c) selected @endif>{{ $sh_c }}</option>@endforeach
							@if ($r->shipping_company && !$sh_is_direct && !in_array($r->shipping_company, $sh_carriers, true))<option selected>{{ $r->shipping_company }}</option>@endif
							<option value="__direct" @if ($sh_is_direct) selected @endif>{{ $sh_direct }}</option>
						</select>
						<input type="text" class="sh-invoice" inputmode="numeric" autocomplete="off" value="{{ $r->shipping_invoice }}" placeholder="{{ lang('commerce.sh_invoice_ph') }}" aria-label="{{ lang('commerce.sh_col_invoice') }}" @if ($sh_is_direct) disabled @endif />
					</div>
					@if ($ship_tab === 'shipping')<span class="sh-sub">{{ lang('commerce.sh_shipped_at') }} {{ $r->shipped_date ? zdate($r->shipped_date, 'm.d H:i') : '' }}</span>@endif
					@endif
				</td>
			</tr>
			@endforeach
			</tbody>
		</table>
	</div>
	@if ($sh_editable)
	<p class="sh-hint">{{ lang('commerce.sh_hint') }}</p>
	@endif
	@endif
</div>

<script>
(function () {
	var table = document.getElementById('shTable');
	var T = {
		needPick: {!! json_encode(lang('commerce.sh_need_pick')) !!},
		needInvoice: {!! json_encode(lang('commerce.sh_need_invoice')) !!},
		ask: {!! $sh_ask_json !!},
		done: {!! json_encode(lang('commerce.sh_done')) !!},
		fail: {!! json_encode(lang('commerce.sh_fail')) !!},
		csvHead: {!! json_encode(lang('commerce.sh_csv_head')) !!},
		csvMatched: {!! json_encode(lang('commerce.sh_csv_matched')) !!},
		csvBad: {!! json_encode(lang('commerce.sh_csv_bad')) !!}
	};
	var DIRECT = '__direct';
	function toast(msg) {
		var t = document.createElement('div'); t.className = 'sh-toast'; t.setAttribute('role', 'status'); t.textContent = msg;
		document.body.appendChild(t); setTimeout(function () { t.remove(); }, 3200);
	}
	if (!table) {
		var out0 = document.getElementById('shCsvOut'); if (out0) out0.disabled = true;
		return;
	}
	var rows = [].slice.call(table.querySelectorAll('tbody tr'));

	var all = document.getElementById('shAll'), selEl = document.getElementById('shSel');
	function picked() { return rows.filter(function (tr) { var c = tr.querySelector('.sh-chk'); return c && c.checked; }); }
	function syncSel() {
		var n = picked().length;
		rows.forEach(function (tr) { var c = tr.querySelector('.sh-chk'); tr.classList.toggle('is-checked', !!(c && c.checked)); });
		if (selEl) selEl.textContent = n;
		if (all) { all.checked = n > 0 && n === rows.length; all.indeterminate = n > 0 && n < rows.length; }
	}
	if (all) all.addEventListener('change', function () { rows.forEach(function (tr) { var c = tr.querySelector('.sh-chk'); if (c) c.checked = all.checked; }); syncSel(); });
	table.addEventListener('change', function (e) { if (e.target.classList.contains('sh-chk')) syncSel(); });

	var saved = '';
	try { saved = localStorage.getItem('zmcShipCarrier') || ''; } catch (e) {}
	function setDirect(tr) {
		var sel = tr.querySelector('.sh-carrier'), inp = tr.querySelector('.sh-invoice');
		if (!sel || !inp) return;
		inp.disabled = sel.value === DIRECT;
		if (inp.disabled) inp.value = '';
	}
	rows.forEach(function (tr) {
		var sel = tr.querySelector('.sh-carrier');
		if (sel && !sel.value && saved) { sel.value = saved; if (!sel.value) sel.value = ''; setDirect(tr); }
	});
	table.addEventListener('change', function (e) {
		if (!e.target.classList.contains('sh-carrier')) return;
		var tr = e.target.closest('tr'); setDirect(tr);
		var c = tr.querySelector('.sh-chk'); if (c) { c.checked = true; syncSel(); }
	});
	table.addEventListener('input', function (e) {
		if (!e.target.classList.contains('sh-invoice')) return;
		var tr = e.target.closest('tr'), c = tr.querySelector('.sh-chk');
		tr.classList.remove('is-miss');
		if (c) { c.checked = e.target.value.trim() !== '' || c.checked; syncSel(); }
	});
	table.addEventListener('keydown', function (e) {
		if (e.key !== 'Enter' || !e.target.classList.contains('sh-invoice')) return;
		e.preventDefault();
		var list = [].slice.call(table.querySelectorAll('.sh-invoice:not(:disabled)'));
		var next = list[list.indexOf(e.target) + 1];
		if (next) { next.focus(); next.select(); }
	});
	var applyBtn = document.getElementById('shApplyCarrier');
	if (applyBtn) applyBtn.addEventListener('click', function () {
		var v = document.getElementById('shBulkCarrier').value;
		var target = picked().length ? picked() : rows;
		target.forEach(function (tr) { var sel = tr.querySelector('.sh-carrier'); if (sel) { sel.value = v; setDirect(tr); } });
		try { if (v !== DIRECT) localStorage.setItem('zmcShipCarrier', v); } catch (e) {}
	});
	var bulkSel = document.getElementById('shBulkCarrier');
	if (bulkSel && saved) bulkSel.value = saved;

	document.querySelectorAll('[data-act]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var act = btn.dataset.act, list = picked();
			if (!list.length) { toast(T.needPick); return; }
			var data = [], miss = 0;
			list.forEach(function (tr) {
				var sel = tr.querySelector('.sh-carrier'), inp = tr.querySelector('.sh-invoice');
				var direct = sel && sel.value === DIRECT;
				var company = sel && !direct ? sel.value : '', invoice = inp ? inp.value.trim() : '';
				if ((act === 'ship' || act === 'reinvoice') && !direct && (!company || !invoice)) { tr.classList.add('is-miss'); miss++; return; }
				data.push({ order_srl: tr.dataset.order, order_seller_srl: tr.dataset.os, company: company, invoice: invoice, direct: direct ? 'Y' : 'N' });
				if (company) { try { localStorage.setItem('zmcShipCarrier', company); } catch (e) {} }
			});
			if (miss) { toast(T.needInvoice.replace('%d', miss)); return; }
			if (!confirm(T.ask[act].replace('%d', data.length))) return;
			btn.disabled = true;
			exec_json('commerce.procCommerceAdminBulkShipping', { ship_action: act, rows: JSON.stringify(data) }, function (res) {
				var msg = T.done.replace('%d', res.done || 0);
				if (res.failed && res.failed.length) msg += ' ' + T.fail.replace('%s', res.failed.join(', '));
				try { sessionStorage.setItem('zmcShipToast', msg); } catch (e) {}
				location.reload();
			}, function () { btn.disabled = false; });
		});
	});
	try { var last = sessionStorage.getItem('zmcShipToast'); if (last) { sessionStorage.removeItem('zmcShipToast'); toast(last); } } catch (e) {}

	function cell(v) { v = String(v == null ? '' : v); return /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v; }
	var outBtn = document.getElementById('shCsvOut');
	if (outBtn) outBtn.addEventListener('click', function () {
		var list = picked().length ? picked() : rows;
		var lines = [T.csvHead];
		list.forEach(function (tr) {
			var sel = tr.querySelector('.sh-carrier'), inp = tr.querySelector('.sh-invoice');
			var company = sel ? (sel.value === DIRECT ? sel.options[sel.selectedIndex].text : sel.value) : '';
			lines.push([tr.dataset.code, tr.dataset.to, tr.dataset.phone, tr.dataset.zip, tr.dataset.addr, tr.dataset.memo, tr.dataset.items, tr.dataset.qty, company, inp ? inp.value : ''].map(cell).join(','));
		});
		var blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8' });
		var a = document.createElement('a');
		a.href = URL.createObjectURL(blob);
		a.download = 'shipping-' + new Date().toISOString().slice(0, 10) + '.csv';
		document.body.appendChild(a); a.click(); a.remove();
		setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
	});

	function parseCsv(text) {
		var out = [], row = [], cur = '', q = false;
		for (var i = 0; i < text.length; i++) {
			var ch = text[i];
			if (q) { if (ch === '"') { if (text[i + 1] === '"') { cur += '"'; i++; } else q = false; } else cur += ch; }
			else if (ch === '"') q = true;
			else if (ch === ',') { row.push(cur); cur = ''; }
			else if (ch === '\n' || ch === '\r') { if (ch === '\r' && text[i + 1] === '\n') i++; row.push(cur); out.push(row); row = []; cur = ''; }
			else cur += ch;
		}
		if (cur !== '' || row.length) { row.push(cur); out.push(row); }
		return out.filter(function (r) { return r.join('').trim() !== ''; });
	}
	var inBtn = document.getElementById('shCsvIn'), file = document.getElementById('shCsvFile');
	if (inBtn) inBtn.addEventListener('click', function () { file.click(); });
	if (file) file.addEventListener('change', function () {
		var f = file.files && file.files[0]; if (!f) return;
		var reader = new FileReader();
		reader.onload = function () {
			var data = parseCsv(String(reader.result).replace(/^﻿/, ''));
			var head = (data[0] || []).map(function (h) { return h.replace(/\s/g, ''); });
			var ci = head.findIndex(function (h) { return /주문번호|order/i.test(h); });
			var ki = head.findIndex(function (h) { return /택배사|carrier|courier/i.test(h); });
			var ii = head.findIndex(function (h) { return /송장|운송장|invoice|tracking/i.test(h); });
			if (ci < 0 || ii < 0) { toast(T.csvBad); file.value = ''; return; }
			var byCode = {};
			rows.forEach(function (tr) { byCode[tr.dataset.code] = tr; });
			var n = 0;
			data.slice(1).forEach(function (r) {
				var tr = byCode[(r[ci] || '').trim()], inv = (r[ii] || '').replace(/\s/g, '');
				if (!tr || !inv) return;
				var sel = tr.querySelector('.sh-carrier'), inp = tr.querySelector('.sh-invoice');
				var comp = ki >= 0 ? (r[ki] || '').trim() : '';
				if (comp && sel) {
					var opt = [].find.call(sel.options, function (o) { return o.value === comp || o.value.replace(/택배|\s/g, '') === comp.replace(/택배|\s/g, ''); });
					if (!opt) { opt = new Option(comp, comp); sel.insertBefore(opt, sel.lastElementChild); }
					sel.value = opt.value; setDirect(tr);
				}
				if (inp && !inp.disabled) inp.value = inv;
				var c = tr.querySelector('.sh-chk'); if (c) c.checked = true;
				n++;
			});
			syncSel();
			toast(T.csvMatched.replace('%d', n));
			file.value = '';
		};
		reader.readAsText(f, 'utf-8');
	});
	syncSel();
})();
</script>
