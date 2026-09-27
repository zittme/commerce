@include('_tabs')
@php
$sp_fixed = [];
$sp_contact = [];
$sp_bank = [];
$sp_extra = [];
$sp_intro = [];
foreach ($mk_af['biz'] ?? [] as $sp_f)
{
	if ($sp_f->builtin && !in_array($sp_f->key, $mk_editable, true)) { $sp_fixed[] = $sp_f; }
	elseif ($sp_f->builtin) { $sp_contact[] = $sp_f; }
	else { $sp_extra[] = $sp_f; }
}
foreach ($mk_af['bank'] ?? [] as $sp_f) { $sp_bank[] = $sp_f; }
foreach ($mk_af['intro'] ?? [] as $sp_f) { $sp_intro[] = $sp_f; }
@endphp
<style>
.sp-grid .af-help, .sp-grid .af-file { display: block; margin-top: 5px; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
.sp-grid textarea { min-height: 110px; }
.sp-addr { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; margin: 12px 0 0; font-size: 13px; }
.sp-addr a.sp-link { font-weight: 600; overflow-wrap: anywhere; }
</style>

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
		<div class="sp-addr">
			<span>{{ lang('commerce.sc_store_address') }} <a class="sp-link" href="{{ $mk_store_url }}" target="_blank">{{ $mk_store_full }}</a></span>
			<button type="button" class="rsva-btn rsva-btn-sm" data-qr-url="{{ $mk_store_full }}" data-qr-name="{{ $mk_me->shop_id }}" data-qr-title="{{ $mk_me->shop_name }}">{{ lang('commerce.qr_open') }}</button>
		</div>
		@endif
		@if (!empty($mk_me->shop_prev_id))
		<p style="margin:6px 0 0;font-size:12.5px;color:var(--zmc-sub, #7a7f8c)">{{ sprintf(lang('commerce.sc_shop_prev_id'), $mk_me->shop_prev_id) }}</p>
		@endif
	</form>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.mk_profile_biz') }}</h3>
		<p style="margin:-6px 0 14px;font-size:13px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.mk_profile_biz_desc') }}</p>
		<div class="rsva-form-grid">
			@foreach ($sp_fixed as $sp_f)
			@if ($sp_f->key !== 'shop_id')<div><label>{{ $sp_f->label }}</label><input type="text" value="{{ $sp_f->value }}" disabled /></div>@endif
			@endforeach
			<div><label>{{ lang('commerce.mk_f_commission') }}</label><input type="text" value="{{ $mk_rate }}%" disabled /></div>
		</div>
	</div>

	<form action="{{ getUrl('') }}" method="post" enctype="multipart/form-data">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminSaveSellerProfile" />
		@if (count($sp_contact) || count($sp_bank))
		<div class="rsva-panel">
			<h3>{{ lang('commerce.mk_profile_contact') }}</h3>
			<div class="rsva-form-grid sp-grid">
				@foreach ($sp_contact as $f)@include('_affield', ['f' => $f])@endforeach
				@foreach ($sp_bank as $f)@include('_affield', ['f' => $f])@endforeach
			</div>
		</div>
		@endif
		@if (count($sp_extra))
		<div class="rsva-panel">
			<h3>{{ lang('commerce.af_extra_title') }}</h3>
			<div class="rsva-form-grid sp-grid">
				@foreach ($sp_extra as $f)@include('_affield', ['f' => $f])@endforeach
			</div>
		</div>
		@endif
		<div class="rsva-panel">
			<h3>{{ lang('commerce.mk_profile_ship') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.mk_f_ship_fee') }}</label><input type="text" inputmode="decimal" name="ship_fee" value="{{ $mk_me->ship_fee_input }}" /></div>
				<div><label>{{ lang('commerce.mk_f_free_over') }}</label><input type="text" inputmode="decimal" name="free_ship_over" value="{{ $mk_me->free_over_input }}" placeholder="0" /></div>
			</div>
			<p style="margin:10px 0 0;font-size:12.5px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.mk_profile_ship_desc') }}</p>
		</div>
		@if (count($sp_intro))
		<div class="rsva-panel">
			<h3>{{ lang('commerce.mk_f_intro') }}</h3>
			<div class="rsva-form-grid sp-grid" style="grid-template-columns:1fr">
				@foreach ($sp_intro as $f)@include('_affield', ['f' => $f])@endforeach
			</div>
		</div>
		@endif
		<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.mk_save') }}</button>
	</form>
</div>
@include('_qr')
