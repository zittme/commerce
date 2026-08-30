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
.ie-axis { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; margin-bottom: 6px; }
.ie-axis > .zlf-row-wrap { flex: 0 0 226px; }
/* 값 칩은 축 한 줄 아래로 내려 붙인다 */
.ie-axis-vals { flex: 1 0 100%; display: flex; flex-wrap: wrap; gap: 6px; margin: 2px 0 8px; padding-left: 2px; }
.ie-vchip input[data-v="text"] { width: 130px; }
.ie-vhint { color: #8b95a1; font-size: 12px; }
.ie-axis input[data-a="name"] { flex: 1; min-width: 0; }
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
			<h2>{{ $item ? lang('commerce.admin_item_edit_151') : (Context::get('clone_from') ? lang('commerce.admin_item_edit_152') : lang('commerce.admin_item_edit_153')) }}</h2>
			<p>{{ lang('commerce.admin_item_edit_1') }}<span class="ie-req">*</span>{{ lang('commerce.admin_item_edit_2') }}</p>
		</div>
		<a href="{{ \Context::get('act') === 'dispCommerceConsole' ? getUrl('', 'act', 'dispCommerceConsole', 'p', 'items') : getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminItems') }}" class="rsva-btn">{{ lang('commerce.admin_item_edit_3') }}</a>
	</div>

	{{-- 콘솔에서 열렸으면 저장 후에도 콘솔 주소로 돌아간다 --}}
	@php $ie_console = \Context::get('act') === 'dispCommerceConsole'; @endphp
	{{-- 저장 전 상품은 옵션 조작이 화면을 떠나므로 입력값을 먼저 저장하고 옵션 자리로 돌아온다 --}}
	@php $ie_is_new = !$item; @endphp
	@php $ie_form = $item ?: (Context::get('clone_item') ?: null); @endphp
	{{-- 저장 전 상품도 미리 발급한 srl 로 옵션을 담는다. 옵션 판은 등록·수정이 같은 srl 을 쓴다 --}}
	@php $ie_item_srl = $item ? (int)$item->item_srl : (int)$editor_target_srl; @endphp
	@php $ie_return = $ie_console ? getNotEncodedUrl('', 'act', 'dispCommerceConsole', 'p', 'item_edit', 'item_srl', $ie_item_srl) : getNotEncodedUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $ie_item_srl); @endphp
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
			if (!empty($ie_form->images))
			{
				$decoded = json_decode($ie_form->images, true);
				if (is_array($decoded)) { $ie_images = array_values(array_filter($decoded, 'is_string')); }
			}
			if (!count($ie_images) && !empty($ie_form->thumb)) { $ie_images = [$ie_form->thumb]; }
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
						<input type="text" name="item_name" required maxlength="250" placeholder="{{ lang('commerce.admin_item_edit_142') }}" value="{{ $ie_form->item_name ?? '' }}" />
						@include('_langfield', ['lf_name' => 'item_name', 'lf_value' => $ie_form->item_name ?? '', 'lf_key' => 'name'])
					</div>
				</div>
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.admin_item_edit_10') }}</label>
					<div class="ie-langrow">
						<input type="text" name="summary" maxlength="250" placeholder="{{ lang('commerce.admin_item_edit_143') }}" value="{{ $ie_form->summary ?? '' }}" />
						@include('_langfield', ['lf_name' => 'summary', 'lf_value' => $ie_form->summary ?? '', 'lf_key' => 'summary'])
					</div>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_11') }}</label>
					<select name="category_srl" style="width:100%">
						<option value="0">{{ lang('commerce.admin_item_edit_12') }}</option>
						@foreach ($categories as $srl => $c)
						<option value="{{ $srl }}" @if((int)($ie_form->category_srl ?? 0) === $srl) selected @endif>{{ ($c->depth ?? 0) > 0 ? str_repeat('&nbsp;&nbsp;', $c->depth) . '└ ' : '' }}{{ $c->title }}</option>
						@endforeach
					</select>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_13') }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminCategories') }}" style="color:#2677e3">{{ lang('commerce.admin_item_edit_14') }}</a>{{ lang('commerce.admin_item_edit_15') }}</span>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_16') }}</label>
					<input type="text" name="item_code" placeholder="{{ lang('commerce.admin_item_edit_144') }}" value="{{ $ie_form->item_code ?? '' }}" style="width:100%" />
					<span class="ie-help">{{ lang('commerce.admin_item_edit_17') }}</span>
				</div>
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.adm_attrs') }}</label>
					@php
					$ie_attrs = json_decode((string)($ie_form->attrs ?? ''), true);
					$ie_attrs = is_array($ie_attrs) ? $ie_attrs : [];
					@endphp
					<div id="ieAttrRows">
						@foreach ($ie_attrs as $ie_at)
						<div class="ie-attr-row">
							<input type="text" name="attr_name[]" maxlength="40" value="{{ $ie_at['name'] ?? '' }}" placeholder="{{ lang('commerce.adm_attr_name_ph') }}" />
							<input type="text" name="attr_value[]" maxlength="200" value="{{ $ie_at['value'] ?? '' }}" placeholder="{{ lang('commerce.adm_attr_value_ph') }}" />
							<button type="button" class="rsva-btn rsva-btn-sm" data-attr-del>{{ lang('commerce.admin_delete') }}</button>
						</div>
						@endforeach
					</div>
					<button type="button" class="rsva-btn rsva-btn-sm" id="ieAttrAdd">{{ lang('commerce.adm_attr_add') }}</button>
					<span class="ie-help">{{ lang('commerce.adm_attrs_help') }}</span>
				</div>
			</div>
		</div>

		{{-- 2. 가격 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_18') }}</h3>
			<div class="rsva-form-grid">
				<div>
					<label>{{ lang('commerce.admin_item_edit_19') }} <span class="ie-req">*</span></label>
					<div class="ie-suffix" data-suffix="{{ \Zittme\Modules\Commerce\Models\Money::unitLabel() }}"><input type="number" name="price" min="0" step="any" required id="iePrice" value="{{ isset($ie_form->price) ? \Zittme\Modules\Commerce\Models\Money::minorToInput((int)$ie_form->price) : '' }}" placeholder="0" /></div>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_20') }}</label>
					<div class="ie-suffix" data-suffix="{{ \Zittme\Modules\Commerce\Models\Money::unitLabel() }}"><input type="number" name="sale_price" min="0" step="any" id="ieSalePrice" value="{{ ($ie_form->sale_price ?? 0) > 0 ? \Zittme\Modules\Commerce\Models\Money::minorToInput((int)$ie_form->sale_price) : '' }}" placeholder="{{ lang('commerce.admin_item_edit_145') }}" /></div>
					<span class="ie-discount" id="ieDiscountBadge"></span>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_21') }}</span>
				</div>
				@foreach ($fx_currencies as $fx_code)
				<div>
					<label>{{ sprintf(lang('commerce.shop_fx_price_label'), $fx_code) }}</label>
					<div class="ie-suffix" data-suffix="{{ $fx_code }}"><input type="number" name="fx_price[{{ $fx_code }}]" min="0" step="any" value="{{ $fx_values[$fx_code]['price'] ?? '' }}" placeholder="{{ lang('commerce.shop_fx_price_auto') }}" /></div>
					<div class="ie-suffix" data-suffix="{{ $fx_code }}"><input type="number" name="fx_sale_price[{{ $fx_code }}]" min="0" step="any" value="{{ $fx_values[$fx_code]['sale_price'] ?? '' }}" placeholder="{{ lang('commerce.admin_item_edit_145') }}" /></div>
					<span class="ie-help">{{ lang('commerce.shop_fx_price_help') }}</span>
				</div>
				@endforeach
				<div>
					<label>{{ lang('commerce.admin_item_edit_22') }}</label>
					<div class="ie-pills">
						<label><input type="radio" name="tax_type" value="taxable" @if(($ie_form->tax_type ?? 'taxable') === 'taxable') checked @endif /> {{ lang('commerce.admin_item_edit_23') }}</label>
						<label><input type="radio" name="tax_type" value="free" @if(($ie_form->tax_type ?? '') === 'free') checked @endif /> {{ lang('commerce.admin_item_edit_24') }}</label>
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
						<label><input type="radio" name="use_stock" value="Y" @if(($ie_form->use_stock ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_item_edit_28') }}</label>
						<label><input type="radio" name="use_stock" value="N" @if(($ie_form->use_stock ?? '') === 'N') checked @endif /> {{ lang('commerce.admin_item_edit_29') }}</label>
					</div>
				</div>
				<div id="ieStockField">
					<label>{{ lang('commerce.admin_item_edit_30') }}</label>
					@if ($ie_is_new)
					<div class="ie-suffix" data-suffix="{{ lang('commerce.admin_item_edit_154') }}"><input type="number" name="init_stock" min="0" value="0" /></div>
					<span class="ie-help">{{ lang('commerce.adm_init_stock_help') }}</span>
					@else
					<div style="padding:10px 0;font-size:15px;font-weight:700">{{ number_format((int)($ie_form->stock ?? 0)) }}{{ lang('commerce.admin_item_edit_154') }}</div>
					@endif
					<span class="ie-help">{{ lang('commerce.admin_item_edit_31') }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock') }}">{{ lang('commerce.admin_item_edit_27') }}</a> {{ lang('commerce.admin_item_edit_32') }}</span>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_33') }}</label>
					<div style="display:flex;gap:8px;align-items:center">
						<div class="ie-suffix" data-suffix="{{ lang('commerce.admin_item_edit_154') }}" style="flex:1"><input type="number" name="min_qty" min="0" value="{{ $ie_form->min_qty ?? 0 }}" placeholder="{{ lang('commerce.admin_item_edit_146') }}" /></div>
						<span style="color:#8b95a1">~</span>
						<div class="ie-suffix" data-suffix="{{ lang('commerce.admin_item_edit_154') }}" style="flex:1"><input type="number" name="max_qty" min="0" value="{{ $ie_form->max_qty ?? 0 }}" placeholder="{{ lang('commerce.admin_item_edit_147') }}" /></div>
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
						<label><input type="radio" name="ship_fee_type" value="default" @if(($ie_form->ship_fee_type ?? 'default') === 'default') checked @endif /> {{ sprintf(lang('commerce.admin_item_edit_155'), shop_money_base((int)($shop_config->default_ship_fee ?? 0)) . ((int)($shop_config->free_ship_over ?? 0) > 0 ? sprintf(lang('commerce.admin_item_edit_156'), shop_money_base((int)$shop_config->free_ship_over)) : '')) }}</label>
						<label><input type="radio" name="ship_fee_type" value="free" @if(($ie_form->ship_fee_type ?? '') === 'free') checked @endif /> {{ lang('commerce.admin_item_edit_37') }}</label>
						<label><input type="radio" name="ship_fee_type" value="fixed" @if(($ie_form->ship_fee_type ?? '') === 'fixed') checked @endif /> {{ lang('commerce.admin_item_edit_38') }}</label>
					</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_39') }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminConfig') }}" style="color:#2677e3">{{ lang('commerce.admin_item_edit_40') }}</a>{{ lang('commerce.admin_item_edit_41') }}</span>
				</div>
				<div id="ieShipFeeField">
					<label>{{ lang('commerce.admin_item_edit_42') }}</label>
					<div class="ie-suffix" data-suffix="{{ \Zittme\Modules\Commerce\Models\Money::unitLabel() }}"><input type="number" name="ship_fee" min="0" step="any" value="{{ \Zittme\Modules\Commerce\Models\Money::minorToInput((int)($ie_form->ship_fee ?? 0)) }}" /></div>
				</div>
			</div>
		</div>

		{{-- 5. 상세 설명 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_43') }}</h3>
			<p class="ie-sec-desc">{{ lang('commerce.admin_item_edit_44') }}</p>
			{{-- 에디터는 폼 안의 content 필드에서 초기 내용을 읽는다 (CKEDITOR.appendTo 규약) --}}
			<textarea name="content" style="display:none">{{ $ie_form->content ?? '' }}</textarea>
			{!! $editor !!}
		</div>

		{{-- 6. 노출·판매 설정 --}}
		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_item_edit_45') }}</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.admin_item_edit_46') }}</label>
					<div class="ie-pills">
						<label><input type="radio" name="status" value="sale" @if(($ie_form->status ?? 'sale') === 'sale') checked @endif /> {{ lang('commerce.admin_item_edit_47') }}</label>
						<label><input type="radio" name="status" value="soldout" @if(($ie_form->status ?? '') === 'soldout') checked @endif /> {{ lang('commerce.admin_item_edit_48') }}</label>
						<label><input type="radio" name="status" value="hidden" @if(($ie_form->status ?? '') === 'hidden') checked @endif /> {{ lang('commerce.admin_item_edit_49') }}</label>
						<label><input type="radio" name="status" value="stop" @if(($ie_form->status ?? '') === 'stop') checked @endif /> {{ lang('commerce.admin_item_edit_50') }}</label>
					</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_51') }}</span>
				</div>
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.admin_item_edit_52') }}</label>
					@php $ie_has_period = !empty($ie_form->sale_start) || !empty($ie_form->sale_end); @endphp
					<label style="display:inline-flex;align-items:center;gap:7px;font-weight:600;font-size:13.5px;cursor:pointer"><input type="checkbox" id="iePeriodToggle" @if($ie_has_period) checked @endif style="accent-color:#2677e3;width:16px;height:16px" /> {{ lang('commerce.admin_item_edit_53') }}</label>
					<div id="iePeriodFields" class="{{ $ie_has_period ? '' : 'ie-hidden' }}" style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
						<input type="datetime-local" name="sale_start" value="{{ !empty($ie_form->sale_start) ? substr($ie_form->sale_start,0,4).'-'.substr($ie_form->sale_start,4,2).'-'.substr($ie_form->sale_start,6,2).'T'.substr($ie_form->sale_start,8,2).':'.substr($ie_form->sale_start,10,2) : '' }}" />
						<span style="color:#8b95a1">~</span>
						<input type="datetime-local" name="sale_end" value="{{ !empty($ie_form->sale_end) ? substr($ie_form->sale_end,0,4).'-'.substr($ie_form->sale_end,4,2).'-'.substr($ie_form->sale_end,6,2).'T'.substr($ie_form->sale_end,8,2).':'.substr($ie_form->sale_end,10,2) : '' }}" />
					</div>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_54') }}</label>
					<div class="ie-checks" style="padding-top:8px">
						<label><input type="checkbox" name="is_recommend" value="Y" @if(($ie_form->is_recommend ?? '') === 'Y') checked @endif /> {{ lang('commerce.admin_item_edit_55') }}</label>
						<label><input type="checkbox" name="is_new" value="Y" @if(($ie_form->is_new ?? '') === 'Y') checked @endif /> NEW</label>
						@php $ie_badge_srls = \Zittme\Modules\Commerce\Models\Badge::parseSrls($ie_form->badges ?? ''); @endphp
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
					<p class="ie-help">{{ lang('commerce.admin_item_edit_56') }} <a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminBadges') }}">{{ lang('commerce.admin_item_edit_57') }}</a>{{ lang('commerce.admin_item_edit_58') }}</p>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_59') }}</label>
					<div class="ie-checks" style="padding-top:8px">
						<label><input type="checkbox" name="is_adult" value="Y" @if(($ie_form->is_adult ?? '') === 'Y') checked @endif /> {{ lang('commerce.admin_item_edit_60') }}</label>
						<label><input type="hidden" name="grade_discount" value="N" /><input type="checkbox" name="grade_discount" value="Y" @if(($ie_form->grade_discount ?? 'Y') !== 'N') checked @endif /> {{ lang('commerce.admin_item_edit_194') }}</label>
					</div>
					<span class="ie-help">{{ lang('commerce.admin_item_edit_61') }} <a href="{{ getUrl('', 'p', '', 'module', 'admin', 'act', 'dispMemberAdminIdentityConfig') }}" target="_blank" style="color:#2677e3">{{ lang('commerce.admin_item_edit_62') }}</a>{{ lang('commerce.admin_item_edit_63') }}</span>
				</div>
				<div>
					<label>{{ lang('commerce.admin_item_edit_64') }}</label>
					<input type="number" name="list_order" value="{{ $ie_form->list_order ?? 0 }}" style="width:120px" />
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

		<input type="hidden" name="from_console" value="{{ \Context::get('act') === 'dispCommerceConsole' ? 'Y' : 'N' }}" />
		{{-- 옵션을 먼저 건드리면 이 값을 옵션 자리로 바꿔 저장 후 그 자리로 돌아온다 --}}
		<input type="hidden" name="success_return_url" id="ieReturnUrl" value="" />
		<input type="hidden" name="options_json" id="ieOptionsJson" value="" />
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
		$ie_mode = ($ie_form->option_mode ?? 'single') === 'combo' ? 'combo' : 'single';
		$ie_opt_shown = $ie_opt_basic;
		$ie_opt_keys = [];
		foreach ($ie_opt_basic as $ie_opt_row)
		{
			$ie_opt_keys[] = ['key' => \Zittme\Modules\Commerce\Models\Combo::key($ie_opt_row->combo ?? ''), 'label' => (string)$ie_opt_row->option_label];
		}
		@endphp
		{{-- 행 인라인 수정: 셀 입력은 form 속성으로 행별 폼(테이블 밖)에 연결한다 (중첩 폼 회피) --}}
		@foreach ($options as $opt)
		<form id="optEdit{{ $opt->option_srl }}" action="{{ getUrl('') }}" method="post">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminUpdateOption" />
			<input type="hidden" name="success_return_url" value="{{ $ie_return }}#ieOptions" />
			<input type="hidden" name="option_srl" value="{{ $opt->option_srl }}" />
			<input type="hidden" name="item_srl" value="{{ $ie_item_srl }}" />
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
		@php
		$ie_axes = Zittme\Modules\Commerce\Models\Combo::axes($ie_form->option_axes ?? '');
		// 축 이름·값에 연결한 다국어 코드를 편집 화면용으로 풀어 둔다 (칸에는 현재 언어 문구를 보여준다)
		$ie_axes_init = [];
		foreach ($ie_axes as $ie_ax)
		{
			$ie_name_code = Zittme\Modules\Commerce\Models\Lang::codeOf($ie_ax->name);
			$ie_vals = [];
			foreach ($ie_ax->values as $ie_raw)
			{
				$ie_color = '';
				$ie_text = $ie_raw;
				if (strpos($ie_raw, '|') !== false)
				{
					$ie_pair = array_map('trim', explode('|', $ie_raw, 2));
					$ie_text = $ie_pair[0];
					$ie_color = $ie_pair[1];
				}
				$ie_code = Zittme\Modules\Commerce\Models\Lang::codeOf($ie_text);
				$ie_vals[] = [
					'text' => $ie_code !== '' ? Zittme\Modules\Commerce\Models\Lang::display($ie_code) : $ie_text,
					'code' => $ie_code,
					'color' => $ie_color,
				];
			}
			$ie_axes_init[] = [
				'name' => $ie_name_code !== '' ? Zittme\Modules\Commerce\Models\Lang::display($ie_name_code) : $ie_ax->name,
				'name_code' => $ie_name_code,
				'values' => $ie_vals,
				'style' => $ie_ax->style,
			];
		}
		// 출력식 안에서 상수를 '|' 로 묶으면 템플릿이 필터 문법으로 읽는다. 여기서 미리 만든다
		$ie_axes_json = json_encode($ie_axes_init, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		@endphp
		<div class="ie-axes" data-mode-only="combo" style="margin-bottom:22px">
			<b style="display:block;margin-bottom:4px;font-size:14px">{{ lang('commerce.admin_item_edit_113') }} <small style="font-weight:500;color:#8b95a1">{{ lang('commerce.admin_item_edit_114') }}</small></b>
			<p class="ie-help" style="margin:0 0 10px">{{ lang('commerce.admin_item_edit_115') }} <b>{{ lang('commerce.admin_item_edit_116') }}</b>{{ lang('commerce.admin_item_edit_117') }} <b>{{ lang('commerce.admin_item_edit_118') }}</b>{{ lang('commerce.admin_item_edit_119') }} <code>{{ lang('commerce.admin_item_edit_120') }}</code> {{ lang('commerce.admin_item_edit_121') }}</p>
			<div id="ieAxes"></div>
			<div style="display:flex;gap:8px;align-items:center;margin-top:8px">
				<button type="button" class="rsva-btn rsva-btn-sm" id="ieAxisAdd">{{ lang('commerce.admin_item_edit_122') }}</button>
				<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-primary" id="ieComboBuild" data-item="{{ $ie_item_srl }}">{{ lang('commerce.admin_item_edit_116') }}</button>
				<small style="color:#8b95a1">{{ lang('commerce.admin_item_edit_123') }}</small>
			</div>
			<input type="hidden" name="option_axes" id="ieAxesJson" value="{{ $ie_form->option_axes ?? '' }}" form="ieForm" />
			<script type="application/json" id="ieAxesInit">{!! $ie_axes_json !!}</script>
		</div>

		{{-- ── 기본 옵션 (상품 변형) ── --}}
		<div style="margin-bottom:22px">
			<div class="ie-opt-warn" id="ieOptWarn" hidden>
				{{ lang('commerce.admin_item_edit_124') }} <b>{{ lang('commerce.admin_item_edit_79') }}</b>{{ lang('commerce.admin_item_edit_125') }} <b>{{ lang('commerce.admin_item_edit_116') }}</b>{{ lang('commerce.admin_item_edit_126') }}
			</div>
			<script type="application/json" id="ieOptKeys">{!! json_encode($ie_opt_keys, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
			<b style="display:block;margin-bottom:4px;font-size:14px">{{ lang('commerce.admin_item_edit_99') }} <small style="font-weight:500;color:#8b95a1">{{ lang('commerce.admin_item_edit_100') }}</small></b>
			@if (count($ie_opt_shown))
			<table class="rsva-table" style="margin-bottom:10px">
				<thead><tr><th>{{ lang('commerce.admin_item_edit_128') }}</th><th>{{ lang('commerce.admin_item_edit_129') }}</th><th>{{ lang('commerce.admin_item_edit_130') }}</th><th>SKU</th><th></th></tr></thead>
				<tbody>
					@foreach ($ie_opt_shown as $opt)
					<tr @if (empty($opt->combo)) class="ie-opt-manual" @endif>
						<td>
							@if (empty($opt->combo))
							<div class="zlf-row-wrap" style="min-width:180px">
								<input type="text" name="option_label" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->option_label }}" required style="width:100%" />
								@include('_langfield', ['lf_name' => 'option_label', 'lf_value' => $opt->option_label_raw ?? $opt->option_label, 'lf_key' => 'opt' . $opt->option_srl, 'lf_form' => 'optEdit' . $opt->option_srl])
							</div>
							@else
							{{-- 조합 옵션 이름은 축 값에서 나오는 파생값이다. 여기서 따로 고치면 축과 어긋난다 --}}
							<div style="min-width:180px">
								<input type="text" value="{{ $opt->option_label }}" style="width:100%;background:#f4f6f9;color:#4e5968" readonly />
								<input type="hidden" name="option_label" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->option_label_raw ?? $opt->option_label }}" />
							</div>
							@endif
						</td>
						<td><input type="number" name="price_add" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->price_add }}" style="width:110px" /></td>
						<td><input type="number" name="stock" form="optEdit{{ $opt->option_srl }}" min="0" value="{{ $opt->stock }}" style="width:80px" /></td>
						<td><input type="text" name="sku" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->sku }}" style="width:110px" /></td>
						<td style="white-space:nowrap">
							<button type="submit" form="optEdit{{ $opt->option_srl }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_item_edit_131') }}</button>
							<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ lang('commerce.admin_item_edit_158') }}')">
								<input type="hidden" name="module" value="admin" />
								<input type="hidden" name="act" value="procCommerceAdminDeleteOption" />
								<input type="hidden" name="success_return_url" value="{{ $ie_return }}#ieOptions" />
								<input type="hidden" name="option_srl" value="{{ $opt->option_srl }}" />
								<input type="hidden" name="item_srl" value="{{ $ie_item_srl }}" />
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
				<input type="hidden" name="success_return_url" value="{{ $ie_return }}#ieOptions" />
				<input type="hidden" name="item_srl" value="{{ $ie_item_srl }}" />
				<input type="hidden" name="option_type" value="basic" />
				<div class="rsva-inline">
					<div style="min-width:240px"><label>{{ lang('commerce.admin_item_edit_128') }}</label><div class="zlf-row-wrap"><input type="text" name="option_label" required placeholder="{{ lang('commerce.admin_item_edit_149') }}" style="width:100%" />@include('_langfield', ['lf_name' => 'option_label', 'lf_value' => '', 'lf_key' => 'optnewbasic'])</div></div>
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
						<td>
							<div class="zlf-row-wrap" style="min-width:180px">
								<input type="text" name="option_label" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->option_label }}" required style="width:100%" />
								@include('_langfield', ['lf_name' => 'option_label', 'lf_value' => $opt->option_label_raw ?? $opt->option_label, 'lf_key' => 'opt' . $opt->option_srl, 'lf_form' => 'optEdit' . $opt->option_srl])
							</div>
						</td>
						<td><input type="number" name="price_add" form="optEdit{{ $opt->option_srl }}" min="0" value="{{ $opt->price_add }}" style="width:110px" /></td>
						<td><input type="number" name="stock" form="optEdit{{ $opt->option_srl }}" min="0" value="{{ $opt->stock }}" style="width:80px" /></td>
						<td><input type="text" name="sku" form="optEdit{{ $opt->option_srl }}" value="{{ $opt->sku }}" style="width:110px" /></td>
						<td style="white-space:nowrap">
							<button type="submit" form="optEdit{{ $opt->option_srl }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_item_edit_131') }}</button>
							<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ lang('commerce.admin_item_edit_158') }}')">
								<input type="hidden" name="module" value="admin" />
								<input type="hidden" name="act" value="procCommerceAdminDeleteOption" />
								<input type="hidden" name="success_return_url" value="{{ $ie_return }}#ieOptions" />
								<input type="hidden" name="option_srl" value="{{ $opt->option_srl }}" />
								<input type="hidden" name="item_srl" value="{{ $ie_item_srl }}" />
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
				<input type="hidden" name="success_return_url" value="{{ $ie_return }}#ieOptions" />
				<input type="hidden" name="item_srl" value="{{ $ie_item_srl }}" />
				<input type="hidden" name="option_type" value="extra" />
				<div class="rsva-inline">
					<div style="min-width:240px"><label>{{ lang('commerce.admin_item_edit_128') }}</label><div class="zlf-row-wrap"><input type="text" name="option_label" required placeholder="{{ lang('commerce.admin_item_edit_150') }}" style="width:100%" />@include('_langfield', ['lf_name' => 'option_label', 'lf_value' => '', 'lf_key' => 'optnewextra'])</div></div>
					<div><label>{{ lang('commerce.admin_item_edit_137') }}</label><input type="number" name="price_add" min="0" value="0" style="width:130px" /></div>
					<div><label>{{ lang('commerce.admin_item_edit_133') }}</label><input type="number" name="stock" min="0" value="0" style="width:90px" /></div>
					<div><label>SKU</label><input type="text" name="sku" style="width:120px" /></div>
					<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_item_edit_138') }}</button></div>
				</div>
			</form>
		</div>
	</div>

	{{-- 저장 바 - 옵션까지 훑은 뒤 저장하도록 옵션 영역 아래에 둔다. 버튼은 form 속성으로 상단 폼을 제출한다 --}}
	<div class="ie-savebar is-stuck" id="ieSavebar">
		<button type="submit" form="ieForm" class="rsva-btn rsva-btn-primary">{{ $item ? lang('commerce.admin_item_edit_159') : lang('commerce.admin_item_edit_160') }}</button>
		<a href="{{ \Context::get('act') === 'dispCommerceConsole' ? getUrl('', 'act', 'dispCommerceConsole', 'p', 'items') : getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminItems') }}" class="rsva-btn">{{ lang('commerce.admin_item_edit_68') }}</a>
		<small>{{ lang('commerce.admin_item_edit_69') }} {{ lang('commerce.admin_item_edit_161') }}</small>
	</div>
	<div id="ieSaveSentinel" style="height:1px"></div>
