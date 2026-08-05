@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>카테고리</h3>
		@if (empty($categories))
		<p class="rsva-empty">카테고리가 없습니다. 아래에서 추가하세요.</p>
		@else
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>이름</th><th>상위</th><th>순서</th><th>노출</th><th></th></tr></thead>
			<tbody>
				@foreach ($categories as $c)
				<tr>
					<td><strong>{{ $c->title }}</strong></td>
					<td>{{ (int)$c->parent_srl > 0 ? '#' . $c->parent_srl : '-' }}</td>
					<td>{{ $c->list_order }}</td>
					<td>{{ $c->is_active === 'Y' ? '노출' : '숨김' }}</td>
					<td>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('삭제하시겠습니까? 소속 상품은 미분류로 이동합니다.')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procCommerceAdminDeleteCategory" />
							<input type="hidden" name="category_srl" value="{{ $c->category_srl }}" />
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
			<input type="hidden" name="act" value="procCommerceAdminInsertCategory" />
			<div class="rsva-inline">
				<div><label>이름 *</label><input type="text" name="title" required /></div>
				<div style="min-width:150px">
					<label>상위 카테고리</label>
					<select name="parent_srl">
						<option value="0">최상위</option>
						@foreach ($categories as $c)
						<option value="{{ $c->category_srl }}">{{ $c->title }}</option>
						@endforeach
					</select>
				</div>
				<div><label>순서</label><input type="number" name="list_order" value="0" style="width:70px" /></div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">추가</button></div>
			</div>
		</form>
	</div>
</div>
