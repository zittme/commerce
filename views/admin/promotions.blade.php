@include('_tabs')
@include('_langfield_assets')

@include('_studio_style')

@php
$pm_list_url = getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'promotions');
@endphp

<div class="rsva pm">
@if (!$promo_edit)
	<div class="pm-head">
		<p>{{ lang('commerce.pm_list_hint') }}</p>
	</div>
	<div class="pm-cards">
		<button type="button" class="pm-card pm-card-new" id="pmCreate"><i aria-hidden="true">+</i>{{ lang('commerce.pm_new') }}</button>
		@foreach ($promotions as $pm)
		@php
		$pm_state = 'running';
		if (($pm->status ?? 'Y') !== 'Y') { $pm_state = 'hidden'; }
		elseif (!empty($pm->start_date) && $promo_now < $pm->start_date) { $pm_state = 'upcoming'; }
		elseif (!empty($pm->end_date) && $promo_now > $pm->end_date) { $pm_state = 'ended'; }
		$pm_state_label = ['running' => lang('commerce.admin_promotions_8'), 'upcoming' => lang('commerce.admin_promotions_9'), 'ended' => lang('commerce.admin_promotions_10'), 'hidden' => lang('commerce.admin_promotions_11')][$pm_state];
		$pm_card = $promo_cards[(int)$pm->promo_srl] ?? null;
		$pm_bn = $pm_card ? $pm_card->banner : [];
		@endphp
		<a class="pm-card" href="{{ getUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'promotions', 'promo_srl', $pm->promo_srl) }}">
			<span class="pm-card-bn {{ ($pm_bn['shadow'] ?? 'Y') !== 'N' ? 'is-shadow' : '' }}" style="{{ $pm_bn['bg_style'] ?? '' }};color:{{ $pm_bn['text_color'] ?? '#ffffff' }}">
				<span class="pm-state is-{{ $pm_state }}">{{ $pm_state_label }}</span>
				<strong>{!! $pm_bn['title_html'] ?? escape($pm->title) !!}</strong>
			</span>
			<span class="pm-card-body">
				<b>{{ $pm->title }}</b>
				<small>{{ $pm->start_date ? zdate($pm->start_date, 'm.d') : '' }}~{{ $pm->end_date ? zdate($pm->end_date, 'm.d') : '' }} · {{ sprintf(lang('commerce.st_unit_ea'), $pm_card ? $pm_card->count : 0) }}</small>
			</span>
		</a>
		@endforeach
	</div>
	@if (empty($promotions))
	<p class="pm-empty">{{ lang('commerce.pm_empty') }}</p>
	@endif
	<script>
	(function () {
		var btn = document.getElementById('pmCreate');
		var base = {!! json_encode(html_entity_decode($pm_list_url)) !!};
		btn.addEventListener('click', function () {
			var title = prompt({!! json_encode(lang('commerce.pm_ask_title')) !!}, {!! json_encode(lang('commerce.pm_default_title')) !!});
			if (title === null) return;
			btn.disabled = true;
			exec_json('commerce.procCommerceAdminCreatePromotion', { title: title }, function (res) {
				location.href = base + (base.indexOf('?') < 0 ? '?' : '&') + 'promo_srl=' + res.promo_srl;
			}, function () { btn.disabled = false; });
		});
	})();
	</script>
