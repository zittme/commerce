@include('_tabs')
@php
$sd = $sc_design;
$sd_sections_json = json_encode($sd['sections'], JSON_UNESCAPED_UNICODE);
$sd_banners_json = json_encode($sd['banners'], JSON_UNESCAPED_SLASHES);
$sd_labels_json = json_encode($sc_section_labels, JSON_UNESCAPED_UNICODE);
$sd_text_json = json_encode(['upFail' => lang('commerce.sc_msg_upload_fail'), 'up' => lang('commerce.sc_up'), 'down' => lang('commerce.sc_down'), 'remove' => lang('commerce.sc_remove'), 'busy' => lang('commerce.cfg_live_busy'), 'ready' => lang('commerce.cfg_live_ready'), 'fail' => lang('commerce.cfg_live_fail')], JSON_UNESCAPED_UNICODE);
@endphp
<style>
.sd-wrap { display: grid; grid-template-columns: minmax(340px, 440px) 1fr; gap: 20px; align-items: start; }
.sd-view { position: sticky; top: 76px; }
.sd-frame { height: calc(100vh - 150px); min-height: 480px; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: 6px; overflow: hidden; background: #fff; }
.sd-frame iframe { width: 100%; height: 100%; border: 0; }
.sd-state { font-size: 12.5px; color: var(--zmc-sub, #7a7f8c); margin-bottom: 6px; }
.sd-img { display: flex; align-items: center; gap: 10px; }
.sd-img-thumb { width: 64px; height: 64px; border-radius: 6px; border: 1px solid var(--zmc-line, #e6e3dc); background: #f4f3ef center / cover no-repeat; flex: none; }
.sd-list { list-style: none; margin: 0; padding: 0; }
.sd-list li { display: flex; align-items: center; gap: 8px; padding: 8px 0; border-bottom: 1px solid var(--zmc-line, #e6e3dc); }
.sd-list li span { flex: 1; }
.sd-list li .sd-bthumb { width: 96px; height: 40px; border-radius: 4px; background: #f4f3ef center / cover no-repeat; flex: none; }
.sd-items { max-height: 260px; overflow-y: auto; border: 1px solid var(--zmc-line, #e6e3dc); border-radius: 6px; padding: 6px 10px; }
.sd-items label { display: flex !important; align-items: center; gap: 8px; font-weight: 500 !important; margin: 4px 0 !important; }
@media (max-width: 1100px) { .sd-wrap { grid-template-columns: 1fr; } .sd-view { position: static; } }
</style>

<div class="rsva">
	<div class="sd-wrap">
		<form id="sdForm" action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="commerce" />
			<input type="hidden" name="act" value="procCommerceSellerCenterSaveDesign" />
			<input type="hidden" name="sections_json" id="sdSections" value="{{ $sd_sections_json }}" />
			<input type="hidden" name="banners_json" id="sdBanners" value="{{ $sd_banners_json }}" />

			<div class="rsva-panel">
				<h3>{{ lang('commerce.sc_design_head') }}</h3>
				<div class="rsva-field"><label>{{ lang('commerce.sc_design_color') }}</label><input type="color" name="color" value="{{ $sd['color'] }}" style="width:80px;height:38px;padding:2px" /></div>
				<div class="rsva-field">
					<label>{{ lang('commerce.sc_design_head_style') }}</label>
					<label style="display:inline-flex;gap:6px;font-weight:500;margin-right:14px"><input type="radio" name="head" value="banner" @checked($sd['head'] === 'banner') /> {{ lang('commerce.sc_head_banner') }}</label>
					<label style="display:inline-flex;gap:6px;font-weight:500"><input type="radio" name="head" value="simple" @checked($sd['head'] === 'simple') /> {{ lang('commerce.sc_head_simple') }}</label>
				</div>
				<div class="rsva-field">
					<label>{{ lang('commerce.sc_design_logo') }}</label>
					<div class="sd-img" data-img="logo"><span class="sd-img-thumb"></span><input type="hidden" name="logo" value="{{ $sd['logo'] }}" /><input type="file" accept="image/*" hidden /><button type="button" class="rsva-btn rsva-btn-sm" data-pick>{{ lang('commerce.sc_pick_image') }}</button><button type="button" class="rsva-btn rsva-btn-sm" data-clear>{{ lang('commerce.sc_remove') }}</button></div>
				</div>
				<div class="rsva-field">
					<label>{{ lang('commerce.sc_design_cover') }}</label>
					<div class="sd-img" data-img="cover"><span class="sd-img-thumb"></span><input type="hidden" name="cover" value="{{ $sd['cover'] }}" /><input type="file" accept="image/*" hidden /><button type="button" class="rsva-btn rsva-btn-sm" data-pick>{{ lang('commerce.sc_pick_image') }}</button><button type="button" class="rsva-btn rsva-btn-sm" data-clear>{{ lang('commerce.sc_remove') }}</button></div>
				</div>
			</div>

			<div class="rsva-panel">
				<h3>{{ lang('commerce.sc_design_banners') }}</h3>
				<ul class="sd-list" id="sdBannerList"></ul>
				<input type="file" accept="image/*" id="sdBannerFile" hidden />
				<p style="margin:10px 0 0"><button type="button" class="rsva-btn rsva-btn-sm" id="sdBannerAdd">{{ lang('commerce.sc_banner_add') }}</button> <small>{{ lang('commerce.sc_banner_desc') }}</small></p>
			</div>

			<div class="rsva-panel">
				<h3>{{ lang('commerce.sc_design_sections') }}</h3>
				<ul class="sd-list" id="sdSectionList"></ul>
				<div class="rsva-form-grid" style="margin-top:12px">
					<div>
						<label>{{ lang('commerce.sc_design_image_size') }}</label>
						<select name="image_size">
							@foreach (['S' => lang('commerce.sc_size_s'), 'M' => lang('commerce.sc_size_m'), 'L' => lang('commerce.sc_size_l')] as $sd_k => $sd_v)
							<option value="{{ $sd_k }}" @selected($sd['image_size'] === $sd_k)>{{ $sd_v }}</option>
							@endforeach
						</select>
					</div>
					<div><label>{{ lang('commerce.sc_design_count') }}</label><input type="number" name="count" min="4" max="24" value="{{ $sd['count'] }}" /></div>
				</div>
			</div>

			<div class="rsva-panel">
				<h3>{{ lang('commerce.sc_design_featured') }}</h3>
				@if (count($sc_items))
				<div class="sd-items">
					@foreach ($sc_items as $sd_it)
					<label><input type="checkbox" name="featured[]" value="{{ $sd_it->item_srl }}" @checked(in_array((int)$sd_it->item_srl, $sd['featured'], true)) /> {{ $sd_it->item_name }}</label>
					@endforeach
				</div>
				@else
				<div class="rsva-empty">{{ lang('commerce.sc_no_items') }}</div>
				@endif
			</div>

			<div class="rsva-panel">
				<h3>{{ lang('commerce.sc_design_notice') }}</h3>
				<div class="rsva-field"><textarea name="notice" rows="4" maxlength="1000">{{ $sd['notice'] }}</textarea></div>
				<small>{{ lang('commerce.sc_design_notice_desc') }}</small>
			</div>

			<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.mk_save') }}</button>
		</form>

		<div class="sd-view">
			@if ($sc_preview_url !== '')
			<div class="sd-state" id="sdState"></div>
			@if (count($sc_preview_items))
			<div class="rsva-inline" style="margin-bottom:8px">
				<select id="sdPreviewPage" style="flex:1">
					<option value="{{ $sc_preview_url }}">{{ lang('commerce.sc_store_home') }}</option>
					@foreach ($sc_preview_items as $sd_pv)
					<option value="{{ $sd_pv->url }}">{{ lang('commerce.sc_preview_item') }}: {{ $sd_pv->item_name }}</option>
					@endforeach
				</select>
			</div>
			@endif
			<div class="sd-frame"><iframe id="sdFrame" src="{{ $sc_preview_url }}" title="{{ lang('commerce.sc_preview') }}"></iframe></div>
			@else
			<div class="rsva-panel"><p style="margin:0">{{ lang('commerce.sc_need_shop_id_desc') }}</p></div>
			@endif
		</div>
	</div>
</div>

<script>
(function () {
	var form = document.getElementById('sdForm');
	if (!form) return;
	var LABELS = {!! $sd_labels_json !!};
	var T = {!! $sd_text_json !!};
	var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
	var sections = JSON.parse(document.getElementById('sdSections').value || '[]');
	var banners = JSON.parse(document.getElementById('sdBanners').value || '[]');
	var frame = document.getElementById('sdFrame');
	var state = document.getElementById('sdState');
	var timer = null;

	function upload(file, done) {
		var fd = new FormData(); fd.append('file', file);
		fetch('./?module=commerce&act=procCommerceSellerCenterUpload', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf }, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (res) { if (res && !res.error && res.url) { done(res.url); } else { alert(res && res.message ? res.message : T.upFail); } })
			.catch(function () { alert(T.upFail); });
	}
	function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text) e.textContent = text; return e; }
	function btn(text, fn) { var b = el('button', 'rsva-btn rsva-btn-sm', text); b.type = 'button'; b.addEventListener('click', fn); return b; }
	function move(list, i, d) { var j = i + d; if (j < 0 || j >= list.length) return; var t = list[i]; list[i] = list[j]; list[j] = t; }

	function drawSections() {
		var ul = document.getElementById('sdSectionList'); ul.innerHTML = '';
		sections.forEach(function (s, i) {
			var li = el('li');
			var cb = document.createElement('input'); cb.type = 'checkbox'; cb.checked = !!s.on;
			cb.addEventListener('change', function () { s.on = cb.checked; sync(); });
			li.appendChild(cb);
			li.appendChild(el('span', '', LABELS[s.key] || s.key));
			li.appendChild(btn(T.up, function () { move(sections, i, -1); drawSections(); sync(); }));
			li.appendChild(btn(T.down, function () { move(sections, i, 1); drawSections(); sync(); }));
			ul.appendChild(li);
		});
	}
	function drawBanners() {
		var ul = document.getElementById('sdBannerList'); ul.innerHTML = '';
		banners.forEach(function (url, i) {
			var li = el('li');
			var th = el('span', 'sd-bthumb'); th.style.backgroundImage = "url('" + url.replace(/'/g, '%27') + "')";
			li.appendChild(th); li.appendChild(el('span'));
			li.appendChild(btn(T.up, function () { move(banners, i, -1); drawBanners(); sync(); }));
			li.appendChild(btn(T.down, function () { move(banners, i, 1); drawBanners(); sync(); }));
			li.appendChild(btn(T.remove, function () { banners.splice(i, 1); drawBanners(); sync(); }));
			ul.appendChild(li);
		});
		document.getElementById('sdBannerAdd').hidden = banners.length >= 5;
	}
	function sync() {
		document.getElementById('sdSections').value = JSON.stringify(sections);
		document.getElementById('sdBanners').value = JSON.stringify(banners);
		later();
	}
	function send() {
		if (!frame) return;
		var fd = new FormData(form), data = {}, featured = [];
		fd.forEach(function (v, k) {
			if (k === 'featured[]') { featured.push(v); }
			else if (typeof v === 'string' && k !== 'act' && k !== 'module') { data[k] = v; }
		});
		data.featured = featured;
		if (state) state.textContent = T.busy;
		exec_json('commerce.procCommerceSellerCenterPreviewDesign', data, function () {
			frame.contentWindow.location.reload();
			if (state) state.textContent = T.ready;
		}, function () { if (state) state.textContent = T.fail; });
	}
	function later() { clearTimeout(timer); timer = setTimeout(send, 600); }

	document.querySelectorAll('.sd-img').forEach(function (box) {
		var hidden = box.querySelector('input[type=hidden]'), file = box.querySelector('input[type=file]'), thumb = box.querySelector('.sd-img-thumb');
		function show() { thumb.style.backgroundImage = hidden.value ? "url('" + hidden.value.replace(/'/g, '%27') + "')" : ''; }
		box.querySelector('[data-pick]').addEventListener('click', function () { file.click(); });
		box.querySelector('[data-clear]').addEventListener('click', function () { hidden.value = ''; show(); later(); });
		file.addEventListener('change', function () {
			var f = file.files && file.files[0]; if (!f) return;
			upload(f, function (url) { hidden.value = url; show(); later(); });
			file.value = '';
		});
		show();
	});
	var bf = document.getElementById('sdBannerFile');
	document.getElementById('sdBannerAdd').addEventListener('click', function () { bf.click(); });
	bf.addEventListener('change', function () {
		var f = bf.files && bf.files[0]; if (!f) return;
		upload(f, function (url) { banners.push(url); drawBanners(); sync(); });
		bf.value = '';
	});
	var pageSel = document.getElementById('sdPreviewPage');
	if (pageSel && frame) { pageSel.addEventListener('change', function () { frame.src = pageSel.value; }); }
	form.addEventListener('input', later);
	form.addEventListener('change', later);
	drawSections();
	drawBanners();
	send();
})();
</script>
