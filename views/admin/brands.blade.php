@include('_tabs')
@include('_langfield_assets')
@include('_studio_style')

<style>
.br-note { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; margin-bottom: 16px; padding: 14px 18px; border-radius: var(--zmc-r, 6px); background: var(--zmc-brand-soft, #fbf1d6); font-size: 14px; }
.br-note p { margin: 0; flex: 1; min-width: 240px; }
.br-note small { display: block; margin-top: 2px; }
.br-card .pm-card-bn { aspect-ratio: 16 / 6; background: var(--zmc-side, #efede8) center / cover no-repeat; }
.br-card .pm-card-bn .pm-state { top: 10px; left: auto; right: 10px; }
.br-logo { position: absolute; left: 14px; bottom: -22px; display: grid; place-items: center; width: 48px; height: 48px; border: 3px solid var(--zmc-surface, #fff); border-radius: 50%; background: var(--zmc-brand, #26345c) center / cover no-repeat; color: var(--zmc-on-brand, #fff8e6); font-size: 17px; font-weight: 800; }
.br-card .pm-card-body { padding-top: 28px; align-items: flex-end; }
.br-card .pm-card-body b small { display: block; font-size: 12px; font-weight: 500; color: var(--pm-sub); }
.br-card[draggable=true] { cursor: grab; }
.br-card.is-drag { opacity: .4; }
.br-card.is-over { box-shadow: inset 3px 0 0 var(--pm-brand); }
.br-cover-thumb { width: 120px !important; height: 44px !important; }
.br-other { flex: none; padding: 2px 6px; border-radius: 4px; background: var(--zmc-side, #efede8); font-size: 11px; color: var(--pm-sub); }
</style>

@php
$br_list_url = getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'brands');
@endphp

<div class="rsva pm">
@if (!$brand_edit)
	@if ($brand_named_count > 0)
	<div class="br-note">
		<p>{{ sprintf(lang('commerce.admin_brand_named'), $brand_named_count, count($brand_named)) }}<small>{{ implode(', ', array_slice(array_keys($brand_named), 0, 8)) }}@if (count($brand_named) > 8) …@endif</small></p>
		<form action="{{ getUrl('') }}" method="post" onsubmit="return confirm('{{ lang('commerce.admin_brand_migrate_confirm') }}');">
			<input type="hidden" name="module" value="admin" /><input type="hidden" name="act" value="procCommerceAdminMigrateBrands" />
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_brand_migrate') }}</button>
		</form>
	</div>
	@endif
	<div class="pm-head">
		<p>{{ lang('commerce.br_list_hint') }}</p>
		<span class="pm-view-state" id="brOrderState"></span>
	</div>
	<div class="pm-cards" id="brCards">
		<button type="button" class="pm-card pm-card-new" id="brCreate"><i aria-hidden="true">+</i>{{ lang('commerce.br_new') }}</button>
		@foreach ($brands as $b)
		<a class="pm-card br-card" draggable="true" data-srl="{{ $b->brand_srl }}" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'brands', 'brand_srl', $b->brand_srl) }}">
			<span class="pm-card-bn" @if ($b->cover) style="background-image:url('{{ $b->cover }}')" @endif>
				@if ($b->is_visible === 'N')<span class="pm-state is-hidden">{{ lang('commerce.admin_brand_hidden') }}</span>@endif
				<span class="br-logo" @if ($b->logo) style="background-image:url('{{ $b->logo }}')" @endif>@if (!$b->logo){{ mb_substr((string)$b->name, 0, 1) }}@endif</span>
			</span>
			<span class="pm-card-body">
				<b>{{ $b->name }}@if ($b->name_en)<small>{{ $b->name_en }}</small>@endif</b>
				<small>{{ sprintf(lang('commerce.st_unit_ea'), number_format($brand_counts[(int)$b->brand_srl] ?? 0)) }}</small>
			</span>
		</a>
		@endforeach
	</div>
	@if (empty($brands))
	<p class="pm-empty">{{ lang('commerce.br_empty') }}</p>
	@endif
	<script>
	(function () {
		var base = {!! json_encode(html_entity_decode($br_list_url)) !!};
		var btn = document.getElementById('brCreate');
		btn.addEventListener('click', function () {
			var name = prompt({!! json_encode(lang('commerce.br_ask_name')) !!}, '');
			if (name === null) return;
			btn.disabled = true;
			exec_json('commerce.procCommerceAdminCreateBrand', { name: name }, function (res) {
				location.href = base + (base.indexOf('?') < 0 ? '?' : '&') + 'brand_srl=' + res.brand_srl;
			}, function () { btn.disabled = false; });
		});
		var box = document.getElementById('brCards'), drag = null, state = document.getElementById('brOrderState');
		box.addEventListener('dragstart', function (e) { var c = e.target.closest('.br-card'); if (!c) return; drag = c; c.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; });
		box.addEventListener('dragend', function () { if (drag) drag.classList.remove('is-drag'); drag = null; box.querySelectorAll('.is-over').forEach(function (c) { c.classList.remove('is-over'); }); });
		box.addEventListener('dragover', function (e) {
			if (!drag) return; e.preventDefault();
			box.querySelectorAll('.is-over').forEach(function (c) { c.classList.remove('is-over'); });
			var c = e.target.closest('.br-card'); if (c && c !== drag) c.classList.add('is-over');
		});
		box.addEventListener('drop', function (e) {
			e.preventDefault();
			var c = e.target.closest('.br-card');
			if (!drag || !c || c === drag) return;
			box.insertBefore(drag, c);
			var srls = [].map.call(box.querySelectorAll('.br-card'), function (x) { return x.dataset.srl; });
			state.textContent = {!! json_encode(lang('commerce.cfg_live_busy')) !!};
			exec_json('commerce.procCommerceAdminReorderBrands', { brand_srls: JSON.stringify(srls) }, function () {
				state.textContent = {!! json_encode(lang('commerce.br_order_saved')) !!};
			});
		});
	})();
	</script>
@else
	@php
	$be = $brand_edit;
	$br_brand_names = [];
	foreach ($brands as $b) { $br_brand_names[(int)$b->brand_srl] = (string)$b->name; }
	$br_status_label = ['sale' => lang('commerce.st_item_sale'), 'soldout' => lang('commerce.st_item_soldout')];
	$br_data = [];
	$br_picked = [];
	foreach ($brand_all_items as $bi)
	{
		$br_price = ($bi->sale_price > 0 && $bi->sale_price < $bi->price) ? $bi->sale_price : $bi->price;
		$br_other = (int)($bi->brand_srl ?? 0);
		if ($br_other === (int)$be->brand_srl) { $br_picked[] = (int)$bi->item_srl; }
		$br_data[] = [
			'srl' => (int)$bi->item_srl,
			'name' => (string)$bi->item_name,
			'thumb' => (string)($bi->thumb ?? ''),
			'cat' => (int)$bi->category_srl,
			'other' => ($br_other > 0 && $br_other !== (int)$be->brand_srl) ? ($br_brand_names[$br_other] ?? '') : '',
			'meta' => shop_money_base($br_price) . ' · ' . ($br_status_label[$bi->status] ?? $bi->status),
		];
	}
	$br_data_json = json_encode($br_data, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG + JSON_HEX_AMP);
	$br_picked_json = json_encode($br_picked);
	$br_view_url = getUrl('', 'mid', $shop_mid, 'v', 'list', 'brand', $be->slug ?: $be->brand_srl, 'zmc_preview', 'Y');
	@endphp
	<div class="pm-head">
		<a href="{{ $br_list_url }}" class="rsva-btn rsva-btn-sm">← {{ lang('commerce.br_back') }}</a>
	</div>
	<div class="pm-edit">
	<div>
	<form action="{{ getUrl('') }}" method="post" id="brForm">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminSaveBrand" />
		<input type="hidden" name="success_return_url" value="{{ $_SERVER['REQUEST_URI'] ?? '' }}" />
		<input type="hidden" name="brand_srl" value="{{ $be->brand_srl }}" />
		<input type="hidden" name="item_srls" id="brItems" value="" />

		<section class="pm-sec">
			<h3>{{ lang('commerce.pm_sec_basic') }}</h3>
			<div class="pm-row">
				<label for="brName">{{ lang('commerce.br_name') }}</label>
				<input type="text" name="name" id="brName" required maxlength="100" value="{{ $be->name }}" />
			</div>
			<div class="pm-row">
				<label for="brNameEn">{{ lang('commerce.br_name_en') }}</label>
				<div><input type="text" name="name_en" id="brNameEn" maxlength="100" value="{{ $be->name_en }}" /><p class="pm-hint">{{ lang('commerce.br_name_en_hint') }}</p></div>
			</div>
			<div class="pm-row">
				<label>{{ lang('commerce.pm_open') }}</label>
				<div>
					<input type="hidden" name="is_visible" id="brVisible" value="{{ $be->is_visible === 'N' ? 'N' : 'Y' }}" />
					<div class="pm-seg" data-for="brVisible">
						<button type="button" data-v="Y" class="{{ $be->is_visible !== 'N' ? 'is-on' : '' }}">{{ lang('commerce.pm_open_y') }}</button>
						<button type="button" data-v="N" class="{{ $be->is_visible === 'N' ? 'is-on' : '' }}">{{ lang('commerce.pm_open_n') }}</button>
					</div>
					<p class="pm-hint">{{ lang('commerce.br_open_hint') }}</p>
				</div>
			</div>
			<details>
				<summary>{{ lang('commerce.br_more') }}</summary>
				<div class="pm-row" style="margin-top:8px">
					<label for="brSlug">{{ lang('commerce.pm_slug') }}</label>
					<div><input type="text" name="slug" id="brSlug" maxlength="100" value="{{ $be->slug }}" /><p class="pm-hint">{{ lang('commerce.pm_slug_hint') }}</p></div>
				</div>
			</details>
		</section>

		<section class="pm-sec">
			<h3>{{ lang('commerce.br_sec_look') }}</h3>
			<div class="pm-row">
				<label>{{ lang('commerce.admin_brand_logo') }}</label>
				<div>
					<div class="pm-img" data-img="brLogo">
						<span class="pm-img-thumb is-round"></span>
						<input type="hidden" name="logo_url" id="brLogo" value="{{ $be->logo }}" />
						<input type="file" accept="image/*" hidden />
						<button type="button" class="rsva-btn rsva-btn-sm" data-pick>{{ lang('commerce.pm_pick') }}</button>
						<button type="button" class="rsva-btn rsva-btn-sm" data-clear>{{ lang('commerce.admin_item_edit_170') }}</button>
					</div>
					<p class="pm-hint">{{ lang('commerce.br_logo_hint') }}</p>
				</div>
			</div>
			<div class="pm-row">
				<label>{{ lang('commerce.br_cover') }}</label>
				<div>
					<div class="pm-img" data-img="brCover">
						<span class="pm-img-thumb br-cover-thumb"></span>
						<input type="hidden" name="cover_url" id="brCover" value="{{ $be->cover }}" />
						<input type="file" accept="image/*" hidden />
						<button type="button" class="rsva-btn rsva-btn-sm" data-pick>{{ lang('commerce.pm_pick') }}</button>
						<button type="button" class="rsva-btn rsva-btn-sm" data-clear>{{ lang('commerce.admin_item_edit_170') }}</button>
					</div>
					<p class="pm-hint">{{ lang('commerce.br_cover_hint') }}</p>
				</div>
			</div>
			<div class="pm-row">
				<label for="brDesc">{{ lang('commerce.admin_brand_desc') }}</label>
				<textarea name="description" id="brDesc" rows="3" maxlength="2000">{{ $be->description }}</textarea>
			</div>
		</section>

		<section class="pm-sec">
			<h3>{{ lang('commerce.br_sec_items') }} <small id="pmCount"></small></h3>
			<ol class="pm-picked" id="pmPicked" data-empty="{{ lang('commerce.br_items_empty') }}"></ol>
			<div class="pm-finder">
				<div class="pm-finder-bar" style="grid-template-columns:minmax(0,1fr) 150px 130px">
					<input type="search" id="pmQ" placeholder="{{ lang('commerce.pm_search_ph') }}" />
					<select id="pmCat">
						<option value="0">{{ lang('commerce.pm_all_categories') }}</option>
						@foreach ($brand_categories as $pc)
						<option value="{{ $pc->category_srl }}">{{ str_repeat('· ', (int)($pc->depth ?? 0)) }}{{ $pc->title }}</option>
						@endforeach
					</select>
					<select id="pmWho">
						<option value="free">{{ lang('commerce.br_who_free') }}</option>
						<option value="all">{{ lang('commerce.br_who_all') }}</option>
					</select>
				</div>
				<ul class="pm-found" id="pmFound"></ul>
				<div class="pm-found-foot">
					<span id="pmFoundCount"></span>
					<button type="button" class="rsva-btn rsva-btn-sm" id="pmAddAll"></button>
				</div>
			</div>
		</section>

		<div class="pm-savebar">
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_item_edit_159') }}</button>
			<span class="pm-grow"></span>
			<button type="button" class="rsva-btn rsva-btn-danger" id="brDelete">{{ lang('commerce.admin_brand_delete') }}</button>
		</div>
	</form>
	<form action="{{ getUrl('') }}" method="post" id="brDeleteForm" hidden>
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminDeleteBrand" />
		<input type="hidden" name="brand_srl" value="{{ $be->brand_srl }}" />
	</form>
	</div>

	<aside class="pm-view" aria-label="{{ lang('commerce.cfg_live_title') }}">
		<div class="pm-view-bar">
			<b>{{ lang('commerce.cfg_live_title') }}</b>
			<span class="pm-view-state" id="pmState">{{ lang('commerce.cfg_live_hint') }}</span>
			<div class="pm-seg" id="pmDev">
				<button type="button" data-w="100%" class="is-on">PC</button>
				<button type="button" data-w="390px">{{ lang('commerce.cfg_live_mobile') }}</button>
			</div>
		</div>
		<div class="pm-view-frame"><iframe id="pmFrame" title="{{ lang('commerce.cfg_live_title') }}" src="{{ $br_view_url }}"></iframe></div>
	</aside>
	</div>

	<script>
	(function () {
		var ITEMS = {!! $br_data_json !!};
		var picked = {!! $br_picked_json !!};
		var T = {
			busy: {!! json_encode(lang('commerce.cfg_live_busy')) !!},
			ready: {!! json_encode(lang('commerce.cfg_live_ready')) !!},
			fail: {!! json_encode(lang('commerce.cfg_live_fail')) !!},
			count: {!! json_encode(lang('commerce.br_count')) !!},
			found: {!! json_encode(lang('commerce.pm_found')) !!},
			addAll: {!! json_encode(lang('commerce.pm_add_all')) !!},
			added: {!! json_encode(lang('commerce.pm_added')) !!},
			remove: {!! json_encode(lang('commerce.pm_remove')) !!},
			add: {!! json_encode(lang('commerce.pm_add')) !!},
			move: {!! json_encode(lang('commerce.br_move_from')) !!},
			delAsk: {!! json_encode(lang('commerce.admin_brand_delete_confirm')) !!},
			upFail: {!! json_encode(lang('commerce.msg_shop_upload_failed')) !!}
		};
		var byId = {};
		ITEMS.forEach(function (it) { byId[it.srl] = it; });
		var $ = function (id) { return document.getElementById(id); };
		var form = $('brForm');
		function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
		function row(it, showOther) {
			var th = el('span', 'pm-th');
			if (it.thumb) th.style.backgroundImage = "url('" + it.thumb.replace(/'/g, '%27') + "')";
			var nm = el('span', 'pm-nm', it.name);
			nm.appendChild(el('small', '', it.meta));
			var out = [th, nm];
			if (showOther && it.other) out.push(el('span', 'br-other', T.move.replace('%s', it.other)));
			return out;
		}

		function drawPicked() {
			var list = $('pmPicked');
			list.textContent = '';
			picked.forEach(function (srl) {
				var it = byId[srl]; if (!it) return;
				var li = el('li');
				li.style.cursor = 'default';
				row(it, false).forEach(function (n) { li.appendChild(n); });
				var x = el('button', 'pm-x', '×');
				x.type = 'button'; x.title = T.remove; x.setAttribute('aria-label', T.remove + ': ' + it.name);
				x.addEventListener('click', function () { picked.splice(picked.indexOf(srl), 1); changed(); });
				li.appendChild(x);
				list.appendChild(li);
			});
			$('pmCount').textContent = T.count.replace('%d', picked.length);
		}

		var found = [];
		function drawFound() {
			var q = $('pmQ').value.trim().toLowerCase(), cat = +$('pmCat').value, all = $('pmWho').value === 'all';
			found = ITEMS.filter(function (it) {
				return picked.indexOf(it.srl) === -1 && (all || !it.other) && (!q || it.name.toLowerCase().indexOf(q) !== -1) && (!cat || it.cat === cat);
			});
			var box = $('pmFound');
			box.textContent = '';
			found.slice(0, 80).forEach(function (it) {
				var li = el('li');
				row(it, true).forEach(function (n) { li.appendChild(n); });
				var b = el('button', 'pm-add', '+');
				b.type = 'button'; b.setAttribute('aria-label', T.add + ': ' + it.name);
				b.addEventListener('click', function () { picked.push(it.srl); changed(); });
				li.appendChild(b);
				box.appendChild(li);
			});
			$('pmFoundCount').textContent = T.found.replace('%d', found.length);
			$('pmAddAll').disabled = !found.length;
			$('pmAddAll').textContent = T.addAll.replace('%d', found.length);
		}
		['pmQ', 'pmCat', 'pmWho'].forEach(function (id) { $(id).addEventListener('input', drawFound); $(id).addEventListener('change', drawFound); });
		$('pmAddAll').addEventListener('click', function () { found.forEach(function (it) { picked.push(it.srl); }); changed(); });

		document.querySelectorAll('.pm-seg[data-for]').forEach(function (seg) {
			seg.addEventListener('click', function (e) {
				var b = e.target.closest('button'); if (!b) return;
				$(seg.dataset.for).value = b.dataset.v;
				seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				later();
			});
		});

		var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
		document.querySelectorAll('.pm-img').forEach(function (box) {
			var hidden = $(box.dataset.img), file = box.querySelector('input[type=file]'), thumb = box.querySelector('.pm-img-thumb');
			function show() { thumb.style.backgroundImage = hidden.value ? "url('" + hidden.value.replace(/'/g, '%27') + "')" : ''; box.querySelector('[data-clear]').hidden = !hidden.value; }
			box.querySelector('[data-pick]').addEventListener('click', function () { file.click(); });
			box.querySelector('[data-clear]').addEventListener('click', function () { hidden.value = ''; show(); later(); });
			file.addEventListener('change', function () {
				var f = file.files && file.files[0]; if (!f) return;
				var fd = new FormData(); fd.append('file', f);
				fetch('./?module=commerce&act=procCommerceAdminUploadBanner', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } })
					.then(function (r) { return r.json(); })
					.then(function (res) { if (res && !res.error && res.url) { hidden.value = res.url; show(); later(); } else alert(res && res.message ? res.message : T.upFail); })
					.catch(function () { alert(T.upFail); });
				file.value = '';
			});
			show();
		});

		form.addEventListener('submit', function () { $('brItems').value = JSON.stringify(picked); });
		$('brDelete').addEventListener('click', function () { if (confirm(T.delAsk)) $('brDeleteForm').submit(); });

		var frame = $('pmFrame'), state = $('pmState'), timer = null, scrollY = 0;
		frame.addEventListener('load', function () { try { frame.contentWindow.scrollTo(0, scrollY); } catch (e) {} });
		function send() {
			state.textContent = T.busy; state.classList.add('is-busy');
			exec_json('commerce.procCommerceAdminPreviewBrand', {
				brand_srl: {{ (int)$be->brand_srl }}, name: $('brName').value, name_en: $('brNameEn').value,
				description: $('brDesc').value, logo: $('brLogo').value, cover: $('brCover').value,
				item_srls: JSON.stringify(picked)
			}, function () {
				try { scrollY = frame.contentWindow.scrollY; } catch (e) { scrollY = 0; }
				frame.contentWindow.location.reload();
				state.textContent = T.ready; state.classList.remove('is-busy');
			}, function () { state.textContent = T.fail; });
		}
		function later() { clearTimeout(timer); timer = setTimeout(send, 700); }
		function changed() { drawPicked(); drawFound(); later(); }
		form.addEventListener('input', function (e) { if (!e.target.closest('.pm-finder')) later(); });
		$('pmDev').addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b) return;
			$('pmDev').querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
			frame.style.width = b.dataset.w;
		});
		drawPicked(); drawFound(); send();
	})();
	</script>
@endif
</div>
