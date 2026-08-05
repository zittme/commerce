@include('_tabs')

<style>
/* 상품 등록 — 친절한 섹션형 폼 */
.ie-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-bottom: 18px; flex-wrap: wrap; }
.ie-head h2 { margin: 0; font-size: 20px; font-weight: 800; letter-spacing: -0.02em; }
.ie-head p { margin: 4px 0 0; font-size: 13px; color: #6b7684; }
.ie-sec-desc { margin: -10px 0 16px; font-size: 13px; color: #6b7684; }
.ie-help { display: block; margin-top: 6px; font-size: 12.5px; color: #8b95a1; font-weight: 400; }
.ie-req { color: #e5484d; font-weight: 700; }
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
.ie-opt-empty { padding: 18px; border: 1px dashed #cfd6e0; border-radius: 12px; background: #fafbfc; font-size: 13px; color: #6b7684; }
/* 폼 가독성: 섹션·헤더 폭 제한 + 균형 잡힌 2열 */
.rsva .rsva-panel, .rsva .ie-head { max-width: 960px; }
.rsva .rsva-form-grid { grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); align-items: start; }
</style>

<div class="rsva">
	<div class="ie-head">
		<div>
			<h2>{{ $item ? '상품 편집' : (Context::get('clone_from') ? '상품 복제' : '새 상품 등록') }}</h2>
			<p>필수 항목(<span class="ie-req">*</span>)만 입력해도 등록할 수 있어요. 나머지는 나중에 언제든 수정할 수 있습니다.</p>
		</div>
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItems') }}" class="rsva-btn">상품 목록</a>
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
			<h3>기본 정보</h3>
			<p class="ie-sec-desc">구매자에게 가장 먼저 보이는 정보입니다.</p>

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
				<label>상품 이미지 <span style="font-weight:500;color:#8b95a1">(최대 7장 — 첫 번째가 대표 썸네일)</span></label>
				<div class="ie-imgs" id="ieImgs"></div>
				<input type="file" name="image_files[]" accept="image/*" multiple id="ieImgFile" class="ie-hidden" />
				<input type="hidden" name="images_json" id="ieImagesJson" value="{{ json_encode($ie_images, JSON_UNESCAPED_SLASHES) }}" />
				<span class="ie-help">다른 사진의 「대표로」 버튼을 누르면 대표 썸네일을 바꿀 수 있어요. 정사각형(1:1) 권장, 장당 10MB 이하 JPG·PNG·WebP. 새로 올린 사진은 저장할 때 목록 끝에 추가됩니다.</span>
			</div>

			<div class="rsva-form-grid">
				<div style="grid-column:1/-1">
					<label>상품명 <span class="ie-req">*</span></label>
					<input type="text" name="item_name" required maxlength="250" placeholder="예: 서킷 주행 기념 티셔츠 (화이트)" value="{{ $item->item_name ?? '' }}" style="width:100%" />
				</div>
				<div style="grid-column:1/-1">
					<label>한 줄 요약</label>
					<input type="text" name="summary" maxlength="250" placeholder="상품명 아래에 작게 표시되는 소개 문구예요 (선택)" value="{{ $item->summary ?? '' }}" style="width:100%" />
				</div>
				<div>
					<label>카테고리</label>
					<select name="category_srl" style="width:100%">
						<option value="0">미분류</option>
						@foreach ($categories as $srl => $c)
						<option value="{{ $srl }}" @if((int)($item->category_srl ?? 0) === $srl) selected @endif>{{ $c->title }}</option>
						@endforeach
					</select>
					<span class="ie-help">카테고리는 <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCategories') }}" style="color:#2677e3">카테고리 관리</a>에서 만들 수 있어요.</span>
				</div>
				<div>
					<label>상품코드 (SKU)</label>
					<input type="text" name="item_code" placeholder="자체 관리용 코드 (선택)" value="{{ $item->item_code ?? '' }}" style="width:100%" />
					<span class="ie-help">재고·정산 관리를 위한 내부 코드입니다. 구매자에게 보이지 않아요.</span>
				</div>
			</div>
		</div>

		{{-- 2. 가격 --}}
		<div class="rsva-panel">
			<h3>가격</h3>
			<div class="rsva-form-grid">
				<div>
					<label>정가 <span class="ie-req">*</span></label>
					<div class="ie-suffix" data-suffix="원"><input type="number" name="price" min="0" required id="iePrice" value="{{ $item->price ?? '' }}" placeholder="0" /></div>
				</div>
				<div>
					<label>할인 판매가</label>
					<div class="ie-suffix" data-suffix="원"><input type="number" name="sale_price" min="0" id="ieSalePrice" value="{{ ($item->sale_price ?? 0) > 0 ? $item->sale_price : '' }}" placeholder="할인하지 않으면 비워두세요" /></div>
					<span class="ie-discount" id="ieDiscountBadge"></span>
					<span class="ie-help">입력하면 정가에 취소선이 그어지고 이 가격으로 판매됩니다.</span>
				</div>
				<div>
					<label>세금 구분</label>
					<div class="ie-pills">
						<label><input type="radio" name="tax_type" value="taxable" @if(($item->tax_type ?? 'taxable') === 'taxable') checked @endif /> 과세</label>
						<label><input type="radio" name="tax_type" value="free" @if(($item->tax_type ?? '') === 'free') checked @endif /> 면세</label>
					</div>
					<span class="ie-help">농·수산물, 도서 등 부가세 면세 상품만 면세를 선택하세요.</span>
				</div>
			</div>
		</div>

		{{-- 3. 재고·구매수량 --}}
		<div class="rsva-panel">
			<h3>재고·구매 수량</h3>
			<div class="rsva-form-grid">
				<div>
					<label>재고 관리</label>
					<div class="ie-pills">
						<label><input type="radio" name="use_stock" value="Y" @if(($item->use_stock ?? 'Y') === 'Y') checked @endif /> 재고 수량만큼 판매</label>
						<label><input type="radio" name="use_stock" value="N" @if(($item->use_stock ?? '') === 'N') checked @endif /> 무제한 판매</label>
					</div>
				</div>
				<div id="ieStockField">
					<label>재고 수량</label>
					<div class="ie-suffix" data-suffix="개"><input type="number" name="stock" min="0" value="{{ $item->stock ?? 0 }}" /></div>
					<span class="ie-help">옵션을 추가하면 재고는 옵션 단위로만 관리됩니다.</span>
				</div>
				<div>
					<label>1회 구매 수량 제한</label>
					<div style="display:flex;gap:8px;align-items:center">
						<div class="ie-suffix" data-suffix="개" style="flex:1"><input type="number" name="min_qty" min="0" value="{{ $item->min_qty ?? 0 }}" placeholder="최소" /></div>
						<span style="color:#8b95a1">~</span>
						<div class="ie-suffix" data-suffix="개" style="flex:1"><input type="number" name="max_qty" min="0" value="{{ $item->max_qty ?? 0 }}" placeholder="최대" /></div>
					</div>
					<span class="ie-help">0이면 제한하지 않습니다.</span>
				</div>
			</div>
		</div>

		{{-- 4. 배송 --}}
		<div class="rsva-panel">
			<h3>배송</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1">
					<label>배송비</label>
					<div class="ie-pills">
						<label><input type="radio" name="ship_fee_type" value="default" @if(($item->ship_fee_type ?? 'default') === 'default') checked @endif /> 기본 정책 ({{ number_format((int)($shop_config->default_ship_fee ?? 0)) }}원@if ((int)($shop_config->free_ship_over ?? 0) > 0), {{ number_format((int)$shop_config->free_ship_over) }}원 이상 무료@endif)</label>
						<label><input type="radio" name="ship_fee_type" value="free" @if(($item->ship_fee_type ?? '') === 'free') checked @endif /> 무료배송</label>
						<label><input type="radio" name="ship_fee_type" value="fixed" @if(($item->ship_fee_type ?? '') === 'fixed') checked @endif /> 이 상품만 개별 배송비</label>
					</div>
					<span class="ie-help">기본 정책은 <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminConfig') }}" style="color:#2677e3">설정</a>에서 바꿀 수 있어요.</span>
				</div>
				<div id="ieShipFeeField">
					<label>개별 배송비</label>
					<div class="ie-suffix" data-suffix="원"><input type="number" name="ship_fee" min="0" value="{{ $item->ship_fee ?? 0 }}" /></div>
				</div>
			</div>
		</div>

		{{-- 5. 상세 설명 --}}
		<div class="rsva-panel">
			<h3>상세 설명</h3>
			<p class="ie-sec-desc">이미지를 끌어다 놓거나 붙여넣을 수 있어요. 상품 페이지 본문에 그대로 표시됩니다.</p>
			{!! $editor !!}
		</div>

		{{-- 6. 노출·판매 설정 --}}
		<div class="rsva-panel">
			<h3>노출·판매 설정</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1">
					<label>판매 상태</label>
					<div class="ie-pills">
						<label><input type="radio" name="status" value="sale" @if(($item->status ?? 'sale') === 'sale') checked @endif /> 판매중</label>
						<label><input type="radio" name="status" value="soldout" @if(($item->status ?? '') === 'soldout') checked @endif /> 품절</label>
						<label><input type="radio" name="status" value="hidden" @if(($item->status ?? '') === 'hidden') checked @endif /> 숨김</label>
						<label><input type="radio" name="status" value="stop" @if(($item->status ?? '') === 'stop') checked @endif /> 판매중지</label>
					</div>
					<span class="ie-help">숨김·판매중지 상품은 상점에 노출되지 않아요. 품절은 노출되지만 구매할 수 없습니다.</span>
				</div>
				<div style="grid-column:1/-1">
					<label>판매 기간</label>
					@php $ie_has_period = !empty($item->sale_start) || !empty($item->sale_end); @endphp
					<label style="display:inline-flex;align-items:center;gap:7px;font-weight:600;font-size:13.5px;cursor:pointer"><input type="checkbox" id="iePeriodToggle" @if($ie_has_period) checked @endif style="accent-color:#2677e3;width:16px;height:16px" /> 특정 기간에만 판매하기</label>
					<div id="iePeriodFields" class="{{ $ie_has_period ? '' : 'ie-hidden' }}" style="display:flex;gap:8px;align-items:center;margin-top:10px;flex-wrap:wrap">
						<input type="datetime-local" name="sale_start" value="{{ !empty($item->sale_start) ? substr($item->sale_start,0,4).'-'.substr($item->sale_start,4,2).'-'.substr($item->sale_start,6,2).'T'.substr($item->sale_start,8,2).':'.substr($item->sale_start,10,2) : '' }}" />
						<span style="color:#8b95a1">~</span>
						<input type="datetime-local" name="sale_end" value="{{ !empty($item->sale_end) ? substr($item->sale_end,0,4).'-'.substr($item->sale_end,4,2).'-'.substr($item->sale_end,6,2).'T'.substr($item->sale_end,8,2).':'.substr($item->sale_end,10,2) : '' }}" />
					</div>
				</div>
				<div>
					<label>뱃지</label>
					<div class="ie-checks" style="padding-top:8px">
						<label><input type="checkbox" name="is_recommend" value="Y" @if(($item->is_recommend ?? '') === 'Y') checked @endif /> 추천</label>
						<label><input type="checkbox" name="is_new" value="Y" @if(($item->is_new ?? '') === 'Y') checked @endif /> NEW</label>
					</div>
				</div>
				<div>
					<label>성인 상품</label>
					<div class="ie-checks" style="padding-top:8px">
						<label><input type="checkbox" name="is_adult" value="Y" @if(($item->is_adult ?? '') === 'Y') checked @endif /> 성인 인증(본인인증) 회원만 구매 가능</label>
					</div>
					<span class="ie-help">이 기능을 쓰려면 <a href="{{ getUrl('', 'p', '', 'module', 'admin', 'act', 'dispMemberAdminIdentityConfig') }}" target="_blank" style="color:#2677e3">회원 설정 › 본인인증 설정</a>이 켜져 있어야 해요. 본인인증이 꺼져 있으면 이 상품은 아무도 구매할 수 없습니다.</span>
				</div>
				<div>
					<label>진열 순서</label>
					<input type="number" name="list_order" value="{{ $item->list_order ?? 0 }}" style="width:120px" />
					<span class="ie-help">숫자가 낮을수록 목록 앞에 나옵니다.</span>
				</div>
			</div>
		</div>

		<div class="ie-savebar is-stuck" id="ieSavebar">
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ $item ? '변경사항 저장' : '상품 등록' }}</button>
			<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItems') }}" class="rsva-btn">취소</a>
			<small>저장 후에도 모든 항목을 다시 수정할 수 있어요.</small>
		</div>
		<div id="ieSaveSentinel" style="height:1px"></div>
	</form>

	{{-- 옵션 --}}
	<div class="rsva-panel">
		<h3>옵션</h3>
		<p class="ie-sec-desc">색상·사이즈처럼 선택지가 있는 상품이라면 옵션을 추가하세요. 옵션을 추가하면 재고는 옵션 단위로만 관리됩니다.</p>
		@if (!$item)
		<div class="ie-opt-empty">상품을 먼저 등록하면 여기서 옵션을 추가할 수 있어요.</div>
		@else
		@if (!empty($options))
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>옵션</th><th>추가금</th><th>재고</th><th>SKU</th><th></th></tr></thead>
			<tbody>
				@foreach ($options as $opt)
				<tr>
					<td>{{ $opt->option_label }}</td>
					<td>{{ $opt->price_add != 0 ? ($opt->price_add > 0 ? '+' : '') . number_format($opt->price_add) . '원' : '-' }}</td>
					<td>{{ number_format($opt->stock) }}개</td>
					<td>{{ $opt->sku ?: '-' }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('옵션을 삭제하시겠습니까?')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procCommerceAdminDeleteOption" />
							<input type="hidden" name="option_srl" value="{{ $opt->option_srl }}" />
							<input type="hidden" name="item_srl" value="{{ $item->item_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
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
			<input type="hidden" name="item_srl" value="{{ $item->item_srl }}" />
			<div class="rsva-inline">
				<div style="min-width:240px"><label>옵션명</label><input type="text" name="option_label" required placeholder="예: 색상: 블랙 / 사이즈: L" style="width:100%" /></div>
				<div><label>추가금 (원)</label><input type="number" name="price_add" value="0" style="width:110px" /></div>
				<div><label>재고 (개)</label><input type="number" name="stock" min="0" value="0" style="width:90px" /></div>
				<div><label>SKU</label><input type="text" name="sku" style="width:120px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">옵션 추가</button></div>
			</div>
		</form>
		@endif
	</div>
</div>

<script>
(function () {
	// ── 이미지 갤러리 (최대 7장, 첫 장 = 대표 썸네일) ──
	var MAX_IMGS = 7;
	var imgsWrap = document.getElementById('ieImgs');
	var imgsJson = document.getElementById('ieImagesJson');
	var imgFile = document.getElementById('ieImgFile');
	var imgs = [];
	try { imgs = JSON.parse(imgsJson.value) || []; } catch (e) { imgs = []; }
	var pendingCount = 0;

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
			pb.textContent = '저장 시 추가';
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

	imgFile.addEventListener('change', function () {
		var remain = MAX_IMGS - imgs.length;
		if (imgFile.files.length > remain) {
			alert('이미지는 최대 ' + MAX_IMGS + '장까지 등록할 수 있어요. (' + remain + '장 더 가능)');
		}
		pendingCount = Math.min(imgFile.files.length, remain);
		syncImgs();
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
})();
</script>
