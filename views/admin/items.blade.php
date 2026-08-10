@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="get" class="rsva-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispCommerceAdminItems" />
		<select name="f_status">
			<option value="">{{ lang('commerce.admin_items_1') }}</option>
			<option value="sale" @if($filters->status === 'sale') selected @endif>{{ lang('commerce.admin_items_2') }}</option>
			<option value="soldout" @if($filters->status === 'soldout') selected @endif>{{ lang('commerce.admin_items_3') }}</option>
			<option value="hidden" @if($filters->status === 'hidden') selected @endif>{{ lang('commerce.admin_items_4') }}</option>
			<option value="stop" @if($filters->status === 'stop') selected @endif>{{ lang('commerce.admin_items_5') }}</option>
		</select>
		<select name="f_category">
			<option value="">{{ lang('commerce.admin_items_6') }}</option>
			@foreach ($categories as $srl => $c)
			<option value="{{ $srl }}" @if($filters->category === $srl) selected @endif>{{ $c->title }}</option>
			@endforeach
		</select>
		<input type="text" name="f_keyword" placeholder="{{ lang('commerce.admin_items_11') }}" value="{{ $filters->keyword }}" />
		<button type="submit" class="rsva-btn">{{ lang('commerce.admin_items_7') }}</button>
		<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit') }}" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_items_8') }}</a>
	</form>

	@if (empty($items))
	<p class="rsva-empty">{{ lang('commerce.admin_items_9') }}</p>
	@else
	<p class="zmc-sorthint">{{ lang('commerce.admin_items_10') }}</p>
	<table class="rsva-table" id="zmcItemTable">
		<thead><tr><th style="width:34px"></th><th style="width:56px"></th><th>{{ lang('commerce.admin_items_11') }}</th><th>{{ lang('commerce.admin_items_12') }}</th><th>{{ lang('commerce.admin_items_13') }}</th><th>{{ lang('commerce.admin_items_14') }}</th><th>{{ lang('commerce.admin_items_15') }}</th><th>{{ lang('commerce.admin_items_16') }}</th></tr></thead>
		<tbody id="zmcItemRows">
			@foreach ($items as $it)
			@php $it_thumb_style = !empty($it->thumb) ? "background-image:url('" . $it->thumb . "');" : ''; @endphp
			<tr draggable="true" data-item-srl="{{ $it->item_srl }}">
				<td class="zmc-handle" aria-label="순서 이동 손잡이">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="5" r="1.6"/><circle cx="15" cy="5" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="19" r="1.6"/><circle cx="15" cy="19" r="1.6"/></svg>
				</td>
				<td><span style="display:block;width:44px;height:44px;border-radius:8px;background:#f7f8fa center/cover no-repeat;{{ $it_thumb_style }}"></span></td>
				<td>
					<strong>{{ $it->item_name }}</strong>
					@if ($it->is_recommend === 'Y')<span class="rsva-st rsva-st-confirmed">{{ lang('commerce.admin_items_17') }}</span>@endif
					@if ($it->is_new === 'Y')<span class="rsva-st">NEW</span>@endif
					@if ($it->is_adult === 'Y')<span class="rsva-st rsva-st-cancelled">{{ lang('commerce.admin_items_18') }}</span>@endif
					@if ($it->tax_type === 'free')<span class="rsva-st">{{ lang('commerce.admin_items_19') }}</span>@endif
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
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $it->item_srl) }}" class="rsva-btn rsva-btn-sm">{{ lang('commerce.admin_items_20') }}</a>
					<a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'clone_from', $it->item_srl) }}" class="rsva-btn rsva-btn-sm">{{ lang('commerce.admin_items_21') }}</a>
					<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까? 주문 이력이 있으면 숨김으로 전환됩니다.')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminDeleteItem" />
						<input type="hidden" name="item_srl" value="{{ $it->item_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_items_22') }}</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>

