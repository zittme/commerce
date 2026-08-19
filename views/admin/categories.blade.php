@include('_tabs')

@include('_langfield_assets')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_categories_1') }}</h3>
		@if (empty($categories))
		<p class="rsva-empty">{{ lang('commerce.admin_categories_2') }}</p>
		@else
		<p class="zmc-cathint">
			{{ lang('commerce.admin_categories_3') }} <b>&rarr;</b> {{ lang('commerce.admin_categories_4') }} <b>&larr;</b> {{ lang('commerce.admin_categories_5') }}
		</p>
		<table class="rsva-table zmc-cattable" id="zmcCatTable" style="margin-bottom:14px">
			<thead><tr><th style="width:34px"></th><th style="width:78px">{{ lang('commerce.admin_categories_6') }}</th><th>{{ lang('commerce.admin_categories_7') }}</th><th style="width:110px">{{ lang('commerce.admin_categories_8') }}</th><th style="width:150px"></th></tr></thead>
			<tbody id="zmcCatRows">
				@foreach ($categories as $c)
				<tr draggable="true" data-srl="{{ $c->category_srl }}" data-parent="{{ (int)$c->parent_srl }}" data-depth="{{ (int)($c->depth ?? 0) }}">
					{{-- 행마다 폼을 표 안에 두면 중첩되므로 form 속성으로 잇는다 --}}
					<form action="{{ getUrl('') }}" method="post" id="zmcCatForm{{ $c->category_srl }}"></form>
					<td class="zmc-handle" aria-label="{{ lang('commerce.adm_order_handle') }}">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="5" r="1.6"/><circle cx="15" cy="5" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="19" r="1.6"/><circle cx="15" cy="19" r="1.6"/></svg>
					</td>
					<td class="zmc-indent-cell">
						<button type="button" class="zmc-ind" data-move="out" title="{{ lang('commerce.admin_categories_18') }}">&larr;</button>
						<button type="button" class="zmc-ind" data-move="in" title="{{ lang('commerce.admin_categories_19') }}">&rarr;</button>
					</td>
					<td class="zmc-name-cell">
						<input type="hidden" name="module" value="admin" form="zmcCatForm{{ $c->category_srl }}" />
						<input type="hidden" name="act" value="procCommerceAdminInsertCategory" form="zmcCatForm{{ $c->category_srl }}" />
						{{-- 저장한 뒤 보던 자리로 돌아온다. 없으면 쪽수와 검색 조건이 날아간다 --}}
						<input type="hidden" name="success_return_url" value="{{ getUrl() }}" form="zmcCatForm{{ $c->category_srl }}" />
						<input type="hidden" name="category_srl" value="{{ $c->category_srl }}" form="zmcCatForm{{ $c->category_srl }}" />
						<input type="hidden" name="parent_srl" value="{{ (int)$c->parent_srl }}" form="zmcCatForm{{ $c->category_srl }}" class="zmc-parent-field" />
						<input type="hidden" name="list_order" value="{{ (int)$c->list_order }}" form="zmcCatForm{{ $c->category_srl }}" />
						<span class="zmc-indent"></span>
						<input type="text" name="title" value="{{ $c->title }}" required form="zmcCatForm{{ $c->category_srl }}" style="min-width:200px" />
						@include('_langfield', ['lf_name' => 'title', 'lf_value' => $c->title_raw ?? $c->title, 'lf_key' => $c->category_srl, 'lf_form' => 'zmcCatForm' . $c->category_srl])
					</td>
					<td>
						<select name="is_active" form="zmcCatForm{{ $c->category_srl }}">
							<option value="Y" @if ($c->is_active !== 'N') selected @endif>{{ lang('commerce.admin_categories_8') }}</option>
							<option value="N" @if ($c->is_active === 'N') selected @endif>{{ lang('commerce.admin_categories_9') }}</option>
						</select>
					</td>
					<td>
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-primary" form="zmcCatForm{{ $c->category_srl }}">{{ lang('commerce.admin_categories_10') }}</button>
						<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ lang('commerce.adm_cat_delete_ask') }}')">
							<input type="hidden" name="module" value="admin" />
							<input type="hidden" name="act" value="procCommerceAdminDeleteCategory" />
							<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
							<input type="hidden" name="category_srl" value="{{ $c->category_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_categories_11') }}</button>
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
			<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
			<div class="rsva-inline">
				<div><label>{{ lang('commerce.admin_categories_12') }}</label><input type="text" name="title" required /></div>
				<div style="min-width:150px">
					<label>{{ lang('commerce.admin_categories_13') }}</label>
					<select name="parent_srl">
						<option value="0">{{ lang('commerce.admin_categories_14') }}</option>
						@foreach ($categories as $c)
						<option value="{{ $c->category_srl }}">{{ ($c->depth ?? 0) > 0 ? str_repeat('&nbsp;&nbsp;', $c->depth) . '└ ' : '' }}{{ $c->title }}</option>
						@endforeach
					</select>
				</div>
				<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_categories_15') }}</button></div>
			</div>
		</form>
	</div>
