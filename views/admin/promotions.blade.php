@include('_tabs')
@include('_langfield_assets')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_promotions_1') }}</h3>
		@if (empty($promotions))
		<p class="rsva-empty">{{ lang('commerce.admin_promotions_2') }}</p>
		@else
		<table class="rsva-table" style="margin-bottom:4px">
			<thead><tr><th>{{ lang('commerce.admin_promotions_3') }}</th><th>{{ lang('commerce.admin_promotions_4') }}</th><th>{{ lang('commerce.admin_promotions_5') }}</th><th>{{ lang('commerce.admin_promotions_6') }}</th><th>{{ lang('commerce.admin_promotions_7') }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($promotions as $pm)
				@php
				$pm_state = 'running';
				if (($pm->status ?? 'Y') !== 'Y') $pm_state = 'hidden';
				elseif (!empty($pm->start_date) && $promo_now < $pm->start_date) $pm_state = 'upcoming';
				elseif (!empty($pm->end_date) && $promo_now > $pm->end_date) $pm_state = 'ended';
				@endphp
				<tr>
					<td><strong>{{ $pm->title }}</strong></td>
					<td><small>?v=promo&amp;p={{ $pm->slug }}</small></td>
					<td><small>{{ $pm->start_date ? zdate($pm->start_date, 'Y.m.d') : '' }} ~ {{ $pm->end_date ? zdate($pm->end_date, 'Y.m.d') : '' }}</small></td>
					<td>
						@if ($pm_state === 'running')<span class="rsva-st rsva-st-confirmed">{{ lang('commerce.admin_promotions_8') }}</span>
						@elseif ($pm_state === 'upcoming')<span class="rsva-st rsva-st-pending">{{ lang('commerce.admin_promotions_9') }}</span>
						@elseif ($pm_state === 'ended')<span class="rsva-st rsva-st-cancelled">{{ lang('commerce.admin_promotions_10') }}</span>
						@else<span class="rsva-st">{{ lang('commerce.admin_promotions_11') }}</span>@endif
					</td>
					<td>{{ sprintf(lang('commerce.st_unit_ea'), count(\Zittme\Modules\Commerce\Models\Promotion::itemSrlsOf((int)$pm->promo_srl))) }}</td>
					<td style="white-space:nowrap">
						<a class="rsva-btn rsva-btn-sm" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminPromotions', 'promo_srl', $pm->promo_srl) }}">{{ lang('commerce.admin_promotions_12') }}</a>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ lang('commerce.adm_promo_delete_ask') }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procCommerceAdminDeletePromotion" />
							{{-- 저장한 뒤 보던 자리로 돌아온다. 없으면 쪽수와 검색 조건이 날아간다 --}}
							<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
							<input type="hidden" name="promo_srl" value="{{ $pm->promo_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_promotions_13') }}</button>
						</form>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
	</div>

	<div class="rsva-panel">
		<h3>{{ $promo_edit ? sprintf(lang('commerce.adm_promo_edit'), $promo_edit->title) : lang('commerce.adm_promo_new') }}
			@if ($promo_edit)
			<a class="rsva-btn rsva-btn-sm" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminPromotions') }}" style="margin-left:10px">{{ lang('commerce.admin_promotions_14') }}</a>
			@endif
		</h3>
		@php $pm_bn = $promo_edit ? json_decode((string)$promo_edit->banner, true) : []; $pm_bn = is_array($pm_bn) ? $pm_bn : []; @endphp
		<form action="{{ getUrl('') }}" method="post" id="zmcPromoForm">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminInsertPromotion" />
			<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
			@if ($promo_edit)<input type="hidden" name="promo_srl" value="{{ $promo_edit->promo_srl }}" />@endif
			<input type="hidden" name="banner" id="zmcPromoBanner" value="{{ $promo_edit->banner ?? '' }}" />
			<input type="hidden" name="item_srls" id="zmcPromoItems" value="" />

			<div class="rsva-form-grid" style="margin-bottom:14px">
				<div><label>{{ lang('commerce.admin_promotions_15') }}</label><div class="zlf-row-wrap"><input type="text" name="title" required value="{{ $promo_edit->title_raw ?? $promo_edit->title ?? '' }}" placeholder="{{ lang('commerce.admin_promotions_37') }}" />@include('_langfield', ['lf_name' => 'title', 'lf_value' => $promo_edit->title_raw ?? $promo_edit->title ?? '', 'lf_key' => 'promo'])</div></div>
				<div><label>{{ lang('commerce.admin_promotions_16') }}</label><input type="text" name="slug" value="{{ $promo_edit->slug ?? '' }}" placeholder="{{ lang('commerce.admin_promotions_38') }}" /></div>
				<div><label>{{ lang('commerce.admin_promotions_17') }}</label><input type="date" name="start_date" value="{{ !empty($promo_edit->start_date) ? substr($promo_edit->start_date, 0, 4) . '-' . substr($promo_edit->start_date, 4, 2) . '-' . substr($promo_edit->start_date, 6, 2) : '' }}" /></div>
				<div><label>{{ lang('commerce.admin_promotions_18') }}</label><input type="date" name="end_date" value="{{ !empty($promo_edit->end_date) ? substr($promo_edit->end_date, 0, 4) . '-' . substr($promo_edit->end_date, 4, 2) . '-' . substr($promo_edit->end_date, 6, 2) : '' }}" /></div>
				<div><label>{{ lang('commerce.admin_promotions_19') }}</label><select name="status"><option value="Y">{{ lang('commerce.admin_promotions_19') }}</option><option value="N" @if (($promo_edit->status ?? 'Y') === 'N') selected @endif>{{ lang('commerce.admin_promotions_11') }}</option></select></div>
			</div>

			<div class="rsva-field">
				<label>{{ lang('commerce.admin_promotions_20') }}</label>
				<div class="zlf-row-wrap" style="align-items:flex-start">
					<textarea name="description" rows="2" style="flex:1;min-width:0">{{ $promo_edit->description_raw ?? $promo_edit->description ?? '' }}</textarea>
					@include('_langfield', ['lf_name' => 'description', 'lf_value' => $promo_edit->description_raw ?? $promo_edit->description ?? ''])
				</div>
			</div>

			<style>
			/* 기획전 폼 전용 정리 — 콘솔 공통 스타일이 깨뜨리는 부분 복구 */
			.zmcpm-sec { margin: 18px 0 0; padding: 16px 18px; border: 1px solid #eef1f5; border-radius: 12px; background: #fafbfc; }
			.zmcpm-sec > b { display: block; margin-bottom: 12px; font-size: 13.5px; color: #333d4b; }
			.zmcpm-row { display: flex; flex-wrap: wrap; gap: 14px 22px; align-items: flex-end; }
			.zmcpm-row > div > label { font-weight: 600; font-size: 12.5px; color: #6b7684; margin-bottom: 6px; }
			.rsva .zmcpm-row input[type=color] { width: 42px; height: 36px; padding: 2px; border: 1px solid #dde3ec; border-radius: 8px; cursor: pointer; }
			.zmcpm-check { display: inline-flex !important; align-items: center; gap: 7px; font-weight: 600 !important; font-size: 13.5px !important; color: #333d4b !important; margin: 0 !important; cursor: pointer; }
			.rsva .zmcpm-check input { width: 16px; height: 16px; accent-color: #2677e3; }
			.zmcpm-file small { display: block; margin-top: 4px; font-size: 11.5px; color: #4a6; word-break: break-all; }
			.zmcpm-items { max-height: 320px; overflow-y: auto; border: 1px solid #e5e8ee; border-radius: 12px; background: #fff; }
			.zmcpm-items label { display: flex !important; align-items: center; gap: 10px; padding: 10px 14px !important; margin: 0 !important; font-weight: 500 !important; font-size: 13.5px !important; border-bottom: 1px solid #f4f6f9; cursor: pointer; }
			.zmcpm-items label:last-child { border-bottom: 0; }
			.zmcpm-items label:hover { background: #f7faff; }
			.rsva .zmcpm-items input[type=checkbox] { width: 16px; height: 16px; flex: 0 0 auto; accent-color: #2677e3; }
			.zmcpm-items .zmcpm-name { flex: 1; text-align: left; color: #191f28; }
			.zmcpm-items small { flex: 0 0 auto; color: #8b95a1; }
			</style>

			<div class="zmcpm-sec">
				<b>{{ lang('commerce.admin_promotions_21') }}</b>
				<div class="zmcpm-row">
					<div><label style="font-weight:500">{{ lang('commerce.admin_promotions_22') }}</label>
						<select id="zmcPmBgType">
							<option value="gradient">{{ lang('commerce.admin_promotions_23') }}</option>
							<option value="color" @if (($pm_bn['bg_type'] ?? '') === 'color') selected @endif>{{ lang('commerce.admin_promotions_24') }}</option>
							<option value="image" @if (($pm_bn['bg_type'] ?? '') === 'image') selected @endif>{{ lang('commerce.admin_promotions_25') }}</option>
						</select>
					</div>
					<div><label style="font-weight:500">{{ lang('commerce.admin_promotions_26') }}</label>
						<input type="color" id="zmcPmC1" value="{{ $pm_bn['bg_color'] ?? '#1a1f2e' }}" style="width:44px" />
						<input type="color" id="zmcPmC2" value="{{ $pm_bn['bg_color2'] ?? '#0d1019' }}" style="width:44px" />
					</div>
					<div><label style="font-weight:500">{{ lang('commerce.admin_promotions_27') }}</label>
						<input type="file" accept="image/*" data-up="zmcPmImg" />
						<input type="hidden" id="zmcPmImg" value="{{ $pm_bn['image'] ?? '' }}" />
						<small class="zmc-pm-file" data-for="zmcPmImg"></small>
					</div>
					<div><label style="font-weight:500">{{ lang('commerce.admin_promotions_28') }}</label>
						<input type="file" accept="image/*" data-up="zmcPmPoint" />
						<input type="hidden" id="zmcPmPoint" value="{{ $pm_bn['point_image'] ?? '' }}" />
						<small class="zmc-pm-file" data-for="zmcPmPoint"></small>
					</div>
					<div class="zmcpm-file"><label>{{ lang('commerce.admin_promotions_29') }}</label>
						<input type="color" id="zmcPmTextColor" value="{{ $pm_bn['text_color'] ?? '#ffffff' }}" />
					</div>
					<div style="padding-bottom:8px">
						<label class="zmcpm-check"><input type="checkbox" id="zmcPmShadow" @if (($pm_bn['shadow'] ?? 'Y') !== 'N') checked @endif /> {{ lang('commerce.admin_promotions_30') }}</label>
					</div>
					<div style="flex:1;min-width:220px"><label>{{ lang('commerce.admin_promotions_31') }}</label><div class="zlf-row-wrap"><input type="text" id="zmcPmText" value="{{ $pm_bn['text'] ?? '' }}" style="width:100%" />@include('_langfield', ['lf_name' => 'banner_text', 'lf_value' => $pm_bn['text'] ?? '', 'lf_key' => 'promobn'])</div></div>
				</div>
			</div>

			<div class="zmcpm-sec">
				<b>{{ lang('commerce.admin_promotions_32') }}</b>
				<div class="zmcpm-row" style="align-items:center">
					<div style="padding-bottom:2px">
						<label class="zmcpm-check"><input type="checkbox" id="zmcPmMain" @if (($pm_bn['main'] ?? 'N') === 'Y') checked @endif /> {{ lang('commerce.admin_promotions_33') }}</label>
					</div>
					<div class="zmcpm-file"><label>{{ lang('commerce.admin_promotions_34') }}</label>
						<input type="file" accept="image/*" data-up="zmcPmLogo" />
						<input type="hidden" id="zmcPmLogo" value="{{ $pm_bn['logo'] ?? '' }}" />
						<small class="zmc-pm-file" data-for="zmcPmLogo"></small>
					</div>
					<img id="zmcPmLogoPreview" src="{{ $pm_bn['logo'] ?? '' }}" alt="" style="width:52px;height:52px;border-radius:50%;object-fit:cover;border:1px solid #e5e8ee;{{ empty($pm_bn['logo']) ? 'display:none' : '' }}" />
				</div>
			</div>

			<div class="zmcpm-sec">
				<b>{{ lang('commerce.admin_promotions_35') }} <span id="zmcPmCount" style="color:#2677e3">0</span>{{ lang('commerce.admin_promotions_36') }}</b>
				<input type="text" id="zmcPmSearch" placeholder="{{ lang('commerce.admin_promotions_39') }}" style="width:280px;margin-bottom:10px" />
				<div id="zmcPmItemList" class="zmcpm-items">
					@foreach ($promo_all_items as $pi)
					<label data-name="{{ $pi->item_name }}">
						<input type="checkbox" class="zmc-pm-item" value="{{ $pi->item_srl }}" @if (in_array((int)$pi->item_srl, $promo_edit_items ?? [], true)) checked @endif />
						<span class="zmcpm-name">{{ $pi->item_name }}</span>
						<small>{{ shop_money_base($pi->sale_price > 0 && $pi->sale_price < $pi->price ? $pi->sale_price : $pi->price) }} · {{ $pi->status === 'soldout' ? lang('commerce.st_item_soldout') : lang('commerce.st_item_sale') }}</small>
					</label>
					@endforeach
				</div>
			</div>

			<button type="submit" class="rsva-btn rsva-btn-primary" style="margin-top:16px">{{ $promo_edit ? lang('commerce.admin_item_edit_159') : lang('commerce.adm_promo_create') }}</button>
		</form>
	</div>
</div>

<script>
	// 배너 문구도 다국어를 연결할 수 있다. 코드를 골랐으면 '$user_lang->코드' 로 담는다
	function zmcPmTextValue() {
		var input = document.getElementById('zmcPmText');
		var wrap = input.closest('.zlf-row-wrap');
		var code = wrap ? wrap.querySelector('[data-lf-code]') : null;
		if (code && code.value.trim() !== '') { return '$user_lang->' + code.value.trim(); }
		return input.value.trim();
	}
(function () {
	// 이미지 첨부: 관리자 배너 업로드 액션으로 보내고 URL 을 hidden 에 채운다
	var csrf = (document.querySelector('meta[name=csrf-token]') || {}).content || '';
	function fileLabel(id) {
		var url = document.getElementById(id).value;
		document.querySelectorAll('.zmc-pm-file[data-for=' + id + ']').forEach(function (el) {
			el.textContent = url ? url.split('/').pop() : '';
		});
		if (id === 'zmcPmLogo') {
			var pv = document.getElementById('zmcPmLogoPreview');
			pv.src = url; pv.style.display = url ? '' : 'none';
		}
	}
	document.querySelectorAll('input[type=file][data-up]').forEach(function (input) {
		input.addEventListener('change', function () {
			var f = input.files && input.files[0];
			if (!f) return;
			var fd = new FormData();
			fd.append('file', f);
			fetch('./?module=commerce&act=procCommerceAdminUploadBanner', { method: 'POST', body: fd, headers: { 'X-CSRF-Token': csrf } })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res && !res.error && res.url) {
						document.getElementById(input.getAttribute('data-up')).value = res.url;
						fileLabel(input.getAttribute('data-up'));
					} else alert(res && res.message ? res.message : {!! json_encode(lang('commerce.msg_shop_upload_failed')) !!});
				})
				.catch(function () { alert({!! json_encode(lang('commerce.msg_shop_upload_failed')) !!}); });
		});
	});
	['zmcPmImg', 'zmcPmPoint', 'zmcPmLogo'].forEach(fileLabel);

	// 상품 검색 필터
	var search = document.getElementById('zmcPmSearch');
	var listEl = document.getElementById('zmcPmItemList');
	search.addEventListener('input', function () {
		var q = search.value.trim();
		listEl.querySelectorAll('label[data-name]').forEach(function (row) {
			row.style.display = (!q || row.getAttribute('data-name').indexOf(q) !== -1) ? '' : 'none';
		});
	});

	// 체크 순서 추적 (체크한 순서 = 노출 순서, 기존 저장분은 기존 순서 유지)
	var order = [];
	@if ($promo_edit)
	order = {!! json_encode(array_values($promo_edit_items ?? [])) !!};
	@endif
	listEl.querySelectorAll('.zmc-pm-item').forEach(function (cb) {
		cb.addEventListener('change', function () {
			var srl = parseInt(cb.value, 10);
			var idx = order.indexOf(srl);
			if (cb.checked && idx === -1) order.push(srl);
			if (!cb.checked && idx !== -1) order.splice(idx, 1);
			count();
		});
	});
	function count() { document.getElementById('zmcPmCount').textContent = order.length; }
	count();

	// 저장 시 직렬화 (배너 + 상품 순서). 메인 노출 체크 시 로고 필수
	document.getElementById('zmcPromoForm').addEventListener('submit', function (e) {
		var main = document.getElementById('zmcPmMain').checked;
		var logo = document.getElementById('zmcPmLogo').value.trim();
		if (main && !logo) {
			e.preventDefault();
			alert({!! json_encode(lang('commerce.adm_promo_logo_first')) !!});
			return;
		}
		document.getElementById('zmcPromoItems').value = JSON.stringify(order);
		document.getElementById('zmcPromoBanner').value = JSON.stringify({
			bg_type: document.getElementById('zmcPmBgType').value,
			bg_color: document.getElementById('zmcPmC1').value,
			bg_color2: document.getElementById('zmcPmC2').value,
			image: document.getElementById('zmcPmImg').value.trim(),
			point_image: document.getElementById('zmcPmPoint').value.trim(),
			logo: logo,
			main: main ? 'Y' : 'N',
			text_color: document.getElementById('zmcPmTextColor').value,
			shadow: document.getElementById('zmcPmShadow').checked ? 'Y' : 'N',
			text: zmcPmTextValue()
		});
	});
})();
</script>
