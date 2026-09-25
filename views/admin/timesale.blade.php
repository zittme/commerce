@include('_tabs')
@include('_studio_style')

@php
$ts_base_url = getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'timesale');
$ts_join = strpos($ts_base_url, '?') === false ? '?' : '&';
@endphp

<style>
.ts-list { width: 100%; border-collapse: collapse; font-size: 13px; }
.ts-list th { padding: 9px 12px; border-bottom: 1px solid var(--zmc-line-strong, #d6d2c8); background: var(--zmc-side, #efede8); font-size: 12px; font-weight: 600; color: var(--pm-sub); text-align: left; }
.ts-list td { padding: 12px; border-bottom: 1px solid var(--pm-line); vertical-align: middle; }
.ts-list tr:hover td { background: var(--zmc-bg, #f7f6f3); }
.ts-list a.ts-name { font-weight: 600; color: var(--pm-ink) !important; }
.ts-when { font-size: 12.5px; color: var(--pm-sub); font-variant-numeric: tabular-nums; }
.ts-st { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 11.5px; font-weight: 700; background: var(--zmc-side, #efede8); color: var(--pm-sub); }
.ts-st.is-on { background: #b3261e; color: #fff; }
.ts-st.is-soon { background: var(--pm-brand); color: var(--zmc-on-brand, #fff8e6); }
.ts-rows { width: 100%; border-collapse: collapse; font-size: 13px; }
.ts-rows th { padding: 6px 6px; font-size: 11.5px; font-weight: 600; color: var(--pm-sub); text-align: left; white-space: nowrap; }
.ts-rows td { padding: 6px; border-top: 1px solid var(--pm-line); vertical-align: middle; }
.ts-rows .ts-item { display: flex; align-items: center; gap: 8px; min-width: 180px; }
.ts-rows .ts-item span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px; }
.pm .ts-rows input[type=number] { width: 80px; height: 32px; padding: 0 8px; box-sizing: border-box; }
.pm .ts-rows select { height: 32px; padding: 0 6px; }
.ts-calc { font-size: 12px; white-space: nowrap; font-variant-numeric: tabular-nums; }
.ts-calc s { color: var(--pm-sub); }
.ts-calc b { color: #b3261e; }
.ts-sold { font-size: 12px; color: var(--pm-sub); white-space: nowrap; }
.ts-empty { padding: 20px; text-align: center; font-size: 13px; color: var(--pm-sub); border: 1px dashed var(--pm-line); border-radius: var(--pm-r); }
.ts-times { display: grid; grid-template-columns: 1fr auto 1fr; gap: 8px; align-items: center; }
.ts-edit { max-width: 980px; }
</style>

<div class="rsva pm">
@if (!$ts_edit && !$ts_new)
	<div class="pm-head">
		<p>{{ lang('commerce.ts_list_hint') }}</p>
		<a class="rsva-btn rsva-btn-primary" href="{{ $ts_base_url }}{{ $ts_join }}new=Y">+ {{ lang('commerce.ts_new') }}</a>
	</div>
	<div class="rsva-panel" style="padding:0;overflow-x:auto">
		@if (empty($ts_list))
		<p class="pm-empty">{{ lang('commerce.ts_empty') }}</p>
		@else
		<table class="ts-list">
			<thead><tr><th>{{ lang('commerce.ts_col_name') }}</th><th>{{ lang('commerce.ts_col_when') }}</th><th>{{ lang('commerce.ts_col_state') }}</th><th>{{ lang('commerce.ts_col_items') }}</th><th>{{ lang('commerce.ts_col_sold') }}</th></tr></thead>
			<tbody>
			@foreach ($ts_list as $t)
			@php
			$t_now = date('YmdHis');
			if ($t->status !== 'Y') { $t_st = 'off'; }
			elseif ($t->ends_at) { $t_st = 'on'; }
			elseif ($t->starts_at) { $t_st = 'soon'; }
			elseif ($t->end_date < $t_now) { $t_st = 'ended'; }
			else { $t_st = 'wait'; }
			@endphp
			<tr>
				<td><a class="ts-name" href="{{ $ts_base_url }}{{ $ts_join }}sale_srl={{ $t->sale_srl }}">{{ $t->title }}</a></td>
				<td class="ts-when">
					@if ($t->mode === 'daily')
					{{ zdate($t->start_date, 'Y.m.d') }} ~ {{ zdate($t->end_date, 'Y.m.d') }} · {{ lang('commerce.ts_daily') }} {{ substr($t->daily_start, 0, 2) }}:{{ substr($t->daily_start, 2, 2) }}~{{ substr($t->daily_end, 0, 2) }}:{{ substr($t->daily_end, 2, 2) }}
					@else
					{{ zdate($t->start_date, 'Y.m.d H:i') }} ~ {{ zdate($t->end_date, 'Y.m.d H:i') }}
					@endif
				</td>
				<td><span class="ts-st is-{{ $t_st }}">{{ lang('commerce.ts_st_' . $t_st) }}</span></td>
				<td>{{ sprintf(lang('commerce.st_unit_ea'), $t->item_count) }}</td>
				<td>{{ number_format($t->sold) }}</td>
			</tr>
			@endforeach
			</tbody>
		</table>
		@endif
	</div>
@else
	@php
	$te = $ts_edit;
	$te_mode = $te->mode ?? 'once';
	$te_start_at = $te && $te_mode === 'once' ? substr($te->start_date, 0, 4) . '-' . substr($te->start_date, 4, 2) . '-' . substr($te->start_date, 6, 2) . 'T' . substr($te->start_date, 8, 2) . ':' . substr($te->start_date, 10, 2) : date('Y-m-d\TH:00', time() + 3600);
	$te_end_at = $te && $te_mode === 'once' ? substr($te->end_date, 0, 4) . '-' . substr($te->end_date, 4, 2) . '-' . substr($te->end_date, 6, 2) . 'T' . substr($te->end_date, 8, 2) . ':' . substr($te->end_date, 10, 2) : date('Y-m-d\TH:00', time() + 3600 * 25);
	$te_start_day = $te ? substr($te->start_date, 0, 4) . '-' . substr($te->start_date, 4, 2) . '-' . substr($te->start_date, 6, 2) : date('Y-m-d');
	$te_end_day = $te ? substr($te->end_date, 0, 4) . '-' . substr($te->end_date, 4, 2) . '-' . substr($te->end_date, 6, 2) : date('Y-m-d', time() + 86400 * 6);
	$te_ds = $te && $te->daily_start ? substr($te->daily_start, 0, 2) . ':' . substr($te->daily_start, 2, 2) : '12:00';
	$te_de = $te && $te->daily_end ? substr($te->daily_end, 0, 2) . ':' . substr($te->daily_end, 2, 2) : '13:00';
	$te_data = [];
	foreach ($ts_all_items as $ti)
	{
		$ti_base = ((int)$ti->sale_price > 0 && (int)$ti->sale_price < (int)$ti->price) ? (int)$ti->sale_price : (int)$ti->price;
		$te_data[] = ['srl' => (int)$ti->item_srl, 'name' => (string)$ti->item_name, 'thumb' => (string)($ti->thumb ?? ''), 'cat' => (int)$ti->category_srl, 'base' => $ti_base, 'base_txt' => shop_money_base($ti_base), 'meta' => shop_money_base($ti_base) . ((int)$ti->stock > 0 && ($ti->use_stock ?? 'Y') === 'Y' ? ' · ' . sprintf(lang('commerce.ts_stock'), number_format((int)$ti->stock)) : '')];
	}
	$te_rows = [];
	foreach ($ts_edit_items as $tr)
	{
		$te_rows[] = ['item_srl' => (int)$tr->item_srl, 'discount_type' => $tr->discount_type, 'value' => (float)$tr->value, 'qty_limit' => (int)$tr->qty_limit, 'per_member' => (int)$tr->per_member, 'sold_qty' => (int)$tr->sold_qty];
	}
	$te_data_json = json_encode($te_data, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG + JSON_HEX_AMP);
	$te_rows_json = json_encode($te_rows, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG);
	@endphp
	<div class="pm-head">
		<a href="{{ $ts_base_url }}" class="rsva-btn rsva-btn-sm">← {{ lang('commerce.ts_back') }}</a>
	</div>
	<form class="ts-edit" id="tsForm">
		<input type="hidden" name="sale_srl" value="{{ $te->sale_srl ?? 0 }}" />
		<section class="pm-sec">
			<h3>{{ lang('commerce.pm_sec_basic') }}</h3>
			<div class="pm-row"><label for="tsTitle">{{ lang('commerce.pm_title') }}</label><input type="text" id="tsTitle" name="title" maxlength="120" required value="{{ $te->title ?? '' }}" placeholder="{{ lang('commerce.ts_title_ph') }}" /></div>
			<div class="pm-row">
				<label>{{ lang('commerce.ts_mode') }}</label>
				<div>
					<input type="hidden" name="mode" id="tsMode" value="{{ $te_mode }}" />
					<div class="pm-seg" data-for="tsMode">
						<button type="button" data-v="once" class="{{ $te_mode !== 'daily' ? 'is-on' : '' }}">{{ lang('commerce.ts_mode_once') }}</button>
						<button type="button" data-v="daily" class="{{ $te_mode === 'daily' ? 'is-on' : '' }}">{{ lang('commerce.ts_mode_daily') }}</button>
					</div>
					<p class="pm-hint" id="tsModeHint"></p>
				</div>
			</div>
			<div class="pm-row" data-mode="once">
				<label>{{ lang('commerce.ts_when') }}</label>
				<div class="ts-times"><input type="datetime-local" name="start_at" value="{{ $te_start_at }}" /> ~ <input type="datetime-local" name="end_at" value="{{ $te_end_at }}" /></div>
			</div>
			<div class="pm-row" data-mode="daily">
				<label>{{ lang('commerce.ts_days') }}</label>
				<div class="ts-times"><input type="date" name="start_day" value="{{ $te_start_day }}" /> ~ <input type="date" name="end_day" value="{{ $te_end_day }}" /></div>
			</div>
			<div class="pm-row" data-mode="daily">
				<label>{{ lang('commerce.ts_hours') }}</label>
				<div><div class="ts-times"><input type="time" name="daily_start" value="{{ $te_ds }}" /> ~ <input type="time" name="daily_end" value="{{ $te_de }}" /></div><p class="pm-hint">{{ lang('commerce.ts_hours_hint') }}</p></div>
			</div>
			<div class="pm-row">
				<label>{{ lang('commerce.pm_open') }}</label>
				<div>
					<input type="hidden" name="status" id="tsStatus" value="{{ ($te->status ?? 'Y') === 'N' ? 'N' : 'Y' }}" />
					<div class="pm-seg" data-for="tsStatus">
						<button type="button" data-v="Y" class="{{ ($te->status ?? 'Y') !== 'N' ? 'is-on' : '' }}">{{ lang('commerce.ts_on') }}</button>
						<button type="button" data-v="N" class="{{ ($te->status ?? 'Y') === 'N' ? 'is-on' : '' }}">{{ lang('commerce.ts_off') }}</button>
					</div>
				</div>
			</div>
		</section>

		<section class="pm-sec">
			<h3>{{ lang('commerce.ts_sec_items') }} <small id="tsCount"></small></h3>
			<div style="overflow-x:auto">
				<table class="ts-rows">
					<thead><tr><th>{{ lang('commerce.ts_col_item') }}</th><th>{{ lang('commerce.ts_col_discount') }}</th><th>{{ lang('commerce.ts_col_price') }}</th><th>{{ lang('commerce.ts_col_limit') }}</th><th>{{ lang('commerce.ts_col_per') }}</th><th>{{ lang('commerce.ts_col_sold') }}</th><th></th></tr></thead>
					<tbody id="tsRows"></tbody>
				</table>
			</div>
			<p class="ts-empty" id="tsEmpty">{{ lang('commerce.ts_items_empty') }}</p>
			<div class="pm-hint" style="margin-top:8px">{{ lang('commerce.ts_items_hint') }}</div>
			<div class="pm-finder">
				<div class="pm-finder-bar" style="grid-template-columns:minmax(0,1fr) 160px">
					<input type="search" id="tsQ" placeholder="{{ lang('commerce.pm_search_ph') }}" />
					<select id="tsCat">
						<option value="0">{{ lang('commerce.pm_all_categories') }}</option>
						@foreach ($ts_categories as $tc)<option value="{{ $tc->category_srl }}">{{ str_repeat('· ', (int)($tc->depth ?? 0)) }}{{ $tc->title }}</option>@endforeach
					</select>
				</div>
				<ul class="pm-found" id="tsFound"></ul>
			</div>
		</section>

		<div class="pm-savebar">
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_item_edit_159') }}</button>
			<span class="pm-grow"></span>
			@if ($te)<button type="button" class="rsva-btn rsva-btn-danger" id="tsDelete">{{ lang('commerce.ts_delete') }}</button>@endif
		</div>
	</form>

	<script>
	(function () {
		var ITEMS = {!! $te_data_json !!}, rows = {!! $te_rows_json !!};
		var T = {
			count: {!! json_encode(lang('commerce.br_count')) !!},
			pct: {!! json_encode(lang('commerce.ts_type_percent')) !!},
			price: {!! json_encode(lang('commerce.ts_type_price')) !!},
			none: {!! json_encode(lang('commerce.ts_unlimited')) !!},
			remove: {!! json_encode(lang('commerce.pm_remove')) !!},
			added: {!! json_encode(lang('commerce.pm_added')) !!},
			hintOnce: {!! json_encode(lang('commerce.ts_hint_once')) !!},
			hintDaily: {!! json_encode(lang('commerce.ts_hint_daily')) !!},
			delAsk: {!! json_encode(lang('commerce.ts_delete_ask')) !!},
			bad: {!! json_encode(lang('commerce.ts_msg_bad_price')) !!}
		};
		var byId = {}; ITEMS.forEach(function (it) { byId[it.srl] = it; });
		rows = rows.filter(function (r) { return byId[r.item_srl]; });
		var $ = function (id) { return document.getElementById(id); };
		function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
		function won(n) { return Math.max(0, Math.round(n)).toLocaleString(); }
		function calc(r) {
			var base = byId[r.item_srl].base;
			return r.discount_type === 'price' ? +r.value : Math.floor(base * (100 - (+r.value || 0)) / 100);
		}

		function draw() {
			var tb = $('tsRows'); tb.textContent = '';
			rows.forEach(function (r, i) {
				var it = byId[r.item_srl], tr = el('tr');
				var c1 = el('td'), box = el('div', 'ts-item'), th = el('span', 'pm-th');
				if (it.thumb) th.style.backgroundImage = "url('" + it.thumb.replace(/'/g, '%27') + "')";
				box.appendChild(th); box.appendChild(el('span', '', it.name)); c1.appendChild(box); tr.appendChild(c1);
				var c2 = el('td'), sel = el('select'), inp = el('input');
				[['percent', T.pct], ['price', T.price]].forEach(function (o) { var op = el('option', '', o[1]); op.value = o[0]; sel.appendChild(op); });
				sel.value = r.discount_type; inp.type = 'number'; inp.min = 0; inp.value = r.value;
				sel.addEventListener('change', function () { r.discount_type = sel.value; if (sel.value === 'percent' && r.value > 99) r.value = 10; if (sel.value === 'price' && r.value < 100) r.value = Math.floor(it.base * 0.8); inp.value = r.value; draw(); });
				inp.addEventListener('input', function () { r.value = +inp.value || 0; calcCell.textContent = ''; fillCalc(); });
				var wrap = el('div'); wrap.style.cssText = 'display:flex;gap:4px'; wrap.appendChild(sel); wrap.appendChild(inp); c2.appendChild(wrap); tr.appendChild(c2);
				var calcCell = el('td', 'ts-calc');
				function fillCalc() {
					calcCell.textContent = '';
					var p = calc(r), bad = p <= 0 || p >= it.base;
					calcCell.appendChild(el('s', '', won(it.base))); calcCell.appendChild(document.createTextNode(' → '));
					var b = el('b', '', won(p)); if (bad) { b.style.color = '#8b95a1'; b.title = T.bad; } calcCell.appendChild(b);
				}
				fillCalc(); tr.appendChild(calcCell);
				['qty_limit', 'per_member'].forEach(function (k) {
					var c = el('td'), n = el('input'); n.type = 'number'; n.min = 0; n.value = r[k] || ''; n.placeholder = T.none;
					n.addEventListener('input', function () { r[k] = +n.value || 0; }); c.appendChild(n); tr.appendChild(c);
				});
				tr.appendChild(el('td', 'ts-sold', r.sold_qty ? won(r.sold_qty) : '-'));
				var c7 = el('td'), x = el('button', 'pm-x', '×'); x.type = 'button'; x.setAttribute('aria-label', T.remove + ': ' + it.name);
				x.addEventListener('click', function () { rows.splice(i, 1); draw(); find(); }); c7.appendChild(x); tr.appendChild(c7);
				tb.appendChild(tr);
			});
			$('tsEmpty').hidden = rows.length > 0;
			$('tsCount').textContent = T.count.replace('%d', rows.length);
		}
		function find() {
			var q = $('tsQ').value.trim().toLowerCase(), cat = +$('tsCat').value, box = $('tsFound');
			var picked = rows.map(function (r) { return r.item_srl; });
			box.textContent = '';
			ITEMS.filter(function (it) { return (!q || it.name.toLowerCase().indexOf(q) !== -1) && (!cat || it.cat === cat); }).slice(0, 60).forEach(function (it) {
				var li = el('li'), th = el('span', 'pm-th'), nm = el('span', 'pm-nm', it.name);
				if (it.thumb) th.style.backgroundImage = "url('" + it.thumb.replace(/'/g, '%27') + "')";
				nm.appendChild(el('small', '', it.meta)); li.appendChild(th); li.appendChild(nm);
				var on = picked.indexOf(it.srl) !== -1, b = el('button', 'pm-add', on ? T.added : '+'); b.type = 'button'; b.disabled = on;
				b.addEventListener('click', function () { rows.push({ item_srl: it.srl, discount_type: 'percent', value: 20, qty_limit: 0, per_member: 0, sold_qty: 0 }); draw(); find(); });
				li.appendChild(b); box.appendChild(li);
			});
		}
		['tsQ', 'tsCat'].forEach(function (id) { $(id).addEventListener('input', find); $(id).addEventListener('change', find); });

		function modeUi() {
			var m = $('tsMode').value;
			document.querySelectorAll('[data-mode]').forEach(function (r) { r.hidden = r.dataset.mode !== m; });
			$('tsModeHint').textContent = m === 'daily' ? T.hintDaily : T.hintOnce;
		}
		document.querySelectorAll('.pm-seg[data-for]').forEach(function (seg) {
			seg.addEventListener('click', function (e) {
				var b = e.target.closest('button'); if (!b) return;
				$(seg.dataset.for).value = b.dataset.v;
				seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				modeUi();
			});
		});

		var form = $('tsForm');
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var fd = new FormData(form), data = {};
			fd.forEach(function (v, k) { data[k] = v; });
			data.items = JSON.stringify(rows);
			exec_json('commerce.procCommerceAdminSaveTimesale', data, function (res) {
				var base = {!! json_encode(html_entity_decode($ts_base_url)) !!};
				location.href = base + (base.indexOf('?') < 0 ? '?' : '&') + 'sale_srl=' + res.sale_srl;
			});
		});
		var del = $('tsDelete');
		if (del) del.addEventListener('click', function () {
			if (!confirm(T.delAsk)) return;
			exec_json('commerce.procCommerceAdminDeleteTimesale', { sale_srl: form.sale_srl.value }, function () { location.href = {!! json_encode(html_entity_decode($ts_base_url)) !!}; });
		});
		modeUi(); draw(); find();
	})();
	</script>
@endif
</div>