<style>
.zmc-sorthint { margin: 0 0 10px; font-size: 12.5px; color: #6b7684; }
#zmcItemTable tbody tr.is-drag { opacity: .45; }
#zmcItemTable tbody tr.is-over td { border-top: 2px solid #2677e3; }
.zmc-handle { color: #b6bcc6; cursor: grab; text-align: center; vertical-align: middle; }
.zmc-handle:active { cursor: grabbing; }
#zmcItemTable tbody tr:hover .zmc-handle { color: #6b7684; }
.zmc-sortsave { position: fixed; right: 26px; bottom: 26px; z-index: 30; display: none; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: #1d2433; color: #fff; box-shadow: 0 12px 30px -12px rgba(16,24,40,.6); }
.zmc-sortsave.is-on { display: flex; }
.zmc-sortsave button { padding: 8px 14px; border: 0; border-radius: 8px; background: #2677e3; color: #fff; font-weight: 700; cursor: pointer; font-family: inherit; }
.zmc-sortsave a { color: #cdd5e0; font-size: 13px; text-decoration: underline; cursor: pointer; }
</style>

<div class="zmc-sortsave" id="zmcSortSave">
	<span>{{ lang('commerce.admin_items_23') }}</span>
	<button type="button" id="zmcSortApply">{{ lang('commerce.admin_items_24') }}</button>
	<a id="zmcSortCancel">{{ lang('commerce.admin_items_25') }}</a>
</div>

<script>
jQuery(function ($) {
	// 상품 목록 드래그 정렬. 놓은 자리에 행을 옮기고, 저장을 눌러야 반영된다
	var tbody = document.getElementById('zmcItemRows');
	if (!tbody) return;
	var bar = document.getElementById('zmcSortSave');
	var original = Array.prototype.map.call(tbody.querySelectorAll('tr'), function (tr) { return tr; });
	var dragging = null;

	function markChanged() { bar.classList.add('is-on'); }

	tbody.addEventListener('dragstart', function (e) {
		var tr = e.target.closest('tr');
		if (!tr) return;
		dragging = tr;
		tr.classList.add('is-drag');
		e.dataTransfer.effectAllowed = 'move';
		try { e.dataTransfer.setData('text/plain', ''); } catch (err) {}
	});
	tbody.addEventListener('dragend', function () {
		if (dragging) dragging.classList.remove('is-drag');
		Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (tr) { tr.classList.remove('is-over'); });
		dragging = null;
	});
	tbody.addEventListener('dragover', function (e) {
		e.preventDefault();
		var tr = e.target.closest('tr');
		if (!tr || tr === dragging) return;
		Array.prototype.forEach.call(tbody.querySelectorAll('tr'), function (row) { row.classList.remove('is-over'); });
		tr.classList.add('is-over');
	});
	tbody.addEventListener('drop', function (e) {
		e.preventDefault();
		var tr = e.target.closest('tr');
		if (!tr || !dragging || tr === dragging) return;
		var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
		var from = rows.indexOf(dragging);
		var to = rows.indexOf(tr);
		if (from < to) { tr.parentNode.insertBefore(dragging, tr.nextSibling); }
		else { tr.parentNode.insertBefore(dragging, tr); }
		tr.classList.remove('is-over');
		markChanged();
	});

	document.getElementById('zmcSortCancel').addEventListener('click', function () {
		original.forEach(function (tr) { tbody.appendChild(tr); });
		bar.classList.remove('is-on');
	});
	document.getElementById('zmcSortApply').addEventListener('click', function () {
		var srls = Array.prototype.map.call(tbody.querySelectorAll('tr'), function (tr) {
			return tr.getAttribute('data-item-srl');
		});
		exec_json('commerce.procCommerceAdminSortItems', { item_srls: srls.join(',') }, function () {
			bar.classList.remove('is-on');
			original = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
			alert('진열 순서를 저장했습니다.');
		}, function (ret) {
			alert((ret && ret.message) || '순서를 저장하지 못했습니다.');
		});
	});
});
</script>
