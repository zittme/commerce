@include('_tabs')
@php
$il_status = ['' => lang('commerce.admin_items_1'), 'sale' => lang('commerce.st_item_sale'), 'soldout' => lang('commerce.st_item_soldout'), 'hidden' => lang('commerce.st_item_hidden'), 'stop' => lang('commerce.st_item_stop'), 'review' => lang('commerce.st_item_review')];
$il_st_class = ['sale' => 'rsva-st-on', 'soldout' => 'rsva-st-hold', 'hidden' => '', 'stop' => 'rsva-st-cancelled', 'review' => 'rsva-st-pending'];
$il_edit = getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit');
@endphp
<style>
.il-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 12px; flex-wrap: wrap; margin-bottom: 12px; border-bottom: 1px solid var(--zmc-line, #e6e3dc); }
.il-tabs { display: flex; gap: 20px; overflow-x: auto; }
.il-tabs a { flex: none; padding: 0 0 10px; border-bottom: 2px solid transparent; font-size: 14px; font-weight: 600; color: var(--zmc-sub, #7a7f8c) !important; text-decoration: none !important; }
.il-tabs a b { margin-left: 5px; font-weight: 600; font-variant-numeric: tabular-nums; }
.il-tabs a.is-on { color: var(--zmc-ink, #232a3b) !important; border-color: var(--zmc-brand, #26345c); }
.il-top .rsva-btn-primary { margin-bottom: 8px; }
.il-filter { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }
.il-filter input[type="text"] { min-width: 220px; }
.il-bulk { display: none; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 10px; padding: 10px 14px; border-radius: var(--zmc-r, 6px); background: var(--zmc-brand-soft, #fbf1d6); font-size: 13.5px; }
.il-bulk.is-on { display: flex; }
.il-bulk b { margin-right: 6px; }
#zmcItemTable tbody tr { cursor: pointer; }
#zmcItemTable td { vertical-align: middle; }
#zmcItemTable th.il-num { text-align: right; }
.il-name small { display: block; font-size: 12px; font-weight: 700; color: var(--zmc-sub, #7a7f8c); }
.il-name strong { font-weight: 600; }
.il-tags { display: inline-flex; gap: 4px; margin-left: 6px; vertical-align: middle; }
.il-tags span { padding: 0 6px; border-radius: 3px; background: var(--zmc-side, #efede8); font-size: 11px; font-weight: 700; color: var(--zmc-ink-2, #3c4458); line-height: 18px; }
.il-num { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.il-more { position: relative; }
.il-more > button { width: 30px; height: 30px; border: 1px solid transparent; border-radius: var(--zmc-r-sm, 5px); background: transparent; color: var(--zmc-sub, #7a7f8c); font-size: 18px; line-height: 1; cursor: pointer; }
.il-more > button:hover, .il-more.is-open > button { border-color: var(--zmc-line-strong, #d6d2c8); background: var(--zmc-surface, #fff); color: var(--zmc-ink, #232a3b); }
.il-menu { display: none; position: absolute; right: 0; top: 34px; z-index: 20; min-width: 130px; padding: 4px; border: 1px solid var(--zmc-line-strong, #d6d2c8); border-radius: var(--zmc-r, 6px); background: var(--zmc-surface, #fff); box-shadow: 0 10px 24px -12px rgba(40,30,20,.35); }
.il-more.is-open .il-menu { display: block; }
.il-menu a, .il-menu button { display: block; width: 100%; padding: 7px 10px; border: 0; border-radius: 4px; background: none; text-align: left; font-size: 13.5px; color: var(--zmc-ink, #232a3b) !important; text-decoration: none !important; cursor: pointer; font-family: inherit; }
.il-menu a:hover, .il-menu button:hover { background: var(--zmc-hover, #e9e6df); }
.il-menu button.is-danger { color: var(--zmc-bad, #b33a2e) !important; }
</style>

<div class="rsva">
	<div class="il-top">
		<nav class="il-tabs">
			@foreach ($il_status as $il_k => $il_label)
			<a class="{{ $filters->status === $il_k ? 'is-on' : '' }}" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItems', 'f_status', $il_k, 'f_category', $filters->category ?: '', 'f_brand', $filters->brand ?: '', 'f_keyword', $filters->keyword) }}">{{ $il_label }}<b>{{ number_format($status_counts[$il_k] ?? 0) }}</b></a>
			@endforeach
		</nav>
		<a href="{{ $il_edit }}" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_items_8') }}</a>
	</div>

	<form action="{{ getUrl('') }}" method="get" class="il-filter">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="dispCommerceAdminItems" />
		<input type="hidden" name="f_status" value="{{ $filters->status }}" />
		<select name="f_category" onchange="this.form.submit()">
			<option value="">{{ lang('commerce.admin_items_6') }}</option>
			@foreach ($categories as $srl => $c)
			<option value="{{ $srl }}" @if($filters->category === $srl) selected @endif>{{ ($c->depth ?? 0) > 0 ? str_repeat('　', $c->depth) : '' }}{{ $c->title }}</option>
			@endforeach
		</select>
		@if (count($brands))
		<select name="f_brand" onchange="this.form.submit()">
			<option value="">{{ lang('commerce.shop_brand_all') }}</option>
			@foreach ($brands as $il_b)
			<option value="{{ $il_b->brand_srl }}" @if((int)$filters->brand === (int)$il_b->brand_srl) selected @endif>{{ $il_b->name }}</option>
			@endforeach
		</select>
		@endif
		@if (($seller_mode ?? '') === 'operator' && count($seller_names ?? []))
		<select name="f_seller" onchange="this.form.submit()" aria-label="{{ lang('commerce.mk_filter_seller') }}">
			<option value="">{{ lang('commerce.mk_all_sellers') }}</option>
			@foreach ($seller_names as $il_sid => $il_sname)
			<option value="{{ $il_sid }}" @if((int)($filters->seller ?? 0) === (int)$il_sid) selected @endif>{{ $il_sname }}</option>
			@endforeach
		</select>
		@endif
		<input type="text" name="f_keyword" placeholder="{{ lang('commerce.admin_items_11') }}" value="{{ $filters->keyword }}" />
		<button type="submit" class="rsva-btn">{{ lang('commerce.admin_items_7') }}</button>
	</form>

	@if (empty($items))
	<div class="rsva-panel"><p class="rsva-empty">{{ lang('commerce.admin_items_9') }}</p></div>
	@else
	<form action="{{ getUrl('') }}" method="post" id="ilBulkForm" class="il-bulk">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminBulkItemStatus" />
		<input type="hidden" name="success_return_url" value="{{ $_SERVER['REQUEST_URI'] ?? '' }}" />
		<b id="ilBulkCount"></b>
		@foreach (['sale', 'soldout', 'hidden', 'stop'] as $il_k)
		<button type="submit" name="status" value="{{ $il_k }}" class="rsva-btn rsva-btn-sm">{{ $il_status[$il_k] }}</button>
		@endforeach
	</form>
	<p class="zmc-sorthint">{{ lang('commerce.admin_items_10') }}</p>
	<table class="rsva-table" id="zmcItemTable">
		<thead><tr><th style="width:34px"><input type="checkbox" id="ilCheckAll" aria-label="{{ lang('commerce.admin_items_select_all') }}" /></th><th style="width:28px"></th><th style="width:56px"></th><th>{{ lang('commerce.admin_items_11') }}</th><th class="il-num">{{ lang('commerce.admin_items_12') }}</th><th class="il-num">{{ lang('commerce.admin_items_13') }}</th><th>{{ lang('commerce.admin_items_15') }}</th><th style="width:44px"></th></tr></thead>
		<tbody id="zmcItemRows">
			@foreach ($items as $it)
			@php $it_thumb_style = !empty($it->thumb) ? "background-image:url('" . $it->thumb . "');" : ''; @endphp
			<tr draggable="true" data-item-srl="{{ $it->item_srl }}">
				<td><input type="checkbox" name="item_srls[]" value="{{ $it->item_srl }}" form="ilBulkForm" class="il-check" aria-label="{{ $it->item_name }}" /></td>
				<td class="zmc-handle" aria-label="{{ lang('commerce.adm_order_handle') }}">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="9" cy="5" r="1.6"/><circle cx="15" cy="5" r="1.6"/><circle cx="9" cy="12" r="1.6"/><circle cx="15" cy="12" r="1.6"/><circle cx="9" cy="19" r="1.6"/><circle cx="15" cy="19" r="1.6"/></svg>
				</td>
				<td><span style="display:block;width:44px;height:44px;border-radius:5px;background:var(--zmc-side, #f7f8fa) center/cover no-repeat;{{ $it_thumb_style }}"></span></td>
				<td class="il-name">
					@if (!empty($it->brand_name))<small>{{ $it->brand_name }}</small>@endif
					@if (($seller_mode ?? '') === 'operator' && isset($seller_names[(int)$it->seller_srl]))<small>{{ $seller_names[(int)$it->seller_srl] }}</small>@endif
					<a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'item_srl', $it->item_srl) }}" style="color:inherit;text-decoration:none"><strong>{{ $it->item_name }}</strong></a>
					<span class="il-tags">
						@if ($it->is_recommend === 'Y')<span>{{ lang('commerce.admin_items_17') }}</span>@endif
						@if ($it->is_new === 'Y')<span>NEW</span>@endif
						@if ($it->is_adult === 'Y')<span>{{ lang('commerce.admin_items_18') }}</span>@endif
						@if ($it->tax_type === 'free')<span>{{ lang('commerce.admin_items_19') }}</span>@endif
						@if ($it->has_options === 'Y')<span>{{ lang('commerce.admin_items_14') }}</span>@endif
					</span>
				</td>
				<td class="il-num">
					@if ($it->sale_price > 0 && $it->sale_price < $it->price)
					<s style="color:var(--zmc-sub, #9aa1ab);font-size:12.5px">{{ shop_money_base((int)$it->price) }}</s> <strong>{{ shop_money_base((int)$it->sale_price) }}</strong>
					@else
					{{ shop_money_base((int)$it->price) }}
					@endif
				</td>
				<td class="il-num">{{ $it->use_stock === 'Y' ? number_format($it->stock) : lang('commerce.st_item_unlimited') }}</td>
				<td><span class="rsva-st {{ $il_st_class[$it->status] ?? '' }}">{{ $il_status[$it->status] ?? $it->status }}</span></td>
				<td>
					<div class="il-more">
						<button type="button" aria-label="{{ lang('commerce.admin_items_more') }}">⋯</button>
						<div class="il-menu">
							<a href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit', 'clone_from', $it->item_srl) }}">{{ lang('commerce.admin_items_21') }}</a>
							<form action="{{ getUrl('') }}" method="post" onsubmit="return confirm('{{ lang('commerce.adm_item_delete_ask') }}')">
								<input type="hidden" name="module" value="admin" />
								<input type="hidden" name="act" value="procCommerceAdminDeleteItem" />
								<input type="hidden" name="item_srl" value="{{ $it->item_srl }}" />
								<button type="submit" class="is-danger">{{ lang('commerce.admin_items_22') }}</button>
							</form>
						</div>
					</div>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@include('_pagenav', ['pn' => $page_navigation])
	@endif
</div>

<script>
(function () {
	var rows = document.getElementById('zmcItemRows');
	if (rows) {
		rows.addEventListener('click', function (e) {
			if (e.target.closest('input, button, a, form, .zmc-handle, .il-more')) return;
			var tr = e.target.closest('tr[data-item-srl]');
			var link = tr && tr.querySelector('.il-name a');
			if (link) location.href = link.href;
		});
	}
	document.addEventListener('click', function (e) {
		var more = e.target.closest('.il-more');
		document.querySelectorAll('.il-more.is-open').forEach(function (m) { if (m !== more) m.classList.remove('is-open'); });
		if (more && e.target.closest('.il-more > button')) more.classList.toggle('is-open');
	});
	var bulk = document.getElementById('ilBulkForm');
	var all = document.getElementById('ilCheckAll');
	var sync = function () {
		var n = document.querySelectorAll('.il-check:checked').length;
		if (!bulk) return;
		bulk.classList.toggle('is-on', n > 0);
		document.getElementById('ilBulkCount').textContent = {!! json_encode(lang('commerce.admin_items_selected')) !!}.replace('%d', n);
	};
	document.querySelectorAll('.il-check').forEach(function (c) { c.addEventListener('change', sync); });
	if (all) all.addEventListener('change', function () { document.querySelectorAll('.il-check').forEach(function (c) { c.checked = all.checked; }); sync(); });
})();
</script>

<style>
.zmc-sorthint { margin: 0 0 10px; font-size: 12.5px; color: #6b7684; }
#zmcItemTable tbody tr.is-drag { opacity: .45; }
#zmcItemTable tbody tr.is-over td { border-top: 2px solid var(--zmc-brand, #2677e3); }
.zmc-handle { color: #b6bcc6; cursor: grab; text-align: center; vertical-align: middle; }
.zmc-handle:active { cursor: grabbing; }
#zmcItemTable tbody tr:hover .zmc-handle { color: #6b7684; }
.zmc-sortsave { position: fixed; right: 26px; bottom: 26px; z-index: 30; display: none; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 12px; background: var(--zmc-ink, #1d2433); color: #fff; box-shadow: 0 12px 30px -12px rgba(16,24,40,.6); }
.zmc-sortsave.is-on { display: flex; }
.zmc-sortsave button { padding: 8px 14px; border: 0; border-radius: 8px; background: var(--zmc-brand, #2677e3); color: #fff; font-weight: 700; cursor: pointer; font-family: inherit; }
.zmc-sortsave a { color: #cdd5e0; font-size: 13px; text-decoration: underline; cursor: pointer; }
</style>

<div class="zmc-sortsave" id="zmcSortSave">
	<span>{{ lang('commerce.admin_items_23') }}</span>
	<button type="button" id="zmcSortApply">{{ lang('commerce.admin_items_24') }}</button>
	<a id="zmcSortCancel">{{ lang('commerce.admin_items_25') }}</a>
</div>

<script>
jQuery(function ($) {
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
			alert({!! json_encode(lang('commerce.adm_order_saved')) !!});
		}, function (ret) {
			alert((ret && ret.message) || {!! json_encode(lang('commerce.adm_order_save_failed')) !!});
		});
	});
});
</script>