@else
	@php
	$pm_bn = json_decode((string)$promo_edit->banner, true);
	$pm_bn = is_array($pm_bn) ? $pm_bn : [];
	$pm_bg_type = $pm_bn['bg_type'] ?? (!empty($pm_bn['image']) ? 'image' : 'gradient');
	$pm_start = !empty($promo_edit->start_date) ? substr($promo_edit->start_date, 0, 4) . '-' . substr($promo_edit->start_date, 4, 2) . '-' . substr($promo_edit->start_date, 6, 2) : '';
	$pm_end = !empty($promo_edit->end_date) ? substr($promo_edit->end_date, 0, 4) . '-' . substr($promo_edit->end_date, 4, 2) . '-' . substr($promo_edit->end_date, 6, 2) : '';
	$pm_cat_names = [];
	foreach ($promo_categories as $pc) { $pm_cat_names[(int)$pc->category_srl] = $pc->title; }
	$pm_data = [];
	foreach ($promo_all_items as $pi)
	{
		$pm_price = ($pi->sale_price > 0 && $pi->sale_price < $pi->price) ? $pi->sale_price : $pi->price;
		$pm_data[] = [
			'srl' => (int)$pi->item_srl,
			'name' => (string)$pi->item_name,
			'thumb' => (string)($pi->thumb ?? ''),
			'cat' => (int)$pi->category_srl,
			'brand' => (int)($pi->brand_srl ?? 0),
			'meta' => trim(($pi->brand_name ?? '') . ' ' . shop_money_base($pm_price) . ($pi->status === 'soldout' ? ' · ' . lang('commerce.st_item_soldout') : '')),
		];
	}
	$pm_data_json = json_encode($pm_data, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG + JSON_HEX_AMP);
	$pm_picked_json = json_encode(array_values($promo_edit_items ?? []));
	$pm_view_url = getUrl('', 'mid', $promo_shop_mid, 'v', 'promo', 'p', $promo_edit->slug, 'zmc_preview', 'Y');
	@endphp
	<div class="pm-head">
		<a href="{{ $pm_list_url }}" class="rsva-btn rsva-btn-sm">← {{ lang('commerce.pm_back') }}</a>
	</div>
	<div class="pm-edit">
	<div>
	<form action="{{ getUrl('') }}" method="post" id="pmForm">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminInsertPromotion" />
		<input type="hidden" name="success_return_url" value="{{ $_SERVER['REQUEST_URI'] ?? '' }}" />
		<input type="hidden" name="promo_srl" value="{{ $promo_edit->promo_srl }}" />
		<input type="hidden" name="banner" id="pmBanner" value="" />
		<input type="hidden" name="item_srls" id="pmItems" value="" />

		<section class="pm-sec">
			<h3>{{ lang('commerce.pm_sec_basic') }}</h3>
			<div class="pm-row">
				<label for="pmTitle">{{ lang('commerce.pm_title') }}</label>
				<div class="zlf-row-wrap"><input type="text" name="title" id="pmTitle" required maxlength="120" value="{{ $promo_edit->title_raw ?? $promo_edit->title }}" />@include('_langfield', ['lf_name' => 'title', 'lf_value' => $promo_edit->title_raw ?? $promo_edit->title, 'lf_key' => 'promo'])</div>
			</div>
			<div class="pm-row">
				<label>{{ lang('commerce.pm_period') }}</label>
				<div>
					<div class="pm-inline"><input type="date" name="start_date" id="pmStart" value="{{ $pm_start }}" /> ~ <input type="date" name="end_date" id="pmEnd" value="{{ $pm_end }}" /></div>
					<div class="pm-quick">
						<button type="button" data-days="7">{{ lang('commerce.pm_q_week') }}</button>
						<button type="button" data-days="14">{{ lang('commerce.pm_q_2week') }}</button>
						<button type="button" data-days="30">{{ lang('commerce.pm_q_month') }}</button>
						<button type="button" data-days="0">{{ lang('commerce.pm_q_none') }}</button>
					</div>
				</div>
			</div>
			<div class="pm-row">
				<label>{{ lang('commerce.pm_open') }}</label>
				<div>
					<input type="hidden" name="status" id="pmStatus" value="{{ ($promo_edit->status ?? 'Y') === 'N' ? 'N' : 'Y' }}" />
					<div class="pm-seg" data-for="pmStatus">
						<button type="button" data-v="Y" class="{{ ($promo_edit->status ?? 'Y') !== 'N' ? 'is-on' : '' }}">{{ lang('commerce.pm_open_y') }}</button>
						<button type="button" data-v="N" class="{{ ($promo_edit->status ?? 'Y') === 'N' ? 'is-on' : '' }}">{{ lang('commerce.pm_open_n') }}</button>
					</div>
					<p class="pm-hint">{{ lang('commerce.pm_open_hint') }}</p>
				</div>
			</div>
			<details>
				<summary>{{ lang('commerce.pm_more') }}</summary>
				<div class="pm-row" style="margin-top:8px">
					<label for="pmDesc">{{ lang('commerce.pm_desc') }}</label>
					<div class="zlf-row-wrap" style="align-items:flex-start"><textarea name="description" id="pmDesc" rows="2">{{ $promo_edit->description_raw ?? $promo_edit->description }}</textarea>@include('_langfield', ['lf_name' => 'description', 'lf_value' => $promo_edit->description_raw ?? $promo_edit->description])</div>
				</div>
				<div class="pm-row">
					<label for="pmSlug">{{ lang('commerce.pm_slug') }}</label>
					<div><input type="text" name="slug" id="pmSlug" value="{{ $promo_edit->slug }}" /><p class="pm-hint">{{ lang('commerce.pm_slug_hint') }}</p></div>
				</div>
			</details>
		</section>

		<section class="pm-sec">
			<h3>{{ lang('commerce.pm_sec_banner') }}</h3>
			<div class="pm-row">
				<label>{{ lang('commerce.admin_promotions_22') }}</label>
				<div class="pm-inline">
					<input type="hidden" id="pmBgType" value="{{ $pm_bg_type }}" />
					<div class="pm-seg" data-for="pmBgType">
						<button type="button" data-v="gradient" class="{{ $pm_bg_type === 'gradient' ? 'is-on' : '' }}">{{ lang('commerce.admin_promotions_23') }}</button>
						<button type="button" data-v="color" class="{{ $pm_bg_type === 'color' ? 'is-on' : '' }}">{{ lang('commerce.admin_promotions_24') }}</button>
						<button type="button" data-v="image" class="{{ $pm_bg_type === 'image' ? 'is-on' : '' }}">{{ lang('commerce.admin_promotions_25') }}</button>
					</div>
					<span class="pm-colors" id="pmColors">
						<input type="color" id="pmC1" value="{{ $pm_bn['bg_color'] ?? '#26345c' }}" aria-label="{{ lang('commerce.pm_color1') }}" />
						<input type="color" id="pmC2" value="{{ $pm_bn['bg_color2'] ?? '#151c33' }}" aria-label="{{ lang('commerce.pm_color2') }}" />
					</span>
				</div>
			</div>
			<div class="pm-row" id="pmImgRow">
				<label>{{ lang('commerce.admin_promotions_27') }}</label>
				<div class="pm-img" data-img="pmImg">
					<span class="pm-img-thumb"></span>
					<input type="hidden" id="pmImg" value="{{ $pm_bn['image'] ?? '' }}" />
					<input type="file" accept="image/*" hidden />
					<button type="button" class="rsva-btn rsva-btn-sm" data-pick>{{ lang('commerce.pm_pick') }}</button>
					<button type="button" class="rsva-btn rsva-btn-sm" data-clear>{{ lang('commerce.admin_item_edit_170') }}</button>
				</div>
			</div>
			<div class="pm-row">
				<label for="pmText">{{ lang('commerce.pm_banner_text') }}</label>
				<div><div class="zlf-row-wrap"><input type="text" id="pmText" value="{{ $pm_bn['text'] ?? '' }}" />@include('_langfield', ['lf_name' => 'banner_text', 'lf_value' => $pm_bn['text'] ?? '', 'lf_key' => 'promobn'])</div><p class="pm-hint">{{ lang('commerce.pm_banner_text_hint') }}</p></div>
			</div>
			<div class="pm-row">
				<label>{{ lang('commerce.admin_promotions_29') }}</label>
				<div class="pm-inline">
					<span class="pm-colors"><input type="color" id="pmTextColor" value="{{ $pm_bn['text_color'] ?? '#ffffff' }}" aria-label="{{ lang('commerce.admin_promotions_29') }}" /></span>
					<label class="pm-check"><input type="checkbox" id="pmShadow" @if (($pm_bn['shadow'] ?? 'Y') !== 'N') checked @endif /> {{ lang('commerce.admin_promotions_30') }}</label>
				</div>
			</div>
			<div class="pm-row">
				<label>{{ lang('commerce.pm_point') }}</label>
				<div>
					<div class="pm-img" data-img="pmPoint">
						<span class="pm-img-thumb"></span>
						<input type="hidden" id="pmPoint" value="{{ $pm_bn['point_image'] ?? '' }}" />
						<input type="file" accept="image/*" hidden />
						<button type="button" class="rsva-btn rsva-btn-sm" data-pick>{{ lang('commerce.pm_pick') }}</button>
						<button type="button" class="rsva-btn rsva-btn-sm" data-clear>{{ lang('commerce.admin_item_edit_170') }}</button>
					</div>
					<p class="pm-hint">{{ lang('commerce.pm_point_hint') }}</p>
				</div>
			</div>
			<div class="pm-row">
				<label for="pmAlign">{{ lang('commerce.cfg_align') }}</label>
				<div class="pm-inline">
					<select id="pmAlign">
						<option value="left" @if (($pm_bn['align'] ?? 'left') === 'left') selected @endif>{{ lang('commerce.cfg_align_left') }}</option>
						<option value="center" @if (($pm_bn['align'] ?? '') === 'center') selected @endif>{{ lang('commerce.cfg_align_center') }}</option>
						<option value="right" @if (($pm_bn['align'] ?? '') === 'right') selected @endif>{{ lang('commerce.cfg_align_right') }}</option>
					</select>
				</div>
			</div>
			<div class="pm-row">
				<label for="pmPointAlign">{{ lang('commerce.cfg_point_align') }}</label>
				<div class="pm-inline">
					<select id="pmPointAlign">
						<option value="right">{{ lang('commerce.cfg_align_right') }}</option>
						<option value="left" @if (($pm_bn['point_align'] ?? 'right') === 'left') selected @endif>{{ lang('commerce.cfg_align_left') }}</option>
					</select>
				</div>
			</div>
		</section>

		<section class="pm-sec">
			<h3>{{ lang('commerce.pm_sec_items') }} <small id="pmCount"></small></h3>
			<ol class="pm-picked" id="pmPicked" data-empty="{{ lang('commerce.pm_items_empty') }}"></ol>
			<div class="pm-finder">
				<div class="pm-finder-bar">
					<input type="search" id="pmQ" placeholder="{{ lang('commerce.pm_search_ph') }}" />
					<select id="pmCat">
						<option value="0">{{ lang('commerce.pm_all_categories') }}</option>
						@foreach ($promo_categories as $pc)
						<option value="{{ $pc->category_srl }}">{{ str_repeat('· ', (int)($pc->depth ?? 0)) }}{{ $pc->title }}</option>
						@endforeach
					</select>
					<select id="pmBrand">
						<option value="0">{{ lang('commerce.pm_all_brands') }}</option>
						@foreach ($promo_brands as $pb)
						<option value="{{ $pb->brand_srl }}">{{ $pb->name }}</option>
						@endforeach
					</select>
				</div>
				<ul class="pm-found" id="pmFound"></ul>
				<div class="pm-found-foot">
					<span id="pmFoundCount"></span>
					<button type="button" class="rsva-btn rsva-btn-sm" id="pmAddAll">{{ lang('commerce.pm_add_all') }}</button>
				</div>
			</div>
		</section>

		<section class="pm-sec">
			<h3>{{ lang('commerce.pm_sec_main') }}</h3>
			<div class="pm-row">
				<label>{{ lang('commerce.pm_main') }}</label>
				<div>
					<label class="pm-check"><input type="checkbox" id="pmMain" @if (($pm_bn['main'] ?? 'N') === 'Y') checked @endif /> {{ lang('commerce.pm_main_label') }}</label>
				</div>
			</div>
			<div class="pm-row">
				<label>{{ lang('commerce.pm_logo') }}</label>
				<div>
					<div class="pm-img" data-img="pmLogo">
						<span class="pm-img-thumb is-round"></span>
						<input type="hidden" id="pmLogo" value="{{ $pm_bn['logo'] ?? '' }}" />
						<input type="file" accept="image/*" hidden />
						<button type="button" class="rsva-btn rsva-btn-sm" data-pick>{{ lang('commerce.pm_pick') }}</button>
						<button type="button" class="rsva-btn rsva-btn-sm" data-clear>{{ lang('commerce.admin_item_edit_170') }}</button>
					</div>
					<p class="pm-hint">{{ lang('commerce.pm_logo_hint') }}</p>
				</div>
			</div>
		</section>

		<div class="pm-savebar">
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_item_edit_159') }}</button>
			<span class="pm-grow"></span>
			<button type="button" class="rsva-btn rsva-btn-danger" id="pmDelete">{{ lang('commerce.pm_delete') }}</button>
		</div>
	</form>
	<form action="{{ getUrl('') }}" method="post" id="pmDeleteForm" hidden>
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminDeletePromotion" />
		<input type="hidden" name="success_return_url" value="{{ $pm_list_url }}" />
		<input type="hidden" name="promo_srl" value="{{ $promo_edit->promo_srl }}" />
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
		<div class="pm-view-frame"><iframe id="pmFrame" title="{{ lang('commerce.cfg_live_title') }}" src="{{ $pm_view_url }}"></iframe></div>
	</aside>
	</div>

	<script>
	(function () {
		var ITEMS = {!! $pm_data_json !!};
		var picked = {!! $pm_picked_json !!};
		var T = {
			busy: {!! json_encode(lang('commerce.cfg_live_busy')) !!},
			ready: {!! json_encode(lang('commerce.cfg_live_ready')) !!},
			fail: {!! json_encode(lang('commerce.cfg_live_fail')) !!},
			count: {!! json_encode(lang('commerce.pm_count')) !!},
			found: {!! json_encode(lang('commerce.pm_found')) !!},
			added: {!! json_encode(lang('commerce.pm_added')) !!},
			remove: {!! json_encode(lang('commerce.pm_remove')) !!},
			add: {!! json_encode(lang('commerce.pm_add')) !!},
			logoFirst: {!! json_encode(lang('commerce.adm_promo_logo_first')) !!},
			delAsk: {!! json_encode(lang('commerce.adm_promo_delete_ask')) !!},
			upFail: {!! json_encode(lang('commerce.msg_shop_upload_failed')) !!}
		};
		var byId = {};
		ITEMS.forEach(function (it) { byId[it.srl] = it; });
		picked = picked.filter(function (s) { return byId[s]; });
		var $ = function (id) { return document.getElementById(id); };
		var form = $('pmForm');

		function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text != null) e.textContent = text; return e; }
		function row(it) {
			var th = el('span', 'pm-th');
			if (it.thumb) th.style.backgroundImage = "url('" + it.thumb.replace(/'/g, '%27') + "')";
			var nm = el('span', 'pm-nm', it.name);
			nm.appendChild(el('small', '', it.meta));
			return [th, nm];
		}

		var list = $('pmPicked'), dragSrl = null;
		function drawPicked() {
			list.textContent = '';
			picked.forEach(function (srl, i) {
				var it = byId[srl], li = el('li');
				li.draggable = true;
				li.dataset.srl = srl;
				li.appendChild(el('span', 'pm-grip', '⋮⋮'));
				li.appendChild(el('span', 'pm-no', String(i + 1)));
				row(it).forEach(function (n) { li.appendChild(n); });
				var x = el('button', 'pm-x', '×');
				x.type = 'button'; x.title = T.remove; x.setAttribute('aria-label', T.remove + ': ' + it.name);
				x.addEventListener('click', function () { picked.splice(picked.indexOf(srl), 1); changed(); });
				li.appendChild(x);
				list.appendChild(li);
			});
			$('pmCount').textContent = T.count.replace('%d', picked.length);
		}
		list.addEventListener('dragstart', function (e) { var li = e.target.closest('li'); if (!li) return; dragSrl = +li.dataset.srl; li.classList.add('is-drag'); e.dataTransfer.effectAllowed = 'move'; });
		list.addEventListener('dragend', function () { dragSrl = null; list.querySelectorAll('li').forEach(function (li) { li.classList.remove('is-drag', 'is-over'); }); });
		list.addEventListener('dragover', function (e) {
			if (dragSrl === null) return;
			e.preventDefault();
			list.querySelectorAll('li.is-over').forEach(function (li) { li.classList.remove('is-over'); });
			var li = e.target.closest('li'); if (li) li.classList.add('is-over');
		});
		list.addEventListener('drop', function (e) {
			e.preventDefault();
			var li = e.target.closest('li');
			if (dragSrl === null || !li) return;
			var to = +li.dataset.srl;
			if (to === dragSrl) return;
			picked.splice(picked.indexOf(dragSrl), 1);
			picked.splice(picked.indexOf(to), 0, dragSrl);
			changed();
		});

		var found = [];
		function drawFound() {
			var q = $('pmQ').value.trim().toLowerCase(), cat = +$('pmCat').value, brand = +$('pmBrand').value;
			found = ITEMS.filter(function (it) {
				return (!q || it.name.toLowerCase().indexOf(q) !== -1) && (!cat || it.cat === cat) && (!brand || it.brand === brand);
			});
			var box = $('pmFound');
			box.textContent = '';
			found.slice(0, 80).forEach(function (it) {
				var li = el('li');
				row(it).forEach(function (n) { li.appendChild(n); });
				var on = picked.indexOf(it.srl) !== -1;
				var b = el('button', 'pm-add', on ? T.added : '+');
				b.type = 'button'; b.disabled = on; b.setAttribute('aria-label', T.add + ': ' + it.name);
				b.addEventListener('click', function () { if (picked.indexOf(it.srl) === -1) { picked.push(it.srl); changed(); } });
				li.appendChild(b);
				box.appendChild(li);
			});
			var fresh = found.filter(function (it) { return picked.indexOf(it.srl) === -1; }).length;
			$('pmFoundCount').textContent = T.found.replace('%d', found.length);
			$('pmAddAll').disabled = !fresh;
			$('pmAddAll').textContent = {!! json_encode(lang('commerce.pm_add_all')) !!}.replace('%d', fresh);
		}
		['pmQ', 'pmCat', 'pmBrand'].forEach(function (id) { $(id).addEventListener('input', drawFound); $(id).addEventListener('change', drawFound); });
		$('pmAddAll').addEventListener('click', function () {
			found.forEach(function (it) { if (picked.indexOf(it.srl) === -1) picked.push(it.srl); });
			changed();
		});

		function ymd(d) { return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); }
		document.querySelectorAll('.pm-quick button').forEach(function (b) {
			b.addEventListener('click', function () {
				var days = +b.dataset.days;
				if (!days) { $('pmStart').value = ''; $('pmEnd').value = ''; }
				else {
					var s = $('pmStart').value ? new Date($('pmStart').value + 'T00:00:00') : new Date();
					var e = new Date(s); e.setDate(e.getDate() + days - 1);
					$('pmStart').value = ymd(s); $('pmEnd').value = ymd(e);
				}
				later();
			});
		});
		document.querySelectorAll('.pm-seg[data-for]').forEach(function (seg) {
			seg.addEventListener('click', function (e) {
				var b = e.target.closest('button'); if (!b) return;
				$(seg.dataset.for).value = b.dataset.v;
				seg.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
				bgUi(); later();
			});
		});
		function bgUi() {
			var t = $('pmBgType').value;
			$('pmImgRow').hidden = t !== 'image';
			$('pmC2').hidden = t !== 'gradient';
			$('pmColors').hidden = t === 'image';
		}

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

		function langValue(input) {
			var wrap = input.closest('.zlf-row-wrap'), code = wrap ? wrap.querySelector('[data-lf-code]') : null;
			return code && code.value.trim() !== '' ? '$user_lang->' + code.value.trim() : input.value.trim();
		}
		function banner() {
			return {
				bg_type: $('pmBgType').value, bg_color: $('pmC1').value, bg_color2: $('pmC2').value,
				image: $('pmImg').value.trim(), point_image: $('pmPoint').value.trim(), logo: $('pmLogo').value.trim(),
				main: $('pmMain').checked ? 'Y' : 'N', text_color: $('pmTextColor').value,
				shadow: $('pmShadow').checked ? 'Y' : 'N', text: langValue($('pmText')),
				align: $('pmAlign').value, point_align: $('pmPointAlign').value
			};
		}
		form.addEventListener('submit', function (e) {
			var bn = banner();
			if (bn.main === 'Y' && !bn.logo) { e.preventDefault(); alert(T.logoFirst); return; }
			$('pmBanner').value = JSON.stringify(bn);
			$('pmItems').value = JSON.stringify(picked);
		});
		$('pmDelete').addEventListener('click', function () { if (confirm(T.delAsk)) $('pmDeleteForm').submit(); });

		var frame = $('pmFrame'), state = $('pmState'), timer = null, scrollY = 0;
		frame.addEventListener('load', function () { try { frame.contentWindow.scrollTo(0, scrollY); } catch (e) {} });
		function send() {
			state.textContent = T.busy; state.classList.add('is-busy');
			exec_json('commerce.procCommerceAdminPreviewPromotion', {
				promo_srl: {{ (int)$promo_edit->promo_srl }}, title: langValue($('pmTitle')), description: langValue($('pmDesc')),
				start_date: $('pmStart').value, end_date: $('pmEnd').value,
				banner: JSON.stringify(banner()), item_srls: JSON.stringify(picked)
			}, function () {
				try { scrollY = frame.contentWindow.scrollY; } catch (e) { scrollY = 0; }
				frame.contentWindow.location.reload();
				state.textContent = T.ready; state.classList.remove('is-busy');
			}, function () { state.textContent = T.fail; });
		}
		function later() { clearTimeout(timer); timer = setTimeout(send, 700); }
		function changed() { drawPicked(); drawFound(); later(); }
		form.addEventListener('input', function (e) { if (!e.target.closest('.pm-finder')) later(); });
		form.addEventListener('change', function (e) { if (!e.target.closest('.pm-finder')) later(); });
		$('pmDev').addEventListener('click', function (e) {
			var b = e.target.closest('button'); if (!b) return;
			$('pmDev').querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-on', x === b); });
			frame.style.width = b.dataset.w;
		});

		bgUi(); drawPicked(); drawFound(); send();
	})();
	</script>
@endif
</div>
