@include('_tabs')
@include('_langfield_assets')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminInsertConfig" />

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_1') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_2') }}</label><select name="enabled"><option value="Y" @if($shop_config->enabled === 'Y') selected @endif>{{ lang('commerce.admin_config_3') }}</option><option value="N" @if($shop_config->enabled === 'N') selected @endif>{{ lang('commerce.admin_config_4') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_5') }}</label><select name="market_mode"><option value="single" @if($shop_config->market_mode === 'single') selected @endif>{{ lang('commerce.admin_config_6') }}</option><option value="open" @if($shop_config->market_mode === 'open') selected @endif>{{ lang('commerce.admin_config_7') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_8') }}</label><input type="text" name="code_prefix" maxlength="5" value="{{ $shop_config->code_prefix }}" /></div>
				<div><label>{{ lang('commerce.admin_config_9') }}</label><select name="allow_guest"><option value="Y" @if($shop_config->allow_guest === 'Y') selected @endif>{{ lang('commerce.admin_config_10') }}</option><option value="N" @if($shop_config->allow_guest === 'N') selected @endif>{{ lang('commerce.admin_config_11') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_12') }}</label><input type="number" name="pending_minutes" min="10" max="1440" value="{{ $shop_config->pending_minutes }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_13') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_14') }}</label><input type="number" name="default_ship_fee" min="0" value="{{ $shop_config->default_ship_fee }}" /></div>
				<div><label>{{ lang('commerce.admin_config_15') }}</label><input type="number" name="free_ship_over" min="0" value="{{ $shop_config->free_ship_over }}" /></div>
				<div><label>{{ lang('commerce.admin_config_16') }}</label><select name="item_sticky"><option value="N" @if(($shop_config->item_sticky ?? 'N') !== 'Y') selected @endif>{{ lang('commerce.admin_config_17') }}</option><option value="Y" @if(($shop_config->item_sticky ?? 'N') === 'Y') selected @endif>{{ lang('commerce.admin_config_18') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_19') }}</label><input type="number" name="claim_days" min="0" max="90" value="{{ $shop_config->claim_days }}" /></div>
			</div>
			<div style="margin-top:16px">
				<label style="font-weight:700">{{ lang('commerce.admin_config_20') }}</label>
				<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1;line-height:1.7">
					{{ lang('commerce.admin_config_21') }}
					<a href="https://tracking.sweettracker.co.kr" target="_blank" rel="noopener" style="color:#2677e3">{{ lang('commerce.admin_config_22') }}</a>에 가입하고 "배송조회 API" 키를 발급받아 입력하면:
					주문에 송장을 등록해두는 것만으로 배송 준비 → 배송 중 → 배송 완료가 자동으로 바뀌고, 구매자 주문 상세에 현재 배송 위치가 표시됩니다.
					조회는 약 10분 간격으로 이루어져 반영에 최대 10분 정도 걸릴 수 있습니다.
					스윗트래커의 "배송추적(실시간 푸시)"은 연 단위 별도 계약 상품이라 사용하지 않으며, 이 기능은 종량제 <b>{{ lang('commerce.admin_config_24') }}</b> {{ lang('commerce.admin_config_25') }}
				</p>
				<input type="text" name="sweettracker_api_key" value="{{ $shop_config->sweettracker_api_key ?? '' }}" placeholder="{{ lang('commerce.admin_config_103') }}" style="width:340px;max-width:100%" autocomplete="off" />
			</div>
			<div style="margin-top:16px">
				<label style="font-weight:700">{{ lang('commerce.admin_config_26') }}</label>
				<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1;line-height:1.7">
					국가는 ISO 2자리(비우면 KR)입니다. 시·도는 이름으로 적고(제주, 강원 - 제주도/제주특별자치도처럼 써도 같은 지역으로 봅니다),
					우편번호는 접두(63) 또는 범위(40200-40240)를 쉼표로 나열합니다.
					시·도와 우편번호 중 하나라도 맞으면 그 줄의 추가금을 씁니다. 국가만 적고 나머지를 비우면 그 나라 전체에 적용됩니다.
				</p>
				<div id="zmcZoneRows"></div>
				<button type="button" class="rsva-btn rsva-btn-sm" id="zmcZoneAdd">{{ lang('commerce.admin_config_28') }}</button>
				<input type="hidden" name="ship_extra_zones" id="zmcZonesJson" value="{{ $shop_config->ship_extra_zones ?? '[]' }}" />
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_29') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_30') }}</label><select name="shop_main"><option value="list" @if(($shop_config->shop_main ?? 'list') !== 'home') selected @endif>{{ lang('commerce.admin_config_31') }}</option><option value="home" @if(($shop_config->shop_main ?? 'list') === 'home') selected @endif>{{ lang('commerce.admin_config_32') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_33') }}</label><select name="category_layout"><option value="top" @if(($shop_config->category_layout ?? 'top') !== 'side') selected @endif>{{ lang('commerce.admin_config_34') }}</option><option value="side" @if(($shop_config->category_layout ?? 'top') === 'side') selected @endif>{{ lang('commerce.admin_config_35') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_36') }}</label><input type="number" name="home_count" min="4" max="24" value="{{ $shop_config->home_count ?? 8 }}" /></div>
			</div>
			<div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:12px;font-size:13.5px">
				<label><input type="checkbox" name="home_show_recommend" value="Y" @if(($shop_config->home_show_recommend ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_config_37') }}</label>
				<label><input type="checkbox" name="home_show_new" value="Y" @if(($shop_config->home_show_new ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_config_38') }}</label>
				<label><input type="checkbox" name="home_show_popular" value="Y" @if(($shop_config->home_show_popular ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_config_39') }}</label>
				<label><input type="checkbox" name="home_show_sale" value="Y" @if(($shop_config->home_show_sale ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_config_40') }}</label>
			</div>
			<div style="margin-top:16px">
				<label style="font-weight:700">{{ lang('commerce.admin_config_41') }}</label>
				<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.admin_config_42') }}</p>
				<style>
				/* 배너 한 건 = 카드. 프론트 편집 패널과 같은 항목을 담는다 */
				.zmc-banner-card { position: relative; display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 16px; padding: 14px 16px; margin-bottom: 10px; border: 1px solid #e5e8ee; border-radius: 12px; background: #fbfcfd; }
				.zmc-banner-imgs { display: flex; flex-direction: column; gap: 10px; }
				.zmc-banner-img { display: flex; gap: 10px; align-items: center; }
				.zmc-banner-thumb { flex: 0 0 auto; width: 56px; height: 56px; border: 1px solid #e5e8ee; border-radius: 8px; background: #fff center/cover no-repeat; }
				.zmc-banner-thumb.is-empty { border-style: dashed; }
				.zmc-banner-imgacts { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
				.zmc-banner-imglabel { font-size: 12px; color: #6b7684; }
				.zmc-banner-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px 12px; align-content: start; }
				.zmc-f { min-width: 0; }
				.zmc-f > label { display: block; margin-bottom: 4px; font-size: 12px; color: #6b7684; }
				.zmc-f input[type="text"], .zmc-f select { width: 100%; box-sizing: border-box; }
				.zmc-f input[type="color"] { width: 42px; height: 32px; padding: 2px; vertical-align: middle; }
				.zmc-f .zmc-inline { display: inline-flex; align-items: center; gap: 5px; margin: 0 0 0 8px; font-size: 12.5px; color: #4e5968; }
				.zmc-banner-del { position: absolute; top: 12px; right: 14px; }
				@media (max-width: 900px) { .zmc-banner-card { grid-template-columns: minmax(0, 1fr); } }
				</style>
				<div id="zmcBannerRows"></div>
				<button type="button" class="rsva-btn rsva-btn-sm" id="zmcBannerAdd">{{ lang('commerce.admin_config_43') }}</button>
				<input type="hidden" name="home_banners" id="zmcBannersJson" value="{{ $shop_config->home_banners ?? '[]' }}" />
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_44') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_45') }}</label><input type="number" name="credit_rate" min="0" max="100" step="0.01" value="{{ $shop_config->credit_rate }}" /></div>
				<div><label>{{ lang('commerce.admin_config_46') }}</label><input type="number" name="credit_min_use" min="0" value="{{ $shop_config->credit_min_use }}" /></div>
				<div><label>{{ lang('commerce.admin_config_47') }}</label><input type="number" name="review_credit_text" min="0" value="{{ $shop_config->review_credit_text ?? 0 }}" /></div>
				<div><label>{{ lang('commerce.admin_config_48') }}</label><input type="number" name="review_credit_photo" min="0" value="{{ $shop_config->review_credit_photo ?? 0 }}" /></div>
			</div>
			<p style="margin:8px 0 0;font-size:12.5px;color:#8b95a1">{{ lang('commerce.admin_config_49') }}</p>
		</div>

		<div class="rsva-panel">
			<h3>결제 {{ $pay_available ? '' : '— 짓미 페이(zittme_pay)가 설치되어 있지 않아 결제를 받을 수 없습니다' }}</h3>
			<p style="margin:0;font-size:13px;color:#6b7684">{{ lang('commerce.admin_config_50') }}</p>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_51') }}</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1"><label>{{ lang('commerce.admin_config_52') }}</label><textarea name="privacy_text" rows="3">{{ $shop_config->privacy_text }}</textarea></div>
				<div><label>{{ lang('commerce.admin_config_53') }}</label><input type="text" name="privacy_version" maxlength="20" value="{{ $shop_config->privacy_version }}" /></div>
				<div><label>{{ lang('commerce.admin_config_54') }}</label><input type="number" name="retention_days" min="0" max="3650" value="{{ $shop_config->retention_days }}" /></div>
				<div><label>{{ lang('commerce.admin_config_55') }}</label><select name="notify_admin"><option value="N" @if($shop_config->notify_admin === 'N') selected @endif>{{ lang('commerce.admin_config_56') }}</option><option value="Y" @if($shop_config->notify_admin === 'Y') selected @endif>{{ lang('commerce.admin_config_57') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_58') }}</label><input type="email" name="notify_admin_email" value="{{ $shop_config->notify_admin_email }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_59') }}</h3>
			<p class="rsva-hint">{{ lang('commerce.admin_config_60') }}</p>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_61') }}</label><input type="text" name="biz_name" maxlength="100" value="{{ $shop_config->biz_name }}" /></div>
				<div><label>{{ lang('commerce.admin_config_62') }}</label><input type="text" name="biz_ceo" maxlength="60" value="{{ $shop_config->biz_ceo }}" /></div>
				<div><label>{{ lang('commerce.admin_config_63') }}</label><input type="text" name="biz_number" maxlength="40" value="{{ $shop_config->biz_number }}" /></div>
				<div><label>{{ lang('commerce.admin_config_64') }}</label><input type="text" name="biz_tel" maxlength="40" value="{{ $shop_config->biz_tel }}" /></div>
				<div style="grid-column:1/-1"><label>{{ lang('commerce.admin_config_65') }}</label><input type="text" name="biz_address" maxlength="250" value="{{ $shop_config->biz_address }}" /></div>
				<div style="grid-column:1/-1"><label>{{ lang('commerce.admin_config_66') }}</label><textarea name="biz_note" rows="2">{{ $shop_config->biz_note }}</textarea></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_67') }}</h3>
			<p class="rsva-hint">{{ lang('commerce.admin_config_68') }}</p>
			<div class="rsva-form-grid">
				<div>
					<label>{{ lang('commerce.admin_config_69') }}</label>
					<select name="biz_tax_mode">
						<option value="taxable" @if($shop_config->biz_tax_mode === 'taxable') selected @endif>{{ lang('commerce.admin_config_70') }}</option>
						<option value="exempt" @if($shop_config->biz_tax_mode === 'exempt') selected @endif>{{ lang('commerce.admin_config_71') }}</option>
						<option value="simplified" @if($shop_config->biz_tax_mode === 'simplified') selected @endif>{{ lang('commerce.admin_config_72') }}</option>
					</select>
				</div>
				<div><label>{{ lang('commerce.admin_config_73') }}</label><input type="number" name="vat_rate" min="0" max="100" step="1" value="{{ (int)$shop_config->vat_rate }}" /></div>
				<div>
					<label>{{ lang('commerce.admin_config_74') }}</label>
					<select name="price_includes_tax">
						<option value="Y" @if($shop_config->price_includes_tax !== 'N') selected @endif>{{ lang('commerce.admin_config_75') }}</option>
						<option value="N" @if($shop_config->price_includes_tax === 'N') selected @endif>{{ lang('commerce.admin_config_76') }}</option>
					</select>
				</div>
				<div>
					<label>{{ lang('commerce.admin_config_77') }}</label>
					<select name="allow_overseas">
						<option value="N" @if($shop_config->allow_overseas !== 'Y') selected @endif>{{ lang('commerce.admin_config_78') }}</option>
						<option value="Y" @if($shop_config->allow_overseas === 'Y') selected @endif>{{ lang('commerce.admin_config_79') }}</option>
					</select>
				</div>
			</div>
			<p class="rsva-hint">{{ lang('commerce.admin_config_80') }}</p>
		</div>

		<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_config_81') }}</button>
	</form>

	<div class="rsva-panel" style="margin-top:18px">
		<h3>{{ lang('commerce.admin_config_82') }}</h3>
		@if ($shop_instance)
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminUpdateSkin" />
			<div style="min-width:220px">
				<label>{{ lang('commerce.admin_config_83') }}</label>
				<select name="skin" style="width:100%">
					<option value="/USE_DEFAULT/" @if(($shop_instance->skin ?? '') === '/USE_DEFAULT/') selected @endif>{{ lang('commerce.admin_config_84') }}</option>
					@foreach ($shop_skins as $sk)
					<option value="{{ $sk->skin }}" @if(($shop_instance->skin ?? '') === $sk->skin) selected @endif>{{ $sk->title ?: $sk->skin }}</option>
					@endforeach
				</select>
			</div>
			<div style="min-width:220px">
				<label>{{ lang('commerce.admin_config_85') }}</label>
				<select name="mskin" style="width:100%">
					<option value="/USE_DEFAULT/" @if(($shop_instance->mskin ?? '') === '/USE_DEFAULT/') selected @endif>{{ lang('commerce.admin_config_86') }}</option>
					@foreach ($shop_mskins as $sk)
					<option value="{{ $sk->skin }}" @if(($shop_instance->mskin ?? '') === $sk->skin) selected @endif>{{ $sk->title ?: $sk->skin }}</option>
					@endforeach
				</select>
			</div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_config_87') }}</button></div>
		</form>
		<small style="display:block;margin-top:10px;color:#6b7684;font-size:12px">{{ lang('commerce.admin_config_88') }}</small>
		@else
		<p class="rsva-empty">{{ lang('commerce.admin_config_89') }}</p>
		@endif
	</div>

	<script>
	(function () {
		// 홈 배너 편집: 행 단위 입력을 hidden JSON 으로 직렬화해서 저장한다
		var rowsEl = document.getElementById('zmcBannerRows');
		var jsonEl = document.getElementById('zmcBannersJson');
		var addBtn = document.getElementById('zmcBannerAdd');
		if (!rowsEl || !jsonEl) return;

		// 배너 이미지: 주소를 직접 적는 대신 파일을 골라 올린다 (프론트 편집 패널과 같은 방식)
		function setBannerImage(row, key, url, touched) {
			var cell = row.querySelector('[data-img=' + key + ']');
			row.querySelector('[data-k=' + key + ']').value = url;
			var thumb = cell.querySelector('[data-thumb]');
			cell.querySelector('[data-imgdel]').hidden = !url;
			cell.querySelector('[data-pick]').textContent = url ? '변경' : '선택';
			thumb.style.backgroundImage = url ? 'url("' + url + '")' : '';
			thumb.classList.toggle('is-empty', !url);
			// 배경 이미지를 넣었는데 배경 종류가 그라디언트·단색이면 화면에 안 보인다 — 함께 맞춘다
			if (touched && key === 'image' && row.zmcExtra) {
				var sel = row.querySelector('[data-k=bg_type]');
				if (url) { if (sel) sel.value = 'image'; }
				else if (sel && sel.value === 'image') { sel.value = 'gradient'; }
			}
		}

		function bindBannerImage(row, onChange) {
			row.querySelectorAll('[data-img]').forEach(function (cell) {
				var key = cell.getAttribute('data-img');
				var fileEl = cell.querySelector('[data-file]');
				var pickBtn = cell.querySelector('[data-pick]');
				pickBtn.addEventListener('click', function () { fileEl.click(); });
				cell.querySelector('[data-imgdel]').addEventListener('click', function () { setBannerImage(row, key, '', true); if (onChange) onChange(); });
				fileEl.addEventListener('change', function () {
					var file = fileEl.files && fileEl.files[0];
					if (!file) return;
					var label = pickBtn.textContent;
					pickBtn.textContent = '올리는 중…';
					pickBtn.disabled = true;

					var fd = new FormData();
					fd.append('file', file);
					fetch('./?module=commerce&act=procCommerceAdminUploadBanner', { method: 'POST', body: fd, credentials: 'same-origin' })
						.then(function (res) { return res.json(); })
						.then(function (ret) {
							if (ret && !ret.error && ret.url) {
								setBannerImage(row, key, ret.url, true);
								if (onChange) onChange();
							} else {
								alert((ret && ret.message) || '업로드에 실패했습니다.');
								pickBtn.textContent = label;
							}
						})
						.catch(function () {
							alert('업로드에 실패했습니다.');
							pickBtn.textContent = label;
						})
						.then(function () {
							pickBtn.disabled = false;
							fileEl.value = '';
						});
				});
			});
		}

		// 저장값이 '$user_lang->코드' 면 코드를 물리고, 칸에는 실제 문구를 보여준다
		var LANG_PREFIX = '$user_lang->';
		function setLangValue(row, key, value) {
			var input = row.querySelector('[data-k=' + key + ']');
			var hidden = row.querySelector('[data-k=' + key + '_code]');
			var button = hidden.nextElementSibling;
			if (value.indexOf(LANG_PREFIX) !== 0) {
				input.value = value;
				return;
			}
			var code = value.substring(LANG_PREFIX.length);
			hidden.value = code;
			input.value = code;
			input.readOnly = true;
			button.classList.add('is-on');
			exec_json('commerce.procCommerceAdminGetLangCode', { code: code }, function (ret) {
				var values = ret.values || {};
				for (var k in values) { if (values[k]) { input.value = values[k]; break; } }
			});
		}

		function addRow(data) {
			data = data || {};
			var row = document.createElement('div');
			row.className = 'zmc-banner-card';
			var globe = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.3 2.6 2.3 14.4 0 17"/><path d="M12 3.5c-2.3 2.6-2.3 14.4 0 17"/></svg>';
			function imgCell(key, label) {
				return '<div class="zmc-banner-img" data-img="' + key + '">' +
					'<span class="zmc-banner-thumb" data-thumb></span>' +
					'<div class="zmc-banner-imgacts">' +
						'<span class="zmc-banner-imglabel">' + label + '</span>' +
						'<span><button type="button" class="rsva-btn rsva-btn-sm" data-pick>선택</button> ' +
						'<button type="button" class="rsva-btn rsva-btn-sm" data-imgdel hidden>지우기</button></span>' +
					'</div>' +
					'<input type="file" accept="image/*" data-file hidden />' +
					'<input type="hidden" data-k="' + key + '" />' +
				'</div>';
			}
			// 제목·문구는 다국어 문구를 연결할 수 있다. 값 자체가 코어 규약이면 버튼이 그걸 물고 있는다.
			function langCell(key, label) {
				return '<div class="zmc-f"><label>' + label + '</label><span class="zlf-row-wrap">' +
					'<input type="text" data-k="' + key + '" />' +
					'<input type="hidden" data-lf-code data-k="' + key + '_code" />' +
					'<button type="button" class="zlf-btn" data-lf-open title="다국어 문구 연결">' + globe + '</button>' +
					'</span></div>';
			}
			row.innerHTML =
				'<div class="zmc-banner-imgs">' + imgCell('image', '배경 이미지') + imgCell('point_image', '포인트 이미지 (우측)') + '</div>' +
				'<div class="zmc-banner-fields">' +
					'<div class="zmc-f"><label>배경</label><select data-k="bg_type">' +
						'<option value="gradient">그라디언트</option><option value="color">단색</option><option value="image">이미지</option>' +
					'</select></div>' +
					'<div class="zmc-f zmc-bgcolors"><label>배경색</label><span><input type="color" data-k="bg_color" /> <input type="color" data-k="bg_color2" /></span></div>' +
					'<div class="zmc-f"><label>글자</label><span><input type="color" data-k="text_color" /> <label class="zmc-inline"><input type="checkbox" data-k="shadow" /> 그림자</label></span></div>' +
					langCell('title', '제목') +
					langCell('text', '문구') +
					'<div class="zmc-f"><label>링크 URL</label><input type="text" data-k="url" placeholder="비우면 상품 목록" /></div>' +
				'</div>' +
				'<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger zmc-banner-del" data-del="1">삭제</button>';

			row.zmcExtra = data;
			row.querySelector('[data-k=url]').value = data.url || '';
			row.querySelector('[data-k=bg_type]').value = data.bg_type || (data.image ? 'image' : 'gradient');
			row.querySelector('[data-k=bg_color]').value = data.bg_color || '#1a1f2e';
			row.querySelector('[data-k=bg_color2]').value = data.bg_color2 || '#0d1019';
			row.querySelector('[data-k=text_color]').value = data.text_color || '#ffffff';
			row.querySelector('[data-k=shadow]').checked = (data.shadow || 'Y') !== 'N';
			setBannerImage(row, 'image', data.image || '', false);
			setBannerImage(row, 'point_image', data.point_image || '', false);
			setLangValue(row, 'title', data.title || '');
			setLangValue(row, 'text', data.text || '');

			function refreshBg() {
				var type = row.querySelector('[data-k=bg_type]').value;
				row.querySelector('.zmc-bgcolors').style.display = type === 'image' ? 'none' : '';
				row.querySelector('[data-img=image]').style.display = type === 'image' ? '' : 'none';
			}
			row.querySelector('[data-k=bg_type]').addEventListener('change', refreshBg);
			refreshBg();

			row.querySelector('[data-del]').addEventListener('click', function () { row.remove(); });
			rowsEl.appendChild(row);
			bindBannerImage(row, refreshBg);
			// 버튼을 공용 다국어 패널에 잇는다 (실패해도 나머지 행은 그려져야 한다)
			try {
				if (window.zlfBind) {
					row.querySelectorAll('[data-lf-open]').forEach(window.zlfBind);
				}
			} catch (e) {}
		}

		var initial = [];
		try { initial = JSON.parse(jsonEl.value) || []; } catch (e) {}
		initial.forEach(addRow);
		if (addBtn) addBtn.addEventListener('click', function () { addRow(); });

		// 지역 추가 배송비 편집 (배너와 같은 방식)
		var zoneRows = document.getElementById('zmcZoneRows');
		var zonesJson = document.getElementById('zmcZonesJson');
		var zoneAdd = document.getElementById('zmcZoneAdd');
		function addZone(data) {
			data = data || {};
			var row = document.createElement('div');
			row.className = 'zmc-zone-row';
			row.style.cssText = 'display:flex;gap:8px;flex-wrap:wrap;margin-bottom:8px;align-items:center';
			row.innerHTML =
				'<input type="text" data-k="name" placeholder="이름 (예: 제주)" style="flex:1;min-width:110px" />' +
				'<input type="text" data-k="country" placeholder="국가 (KR)" maxlength="2" style="width:82px;text-transform:uppercase" />' +
				'<input type="text" data-k="regions" placeholder="시·도 (예: 제주, 강원)" style="flex:1;min-width:150px" />' +
				'<input type="text" data-k="zips" placeholder="우편번호 패턴 (예: 63, 40200-40240)" style="flex:2;min-width:200px" />' +
				'<input type="number" data-k="fee" placeholder="추가금 (원)" min="0" style="width:120px" />' +
				'<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger" data-del="1">삭제</button>';
			row.querySelector('[data-k=name]').value = data.name || '';
			row.querySelector('[data-k=country]').value = data.country || '';
			row.querySelector('[data-k=regions]').value = data.regions || '';
			row.querySelector('[data-k=zips]').value = data.zips || '';
			row.querySelector('[data-k=fee]').value = data.fee || '';
			row.querySelector('[data-del]').addEventListener('click', function () { row.remove(); });
			zoneRows.appendChild(row);
		}
		if (zoneRows && zonesJson) {
			var zoneInitial = [];
			try { zoneInitial = JSON.parse(zonesJson.value) || []; } catch (e) {}
			zoneInitial.forEach(addZone);
			if (zoneAdd) zoneAdd.addEventListener('click', function () { addZone(); });
		}

		var form = jsonEl.closest('form');
		if (form) {
			form.addEventListener('submit', function () {
				var out = [];
				rowsEl.querySelectorAll('.zmc-banner-card').forEach(function (row) {
					var item = {};
					Object.keys(row.zmcExtra || {}).forEach(function (k) { item[k] = row.zmcExtra[k]; });
					item.image = row.querySelector('[data-k=image]').value.trim();
					item.point_image = row.querySelector('[data-k=point_image]').value.trim();
					item.url = row.querySelector('[data-k=url]').value.trim();
					item.bg_type = row.querySelector('[data-k=bg_type]').value;
					item.bg_color = row.querySelector('[data-k=bg_color]').value;
					item.bg_color2 = row.querySelector('[data-k=bg_color2]').value;
					item.text_color = row.querySelector('[data-k=text_color]').value;
					item.shadow = row.querySelector('[data-k=shadow]').checked ? 'Y' : 'N';
					delete item.bg_type_before;
					// 다국어를 연결했으면 코어 규약값으로, 아니면 입력한 글자 그대로
					['title', 'text'].forEach(function (key) {
						var code = row.querySelector('[data-k=' + key + '_code]').value.trim();
						item[key] = code ? (LANG_PREFIX + code) : row.querySelector('[data-k=' + key + ']').value.trim();
					});
					if (item.image || item.title || item.text || item.point_image) out.push(item);
				});
				jsonEl.value = JSON.stringify(out);

				if (zoneRows && zonesJson) {
					var zones = [];
					zoneRows.querySelectorAll('.zmc-zone-row').forEach(function (row) {
						var zone = {
							name: row.querySelector('[data-k=name]').value.trim(),
							country: row.querySelector('[data-k=country]').value.trim().toUpperCase(),
							regions: row.querySelector('[data-k=regions]').value.trim(),
							zips: row.querySelector('[data-k=zips]').value.trim(),
							fee: parseInt(row.querySelector('[data-k=fee]').value, 10) || 0
						};
						// 국가만 지정한 줄은 그 나라 전체에 적용되므로 조건이 비어도 저장한다
						var hasRule = zone.zips || zone.regions || (zone.country && zone.country !== 'KR');
						if (hasRule && zone.fee > 0) zones.push(zone);
					});
					zonesJson.value = JSON.stringify(zones);
				}
			});
		}
	})();
	</script>
</div>