</div>

<style>
.zmc-cathint { margin: 0 0 12px; font-size: 12.5px; color: #6b7684; line-height: 1.6; }
.zmc-cattable td { vertical-align: middle; }
.zmc-cattable input[type="text"], .zmc-cattable select { padding: 6px 8px; }
.zmc-handle { color: #b6bcc6; cursor: grab; text-align: center; }
.zmc-handle:active { cursor: grabbing; }
#zmcCatTable tbody tr:hover .zmc-handle { color: #6b7684; }
#zmcCatTable tbody tr.is-drag { opacity: .45; }
#zmcCatTable tbody tr.is-over td { border-top: 2px solid #2677e3; }
.zmc-indent { display: inline-block; }
.zmc-name-cell { white-space: nowrap; }
.zmc-ind { width: 26px; height: 26px; margin-right: 2px; border: 1px solid #e5e8eb; border-radius: 6px; background: #fff; color: #6b7684; cursor: pointer; font-size: 13px; line-height: 1; }
.zmc-ind:hover { border-color: #2677e3; color: #2677e3; }
.zmc-ind:disabled { opacity: .35; cursor: default; border-color: #e5e8eb; color: #b6bcc6; }
.zmc-catsave { position: fixed; right: 26px; bottom: 26px; z-index: 30; display: none; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: #1d2433; color: #fff; box-shadow: 0 12px 30px -12px rgba(16,24,40,.6); }
.zmc-catsave.is-on { display: flex; }
.zmc-name-cell { position: relative; }
.zmc-catsave button { padding: 8px 14px; border: 0; border-radius: 8px; background: #2677e3; color: #fff; font-weight: 700; cursor: pointer; font-family: inherit; }
</style>

<div class="zmc-catsave" id="zmcCatSave">
	<span>{{ lang('commerce.admin_categories_16') }}</span>
	<button type="button" id="zmcCatApply">{{ lang('commerce.admin_categories_17') }}</button>
</div>

<script>
jQuery(function ($) {
	var tbody = document.getElementById('zmcCatRows');
	if (!tbody) return;
	var bar = document.getElementById('zmcCatSave');
	var dragging = null;

	function rows() { return Array.prototype.slice.call(tbody.querySelectorAll('tr')); }
	function depthOf(tr) { return parseInt(tr.getAttribute('data-depth'), 10) || 0; }

	// 끌어 옮길 때 하위도 함께 따라가야 한다
	function subtreeOf(tr) {
		var all = rows();
		var start = all.indexOf(tr);
		var base = depthOf(tr);
		var group = [tr];
		for (var i = start + 1; i < all.length; i++) {
			if (depthOf(all[i]) > base) { group.push(all[i]); } else { break; }
		}
		return group;
	}

	// 화면의 차례대로 상위·들여쓰기·버튼 상태를 다시 계산한다
	function refresh() {
		var stack = [];
		rows().forEach(function (tr) {
			var d = depthOf(tr);
			stack.length = d;
			var parent = d > 0 ? (stack[d - 1] || 0) : 0;
			tr.setAttribute('data-parent', parent);
			var field = tr.querySelector('.zmc-parent-field');
			if (field) field.value = parent;
			stack[d] = parseInt(tr.getAttribute('data-srl'), 10);
			var pad = tr.querySelector('.zmc-indent');
			if (pad) pad.style.width = (d * 22) + 'px';
		});
		// 들여쓰기 가능 여부: 바로 위 항목이 있고, 그 항목보다 깊어질 수 있을 때만
		var all = rows();
		all.forEach(function (tr, i) {
			var prev = i > 0 ? all[i - 1] : null;
			var inBtn = tr.querySelector('[data-move="in"]');
			var outBtn = tr.querySelector('[data-move="out"]');
			if (inBtn) inBtn.disabled = !prev || depthOf(tr) > depthOf(prev) || depthOf(tr) >= 3;
			if (outBtn) outBtn.disabled = depthOf(tr) === 0;
		});
	}

	function markChanged() { bar.classList.add('is-on'); refresh(); }


	tbody.addEventListener('click', function (e) {
		var btn = e.target.closest('.zmc-ind');
		if (!btn || btn.disabled) return;
		var tr = btn.closest('tr');
		var delta = btn.getAttribute('data-move') === 'in' ? 1 : -1;
		subtreeOf(tr).forEach(function (row) {
			row.setAttribute('data-depth', Math.max(0, depthOf(row) + delta));
		});
		markChanged();
	});

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
		rows().forEach(function (tr) { tr.classList.remove('is-over'); });
		dragging = null;
	});
	tbody.addEventListener('dragover', function (e) {
		e.preventDefault();
		var tr = e.target.closest('tr');
		if (!tr || !dragging || tr === dragging) return;
		rows().forEach(function (row) { row.classList.remove('is-over'); });
		tr.classList.add('is-over');
	});
	tbody.addEventListener('drop', function (e) {
		e.preventDefault();
		var target = e.target.closest('tr');
		if (!target || !dragging || target === dragging) return;
		var group = subtreeOf(dragging);
		if (group.indexOf(target) !== -1) { return; }   // 자기 하위로는 못 들어간다

		var all = rows();
		var after = all.indexOf(target) > all.indexOf(dragging);
		var anchor = after ? subtreeOf(target)[subtreeOf(target).length - 1].nextSibling : target;
		group.forEach(function (row) { tbody.insertBefore(row, anchor); });
		target.classList.remove('is-over');

		// 옮긴 자리의 깊이에 맞춰 통째로 이동시킨다
		var moved = rows();
		var idx = moved.indexOf(dragging);
		var prev = idx > 0 ? moved[idx - 1] : null;
		var maxDepth = prev ? depthOf(prev) + 1 : 0;
		var diff = Math.min(depthOf(dragging), maxDepth) - depthOf(dragging);
		if (diff !== 0) {
			group.forEach(function (row) { row.setAttribute('data-depth', Math.max(0, depthOf(row) + diff)); });
		}
		markChanged();
	});

	document.getElementById('zmcCatApply').addEventListener('click', function () {
		var tree = rows().map(function (tr) {
			return { srl: parseInt(tr.getAttribute('data-srl'), 10), parent: parseInt(tr.getAttribute('data-parent'), 10) || 0 };
		});
		exec_json('commerce.procCommerceAdminSortCategories', { tree: JSON.stringify(tree) }, function () {
			bar.classList.remove('is-on');
			alert({!! json_encode(lang('commerce.adm_cat_saved')) !!});
			location.reload();
		}, function (ret) {
			alert((ret && ret.message) || {!! json_encode(lang('commerce.adm_save_failed')) !!});
		});
	});

	refresh();
});
</script>
