@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispCommerceAdminItems" />
		<select name="f_status">
			<option value="">전체 상태</option>
			<option value="sale" @if($filters->status === 'sale') selected @endif>판매중</option>
			<option value="soldout" @if($filters->status === 'soldout') selected @endif>품절</option>
			<option value="hidden" @if($filters->status === 'hidden') selected @endif>숨김</option>
			<option value="stop" @if($filters->status === 'stop') selected @endif>판매중지</option>
		</select>
		<select name="f_category">
			<option value="">전체 카테고리</option>
			@foreach ($categories as $srl => $c)
			<option value="{{ $srl }}" @if($filters->category === $srl) selected @endif>{{ $c->title }}</option>
			@endforeach
		</select>
		<input type="text" name="f_keyword" placeholder="상품명" value="{{ $filters->keyword }}" />
		<button type="submit" class="rsva-btn">검색</button>
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit') }}" class="rsva-btn rsva-btn-primary">새 상품</a>
	</form>

	@if (empty($items))
	<p class="rsva-empty">등록된 상품이 없습니다.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th style="width:56px"></th><th>상품명</th><th>가격</th><th>재고</th><th>옵션</th><th>상태</th><th>관리</th></tr></thead>
		<tbody>
			@foreach ($items as $it)
			@php $it_thumb_style = !empty($it->thumb) ? "background-image:url('" . $it->thumb . "');" : ''; @endphp
			<tr>
				<td><span style="display:block;width:44px;height:44px;border-radius:8px;background:#f7f8fa center/cover no-repeat;{{ $it_thumb_style }}"></span></td>
				<td>
					<strong>{{ $it->item_name }}</strong>
					@if ($it->is_recommend === 'Y')<span class="rsva-st rsva-st-confirmed">추천</span>@endif
					@if ($it->is_new === 'Y')<span class="rsva-st">NEW</span>@endif
					@if ($it->is_adult === 'Y')<span class="rsva-st rsva-st-cancelled">성인</span>@endif
					@if ($it->tax_type === 'free')<span class="rsva-st">면세</span>@endif
				</td>
				<td>
					@if ($it->sale_price > 0 && $it->sale_price < $it->price)
					<s style="color:#9aa1ab">{{ number_format($it->price) }}</s> <strong>{{ number_format($it->sale_price) }}</strong>
					@else
					{{ number_format($it->price) }}
					@endif
				</td>
				<td>{{ $it->use_stock === 'Y' ? number_format($it->stock) : '무제한' }}</td>
				<td>{{ $it->has_options === 'Y' ? 'Y' : '-' }}</td>
				<td><span class="rsva-st {{ $it->status === 'sale' ? 'rsva-st-confirmed' : ($it->status === 'soldout' ? 'rsva-st-hold' : '') }}">{{ ['sale'=>'판매중','soldout'=>'품절','hidden'=>'숨김','stop'=>'중지'][$it->status] ?? $it->status }}</span></td>
				<td>
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $it->item_srl) }}" class="rsva-btn rsva-btn-sm">편집</a>
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'clone_from', $it->item_srl) }}" class="rsva-btn rsva-btn-sm">복제</a>
					<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까? 주문 이력이 있으면 숨김으로 전환됩니다.')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminDeleteItem" />
						<input type="hidden" name="item_srl" value="{{ $it->item_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