</div>

<script>
(function () {
	// 변경사항 저장 시 옵션 행들도 한 번에 저장한다 - 행별 수정 폼의 값을 JSON 으로 모아 본 폼에 싣는다
	var ieForm = document.getElementById('ieForm');
	var jsonEl = document.getElementById('ieOptionsJson');
	if (ieForm && jsonEl) {
		ieForm.addEventListener('submit', function () {
			var rows = [];
			document.querySelectorAll('form').forEach(function (f) {
				// 행 입력칸은 표 안에 있고 form 속성으로만 이어져 있다.
				// 폼 안쪽만 뒤지면 잡히지 않으므로 elements 로 읽는다
				var pick = function (n) {
					// 같은 이름이 여럿이면(다국어 칸 등) 값이 있는 첫 칸을 쓴다
					var found = '';
					Array.prototype.forEach.call(f.elements || [], function (el) {
						if (el.name === n && found === '' && typeof el.value === 'string') { found = el.value; }
					});
					return found;
				};
				var srl = pick('option_srl');
				if (pick('act') !== 'procCommerceAdminUpdateOption' || !srl) return;
				rows.push({
					option_srl: srl,
					option_label: pick('option_label'),
					option_type: pick('option_type'),
					price_add: pick('price_add'),
					stock: pick('stock'),
					sku: pick('sku')
				});
			});
			jsonEl.value = JSON.stringify(rows);
		});
	}
})();
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

		// 위젯 스크립트가 아직 안 올라왔으면 문서 준비 후에 잇는다
		var lfPending = [];
		function bindLang(button) {
			if (window.zlfBind) { window.zlfBind(button); return; }
			lfPending.push(button);
		}
		document.addEventListener('DOMContentLoaded', function () {
			if (!window.zlfBind) return;
			lfPending.forEach(window.zlfBind);
			lfPending = [];
		});

		// 다국어 버튼 한 벌. 패널은 _langfield_assets 의 것을 함께 쓴다
		function langBtn() {
			return '<input type="hidden" data-lf-code value="" />' +
				'<button type="button" class="zlf-btn" data-lf-open data-lf-display="" title="' + {!! json_encode(lang('commerce.admin_item_edit_162')) !!} + '">' +
				'<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.3 2.6 2.3 14.4 0 17"/><path d="M12 3.5c-2.3 2.6-2.3 14.4 0 17"/></svg>' +
				'</button>';
		}

		// 값 하나를 칩으로 그린다. 칩마다 문구를 따로 연결할 수 있다
		function valueChip(text, code, color) {
			var chip = document.createElement('span');
			chip.className = 'zlf-row-wrap ie-vchip';
			chip.innerHTML = '<input type="text" data-v="text" size="10" />' + langBtn();
			chip.setAttribute('data-color', color || '');
			var input = chip.querySelector('[data-v=text]');
			var hidden = chip.querySelector('[data-lf-code]');
			var button = chip.querySelector('[data-lf-open]');
			input.value = text || '';
			hidden.value = code || '';
			if (code) { button.setAttribute('data-lf-display', text || ''); }
			input.addEventListener('input', sync);
			hidden.addEventListener('change', sync);
			bindLang(button);
			return chip;
		}

		// 쉼표 입력을 칩 목록으로 옮겨 담는다. 이미 연결한 문구는 글자가 같으면 그대로 따라온다
		function rebuildChips(row) {
			var box = row.querySelector('[data-a=chips]');
			var kept = {};
			box.querySelectorAll('.ie-vchip').forEach(function (chip) {
				var t = chip.querySelector('[data-v=text]').value.trim();
				if (t) kept[t] = { code: chip.querySelector('[data-lf-code]').value, color: chip.getAttribute('data-color') };
			});
			var texts = row.querySelector('[data-a=values]').value.split(',').map(function (v) { return v.trim(); }).filter(Boolean);
			box.innerHTML = '';
			texts.forEach(function (t) {
				// 색상칩 축은 '블루미스트|#7ec8e3' 처럼 색을 함께 적는다
				var color = '';
				var cut = t.indexOf('|');
				if (cut > -1) { color = t.slice(cut + 1).trim(); t = t.slice(0, cut).trim(); }
				if (!t) return;
				var had = kept[t] || {};
				box.appendChild(valueChip(t, had.code || '', color || had.color || ''));
			});
			box.hidden = false;
			if (!texts.length) { box.innerHTML = '<small class="ie-vhint">' + {!! json_encode(lang('commerce.admin_item_edit_163')) !!} + '</small>'; }
		}

		function axisRow(data) {
			data = data || {};
			var values = data.values || [];
			var row = document.createElement('div');
			row.className = 'ie-axis';
			row.innerHTML =
				'<span class="zlf-row-wrap"><input type="text" data-a="name" placeholder="' + {!! json_encode(lang('commerce.admin_item_edit_164')) !!} + '" />' + langBtn() + '</span>' +
				'<input type="text" data-a="values" placeholder="' + {!! json_encode(lang('commerce.admin_item_edit_165')) !!} + '" />' +
				'<select data-a="style" title="' + {!! json_encode(lang('commerce.admin_item_edit_166')) !!} + '">' +
					'<option value="select">' + {!! json_encode(lang('commerce.admin_item_edit_167')) !!} + '</option>' +
					'<option value="button">' + {!! json_encode(lang('commerce.admin_item_edit_168')) !!} + '</option>' +
					'<option value="color">' + {!! json_encode(lang('commerce.admin_item_edit_169')) !!} + '</option>' +
				'</select>' +
				'<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger" data-adel>' + {!! json_encode(lang('commerce.admin_item_edit_170')) !!} + '</button>' +
				'<div class="ie-axis-vals" data-a="chips"></div>';
			row.querySelector('[data-a=name]').value = data.name || '';
			row.querySelector('[data-a=values]').value = values.map(function (v) { return v.text; }).join(', ');
			row.querySelector('[data-a=style]').value = data.style || 'select';
			var nameHidden = row.querySelector('[data-lf-code]');
			var nameButton = row.querySelector('[data-lf-open]');
			nameHidden.value = data.name_code || '';
			if (data.name_code) { nameButton.setAttribute('data-lf-display', data.name || ''); }
			bindLang(nameButton);
			nameHidden.addEventListener('change', sync);
			row.querySelector('[data-a=style]').addEventListener('change', sync);
			row.querySelector('[data-adel]').addEventListener('click', function () { row.remove(); sync(); });
			row.querySelector('[data-a=name]').addEventListener('input', sync);
			row.querySelector('[data-a=values]').addEventListener('input', function () { rebuildChips(row); sync(); });
			axesEl.appendChild(row);
			var box = row.querySelector('[data-a=chips]');
			values.forEach(function (v) { box.appendChild(valueChip(v.text, v.code, v.color)); });
			box.hidden = false;
			if (!values.length) { box.innerHTML = '<small class="ie-vhint">' + {!! json_encode(lang('commerce.admin_item_edit_163')) !!} + '</small>'; }
		}

		// 문구를 연결했으면 코어 규약값을, 아니면 입력한 글자를 돌려준다
		function token(text, code, color) {
			var value = code ? '$user_lang->' + code : text;
			return color ? value + '|' + color : value;
		}

		// 화면의 축을 hidden JSON 으로 옮겨 담는다 (상품 저장 때 함께 실려 간다)
		function sync() {
			var out = [];
			axesEl.querySelectorAll('.ie-axis').forEach(function (row) {
				var nameText = row.querySelector('[data-a=name]').value.trim();
				var name = token(nameText, row.querySelector('[data-a=name]').parentNode.querySelector('[data-lf-code]').value, '');
				var values = [];
				row.querySelectorAll('.ie-vchip').forEach(function (chip) {
					var text = chip.querySelector('[data-v=text]').value.trim();
					if (!text) return;
					values.push(token(text, chip.querySelector('[data-lf-code]').value, chip.getAttribute('data-color')));
				});
				var style = row.querySelector('[data-a=style]').value;
				if (nameText && values.length) out.push({ name: name, values: values, style: style });
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
			if (axesEl.querySelectorAll('.ie-axis').length >= MAX_AXES) { alert({!! json_encode(lang('commerce.admin_item_edit_171')) !!}.replace('%d', MAX_AXES)); return; }
			axisRow();
		});

		var buildBtn = document.getElementById('ieComboBuild');
		buildBtn.addEventListener('click', function () {
			var itemSrl = parseInt(buildBtn.getAttribute('data-item'), 10) || 0;
			if (!itemSrl) { alert({!! json_encode(lang('commerce.admin_item_edit_172')) !!}); return; }
			var axes = sync();
			if (!axes.length) { alert({!! json_encode(lang('commerce.admin_item_edit_173')) !!}); return; }
			var total = axes.reduce(function (n, a) { return n * a.values.length; }, 1);
			if (total > 100) { alert({!! json_encode(lang('commerce.admin_item_edit_174')) !!}.replace('%d', total)); return; }
			var keep = {};
			var walk = function (i, prefix) {
				if (i >= axes.length) { keep[prefix.join('|')] = true; return; }
				axes[i].values.forEach(function (v) { walk(i + 1, prefix.concat([axes[i].name + '=' + v])); });
			};
			walk(0, []);
			var current = [];
			try { current = JSON.parse(document.getElementById('ieOptKeys').textContent) || []; } catch (e) {}
			var gone = current.filter(function (o) { return !keep[o.key]; }).map(function (o) { return o.label; });
			var ask = {!! json_encode(lang('commerce.admin_item_edit_175')) !!}.replace('%d', total);
			if (gone.length) {
				ask += '\n\n' + {!! json_encode(lang('commerce.admin_item_edit_127')) !!}.replace('%d', gone.length) + '\n' + gone.join(', ');
			}
			if (!confirm(ask)) return;

			buildBtn.disabled = true;
			exec_json('commerce.procCommerceAdminBuildCombos', { item_srl: itemSrl, option_axes: axesJson.value }, function (ret) {
				alert((ret && ret.message) || {!! json_encode(lang('commerce.admin_item_edit_176')) !!});
				location.reload();
			}, function (ret) {
				buildBtn.disabled = false;
				alert((ret && ret.message) || {!! json_encode(lang('commerce.admin_item_edit_177')) !!});
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
				badge.textContent = {!! json_encode(lang('commerce.admin_item_edit_178')) !!};
				d.appendChild(badge);
			}
			var acts = document.createElement('div');
			acts.className = 'ie-img-acts';
			if (i !== 0) {
				var mainBtn = document.createElement('button');
				mainBtn.type = 'button';
				mainBtn.textContent = {!! json_encode(lang('commerce.admin_item_edit_179')) !!};
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
			delBtn.textContent = {!! json_encode(lang('commerce.admin_item_edit_170')) !!};
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
			pb.textContent = {!! json_encode(lang('commerce.admin_item_edit_180')) !!};
			pd.appendChild(pb);
			imgsWrap.appendChild(pd);
		}

		if (imgs.length + pendingCount < MAX_IMGS) {
			var add = document.createElement('div');
			add.className = 'ie-img-add';
			add.innerHTML = '<b>+</b><span>' + {!! json_encode(lang('commerce.admin_item_edit_181')) !!} + '</span>';
			add.addEventListener('click', function () { imgFile.click(); });
			imgsWrap.appendChild(add);
		}
	}

	// 사진을 고르면 곧바로 올린다. 저장을 누르지 않아도 미리보기가 바로 보인다
	imgFile.addEventListener('change', function () {
		var remain = MAX_IMGS - imgs.length;
		if (!imgFile.files.length) return;
		if (imgFile.files.length > remain) {
			alert({!! json_encode(lang('commerce.admin_item_edit_182')) !!}.replace('%d', MAX_IMGS).replace('%d', remain));
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
				alert((res && res.message) || {!! json_encode(lang('commerce.admin_item_edit_183')) !!});
			}
			imgFile.value = '';
			syncImgs();
		}).fail(function () {
			pendingCount = 0;
			imgFile.value = '';
			syncImgs();
			alert({!! json_encode(lang('commerce.admin_item_edit_184')) !!});
		});
	});
	syncImgs();

	// 할인율 실시간 표시
	var shpUnit = '{{ \Zittme\Modules\Commerce\Models\Money::unitLabel() }}';
	var price = document.getElementById('iePrice');
	var sale = document.getElementById('ieSalePrice');
	var badge = document.getElementById('ieDiscountBadge');
	function updateDiscount() {
		var p = parseInt(price.value, 10) || 0;
		var s = parseInt(sale.value, 10) || 0;
		if (p > 0 && s > 0 && s < p) {
			badge.style.display = 'block';
			badge.textContent = {!! json_encode(lang('commerce.admin_item_edit_185')) !!}.replace('%d', Math.round((1 - s / p) * 100)).replace('%s', s.toLocaleString() + shpUnit);
		} else if (s > 0 && p > 0 && s >= p) {
			badge.style.display = 'block';
			badge.textContent = {!! json_encode(lang('commerce.admin_item_edit_186')) !!};
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


@if ($ie_is_new)
<script>
(function () {
	// 저장 전 상품이다. 옵션 조작은 서버로 가면서 화면을 떠나므로,
	// 먼저 지금까지 입력한 상품을 저장하고 돌아온 뒤 이어서 하게 한다
	var ieForm = document.getElementById('ieForm');
	var returnUrl = document.getElementById('ieReturnUrl');
	// 옵션 판만 가로챈다. 바깥 상자를 잡으면 상품 폼의 저장까지 막힌다
	var anchor = document.getElementById('ieOptions');
	var panel = anchor ? anchor.nextElementSibling : null;
	if (!ieForm || !returnUrl || !panel) return;

	var ASK = {!! json_encode(lang('commerce.admin_item_edit_193')) !!};
	var optionReturn = {!! json_encode($ie_return . '#ieOptions') !!};

	function saveFirst() {
		if (!ieForm.reportValidity || ieForm.reportValidity()) {
			if (!confirm(ASK)) { return; }
			returnUrl.value = optionReturn;
			if (typeof ieForm.requestSubmit === 'function') { ieForm.requestSubmit(); }
			else { ieForm.submit(); }
		}
	}

	panel.addEventListener('click', function (e) {
		var btn = e.target.closest('button');
		if (!btn || btn.type === 'button' && btn.id !== 'ieComboBuild' && !btn.closest('form')) { return; }
		if (btn.id === 'ieAxisAdd' || btn.classList.contains('ie-axis-del') || btn.hasAttribute('data-axis-add')) { return; }
		if (btn.id !== 'ieComboBuild' && btn.type !== 'submit') { return; }
		e.preventDefault();
		e.stopPropagation();
		saveFirst();
	}, true);

	panel.addEventListener('submit', function (e) {
		if (e.target === ieForm) { return; }
		e.preventDefault();
		saveFirst();
	}, true);
})();
</script>
@endif

<style>
.ie-attr-row { display: flex; gap: 8px; margin-bottom: 8px; }
.ie-attr-row input[type="text"]:first-child { flex: 0 0 160px; }
.ie-attr-row input[type="text"] { flex: 1; min-width: 0; }
</style>
<script>
(function () {
	var wrap = document.getElementById('ieAttrRows');
	var add = document.getElementById('ieAttrAdd');
	if (!wrap || !add) return;
	add.addEventListener('click', function () {
		var row = document.createElement('div');
		row.className = 'ie-attr-row';
		row.innerHTML = '<input type="text" name="attr_name[]" maxlength="40" placeholder="{{ lang('commerce.adm_attr_name_ph') }}" />'
			+ '<input type="text" name="attr_value[]" maxlength="200" placeholder="{{ lang('commerce.adm_attr_value_ph') }}" />'
			+ '<button type="button" class="rsva-btn rsva-btn-sm" data-attr-del>{{ lang('commerce.admin_delete') }}</button>';
		wrap.appendChild(row);
	});
	wrap.addEventListener('click', function (e) {
		var btn = e.target.closest('[data-attr-del]');
		if (btn) { btn.parentNode.remove(); }
	});
})();
</script>
