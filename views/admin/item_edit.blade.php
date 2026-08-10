@include('_tabs')
@include('_langfield_assets')

<style>
/* 상품 등록 — 친절한 섹션형 폼 */
.ie-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.ie-head h2 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.02em; }
.ie-head p { margin: 4px 0 0; font-size: 13px; color: #6b7684; }
.ie-sec-desc { margin: -10px 0 16px; font-size: 13px; color: #6b7684; }
.ie-help { display: block; margin-top: 6px; font-size: 12.5px; color: #8b95a1; font-weight: 400; }
.ie-req { color: #e5484d; font-weight: 700; }
.ie-axis { display: flex; gap: 8px; align-items: center; margin-bottom: 6px; }
.ie-axis input[data-a="name"] { flex: 0 0 180px; }
.ie-axis input[data-a="values"] { flex: 1; min-width: 0; }
.ie-axis select[data-a="style"] { flex: 0 0 110px; padding: 8px 10px; border: 1px solid #dde3ec; border-radius: 8px; font-size: 13px; font-family: inherit; }
.ie-axes input[type="text"] { padding: 8px 10px; border: 1px solid #dde3ec; border-radius: 8px; font-size: 13px; font-family: inherit; box-sizing: border-box; }
.ie-langrow { display: flex; gap: 8px; align-items: center; }
.ie-langrow > input { flex: 1; min-width: 0; }
.ie-suffix { position: relative; }
.ie-suffix input { padding-right: 34px !important; width: 100%; }
.ie-suffix::after { content: attr(data-suffix); position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 13px; color: #8b95a1; pointer-events: none; }
.ie-discount { display: none; margin-top: 6px; font-size: 12.5px; font-weight: 700; color: #e5484d; }
.ie-pills { display: flex; gap: 8px; flex-wrap: wrap; }
.ie-pills label { position: relative; display: inline-flex; align-items: center; padding: 10px 16px; margin: 0; border: 1px solid #dde3ec; border-radius: 10px; font-size: 13.5px; font-weight: 600; color: #4e5968; cursor: pointer; background: #fff; white-space: nowrap; line-height: 1; transition: border-color .12s, background .12s; }
.ie-pills label:hover { border-color: #b9c6d8; }
.ie-pills input { position: absolute; opacity: 0; pointer-events: none; }
.ie-pills label:has(input:checked) { border-color: #2677e3; background: #eef4fd; color: #2677e3; box-shadow: inset 0 0 0 1px #2677e3; }
.ie-cond { margin-top: 12px; }
.ie-checks label { display: inline-flex; align-items: center; gap: 7px; margin: 0 14px 0 0; font-size: 13.5px; font-weight: 600; color: #333d4b; cursor: pointer; }
.ie-checks input { accent-color: #2677e3; width: 16px; height: 16px; }
.ie-imgs { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
.ie-img { position: relative; width: 108px; }
.ie-img-box { width: 108px; height: 108px; border: 1px solid #dde3ec; border-radius: 12px; background: #f7f8fa center/cover no-repeat; }
.ie-img.is-main .ie-img-box { border: 2px solid #2677e3; }
.ie-img-badge { position: absolute; top: 6px; left: 6px; padding: 2px 8px; border-radius: 6px; background: #2677e3; color: #fff; font-size: 11px; font-weight: 700; }
.ie-img-acts { display: flex; gap: 4px; margin-top: 5px; }
.ie-img-acts button { flex: 1; padding: 4px 0; border: 1px solid #dde3ec; border-radius: 7px; background: #fff; font-size: 11.5px; font-weight: 600; color: #4e5968; cursor: pointer; }
.ie-img-acts button:hover { border-color: #2677e3; color: #2677e3; }
.ie-img-acts button.ie-img-del:hover { border-color: #e5484d; color: #e5484d; }
.ie-img-add { width: 108px; height: 108px; border: 1px dashed #b9c6d8; border-radius: 12px; background: #fafbfc; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px; font-size: 12px; font-weight: 600; color: #8b95a1; cursor: pointer; }
.ie-img-add:hover { border-color: #2677e3; color: #2677e3; }
.ie-img-add b { font-size: 22px; font-weight: 500; line-height: 1; }
.ie-hidden { display: none !important; }
/* 저장 바 — 하단 고정 중일 때만(is-stuck) 흰 바 배경, 제자리에 오면 버튼만 남는다 */
.ie-savebar { position: sticky; bottom: 0; z-index: 80; margin: 20px -36px 0; padding: 14px 36px; background: transparent; border-top: 1px solid transparent; display: flex; gap: 10px; align-items: center; transition: background .15s, box-shadow .15s, border-color .15s; }
.ie-savebar.is-stuck { background: #fff; border-top-color: #e5e8eb; box-shadow: 0 -6px 16px rgba(25,31,40,.05); }
@media (max-width: 900px) { .ie-savebar { margin: 20px -16px 0; padding: 12px 16px; } }
.ie-savebar .rsva-btn-primary { padding: 11px 26px; font-size: 15px; }
.ie-savebar small { color: #8b95a1; }
.ie-opt-warn { margin-bottom: 12px; padding: 11px 13px; border: 1px solid #f0c36d; border-radius: 10px; background: #fdf7ea; font-size: 12.5px; line-height: 1.6; color: #8a6116; }
.ie-opt-hidden { margin-bottom: 12px; padding: 10px 12px; border: 1px dashed #dde3ec; border-radius: 10px; background: #fafbfc; font-size: 12.5px; color: #6b7684; }
.ie-opt-hidden span { display: inline-block; margin: 0 4px; padding: 1px 8px; border-radius: 999px; background: #eef1f5; }
.ie-opt-hidden small { display: block; margin-top: 4px; color: #9aa1ab; }
.ie-opt-empty { padding: 18px; border: 1px dashed #cfd6e0; border-radius: 12px; background: #fafbfc; font-size: 13px; color: #6b7684; }
/* 폼 가독성: 섹션·헤더 폭 제한 + 균형 잡힌 2열 */
.rsva .rsva-panel, .rsva .ie-head { max-width: 960px; }
.rsva .rsva-form-grid { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); align-items: start; }
</style>

<div class="rsva">
	<div class="ie-head">
		<div>
			<h2>{{ $item ? '상품 편집' : (Context::get('clone_from') ? '상품 복제' : '새 상품 등록') }}</h2>
			<p>{{ lang('commerce.admin_item_edit_1') }}<span class="ie-req">*</span>{{ lang('commerce.admin_item_edit_2') }}</p>
		</div>
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItems') }}" class="rsva-btn">{{ lang('commerce.admin_item_edit_3') }}</a>
	</div>

	<form action="{{ getUrl('') }}" method="post" enctype="multipart/form-data" id="ieForm">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminInsertItem" />
		@if ($item)
		<input type="hidden" name="item_srl" value="{{ $item->item_srl }}" />
		@else
		{{-- 신규: 에디터 첨부 귀속용으로 미리 발급된 srl 로 저장 --}}
		<input type="hidden" name="item_srl" value="{{ $editor_target_srl }}" />
		@if (Context::get('clone_from'))
		<input type="hidden" name="clone_from" value="{{ (int)Context::get('clone_from') }}" />
		@endif
		@endif

		{{-- 1. 기본 정보 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_4') }}</h3>
			<p class="ie-sec-desc">{{ lang('commerce.admin_item_edit_5') }}</p>

			@php
			$ie_images = [];
			if (!empty($item->images))
			{
				$decoded = json_decode($item->images, true);
				if (is_array($decoded)) { $ie_images = array_values(array_filter($decoded, 'is_string')); }
			}
			if (!count($ie_images) && !empty($item->thumb)) { $ie_images = [$item->thumb]; }
			@endphp
			<div style="margin-bottom:18px">
				<label>{{ lang('commerce.admin_item_edit_6') }} <span style="font-weight:500;color:#8b95a1">{{ lang('commerce.admin_item_edit_7') }}</span></label>
				<div class="ie-imgs" id="ieImgs"></div>
				<input type="file" name="image_files[]" accept="image/*" multiple id="ieImgFile" class="ie-hidden" />
				<input type="hidden" name="images_json" id="ieImagesJson" value="{{ json_encode($ie_images, JSON_UNESCAPED_SLASHES) }}" />
				<span class="ie-help">{{ lang('commerce.admin_item_edit_8') }}</span>
			</div>

			<div class="rsva-form-grid">
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.admin_item_edit_9') }} <span class="ie-req">*</span></label>
					<div class="ie-langrow">
						<input type="text" name="item_name" required maxlength="250" placeholder="{{ lang('commerce.admin_item_edit_142') }}" value="{{ $item->item_name ?? '' }}" />
						@include('_langfield', ['lf_name' => 'item_name', 'lf_value' => $item->item_name ?? '', 'lf_key' => 'name'])
					</div>
				</div>
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.admin_item_edit_10') }}</label>
					<div class="ie-langrow">
						<input type="text" name="summary" maxlength="250" placeholder="{{ lang('commerce.admin_item_edit_143') }}" value="{{ $item->summary ?? '' }}" />
						@include('_langfield', ['lf_name' => 'summary', 'lf_value' => $item->summary ?? '', 'lf_key' => 'summary'])
					</div>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_11') }}</label>
					<select name="category_srl" style="width:100%">
						<option value="0">{{ lang('commerce.admin_item_edit_12') }}</option>
						@foreach ($categories as $srl => $c)
						<option value="{{ $srl }}" @if((int)($item->category_srl ?? 0) === $srl) selected @endif>{{ ($c->depth ?? 0) > 0 ? str_repeat('&nbsp;&nbsp;', $c->depth) . '└ ' : '' }}{{ $c->title }}</option>
						@endforeach
					</select>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_13') }} <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCategories') }}" style="color:#2677e3">{{ lang('commerce.admin_item_edit_14') }}</a>{{ lang('commerce.admin_item_edit_15') }}</span>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_16') }}</label>
					<input type="text" name="item_code" placeholder="{{ lang('commerce.admin_item_edit_144') }}" value="{{ $item->item_code ?? '' }}" style="width:100%" />
					<span class="ie-help">{{ lang('commerce.admin_item_edit_17') }}</span>
				</div>
			</div>
		</div>

		{{-- 2. 가격 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_18') }}</h3>
			<div class="rsva-form-grid">
				<div>
					<label>{{ lang('commerce.admin_item_edit_19') }} <span class="ie-req">*</span></label>
					<div class="ie-suffix" data-suffix="원"><input type="number" name="price" min="0" required id="iePrice" value="{{ $item->price ?? '' }}" placeholder="0" /></div>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_20') }}</label>
					<div class="ie-suffix" data-suffix="원"><input type="number" name="sale_price" min="0" id="ieSalePrice" value="{{ ($item->sale_price ?? 0) > 0 ? $item->sale_price : '' }}" placeholder="{{ lang('commerce.admin_item_edit_145') }}" /></div>
					<span class="ie-discount" id="ieDiscountBadge"></span>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_21') }}</span>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_22') }}</label>
					<div class="ie-pills">
						<label><input type="radio" name="tax_type" value="taxable" @if(($item->tax_type ?? 'taxable') === 'taxable') checked @endif /> {{ lang('commerce.admin_item_edit_23') }}</label>
						<label><input type="radio" name="tax_type" value="free" @if(($item->tax_type ?? '') === 'free') checked @endif /> {{ lang('commerce.admin_item_edit_24') }}</label>
					</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_25') }}</span>
				</div>
			</div>
		</div>

		{{-- 3. 재고·구매수량 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_26') }}</h3>
			<div class="rsva-form-grid">
				<div>
					<label>{{ lang('commerce.admin_item_edit_27') }}</label>
					<div class="ie-pills">
						<label><input type="radio" name="use_stock" value="Y" @if(($item->use_stock ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_item_edit_28') }}</label>
						<label><input type="radio" name="use_stock" value="N" @if(($item->use_stock ?? '') === 'N') checked @endif /> {{ lang('commerce.admin_item_edit_29') }}</label>
					</div>
				</div>
				<div id="ieStockField">
					<label>{{ lang('commerce.admin_item_edit_30') }}</label>
					<div style="padding:10px 0;font-size:15px;font-weight:700">{{ number_format((int)($item->stock ?? 0)) }}개</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_31') }} <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminStock') }}">{{ lang('commerce.admin_item_edit_27') }}</a> {{ lang('commerce.admin_item_edit_32') }}</span>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_33') }}</label>
					<div style="display:flex;gap:8px;align-items:center">
						<div class="ie-suffix" data-suffix="개" style="flex:1"><input type="number" name="min_qty" min="0" value="{{ $item->min_qty ?? 0 }}" placeholder="{{ lang('commerce.admin_item_edit_146') }}" /></div>
						<span style="color:#8b95a1">~</span>
						<div class="ie-suffix" data-suffix="개" style="flex:1"><input type="number" name="max_qty" min="0" value="{{ $item->max_qty ?? 0 }}" placeholder="{{ lang('commerce.admin_item_edit_147') }}" /></div>
					</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_34') }}</span>
				</div>
			</div>
		</div>

		{{-- 4. 배송 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_35') }}</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.admin_item_edit_36') }}</label>
					<div class="ie-pills">
						<label><input type="radio" name="ship_fee_type" value="default" @if(($item->ship_fee_type ?? 'default') === 'default') checked @endif /> 기본 정책 ({{ number_format((int)($shop_config->default_ship_fee ?? 0)) }}원@if ((int)($shop_config->free_ship_over ?? 0) > 0), {{ number_format((int)$shop_config->free_ship_over) }}원 이상 무료@endif)</label>
						<label><input type="radio" name="ship_fee_type" value="free" @if(($item->ship_fee_type ?? '') === 'free') checked @endif /> {{ lang('commerce.admin_item_edit_37') }}</label>
						<label><input type="radio" name="ship_fee_type" value="fixed" @if(($item->ship_fee_type ?? '') === 'fixed') checked @endif /> {{ lang('commerce.admin_item_edit_38') }}</label>
					</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_39') }} <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminConfig') }}" style="color:#2677e3">{{ lang('commerce.admin_item_edit_40') }}</a>{{ lang('commerce.admin_item_edit_41') }}</span>
				</div>
				<div id="ieShipFeeField">
					<label>{{ lang('commerce.admin_item_edit_42') }}</label>
					<div class="ie-suffix" data-suffix="원"><input type="number" name="ship_fee" min="0" value="{{ $item->ship_fee ?? 0 }}" /></div>
				</div>
			</div>
		</div>

		{{-- 5. 상세 설명 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_43') }}</h3>
			<p class="ie-sec-desc">{{ lang('commerce.admin_item_edit_44') }}</p>
			{{-- 에디터는 폼 안의 content 필드에서 초기 내용을 읽는다 (CKEDITOR.appendTo 규약) --}}
			<textarea name="content" style="display:none">{{ $item->content ?? '' }}</textarea>
			{!! $editor !!}
		</div>

		{{-- 6. 노출·판매 설정 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_45') }}</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.admin_item_edit_46') }}</label>
					<div class="ie-pills">
						<label><input type="radio" name="status" value="sale" @if(($item->status ?? 'sale') === 'sale') checked @endif /> {{ lang('commerce.admin_item_edit_47') }}</label>
						<label><input type="radio" name="status" value="soldout" @if(($item->status ?? '') === 'soldout') checked @endif /> {{ lang('commerce.admin_item_edit_48') }}</label>
						<label><input type="radio" name="status" value="hidden" @if(($item->status ?? '') === 'hidden') checked @endif /> {{ lang('commerce.admin_item_edit_49') }}</label>
						<label><input type="radio" name="status" value="stop" @if(($item->status ?? '') === 'stop') checked @endif /> {{ lang('commerce.admin_item_edit_50') }}</label>
					</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_51') }}</span>
				</div>
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.admin_item_edit_52') }}</label>
					@php $ie_has_period = !empty($item->sale_start) || !empty($item->sale_end); @endphp
					<label style="display:inline-flex;align-items:center;gap:7px;font-weight:600;font-size:13.5px;cursor:pointer"><input type="checkbox" id="iePeriodToggle" @if($ie_has_period) checked @endif style="accent-color:#2677e3;width:16px;height:16px" /> {{ lang('commerce.admin_item_edit_53') }}</label>
					<div id="iePeriodFields" class="{{ $ie_has_period ? '' : 'ie-hidden' }}" style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
						<input type="datetime-local" name="sale_start" value="{{ !empty($item->sale_start) ? substr($item->sale_start,0,4).'-'.substr($item->sale_start,4,2).'-'.substr($item->sale_start,6,2).'T'.substr($item->sale_start,8,2).':'.substr($item->sale_start,10,2) : '' }}" />
						<span style="color:#8b95a1">~</span>
						<input type="datetime-local" name="sale_end" value="{{ !empty($item->sale_end) ? substr($item->sale_end,0,4).'-'.substr($item->sale_end,4,2).'-'.substr($item->sale_end,6,2).'T'.substr($item->sale_end,8,2).':'.substr($item->sale_end,10,2) : '' }}" />
					</div>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_54') }}</label>
					<div class="ie-checks" style="padding-top:8px">
						<label><input type="checkbox" name="is_recommend" value="Y" @if(($item->is_recommend ?? '') === 'Y') checked @endif /> {{ lang('commerce.admin_item_edit_55') }}</label>
						<label><input type="checkbox" name="is_new" value="Y" @if(($item->is_new ?? '') === 'Y') checked @endif /> NEW</label>
						@php $ie_badge_srls = \Zittme\Modules\Commerce\Models\Badge::parseSrls($item->badges ?? ''); @endphp
						@foreach ($badges ?? [] as $ie_badge)
						@php
							$ie_b_style = '';
							if (!empty($ie_badge->bg_color)) { $ie_b_style .= 'background:' . $ie_badge->bg_color . ';'; }
							if (!empty($ie_badge->color)) { $ie_b_style .= 'color:' . $ie_badge->color . ';'; }
						@endphp
						<label>
							<input type="checkbox" name="badge_srls[]" value="{{ $ie_badge->badge_srl }}" @if (in_array((int)$ie_badge->badge_srl, $ie_badge_srls, true)) checked @endif />
							<span class="zmc-badge-pv" style="{{ $ie_b_style }}">{{ $ie_badge->title }}</span>
						</label>
						@endforeach
					</div>
					<p class="ie-help">{{ lang('commerce.admin_item_edit_56') }} <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminBadges') }}">{{ lang('commerce.admin_item_edit_57') }}</a>{{ lang('commerce.admin_item_edit_58') }}</p>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_59') }}</label>
					<div class="ie-checks" style="padding-top:8px">
						<label><input type="checkbox" name="is_adult" value="Y" @if(($item->is_adult ?? '') === 'Y') checked @endif /> {{ lang('commerce.admin_item_edit_60') }}</label>
					</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_61') }} <a href="{{ getUrl('', 'p', '', 'module', 'admin', 'act', 'dispMemberAdminIdentityConfig') }}" target="_blank" style="color:#2677e3">{{ lang('commerce.admin_item_edit_62') }}</a>{{ lang('commerce.admin_item_edit_63') }}</span>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_64') }}</label>
					<input type="number" name="list_order" value="{{ $item->list_order ?? 0 }}" style="width:120px" />
					<span class="ie-help">{{ lang('commerce.admin_item_edit_65') }}</span>
				</div>
			</div>
		</div>

		@php
		$ie_promos = Context::get('item_promotions') ?: [];
		$ie_promo_srls = Context::get('item_promo_srls') ?: [];
		$ie_shown = [];
		foreach ($ie_promos as $ie_pm0)
		{
			$ie_shown[] = (int)$ie_pm0->promo_srl;
		}
		@endphp
		@if (count($ie_promos))
		{{-- 기획전 노출 — 별도 섹션 (기획전이 많아도 검색·스크롤로 관리) --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_66') }}</h3>
			<p class="ie-sec-desc">{{ lang('commerce.admin_item_edit_67') }}</p>
			<input type="hidden" name="promo_shown" value="{{ json_encode($ie_shown) }}" />
			@if (count($ie_promos) > 8)
			<input type="text" id="iePromoSearch" placeholder="{{ lang('commerce.admin_item_edit_148') }}" style="width:260px;margin-bottom:10px" />
			@endif
			<div id="iePromoList" style="max-height:220px;overflow-y:auto;border:1px solid #e5e8ee;border-radius:12px;background:#fff">
				@foreach ($ie_promos as $ie_pm)
				<label data-name="{{ $ie_pm->title }}" style="display:flex;align-items:center;gap:10px;padding:10px 14px;margin:0;font-weight:500;font-size:13.5px;border-bottom:1px solid #f4f6f9;cursor:pointer">
					<input type="checkbox" name="promo_srls[]" value="{{ $ie_pm->promo_srl }}" style="width:16px;height:16px;accent-color:#2677e3;flex:0 0 auto" @if (in_array((int)$ie_pm->promo_srl, $ie_promo_srls, true)) checked @endif />
					<span style="flex:1;text-align:left">{{ $ie_pm->title }}</span>
					@if (($ie_pm->status ?? 'Y') !== 'Y')<small style="color:#8b95a1;flex:0 0 auto">{{ lang('commerce.admin_item_edit_49') }}</small>@endif
				</label>
				@endforeach
			</div>
		</div>
		<script>
		(function () {
			var s = document.getElementById('iePromoSearch');
			if (!s) return;
			s.addEventListener('input', function () {
				var q = s.value.trim();
				document.querySelectorAll('#iePromoList label[data-name]').forEach(function (row) {
					row.style.display = (!q || row.getAttribute('data-name').indexOf(q) !== -1) ? '' : 'none';
				});
			});
		})();
		</script>
		@endif

		<div class="ie-savebar is-stuck" id="ieSavebar">
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ $item ? '변경사항 저장' : '상품 등록' }}</button>
			<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItems') }}" class="rsva-btn">{{ lang('commerce.admin_item_edit_68') }}</a>
			<small>{{ lang('commerce.admin_item_edit_69') }}</small>
		</div>
		<div id="ieSaveSentinel" style="height:1px"></div>
	</form>

	{{-- 옵션 --}}
	<a id="ieOptions"></a>
	<div class="rsva-panel">
		<h3 style="display:flex;align-items:center;gap:7px">{{ lang('commerce.admin_item_edit_70') }}
			<span class="ie-opt-help" tabindex="0">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"/><path d="M12 16v-5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><circle cx="12" cy="8" r="1.2" fill="currentColor"/></svg>
				<span class="ie-opt-help-layer">
					<strong>{{ lang('commerce.admin_item_edit_71') }}</strong>
					<span>{{ lang('commerce.admin_item_edit_72') }} <b>{{ lang('commerce.admin_item_edit_73') }}</b> {{ lang('commerce.admin_item_edit_74') }} <b>{{ lang('commerce.admin_item_edit_75') }}</b>{{ lang('commerce.admin_item_edit_76') }}</span>
					<span class="ie-opt-ex">{{ lang('commerce.admin_item_edit_77') }}</span>
					<strong>{{ lang('commerce.admin_item_edit_78') }}</strong>
					<span><b>{{ lang('commerce.admin_item_edit_79') }}</b> {{ lang('commerce.admin_item_edit_80') }} <b>{{ lang('commerce.admin_item_edit_81') }}</b>{{ lang('commerce.admin_item_edit_82') }}</span>
					<span><b>{{ lang('commerce.admin_item_edit_83') }}</b> {{ lang('commerce.admin_item_edit_84') }} <b>{{ lang('commerce.admin_item_edit_85') }}</b> {{ lang('commerce.admin_item_edit_86') }} <b>{{ lang('commerce.admin_item_edit_87') }}</b> {{ lang('commerce.admin_item_edit_88') }}</span>
					<span class="ie-opt-ex">{{ lang('commerce.admin_item_edit_89') }}</span>
					<span class="ie-opt-note">{{ lang('commerce.admin_item_edit_90') }}</span>
					<strong>{{ lang('commerce.admin_item_edit_91') }}</strong>
					<span>{{ lang('commerce.admin_item_edit_92') }} <b>{{ lang('commerce.admin_item_edit_93') }}</b> {{ lang('commerce.admin_item_edit_94') }} <b>{{ lang('commerce.admin_item_edit_95') }}</b>{{ lang('commerce.admin_item_edit_96') }}</span>
					<span class="ie-opt-ex">{{ lang('commerce.admin_item_edit_97') }}</span>
					<span class="ie-opt-note">{{ lang('commerce.admin_item_edit_98') }}</span>
				</span>
			</span>
		</h3>
		<style>
		.ie-opt-help { position: relative; display: inline-flex; color: #8b95a1; cursor: help; }
		.ie-opt-help:hover, .ie-opt-help:focus { color: #2677e3; }
		.ie-opt-help-layer { display: none; position: absolute; top: 24px; left: -10px; z-index: 50; width: 420px; max-width: 80vw; padding: 16px 18px; border: 1px solid #e3e6eb; border-radius: 12px; background: #fff; box-shadow: 0 10px 32px rgba(0,0,0,.12); font-size: 13px; font-weight: 400; line-height: 1.65; color: #333d4b; }
		.ie-opt-help:hover .ie-opt-help-layer, .ie-opt-help:focus .ie-opt-help-layer { display: block; }
		.ie-opt-help-layer strong { display: block; margin: 10px 0 3px; font-size: 13.5px; color: #191f28; }
		.ie-opt-help-layer strong:first-child { margin-top: 0; }
		.ie-opt-help-layer > span { display: block; }
		.ie-opt-ex { margin-top: 3px; padding: 7px 10px; border-radius: 8px; background: #f4f6f9; font-size: 12.5px; color: #6b7684; }
		.ie-opt-note { margin-top: 10px; padding-top: 10px; border-top: 1px solid #eef1f5; font-size: 12.5px; color: #b26a00; }
		</style>
		@if (!$item)
		{{-- 새 상품도 옵션을 미리 담아 둘 수 있다. 상품을 저장할 때 함께 등록된다 --}}
		<div class="ie-newopt" id="ieNewOpt">
			<div class="ie-newopt-block">
				<b>{{ lang('commerce.admin_item_edit_99') }} <small>{{ lang('commerce.admin_item_edit_100') }}</small></b>
				<div class="ie-newopt-rows" data-type="basic"></div>
				<button type="button" class="rsva-btn rsva-btn-sm" data-add="basic">{{ lang('commerce.admin_item_edit_101') }}</button>
			</div>
			<div class="ie-newopt-block">
				<b>{{ lang('commerce.admin_item_edit_102') }} <small>{{ lang('commerce.admin_item_edit_103') }}</small></b>
				<div class="ie-newopt-rows" data-type="extra"></div>
				<button type="button" class="rsva-btn rsva-btn-sm" data-add="extra">{{ lang('commerce.admin_item_edit_104') }}</button>
			</div>
			<span class="ie-help">{{ lang('commerce.admin_item_edit_105') }} <b>{{ lang('commerce.admin_item_edit_106') }}</b>{{ lang('commerce.admin_item_edit_107') }}</span>
		</div>
		<style>
		.ie-newopt-block { margin-bottom: 18px; }
		.ie-newopt-block > b { display: block; margin-bottom: 8px; font-size: 14px; }
		.ie-newopt-block > b small { font-weight: 500; color: #8b95a1; }
		.ie-newopt-row { display: flex; gap: 8px; align-items: center; margin-bottom: 8px; flex-wrap: wrap; }
		.ie-newopt-row input[type="text"] { flex: 1 1 220px; min-width: 0; }
		.ie-newopt-row input[type="number"] { width: 120px; }
		.ie-newopt-row .ie-newopt-del { padding: 6px 10px; border: 1px solid #dde3ec; border-radius: 7px; background: #fff; color: #8b95a1; cursor: pointer; }
		.ie-newopt-row .ie-newopt-del:hover { border-color: #e5484d; color: #e5484d; }
		</style>
		@else
		@php
		$ie_opt_basic = [];
		$ie_opt_extra = [];
		foreach ($options as $ie_opt_row)
		{
			if (($ie_opt_row->option_type ?? 'basic') === 'extra')
			{
				$ie_opt_extra[] = $ie_opt_row;
			}
			else
			{
				$ie_opt_basic[] = $ie_opt_row;
			}
		}
		$ie_mode = ($item->option_mode ?? 'single') === 'combo' ? 'combo' : 'single';
		$ie_opt_hidden = [];
		$ie_opt_shown = [];
		foreach ($ie_opt_basic as $ie_opt_row)
		{
			if (($ie_opt_row->status ?? 'Y') === 'N') { $ie_opt_hidden[] = $ie_opt_row; }
			else { $ie_opt_shown[] = $ie_opt_row; }
		}
		@endphp
		{{-- 행 인라인 수정: 셀 입력은 form 속성으로 행별 폼(테이블 밖)에 연결한다 (중첩 폼 회피) --}}
		@foreach ($options as $opt)
		<form id="optEdit{{ $opt->option_srl }}" action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminUpdateOption" />
			<input type="hidden" name="success_return_url" value="{{ getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item->item_srl) }}#ieOptions" />
			<input type="hidden" name="option_srl" value="{{ $opt->option_srl }}" />
			<input type="hidden" name="item_srl" value="{{ $item->item_srl }}" />
			<input type="hidden" name="option_type" value="{{ ($opt->option_type ?? 'basic') === 'extra' ? 'extra' : 'basic' }}" />
		</form>
		@endforeach

		{{-- ── 기본 옵션 방식 ── --}}
		<div style="margin-bottom:18px">
			<b style="display:block;margin-bottom:6px;font-size:14px">{{ lang('commerce.admin_item_edit_108') }}</b>
			<div class="ie-pills">
				<label><input type="radio" name="option_mode" value="single" form="ieForm" @if ($ie_mode !== 'combo') checked @endif /> {{ lang('commerce.admin_item_edit_79') }}</label>
				<label><input type="radio" name="option_mode" value="combo" form="ieForm" @if ($ie_mode === 'combo') checked @endif /> {{ lang('commerce.admin_item_edit_109') }}</label>
			</div>
			<span class="ie-help">{{ lang('commerce.admin_item_edit_110') }} <b>{{ lang('commerce.admin_item_edit_111') }}</b>{{ lang('commerce.admin_item_edit_112') }}</span>
		</div>

		{{-- ── 조합형 옵션 축 (색상 × 사이즈) ── --}}
		@php $ie_axes = Zittme\Modules\Commerce\Models\Combo::axes($item->option_axes ?? ''); @endphp
		<div class="ie-axes" data-mode-only="combo" style="margin-bottom:22px">
			<b style="display:block;margin-bottom:4px;font-size:14px">{{ lang('commerce.admin_item_edit_113') }} <small style="font-weight:500;color:#8b95a1">{{ lang('commerce.admin_item_edit_114') }}</small></b>
			<p class="ie-help" style="margin:0 0 10px">{{ lang('commerce.admin_item_edit_115') }} <b>{{ lang('commerce.admin_item_edit_116') }}</b>{{ lang('commerce.admin_item_edit_117') }} <b>{{ lang('commerce.admin_item_edit_118') }}</b>{{ lang('commerce.admin_item_edit_119') }} <code>{{ lang('commerce.admin_item_edit_120') }}</code> {{ lang('commerce.admin_item_edit_121') }}</p>
			<div id="ieAxes"></div>
			<div style="display:flex;gap:8px;align-items:center;margin-top:8px">
				<button type="button" class="rsva-btn rsva-btn-sm" id="ieAxisAdd">{{ lang('commerce.admin_item_edit_122') }}</button>
				<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-primary" id="ieComboBuild" data-item="{{ $item->item_srl }}">{{ lang('commerce.admin_item_edit_116') }}</button>
				<small style="color:#8b95a1">{{ lang('commerce.admin_item_edit_123') }}</small>
			</div>
			<input type="hidden" name="option_axes" id="ieAxesJson" value="{{ $item->option_axes ?? '' }}" form="ieForm" />
			<script type="application/json" id="ieAxesInit">{!! json_encode($ie_axes, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
		</div>

		{{-- ── 기본 옵션 (상품 변형) ── --}}
		<div style="margin-bottom:22px">
			<div class="ie-opt-warn" id="ieOptWarn" hidden>
				{{ lang('commerce.admin_item_edit_124') }} <b>{{ lang('commerce.admin_item_edit_79') }}</b>{{ lang('commerce.admin_item_edit_125') }} <b>{{ lang('commerce.admin_item_edit_116') }}</b>{{ lang('commerce.admin_item_edit_126') }}
			</div>
			@if (count($ie_opt_hidden))
			<div class="ie-opt-hidden">
				숨긴 기본 옵션 {{ count($ie_opt_hidden) }}개가 있습니다 —
				@foreach ($ie_opt_hidden as $ie_hid)<span>{{ $ie_hid->option_label }}</span>@endforeach
				<small>{{ lang('commerce.admin_item_edit_127') }}</small>
			</div>
			@endif
			<b style="display:block;margin-bottom:4px;font-size:14px">{{ lang('commerce.admin_item_edit_99') }} <small style="font-weight:500;color:#8b95a1">{{ lang('commerce.admin_item_edit_100') }}</small></b>
			@if (count($ie_opt_shown))
			<table class="rsva-table" style="margin-bottom:10px">
				<thead><tr><th>{{ lang('commerce.admin_item_edit_128') }}</th><th>{{ lang('commerce.admin_item_edit_129') }}</th><th>{{ lang('commerce.admin_item_edit_130') }}</th><th>SKU</th><th></th></tr></thead>
				<tbody>
					@foreach ($ie_opt_shown as $opt)
					<tr @if (empty($opt->combo)) class="ie-opt-manual" @endif>
						<td><input type="text" name="option_label" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->option_label }}" required style="width:100%;min-width:180px" /></td>
						<td><input type="number" name="price_add" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->price_add }}" style="width:110px" /></td>
						<td><input type="number" name="stock" form="optEdit{{ $opt->option_srl }}" min="0" value="{{ $opt->stock }}" style="width:80px" /></td>
						<td><input type="text" name="sku" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->sku }}" style="width:110px" /></td>
						<td style="white-space:nowrap">
							<button type="submit" form="optEdit{{ $opt->option_srl }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_item_edit_131') }}</button>
							<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('옵션을 삭제하시겠습니까?')">
								<input type="hidden" name="module" value="admin" />
								<input type="hidden" name="act" value="procCommerceAdminDeleteOption" />
								<input type="hidden" name="success_return_url" value="{{ getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item->item_srl) }}#ieOptions" />
								<input type="hidden" name="option_srl" value="{{ $opt->option_srl }}" />
								<input type="hidden" name="item_srl" value="{{ $item->item_srl }}" />
								<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_item_edit_132') }}</button>
							</form>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
			@endif
			<form action="{{ getUrl('') }}" method="post" data-mode-only="single">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procCommerceAdminInsertOption" />
				<input type="hidden" name="success_return_url" value="{{ getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item->item_srl) }}#ieOptions" />
				<input type="hidden" name="item_srl" value="{{ $item->item_srl }}" />
				<input type="hidden" name="option_type" value="basic" />
				<div class="rsva-inline">
					<div style="min-width:240px"><label>{{ lang('commerce.admin_item_edit_128') }}</label><input type="text" name="option_label" required placeholder="{{ lang('commerce.admin_item_edit_149') }}" style="width:100%" /></div>
					<div><label>{{ lang('commerce.admin_item_edit_129') }}</label><input type="number" name="price_add" value="0" style="width:110px" /></div>
					<div><label>{{ lang('commerce.admin_item_edit_133') }}</label><input type="number" name="stock" min="0" value="0" style="width:90px" /></div>
					<div><label>SKU</label><input type="text" name="sku" style="width:120px" /></div>
					<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_item_edit_134') }}</button></div>
				</div>
			</form>
		</div>

		{{-- ── 추가 옵션 (부가 상품) ── --}}
		<div>
			<b style="display:block;margin-bottom:4px;font-size:14px">{{ lang('commerce.admin_item_edit_135') }} <small style="font-weight:500;color:#8b95a1">{{ lang('commerce.admin_item_edit_136') }}</small></b>
			@if (count($ie_opt_extra))
			<table class="rsva-table" style="margin-bottom:10px">
				<thead><tr><th>{{ lang('commerce.admin_item_edit_128') }}</th><th>{{ lang('commerce.admin_item_edit_137') }}</th><th>{{ lang('commerce.admin_item_edit_130') }}</th><th>SKU</th><th></th></tr></thead>
				<tbody>
					@foreach ($ie_opt_extra as $opt)
					<tr>
						<td><input type="text" name="option_label" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->option_label }}" required style="width:100%;min-width:180px" /></td>
						<td><input type="number" name="price_add" form="optEdit{{ $opt->option_srl }}" min="0" value="{{ $opt->price_add }}" style="width:110px" /></td>
						<td><input type="number" name="stock" form="optEdit{{ $opt->option_srl }}" min="0" value="{{ $opt->stock }}" style="width:80px" /></td>
						<td><input type="text" name="sku" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->sku }}" style="width:110px" /></td>
						<td style="white-space:nowrap">
							<button type="submit" form="optEdit{{ $opt->option_srl }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_item_edit_131') }}</button>
							<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('옵션을 삭제하시겠습니까?')">
								<input type="hidden" name="module" value="admin" />
								<input type="hidden" name="act" value="procCommerceAdminDeleteOption" />
								<input type="hidden" name="success_return_url" value="{{ getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item->item_srl) }}#ieOptions" />
								<input type="hidden" name="option_srl" value="{{ $opt->option_srl }}" />
								<input type="hidden" name="item_srl" value="{{ $item->item_srl }}" />
								<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_item_edit_132') }}</button>
							</form>
						</td>
					</tr>
					@endforeach
				</tbody>
			</table>
			@endif
			<form action="{{ getUrl('') }}" method="post">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procCommerceAdminInsertOption" />
				<input type="hidden" name="success_return_url" value="{{ getNotEncodedUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $item->item_srl) }}#ieOptions" />
				<input type="hidden" name="item_srl" value="{{ $item->item_srl }}" />
				<input type="hidden" name="option_type" value="extra" />
				<div class="rsva-inline">
					<div style="min-width:240px"><label>{{ lang('commerce.admin_item_edit_128') }}</label><input type="text" name="option_label" required placeholder="{{ lang('commerce.admin_item_edit_150') }}" style="width:100%" /></div>
					<div><label>{{ lang('commerce.admin_item_edit_137') }}</label><input type="number" name="price_add" min="0" value="0" style="width:130px" /></div>
					<div><label>{{ lang('commerce.admin_item_edit_133') }}</label><input type="number" name="stock" min="0" value="0" style="width:90px" /></div>
					<div><label>SKU</label><input type="text" name="sku" style="width:120px" /></div>
					<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_item_edit_138') }}</button></div>
				</div>
			</form>
		</div>
		@endif
	</div>
</div>

<script>
(function () {
	// ── 옵션을 저장·삭제한 뒤 보던 자리로 되돌리기 ──
	// 폼 제출 → 리다이렉트 → 새로 그리기 라서, 위치를 기억해 두었다가 복원한다
	(function () {
		var KEY = 'zmcItemScroll:' + (document.querySelector('#ieForm [name=item_srl]') || {}).value;
		document.addEventListener('submit', function (e) {
			if (e.target && e.target.querySelector('[name=act]')) {
				try { sessionStorage.setItem(KEY, String(window.scrollY)); } catch (err) {}
			}
		}, true);
		var saved = null;
		try { saved = sessionStorage.getItem(KEY); } catch (err) {}
		if (saved !== null) {
			try { sessionStorage.removeItem(KEY); } catch (err) {}
			window.scrollTo(0, parseInt(saved, 10) || 0);
			// 이미지·에디터가 늦게 자리를 잡아 높이가 바뀌므로 한 번 더 맞춘다
			window.addEventListener('load', function () { window.scrollTo(0, parseInt(saved, 10) || 0); });
		}
	})();

	// ── 기본 옵션 방식 전환 ──
	var modeRadios = document.querySelectorAll('input[name="option_mode"]');
	if (modeRadios.length) {
		function applyMode() {
			var mode = 'single';
			Array.prototype.forEach.call(modeRadios, function (r) { if (r.checked) mode = r.value; });
			document.querySelectorAll('[data-mode-only]').forEach(function (el) {
				el.style.display = el.getAttribute('data-mode-only') === mode ? '' : 'none';
			});
			// 조합형으로 바꿨는데 직접 입력해 둔 옵션이 남아 있으면 알려 준다
			var warn = document.getElementById('ieOptWarn');
			if (warn) {
				var manual = document.querySelectorAll('.ie-opt-manual').length;
				warn.hidden = !(mode === 'combo' && manual > 0);
			}
		}
		Array.prototype.forEach.call(modeRadios, function (r) { r.addEventListener('change', applyMode); });
		applyMode();
	}

	// ── 조합형 옵션 축 편집 ──
	var axesEl = document.getElementById('ieAxes');
	var axesJson = document.getElementById('ieAxesJson');
	if (axesEl && axesJson) {
		var MAX_AXES = 3;

		function axisRow(data) {
			data = data || {};
			var row = document.createElement('div');
			row.className = 'ie-axis';
			row.innerHTML =
				'<input type="text" data-a="name" placeholder="축 이름 (예: 색상)" />' +
				'<input type="text" data-a="values" placeholder="값 (쉼표로 구분 — 블랙, 화이트)" />' +
				'<select data-a="style" title="구매 화면 표시 방식">' +
					'<option value="select">셀렉트</option>' +
					'<option value="button">버튼</option>' +
					'<option value="color">색상칩</option>' +
				'</select>' +
				'<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger" data-adel>삭제</button>';
			row.querySelector('[data-a=name]').value = data.name || '';
			row.querySelector('[data-a=values]').value = (data.values || []).join(', ');
			row.querySelector('[data-a=style]').value = data.style || 'select';
			row.querySelector('[data-a=style]').addEventListener('change', sync);
			row.querySelector('[data-adel]').addEventListener('click', function () { row.remove(); sync(); });
			row.querySelectorAll('input').forEach(function (el) { el.addEventListener('input', sync); });
			axesEl.appendChild(row);
		}

		// 화면의 축을 hidden JSON 으로 옮겨 담는다 (상품 저장 때 함께 실려 간다)
		function sync() {
			var out = [];
			axesEl.querySelectorAll('.ie-axis').forEach(function (row) {
				var name = row.querySelector('[data-a=name]').value.trim();
				var values = row.querySelector('[data-a=values]').value.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
				var style = row.querySelector('[data-a=style]').value;
				if (name && values.length) out.push({ name: name, values: values, style: style });
			});
			axesJson.value = out.length ? JSON.stringify(out) : '';
			return out;
		}

		var initAxes = [];
		var initEl = document.getElementById('ieAxesInit');
		try { initAxes = JSON.parse(initEl ? initEl.textContent : '[]') || []; } catch (e) {}
		initAxes.forEach(axisRow);
		if (!initAxes.length) axisRow();

		document.getElementById('ieAxisAdd').addEventListener('click', function () {
			if (axesEl.querySelectorAll('.ie-axis').length >= MAX_AXES) { alert('축은 최대 ' + MAX_AXES + '개까지 만들 수 있습니다.'); return; }
			axisRow();
		});

		var buildBtn = document.getElementById('ieComboBuild');
		buildBtn.addEventListener('click', function () {
			var itemSrl = parseInt(buildBtn.getAttribute('data-item'), 10) || 0;
			if (!itemSrl) { alert('상품을 먼저 저장한 뒤 조합을 만들 수 있습니다.'); return; }
			var axes = sync();
			if (!axes.length) { alert('축 이름과 값을 입력해 주세요.'); return; }
			var total = axes.reduce(function (n, a) { return n * a.values.length; }, 1);
			if (total > 100) { alert('조합이 ' + total + '개라 너무 많습니다. 100개 이하로 줄여 주세요.'); return; }
			if (!confirm('조합 ' + total + '개를 준비합니다. 이미 있는 조합의 추가금·재고는 그대로 둡니다.')) return;

			buildBtn.disabled = true;
			exec_json('commerce.procCommerceAdminBuildCombos', { item_srl: itemSrl, option_axes: axesJson.value }, function (ret) {
				alert((ret && ret.message) || '조합을 준비했습니다.');
				location.reload();
			}, function (ret) {
				buildBtn.disabled = false;
				alert((ret && ret.message) || '조합을 만들지 못했습니다.');
			});
		});
	}

	// ── 이미지 갤러리 (최대 7장, 첫 장 = 대표 썸네일) ──
	var MAX_IMGS = 7;
	var imgsWrap = document.getElementById('ieImgs');
	var imgsJson = document.getElementById('ieImagesJson');
	var imgFile = document.getElementById('ieImgFile');
	var imgs = [];
	try { imgs = JSON.parse(imgsJson.value) || []; } catch (e) { imgs = []; }
	var pendingCount = 0;

	// 목록이 바뀌면 상품에 곧바로 반영한다 (저장 버튼을 누르지 않아도 유지된다)
	function persistImgs() {
		exec_json('commerce.procCommerceAdminSaveItemImages', {
			item_srl: document.querySelector('input[name="item_srl"]').value,
			images_json: JSON.stringify(imgs)
		}, function () {}, function () {});
	}

	function syncImgs() {
		imgsJson.value = JSON.stringify(imgs);
		imgsWrap.innerHTML = '';
		imgs.forEach(function (src, i) {
			var d = document.createElement('div');
			d.className = 'ie-img' + (i === 0 ? ' is-main' : '');
			var box = document.createElement('div');
			box.className = 'ie-img-box';
			box.style.backgroundImage = "url('" + src + "')";
			d.appendChild(box);
			if (i === 0) {
				var badge = document.createElement('span');
				badge.className = 'ie-img-badge';
				badge.textContent = '대표';
				d.appendChild(badge);
			}
			var acts = document.createElement('div');
			acts.className = 'ie-img-acts';
			if (i !== 0) {
				var mainBtn = document.createElement('button');
				mainBtn.type = 'button';
				mainBtn.textContent = '대표로';
				mainBtn.addEventListener('click', function () {
					imgs.splice(i, 1);
					imgs.unshift(src);
					syncImgs();
					persistImgs();
				});
				acts.appendChild(mainBtn);
			}
			var delBtn = document.createElement('button');
			delBtn.type = 'button';
			delBtn.className = 'ie-img-del';
			delBtn.textContent = '삭제';
			delBtn.addEventListener('click', function () {
				imgs.splice(i, 1);
				syncImgs();
				persistImgs();
			});
			acts.appendChild(delBtn);
			d.appendChild(acts);
			imgsWrap.appendChild(d);
		});

		// 새로 선택된(저장 대기) 파일 표시
		for (var p = 0; p < pendingCount; p++) {
			var pd = document.createElement('div');
			pd.className = 'ie-img';
			var pb = document.createElement('div');
			pb.className = 'ie-img-box';
			pb.style.display = 'flex';
			pb.style.alignItems = 'center';
			pb.style.justifyContent = 'center';
			pb.style.fontSize = '11.5px';
			pb.style.color = '#2677e3';
			pb.style.fontWeight = '700';
			pb.textContent = '올리는 중…';
			pd.appendChild(pb);
			imgsWrap.appendChild(pd);
		}

		if (imgs.length + pendingCount < MAX_IMGS) {
			var add = document.createElement('div');
			add.className = 'ie-img-add';
			add.innerHTML = '<b>+</b><span>사진 추가</span>';
			add.addEventListener('click', function () { imgFile.click(); });
			imgsWrap.appendChild(add);
		}
	}

	// 사진을 고르면 곧바로 올린다. 저장을 누르지 않아도 미리보기가 바로 보인다
	imgFile.addEventListener('change', function () {
		var remain = MAX_IMGS - imgs.length;
		if (!imgFile.files.length) return;
		if (imgFile.files.length > remain) {
			alert('이미지는 최대 ' + MAX_IMGS + '장까지 등록할 수 있어요. (' + remain + '장 더 가능)');
		}
		var take = Math.min(imgFile.files.length, remain);
		if (take <= 0) { imgFile.value = ''; return; }

		var form = new FormData();
		form.append('module', 'commerce');
		form.append('act', 'procCommerceAdminUploadItemImage');
		form.append('item_srl', document.querySelector('input[name="item_srl"]').value);
		for (var i = 0; i < take; i++) { form.append('image_files[]', imgFile.files[i]); }

		pendingCount = take;
		syncImgs();

		jQuery.ajax({
			url: request_uri.setQuery('module', 'commerce').setQuery('act', 'procCommerceAdminUploadItemImage'),
			type: 'POST',
			data: form,
			processData: false,
			contentType: false,
			dataType: 'json'
		}).done(function (res) {
			pendingCount = 0;
			if (res && res.error === 0 && res.urls && res.urls.length) {
				res.urls.forEach(function (url) {
					if (imgs.length < MAX_IMGS) { imgs.push(url); }
				});
				persistImgs();
			} else {
				alert((res && res.message) || '사진을 올리지 못했어요.');
			}
			imgFile.value = '';
			syncImgs();
		}).fail(function () {
			pendingCount = 0;
			imgFile.value = '';
			syncImgs();
			alert('사진을 올리지 못했어요. 잠시 후 다시 시도해주세요.');
		});
	});
	syncImgs();

	// 할인율 실시간 표시
	var price = document.getElementById('iePrice');
	var sale = document.getElementById('ieSalePrice');
	var badge = document.getElementById('ieDiscountBadge');
	function updateDiscount() {
		var p = parseInt(price.value, 10) || 0;
		var s = parseInt(sale.value, 10) || 0;
		if (p > 0 && s > 0 && s < p) {
			badge.style.display = 'block';
			badge.textContent = Math.round((1 - s / p) * 100) + '% 할인 — ' + s.toLocaleString() + '원에 판매됩니다';
		} else if (s > 0 && p > 0 && s >= p) {
			badge.style.display = 'block';
			badge.textContent = '판매가가 정가보다 높거나 같아요. 확인해주세요.';
		} else {
			badge.style.display = 'none';
		}
	}
	price.addEventListener('input', updateDiscount);
	sale.addEventListener('input', updateDiscount);
	updateDiscount();

	// 재고 관리 토글
	function bindRadioToggle(name, targetId, showValue) {
		var target = document.getElementById(targetId);
		function apply() {
			var checked = document.querySelector('input[name="' + name + '"]:checked');
			target.classList.toggle('ie-hidden', !checked || checked.value !== showValue);
		}
		document.querySelectorAll('input[name="' + name + '"]').forEach(function (r) { r.addEventListener('change', apply); });
		apply();
	}
	bindRadioToggle('use_stock', 'ieStockField', 'Y');
	bindRadioToggle('ship_fee_type', 'ieShipFeeField', 'fixed');

	// 저장 바: 하단에 고정 중일 때만 흰 바 배경 (제자리에 오면 버튼만)
	var savebar = document.getElementById('ieSavebar');
	var sentinel = document.getElementById('ieSaveSentinel');
	if (savebar && sentinel && 'IntersectionObserver' in window) {
		new IntersectionObserver(function (entries) {
			savebar.classList.toggle('is-stuck', !entries[0].isIntersecting);
		}).observe(sentinel);
	}

	// 판매 기간 토글 — 해제하면 값도 비워서 상시 판매로
	var periodToggle = document.getElementById('iePeriodToggle');
	var periodFields = document.getElementById('iePeriodFields');
	periodToggle.addEventListener('change', function () {
		periodFields.classList.toggle('ie-hidden', !periodToggle.checked);
		if (!periodToggle.checked) {
			periodFields.querySelectorAll('input').forEach(function (i) { i.value = ''; });
		}
	});

	// 상세설명 에디터 동기화 — 코어 에디터는 ruleset 폼에서만 자동 동기화되므로,
	// 이 콘솔 폼은 제출 직전에 에디터 내용을 content 필드에 옮겨 담는다.
	// 에디터 번호는 hidden input 이 아니라 data-editor-sequence 속성에 있다.
	var ieForm = document.getElementById('ieForm');
	if (ieForm) {
		ieForm.addEventListener('submit', function () {
			var seqEl = ieForm.querySelector('[data-editor-sequence]');
			if (!seqEl) return;
			var seq = parseInt(seqEl.getAttribute('data-editor-sequence'), 10);
			var html = '';
			try {
				if (typeof editorGetContent === 'function') { html = editorGetContent(seq); }
			} catch (e) {}
			if (!html) {
				try {
					if (typeof _getCkeInstance === 'function') {
						var inst = _getCkeInstance(seq);
						if (inst) { html = inst.getData(); }
					}
				} catch (e) {}
			}
			var target = ieForm.querySelector('textarea[name=content], input[name=content]');
			if (!target) {
				target = document.createElement('input');
				target.type = 'hidden';
				target.name = 'content';
				ieForm.appendChild(target);
			}
			target.value = html;
		});
	}
})();
</script>

<script>
(function () {
	// 새 상품 등록 화면의 옵션 담기. 저장할 때 상품과 함께 등록된다
	var wrap = document.getElementById('ieNewOpt');
	if (!wrap) return;

	function addRow(type) {
		var box = wrap.querySelector('.ie-newopt-rows[data-type="' + type + '"]');
		if (!box) return;
		var row = document.createElement('div');
		row.className = 'ie-newopt-row';
		var priceLabel = type === 'basic' ? '추가금' : '가격';
		row.innerHTML =
			'<input type="text" name="new_option_label_' + type + '[]" placeholder="' +
				(type === 'basic' ? '예: 색상: 블랙 / 사이즈: L' : '예: 선물 포장 / 보냉백') + '" />' +
			'<input type="number" name="new_option_price_' + type + '[]" placeholder="' + priceLabel + '" ' +
				(type === 'extra' ? 'min="0" ' : '') + 'value="0" />' +
			'<input type="number" name="new_option_stock_' + type + '[]" placeholder="재고" min="0" value="0" />' +
			'<button type="button" class="ie-newopt-del" aria-label="줄 삭제">&times;</button>';
		row.querySelector('.ie-newopt-del').addEventListener('click', function () { row.remove(); });
		box.appendChild(row);
	}

	wrap.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-add]');
		if (btn) { addRow(btn.getAttribute('data-add')); }
	});

	// 처음 한 줄씩 열어 둔다 — 바로 입력할 수 있게
	addRow('basic');
	addRow('extra');
})();
</script>
