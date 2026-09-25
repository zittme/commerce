@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post" class="rsva-panel">
		<input type="hidden" name="module" value="commerce" />
		<input type="hidden" name="act" value="procCommerceSellerCenterSaveShopId" />
		<h3>{{ lang('commerce.sc_shop_id') }}</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.sc_shop_id_desc') }}</p>
		<div class="rsva-inline">
			<div style="flex:1;min-width:220px"><input type="text" name="shop_id" value="{{ $mk_me->shop_id ?? '' }}" minlength="3" maxlength="30" required style="width:100%" /></div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.mk_save') }}</button></div>
		</div>
		@if (!empty($mk_store_url))
		<p style="margin:10px 0 0;font-size:13px">{{ lang('commerce.sc_store_address') }} <a href="{{ $mk_store_url }}" target="_blank">{{ $mk_store_url }}</a></p>
		@endif
		@if (!empty($mk_me->shop_prev_id))
		<p style="margin:6px 0 0;font-size:12.5px;color:var(--zmc-sub, #7a7f8c)">{{ sprintf(lang('commerce.sc_shop_prev_id'), $mk_me->shop_prev_id) }}</p>
		@endif
	</form>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.mk_profile_biz') }}</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.mk_profile_biz_desc') }}</p>
		<div class="rsva-form-grid">
			<div><label>{{ lang('commerce.mk_f_shop_name') }}</label><input type="text" value="{{ $mk_me->shop_name }}" disabled /></div>
			<div><label>{{ lang('commerce.mk_f_biz_name') }}</label><input type="text" value="{{ $mk_me->biz_name }}" disabled /></div>
			<div><label>{{ lang('commerce.mk_f_ceo_name') }}</label><input type="text" value="{{ $mk_me->ceo_name }}" disabled /></div>
			<div><label>{{ lang('commerce.mk_f_biz_no') }}</label><input type="text" value="{{ $mk_me->biz_no }}" disabled /></div>
			<div><label>{{ lang('commerce.mk_f_mailorder_no') }}</label><input type="text" value="{{ $mk_me->mailorder_no }}" disabled /></div>
			<div><label>{{ lang('commerce.mk_f_commission') }}</label><input type="text" value="{{ $mk_rate }}%" disabled /></div>
		</div>
	</div>

	<form action="{{ getUrl('') }}" method="post">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminSaveSellerProfile" />
		<div class="rsva-panel">
			<h3>{{ lang('commerce.mk_profile_contact') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.mk_f_tel') }}</label><input type="tel" name="tel" maxlength="30" value="{{ $mk_me->tel }}" required /></div>
				<div><label>{{ lang('commerce.mk_f_email') }}</label><input type="email" name="email" maxlength="120" value="{{ $mk_me->email }}" /></div>
				<div><label>{{ lang('commerce.mk_f_bank_name') }}</label><input type="text" name="bank_name" maxlength="40" value="{{ $mk_me->bank_name }}" required /></div>
				<div><label>{{ lang('commerce.mk_f_bank_account') }}</label><input type="text" name="bank_account" maxlength="60" value="{{ $mk_me->bank_account }}" required /></div>
				<div><label>{{ lang('commerce.mk_f_bank_holder') }}</label><input type="text" name="bank_holder" maxlength="60" value="{{ $mk_me->bank_holder }}" required /></div>
			</div>
		</div>
		<div class="rsva-panel">
			<h3>{{ lang('commerce.mk_profile_ship') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.mk_f_ship_fee') }}</label><input type="text" inputmode="decimal" name="ship_fee" value="{{ $mk_me->ship_fee_input }}" /></div>
				<div><label>{{ lang('commerce.mk_f_free_over') }}</label><input type="text" inputmode="decimal" name="free_ship_over" value="{{ $mk_me->free_over_input }}" placeholder="0" /></div>
			</div>
			<p style="margin:10px 0 0;font-size:12.5px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.mk_profile_ship_desc') }}</p>
		</div>
		<div class="rsva-panel">
			<h3>{{ lang('commerce.mk_f_intro') }}</h3>
			<div class="rsva-field"><textarea name="intro" rows="5" maxlength="2000">{{ $mk_me->intro }}</textarea></div>
		</div>
		<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.mk_save') }}</button>
	</form>
</div>
