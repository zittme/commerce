@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.sc_cats_title') }}</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.sc_cats_desc') }}</p>
		@if (count($sc_cats))
		<table class="rsva-table" style="margin-bottom:14px">
			<thead><tr><th>{{ lang('commerce.sc_cat_name') }}</th><th style="width:110px">{{ lang('commerce.sc_cat_order') }}</th><th style="width:170px"></th></tr></thead>
			<tbody>
				@foreach ($sc_cats as $sc_cat)
				<tr>
					<td colspan="3" style="padding:0">
						<form action="{{ getUrl('') }}" method="post" style="display:flex;gap:8px;align-items:center;padding:8px 14px">
							<input type="hidden" name="module" value="commerce" />
							<input type="hidden" name="act" value="procCommerceSellerCenterSaveCategory" />
							<input type="hidden" name="category_srl" value="{{ $sc_cat->category_srl }}" />
							<input type="text" name="title" value="{{ $sc_cat->title }}" maxlength="80" required style="flex:1" />
							<input type="number" name="list_order" value="{{ $sc_cat->list_order }}" style="width:90px" />
							<button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.mk_save') }}</button>
							<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger" formaction="{{ getUrl('') }}" name="act" value="procCommerceSellerCenterDeleteCategory" onclick="return confirm({{ json_encode(lang('commerce.sc_cat_delete_ask')) }})">{{ lang('commerce.sc_remove') }}</button>
						</form>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="commerce" />
			<input type="hidden" name="act" value="procCommerceSellerCenterSaveCategory" />
			<div style="flex:1;min-width:200px"><label>{{ lang('commerce.sc_cat_new') }}</label><input type="text" name="title" maxlength="80" required style="width:100%" /></div>
			<div><label>{{ lang('commerce.sc_cat_order') }}</label><input type="number" name="list_order" value="{{ count($sc_cats) + 1 }}" style="width:90px" /></div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.sc_cat_add') }}</button></div>
		</form>
	</div>

	@if (count($sc_cats))
	<form action="{{ getUrl('') }}" method="post" class="rsva-panel">
		<input type="hidden" name="module" value="commerce" />
		<input type="hidden" name="act" value="procCommerceSellerCenterItemCategory" />
		<h3>{{ lang('commerce.sc_cat_assign') }}</h3>
		@if (count($sc_items))
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_menu_items') }}</th><th style="width:240px">{{ lang('commerce.sc_cat_name') }}</th></tr></thead>
			<tbody>
				@foreach ($sc_items as $sc_it)
				<tr>
					<td>{{ $sc_it->item_name }}</td>
					<td>
						<select name="cat_of[{{ $sc_it->item_srl }}]" style="width:100%">
							<option value="0">{{ lang('commerce.sc_cat_none') }}</option>
							@foreach ($sc_cats as $sc_cat)
							<option value="{{ $sc_cat->category_srl }}" @selected((int)($sc_it->seller_category_srl ?? 0) === (int)$sc_cat->category_srl)>{{ $sc_cat->title }}</option>
							@endforeach
						</select>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		<p style="margin:14px 0 0"><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.mk_save') }}</button></p>
		@else
		<div class="rsva-empty">{{ lang('commerce.sc_no_items') }}</div>
		@endif
	</form>
	@endif
</div>
