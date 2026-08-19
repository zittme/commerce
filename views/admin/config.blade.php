@include('_tabs')
@include('_langfield_assets')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminInsertConfig" />

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_1') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_2') }}</label><select name="enabled"><option value="Y" @if($shop_config->enabled === 'Y') selected @endif>{{ lang('commerce.admin_config_3') }}</option><option value="N" @if($shop_config->enabled === 'N') selected @endif>{{ lang('commerce.admin_config_4') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_5') }}</label><select name="market_mode"><option value="single" @if($shop_config->market_mode === 'single') selected @endif>{{ lang('commerce.admin_config_6') }}</option><option value="open" @if($shop_config->market_mode === 'open') selected @endif>{{ lang('commerce.admin_config_7') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_8') }}</label><input type="text" name="code_prefix" maxlength="5" value="{{ $shop_config->code_prefix }}" /></div>
				<div><label>{{ lang('commerce.admin_config_9') }}</label><select name="allow_guest"><option value="Y" @if($shop_config->allow_guest === 'Y') selected @endif>{{ lang('commerce.admin_config_10') }}</option><option value="N" @if($shop_config->allow_guest === 'N') selected @endif>{{ lang('commerce.admin_config_11') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_12') }}</label><input type="number" name="pending_minutes" min="10" max="1440" value="{{ $shop_config->pending_minutes }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_13') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_14') }}</label><input type="number" name="default_ship_fee" min="0" step="any" value="{{ \Zittme\Modules\Commerce\Models\Money::minorToInput((int)$shop_config->default_ship_fee) }}" /></div>
				<div><label>{{ lang('commerce.admin_config_15') }}</label><input type="number" name="free_ship_over" min="0" step="any" value="{{ \Zittme\Modules\Commerce\Models\Money::minorToInput((int)$shop_config->free_ship_over) }}" /></div>
				<div><label>{{ lang('commerce.admin_config_16') }}</label><select name="item_sticky"><option value="N" @if(($shop_config->item_sticky ?? 'N') !== 'Y') selected @endif>{{ lang('commerce.admin_config_17') }}</option><option value="Y" @if(($shop_config->item_sticky ?? 'N') === 'Y') selected @endif>{{ lang('commerce.admin_config_18') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_19') }}</label><input type="number" name="claim_days" min="0" max="90" value="{{ $shop_config->claim_days }}" /></div>
			</div>
			<div style="margin-top:16px">
				<label style="font-weight:700">{{ lang('commerce.admin_config_20') }}</label>
				<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1;line-height:1.7">
					{{ lang('commerce.admin_config_21') }}
					<a href="https://tracking.sweettracker.co.kr" target="_blank" rel="noopener" style="color:#2677e3">{{ lang('commerce.admin_config_22') }}</a>{{ lang('commerce.cfg_track_note1') }}
					{{ lang('commerce.cfg_track_note2') }}
					{{ lang('commerce.cfg_track_note3') }} <b>{{ lang('commerce.admin_config_24') }}</b> {{ lang('commerce.admin_config_25') }}
				</p>
				<input type="text" name="sweettracker_api_key" value="{{ $shop_config->sweettracker_api_key ?? '' }}" placeholder="{{ lang('commerce.admin_config_103') }}" style="width:340px;max-width:100%" autocomplete="off" />
			</div>
			<div style="margin-top:16px">
				<label style="font-weight:700">{{ lang('commerce.admin_config_26') }}</label>
				<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1;line-height:1.7">
					{{ lang('commerce.cfg_zone_note') }}
				</p>
				<div id="zmcZoneRows"></div>
				<button type="button" class="rsva-btn rsva-btn-sm" id="zmcZoneAdd">{{ lang('commerce.admin_config_28') }}</button>
				<input type="hidden" name="ship_extra_zones" id="zmcZonesJson" value="{{ $zmc_zones_display }}" />
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_29') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_30') }}</label><select name="shop_main"><option value="list" @if(($shop_config->shop_main ?? 'list') !== 'home') selected @endif>{{ lang('commerce.admin_config_31') }}</option><option value="home" @if(($shop_config->shop_main ?? 'list') === 'home') selected @endif>{{ lang('commerce.admin_config_32') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_33') }}</label><select name="category_layout"><option value="top" @if(($shop_config->category_layout ?? 'top') !== 'side') selected @endif>{{ lang('commerce.admin_config_34') }}</option><option value="side" @if(($shop_config->category_layout ?? 'top') === 'side') selected @endif>{{ lang('commerce.admin_config_35') }}</option></select></div>
				<div><label>{{ lang('commerce.admin_config_36') }}</label><input type="number" name="home_count" min="4" max="24" value="{{ $shop_config->home_count ?? 8 }}" /></div>
			</div>
			<div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:12px;font-size:13.5px">
				<label><input type="checkbox" name="home_show_recommend" value="Y" @if(($shop_config->home_show_recommend ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_config_37') }}</label>
				<label><input type="checkbox" name="home_show_new" value="Y" @if(($shop_config->home_show_new ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_config_38') }}</label>
				<label><input type="checkbox" name="home_show_popular" value="Y" @if(($shop_config->home_show_popular ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_config_39') }}</label>
				<label><input type="checkbox" name="home_show_sale" value="Y" @if(($shop_config->home_show_sale ?? 'Y') === 'Y') checked @endif /> {{ lang('commerce.admin_config_40') }}</label>
			</div>
			<div style="margin-top:16px">
				<label style="font-weight:700">{{ lang('commerce.admin_config_41') }}</label>
				<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.admin_config_42') }}</p>
				<style>
				/* 배너 한 건 = 카드. 프론트 편집 패널과 같은 항목을 담는다 */
				.zmc-banner-card { position: relative; display: grid; grid-template-columns: 220px minmax(0, 1fr); gap: 16px; padding: 14px 16px; margin-bottom: 10px; border: 1px solid #e5e8ee; border-radius: 12px; background: #fbfcfd; }
				.zmc-banner-imgs { display: flex; flex-direction: column; gap: 10px; }
				.zmc-banner-img { display: flex; gap: 10px; align-items: center; }
				.zmc-banner-thumb { flex: 0 0 auto; width: 56px; height: 56px; border: 1px solid #e5e8ee; border-radius: 8px; background: #fff center/cover no-repeat; }
				.zmc-banner-thumb.is-empty { border-style: dashed; }
				.zmc-banner-imgacts { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
				.zmc-banner-imglabel { font-size: 12px; color: #6b7684; }
				.zmc-banner-fields { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 10px 12px; align-content: start; }
				.zmc-f { min-width: 0; }
				.zmc-f > label { display: block; margin-bottom: 4px; font-size: 12px; color: #6b7684; }
				.zmc-f input[type="text"], .zmc-f select { width: 100%; box-sizing: border-box; }
				.zmc-f input[type="color"] { width: 42px; height: 32px; padding: 2px; vertical-align: middle; }
				.zmc-f .zmc-inline { display: inline-flex; align-items: center; gap: 5px; margin: 0 0 0 8px; font-size: 12.5px; color: #4e5968; }
				.zmc-banner-del { position: absolute; top: 12px; right: 14px; }
				@media (max-width: 900px) { .zmc-banner-card { grid-template-columns: minmax(0, 1fr); } }
				.zmc-pay-link { color: #2677e3; }
				.zmc-hint { margin: 4px 0 8px; font-size: 12px; color: #8b95a1; line-height: 1.6; }
				.zmc-logo-row { display: flex; align-items: center; gap: 12px; }
				.zmc-logo-thumb { flex: 0 0 auto; width: 132px; height: 56px; border: 1px solid #e5e8ee; border-radius: 8px; background: #fff center/contain no-repeat; }
				.zmc-logo-thumb.is-empty { border-style: dashed; }
				.zmc-logo-acts { display: flex; gap: 6px; }
				.zmc-zone-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin-bottom: 8px; }
				.zmc-zone-row > select, .zmc-zone-row > input { min-width: 0; }
				.zmc-zone-country { flex: 0 0 170px; }
				.zmc-zone-region { flex: 0 0 130px; }
				.zmc-zone-zips { flex: 1; min-width: 200px; }
				.zmc-zone-row [hidden] { display: none !important; }
				.zmc-tier-wrap { flex: 1 0 100%; margin: 4px 0 10px 8px; padding-left: 12px; border-left: 2px solid #e5e8ee; }
				.zmc-tier { display: flex; gap: 6px; align-items: center; margin-bottom: 6px; }
				.zmc-tier input { width: 150px; }
				.zmc-tier-arrow { color: #8b95a1; }
				</style>
				<div id="zmcBannerRows"></div>
				<button type="button" class="rsva-btn rsva-btn-sm" id="zmcBannerAdd">{{ lang('commerce.admin_config_43') }}</button>
				<input type="hidden" name="home_banners" id="zmcBannersJson" value="{{ $shop_config->home_banners ?? '[]' }}" />
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_44') }}</h3>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_45') }}</label><input type="number" name="credit_rate" min="0" max="100" step="0.01" value="{{ $shop_config->credit_rate }}" /></div>
				<div><label>{{ lang('commerce.admin_config_46') }}</label><input type="number" name="credit_min_use" min="0" step="any" value="{{ \Zittme\Modules\Commerce\Models\Money::minorToInput((int)$shop_config->credit_min_use) }}" /></div>
				<div><label>{{ lang('commerce.admin_config_47') }}</label><input type="number" name="review_credit_text" min="0" step="any" value="{{ \Zittme\Modules\Commerce\Models\Money::minorToInput((int)($shop_config->review_credit_text ?? 0)) }}" /></div>
				<div><label>{{ lang('commerce.admin_config_48') }}</label><input type="number" name="review_credit_photo" min="0" step="any" value="{{ \Zittme\Modules\Commerce\Models\Money::minorToInput((int)($shop_config->review_credit_photo ?? 0)) }}" /></div>
			</div>
			<p style="margin:8px 0 0;font-size:12.5px;color:#8b95a1">{{ lang('commerce.admin_config_49') }}</p>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.cfg_payment') }} {{ $pay_available ? '' : ': ' . lang('commerce.cfg_pay_missing') }}</h3>
			<p style="margin:0;font-size:13px;color:#6b7684">{{ lang('commerce.admin_config_50') }}</p>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_51') }}</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1"><label>{{ lang('commerce.admin_config_52') }}</label><div class="zlf-row-wrap"><textarea name="privacy_text" rows="3">{{ $shop_config->privacy_text }}</textarea>@include('_langfield', ['lf_name' => 'privacy_text', 'lf_value' => $shop_config->privacy_text_raw ?? $shop_config->privacy_text, 'lf_key' => 'cfgprivacy'])</div></div>
				<div><label>{{ lang('commerce.admin_config_53') }}</label><input type="text" name="privacy_version" maxlength="20" value="{{ $shop_config->privacy_version }}" /></div>
				<div><label>{{ lang('commerce.admin_config_54') }}</label><input type="number" name="retention_days" min="0" max="3650" value="{{ $shop_config->retention_days }}" /></div>
				<div><label>{{ lang('commerce.admin_config_55') }}</label><select name="notify_admin"><option value="N" @if($shop_config->notify_admin === 'N') selected @endif>{{ lang('commerce.admin_config_56') }}</option><option value="Y" @if($shop_config->notify_admin === 'Y') selected @endif>{{ lang('commerce.admin_config_57') }}</option></select></div>
				<div>
					<label>{{ lang('commerce.admin_config_58') }}</label>
					<input type="text" name="notify_admin_email" value="{{ $shop_config->notify_admin_email }}" style="min-width:280px" />
					<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.shop_notify_email_help') }}</p>
				</div>
				<div>
					<label>{{ lang('commerce.shop_notify_group') }}</label>
					<select name="notify_admin_group">
						<option value="0">{{ lang('commerce.shop_use_off') }}</option>
						@foreach (\MemberModel::getGroups() as $ng)
						<option value="{{ $ng->group_srl }}" @if((int)($shop_config->notify_admin_group ?? 0) === (int)$ng->group_srl) selected @endif>{{ Context::replaceUserLang($ng->title, true) }}</option>
						@endforeach
					</select>
					<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.shop_notify_group_help') }}</p>
				</div>
			</div>

			{{-- 사건마다 켜고 끈다. 값이 없으면 보내는 것으로 본다 --}}
			<h4 style="margin:18px 0 8px;font-size:14px">{{ lang('commerce.shop_notify_events') }}</h4>
			<div class="rsva-inline">
				@foreach ([
					'notify_admin_new_order' => 'shop_notify_new_order',
					'notify_admin_claim' => 'shop_notify_claim',
					'notify_buyer_received' => 'shop_notify_received',
					'notify_buyer_paid' => 'shop_notify_paid',
					'notify_buyer_shipping' => 'shop_notify_shipping',
					'notify_buyer_delivered' => 'shop_notify_delivered',
					'notify_buyer_claim_done' => 'shop_notify_claim_done',
				] as $nk => $nlabel)
				<div>
					<label>{{ lang('commerce.' . $nlabel) }}</label>
					<select name="{{ $nk }}">
						<option value="Y" @if(($shop_config->$nk ?? 'Y') !== 'N') selected @endif>{{ lang('commerce.shop_use_on') }}</option>
						<option value="N" @if(($shop_config->$nk ?? 'Y') === 'N') selected @endif>{{ lang('commerce.shop_use_off') }}</option>
					</select>
				</div>
				@endforeach
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_59') }}</h3>
			<p class="rsva-hint">{{ lang('commerce.admin_config_60') }}</p>
			<div class="rsva-form-grid">
				<div><label>{{ lang('commerce.admin_config_61') }}</label><div class="zlf-row-wrap"><input type="text" name="biz_name" maxlength="100" value="{{ $shop_config->biz_name }}" />@include('_langfield', ['lf_name' => 'biz_name', 'lf_value' => $shop_config->biz_name_raw ?? $shop_config->biz_name, 'lf_key' => 'cfgbizname'])</div></div>
				<div><label>{{ lang('commerce.admin_config_62') }}</label><input type="text" name="biz_ceo" maxlength="60" value="{{ $shop_config->biz_ceo }}" /></div>
				<div><label>{{ lang('commerce.admin_config_63') }}</label><input type="text" name="biz_number" maxlength="40" value="{{ $shop_config->biz_number }}" /></div>
				<div><label>{{ lang('commerce.admin_config_64') }}</label><input type="text" name="biz_tel" maxlength="40" value="{{ $shop_config->biz_tel }}" /></div>
				<div style="grid-column:1/-1"><label>{{ lang('commerce.admin_config_65') }}</label><div class="zlf-row-wrap"><input type="text" name="biz_address" maxlength="250" value="{{ $shop_config->biz_address }}" />@include('_langfield', ['lf_name' => 'biz_address', 'lf_value' => $shop_config->biz_address_raw ?? $shop_config->biz_address, 'lf_key' => 'cfgbizaddr'])</div></div>
				<div style="grid-column:1/-1"><label>{{ lang('commerce.admin_config_66') }}</label><div class="zlf-row-wrap"><textarea name="biz_note" rows="2">{{ $shop_config->biz_note }}</textarea>@include('_langfield', ['lf_name' => 'biz_note', 'lf_value' => $shop_config->biz_note_raw ?? $shop_config->biz_note, 'lf_key' => 'cfgbiznote'])</div></div>
				<div style="grid-column:1/-1">
					<label>{{ lang('commerce.cfg_biz_logo') }}</label>
					<p class="zmc-hint">{{ lang('commerce.cfg_biz_logo_note') }}</p>
					<div class="zmc-logo-row">
						<span class="zmc-logo-thumb @if (empty($shop_config->biz_logo)) is-empty @endif" id="zmcLogoThumb" @if (!empty($shop_config->biz_logo)) style="background-image:url('{{ $shop_config->biz_logo }}')" @endif></span>
						<span class="zmc-logo-acts">
							<input type="file" id="zmcLogoFile" accept="image/*" hidden />
							<button type="button" class="rsva-btn rsva-btn-sm" id="zmcLogoPick">{{ lang('commerce.cfg_biz_logo_pick') }}</button>
							<button type="button" class="rsva-btn rsva-btn-sm" id="zmcLogoClear">{{ lang('commerce.admin_item_edit_170') }}</button>
						</span>
					</div>
					<input type="hidden" name="biz_logo" id="zmcLogoUrl" value="{{ $shop_config->biz_logo ?? '' }}" />
				</div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>{{ lang('commerce.admin_config_67') }}</h3>
			<p class="rsva-hint">{{ lang('commerce.admin_config_68') }}</p>
			<div class="rsva-form-grid">
				<div>
					<label>{{ lang('commerce.admin_config_69') }}</label>
					<select name="biz_tax_mode">
						<option value="taxable" @if($shop_config->biz_tax_mode === 'taxable') selected @endif>{{ lang('commerce.admin_config_70') }}</option>
						<option value="exempt" @if($shop_config->biz_tax_mode === 'exempt') selected @endif>{{ lang('commerce.admin_config_71') }}</option>
						<option value="simplified" @if($shop_config->biz_tax_mode === 'simplified') selected @endif>{{ lang('commerce.admin_config_72') }}</option>
					</select>
				</div>
				<div><label>{{ lang('commerce.admin_config_73') }}</label><input type="number" name="vat_rate" min="0" max="100" step="1" value="{{ (int)$shop_config->vat_rate }}" /></div>
				<div>
					<label>{{ lang('commerce.admin_config_74') }}</label>
					<select name="price_includes_tax">
						<option value="Y" @if($shop_config->price_includes_tax !== 'N') selected @endif>{{ lang('commerce.admin_config_75') }}</option>
						<option value="N" @if($shop_config->price_includes_tax === 'N') selected @endif>{{ lang('commerce.admin_config_76') }}</option>
					</select>
				</div>
				<div>
					<label>{{ lang('commerce.shop_base_country') }}</label>
					<select name="base_country">
						@foreach (\Zittme\Modules\Commerce\Models\Address::countries() as $bc_code => $bc_name)
						<option value="{{ $bc_code }}" @if(($shop_config->base_country ?? 'KR') === $bc_code) selected @endif>{{ $bc_name }}</option>
						@endforeach
					</select>
					<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.shop_base_country_help') }}</p>
				</div>
				<div>
					<label>{{ lang('commerce.admin_config_77') }}</label>
					<select name="allow_overseas">
						<option value="N" @if($shop_config->allow_overseas !== 'Y') selected @endif>{{ lang('commerce.admin_config_78') }}</option>
						<option value="Y" @if($shop_config->allow_overseas === 'Y') selected @endif>{{ lang('commerce.admin_config_79') }}</option>
					</select>
				</div>
				<div>
					<label>{{ lang('commerce.shop_use_phone_cc') }}</label>
					<select name="use_phone_cc">
						<option value="auto" @if(($shop_config->use_phone_cc ?? 'auto') === 'auto') selected @endif>{{ lang('commerce.shop_use_phone_cc_auto') }}</option>
						<option value="Y" @if(($shop_config->use_phone_cc ?? 'auto') === 'Y') selected @endif>{{ lang('commerce.shop_use_phone_cc_on') }}</option>
						<option value="N" @if(($shop_config->use_phone_cc ?? 'auto') === 'N') selected @endif>{{ lang('commerce.shop_use_phone_cc_off') }}</option>
					</select>
				</div>
				<div>
					<label>{{ lang('commerce.shop_require_state') }}</label>
					<select name="require_state">
						<option value="N" @if(($shop_config->require_state ?? 'N') !== 'Y') selected @endif>{{ lang('commerce.shop_require_state_off') }}</option>
						<option value="Y" @if(($shop_config->require_state ?? 'N') === 'Y') selected @endif>{{ lang('commerce.shop_require_state_on') }}</option>
					</select>
					<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.shop_require_state_help') }}</p>
				</div>
				<div>
					<label>{{ lang('commerce.shop_auto_confirm') }}</label>
					<input type="number" name="auto_confirm_days" min="0" max="365" step="1" value="{{ (int)($shop_config->auto_confirm_days ?? 0) }}" style="width:110px" />
					<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.shop_auto_confirm_help') }}</p>
					<p style="margin:4px 0 8px;font-size:12.5px;color:#c0392b">{{ lang('commerce.shop_auto_confirm_warn') }}</p>
				</div>
				<div>
					<label>{{ lang('commerce.shop_use_coupon') }}</label>
					<select name="use_coupon">
						<option value="Y" @if(($shop_config->use_coupon ?? 'Y') !== 'N') selected @endif>{{ lang('commerce.shop_use_on') }}</option>
						<option value="N" @if(($shop_config->use_coupon ?? 'Y') === 'N') selected @endif>{{ lang('commerce.shop_use_off') }}</option>
					</select>
				</div>
				<div>
					<label>{{ lang('commerce.shop_use_credit') }}</label>
					<select name="use_credit">
						<option value="Y" @if(($shop_config->use_credit ?? 'Y') !== 'N') selected @endif>{{ lang('commerce.shop_use_on') }}</option>
						<option value="N" @if(($shop_config->use_credit ?? 'Y') === 'N') selected @endif>{{ lang('commerce.shop_use_off') }}</option>
					</select>
					<p style="margin:4px 0 8px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.shop_use_credit_help') }}</p>
				</div>
				<div>
					<label>{{ lang('commerce.shop_address_mode') }}</label>
					<select name="address_mode">
						<option value="kr" @if(($shop_config->address_mode ?? 'kr') === 'kr') selected @endif>{{ lang('commerce.shop_address_mode_kr') }}</option>
						<option value="intl" @if(($shop_config->address_mode ?? 'kr') === 'intl') selected @endif>{{ lang('commerce.shop_address_mode_intl') }}</option>
						<option value="both" @if(($shop_config->address_mode ?? 'kr') === 'both') selected @endif>{{ lang('commerce.shop_address_mode_both') }}</option>
					</select>
				</div>
				<div>
					<label>{{ lang('commerce.shop_currencies') }}</label>
					{{-- 통화 선택은 짓미페이 기본 설정 한 곳에서만 한다 --}}
					@php
					// 출력식 안에서 HTML 을 조립하면 템플릿이 style 속성을 코드로 읽는다. 여기서 만든다
					$cfg_pay_link = '<a href="' . escape(getUrl('', 'module', 'admin', 'act', 'dispZittme_payAdminConfig')) . '" class="zmc-pay-link">' . escape(lang('commerce.cfg_pay_settings')) . '</a>';
					$cfg_currency_note = sprintf(escape(lang('commerce.cfg_currency_from')), $cfg_pay_link);
					@endphp
					<div style="padding:8px 0;color:#6b7684;font-size:13px">
						{{ implode(', ', \Zittme\Modules\Commerce\Models\Money::currencies()) }}
						- {!! $cfg_currency_note !!}
					</div>
				</div>
				<div>
					<label>{{ lang('commerce.shop_currency_fallback') }}</label>
					<select name="currency_fallback">
						<option value="convert" @if(($shop_config->currency_fallback ?? 'convert') === 'convert') selected @endif>{{ lang('commerce.shop_currency_fallback_convert') }}</option>
						<option value="none" @if(($shop_config->currency_fallback ?? '') === 'none') selected @endif>{{ lang('commerce.shop_currency_fallback_none') }}</option>
					</select>
				</div>
			</div>
			<p class="rsva-hint">{{ lang('commerce.about_shop_address_mode') }}</p>
			<p class="rsva-hint">{{ lang('commerce.about_shop_currencies') }}</p>
			<p class="rsva-hint">{{ lang('commerce.admin_config_80') }}</p>
		</div>

		<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_config_81') }}</button>
	</form>

	<div class="rsva-panel" style="margin-top:18px">
		<h3>{{ lang('commerce.admin_config_82') }}</h3>
		@if ($shop_instance)
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminUpdateSkin" />
			<div style="min-width:220px">
				<label>{{ lang('commerce.admin_config_83') }}</label>
				<select name="skin" style="width:100%">
					<option value="/USE_DEFAULT/" @if(($shop_instance->skin ?? '') === '/USE_DEFAULT/') selected @endif>{{ lang('commerce.admin_config_84') }}</option>
					@foreach ($shop_skins as $sk)
					<option value="{{ $sk->skin }}" @if(($shop_instance->skin ?? '') === $sk->skin) selected @endif>{{ $sk->title ?: $sk->skin }}</option>
					@endforeach
				</select>
			</div>
			<div style="min-width:220px">
				<label>{{ lang('commerce.admin_config_85') }}</label>
				<select name="mskin" style="width:100%">
					<option value="/USE_DEFAULT/" @if(($shop_instance->mskin ?? '') === '/USE_DEFAULT/') selected @endif>{{ lang('commerce.admin_config_86') }}</option>
					@foreach ($shop_mskins as $sk)
					<option value="{{ $sk->skin }}" @if(($shop_instance->mskin ?? '') === $sk->skin) selected @endif>{{ $sk->title ?: $sk->skin }}</option>
					@endforeach
				</select>
			</div>
			<div style="min-width:220px">
				<label>{{ lang('commerce.admin_config_layout') }}</label>
				<select name="layout_srl" style="width:100%">
					<option value="-1" @if((int)($shop_instance->layout_srl ?? -1) === -1) selected @endif>{{ lang('commerce.admin_config_layout_default') }}</option>
					@foreach ($shop_layouts as $lo)
					<option value="{{ $lo->layout_srl }}" @if((int)($shop_instance->layout_srl ?? -1) === (int)$lo->layout_srl) selected @endif>{{ $lo->title ?: $lo->layout }}</option>
					@endforeach
				</select>
			</div>
			<div style="min-width:220px">
				<label>{{ lang('commerce.admin_config_mlayout') }}</label>
				<select name="mlayout_srl" style="width:100%">
					<option value="-1" @if((int)($shop_instance->mlayout_srl ?? -1) === -1) selected @endif>{{ lang('commerce.admin_config_layout_default') }}</option>
					<option value="-2" @if((int)($shop_instance->mlayout_srl ?? -1) === -2) selected @endif>{{ lang('commerce.admin_config_layout_follow_pc') }}</option>
					@foreach ($shop_mlayouts as $lo)
					<option value="{{ $lo->layout_srl }}" @if((int)($shop_instance->mlayout_srl ?? -1) === (int)$lo->layout_srl) selected @endif>{{ $lo->title ?: $lo->layout }}</option>
					@endforeach
				</select>
			</div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_config_87') }}</button></div>
		</form>
		<small style="display:block;margin-top:10px;color:#6b7684;font-size:12px">{{ lang('commerce.admin_config_88') }}</small>
		@else
		<p class="rsva-empty">{{ lang('commerce.admin_config_89') }}</p>
		@endif
	</div>

	<script>
	(function () {
		// 홈 배너 편집: 행 단위 입력을 hidden JSON 으로 직렬화해서 저장한다
		var rowsEl = document.getElementById('zmcBannerRows');
		var jsonEl = document.getElementById('zmcBannersJson');
		var addBtn = document.getElementById('zmcBannerAdd');
		if (!rowsEl || !jsonEl) return;

		// 배너 이미지: 주소를 직접 적는 대신 파일을 골라 올린다 (프론트 편집 패널과 같은 방식)
		function setBannerImage(row, key, url, touched) {
			var cell = row.querySelector('[data-img=' + key + ']');
			row.querySelector('[data-k=' + key + ']').value = url;
			var thumb = cell.querySelector('[data-thumb]');
			cell.querySelector('[data-imgdel]').hidden = !url;
			cell.querySelector('[data-pick]').textContent = url ? {!! json_encode(lang('commerce.cfg_change')) !!} : {!! json_encode(lang('commerce.cfg_pick')) !!};
			thumb.style.backgroundImage = url ? 'url("' + url + '")' : '';
			thumb.classList.toggle('is-empty', !url);
			// 배경 이미지를 넣었는데 배경 종류가 그라디언트·단색이면 화면에 안 보인다 — 함께 맞춘다
			if (touched && key === 'image' && row.zmcExtra) {
				var sel = row.querySelector('[data-k=bg_type]');
				if (url) { if (sel) sel.value = 'image'; }
				else if (sel && sel.value === 'image') { sel.value = 'gradient'; }
			}
		}

		function bindBannerImage(row, onChange) {
			row.querySelectorAll('[data-img]').forEach(function (cell) {
				var key = cell.getAttribute('data-img');
				var fileEl = cell.querySelector('[data-file]');
				var pickBtn = cell.querySelector('[data-pick]');
				pickBtn.addEventListener('click', function () { fileEl.click(); });
				cell.querySelector('[data-imgdel]').addEventListener('click', function () { setBannerImage(row, key, '', true); if (onChange) onChange(); });
				fileEl.addEventListener('change', function () {
					var file = fileEl.files && fileEl.files[0];
					if (!file) return;
					var label = pickBtn.textContent;
					pickBtn.textContent = {!! json_encode(lang('commerce.admin_item_edit_180')) !!};
					pickBtn.disabled = true;

					var fd = new FormData();
					fd.append('file', file);
					fetch('./?module=commerce&act=procCommerceAdminUploadBanner', { method: 'POST', body: fd, credentials: 'same-origin' })
						.then(function (res) { return res.json(); })
						.then(function (ret) {
							if (ret && !ret.error && ret.url) {
								setBannerImage(row, key, ret.url, true);
								if (onChange) onChange();
							} else {
								alert((ret && ret.message) || {!! json_encode(lang('commerce.msg_shop_upload_failed')) !!});
								pickBtn.textContent = label;
							}
						})
						.catch(function () {
							alert({!! json_encode(lang('commerce.msg_shop_upload_failed')) !!});
							pickBtn.textContent = label;
						})
						.then(function () {
							pickBtn.disabled = false;
							fileEl.value = '';
						});
				});
			});
		}

		// 저장값이 '$user_lang->코드' 면 코드를 물리고, 칸에는 실제 문구를 보여준다
		var LANG_PREFIX = '$user_lang->';
		function setLangValue(row, key, value) {
			var input = row.querySelector('[data-k=' + key + ']');
			var hidden = row.querySelector('[data-k=' + key + '_code]');
			var button = hidden.nextElementSibling;
			if (value.indexOf(LANG_PREFIX) !== 0) {
				input.value = value;
				return;
			}
			var code = value.substring(LANG_PREFIX.length);
			hidden.value = code;
			input.value = code;
			input.readOnly = true;
			button.classList.add('is-on');
			exec_json('commerce.procCommerceAdminGetLangCode', { code: code }, function (ret) {
				var values = ret.values || {};
				for (var k in values) { if (values[k]) { input.value = values[k]; break; } }
			});
		}

		function addRow(data) {
			data = data || {};
			var row = document.createElement('div');
			row.className = 'zmc-banner-card';
			var globe = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.3 2.6 2.3 14.4 0 17"/><path d="M12 3.5c-2.3 2.6-2.3 14.4 0 17"/></svg>';
			function imgCell(key, label) {
				return '<div class="zmc-banner-img" data-img="' + key + '">' +
					'<span class="zmc-banner-thumb" data-thumb></span>' +
					'<div class="zmc-banner-imgacts">' +
						'<span class="zmc-banner-imglabel">' + label + '</span>' +
						'<span><button type="button" class="rsva-btn rsva-btn-sm" data-pick>' + {!! json_encode(lang('commerce.cfg_pick')) !!} + '</button> ' +
						'<button type="button" class="rsva-btn rsva-btn-sm" data-imgdel hidden>' + {!! json_encode(lang('commerce.cfg_clear')) !!} + '</button></span>' +
					'</div>' +
					'<input type="file" accept="image/*" data-file hidden />' +
					'<input type="hidden" data-k="' + key + '" />' +
				'</div>';
			}
			// 제목·문구는 다국어 문구를 연결할 수 있다. 값 자체가 코어 규약이면 버튼이 그걸 물고 있는다.
			function langCell(key, label) {
				return '<div class="zmc-f"><label>' + label + '</label><span class="zlf-row-wrap">' +
					'<input type="text" data-k="' + key + '" />' +
					'<input type="hidden" data-lf-code data-k="' + key + '_code" />' +
					'<button type="button" class="zlf-btn" data-lf-open title="' + {!! json_encode(lang('commerce.adm_lang_link')) !!} + '">' + globe + '</button>' +
					'</span></div>';
			}
			row.innerHTML =
				'<div class="zmc-banner-imgs">' + imgCell('image', {!! json_encode(lang('commerce.cfg_bg_image_label')) !!}) + imgCell('point_image', {!! json_encode(lang('commerce.cfg_point_image_label')) !!}) + '</div>' +
				'<div class="zmc-banner-fields">' +
					'<div class="zmc-f"><label>' + {!! json_encode(lang('commerce.cfg_bg')) !!} + '</label><select data-k="bg_type">' +
						'<option value="gradient">' + {!! json_encode(lang('commerce.cfg_bg_gradient')) !!} + '</option><option value="color">' + {!! json_encode(lang('commerce.cfg_bg_solid')) !!} + '</option><option value="image">' + {!! json_encode(lang('commerce.cfg_bg_image')) !!} + '</option>' +
					'</select></div>' +
					'<div class="zmc-f zmc-bgcolors"><label>' + {!! json_encode(lang('commerce.cfg_bg_color')) !!} + '</label><span><input type="color" data-k="bg_color" /> <input type="color" data-k="bg_color2" /></span></div>' +
					'<div class="zmc-f"><label>' + {!! json_encode(lang('commerce.cfg_text_color')) !!} + '</label><span><input type="color" data-k="text_color" /> <label class="zmc-inline"><input type="checkbox" data-k="shadow" /> ' + {!! json_encode(lang('commerce.cfg_shadow')) !!} + '</label></span></div>' +
					langCell('title', {!! json_encode(lang('commerce.cfg_banner_title')) !!}) +
					langCell('text', {!! json_encode(lang('commerce.cfg_banner_text')) !!}) +
					'<div class="zmc-f"><label>' + {!! json_encode(lang('commerce.cfg_link_url')) !!} + '</label><input type="text" data-k="url" placeholder="' + {!! json_encode(lang('commerce.cfg_link_ph')) !!} + '" /></div>' +
				'</div>' +
				'<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger zmc-banner-del" data-del="1">' + {!! json_encode(lang('commerce.admin_item_edit_170')) !!} + '</button>';

			row.zmcExtra = data;
			row.querySelector('[data-k=url]').value = data.url || '';
			row.querySelector('[data-k=bg_type]').value = data.bg_type || (data.image ? 'image' : 'gradient');
			row.querySelector('[data-k=bg_color]').value = data.bg_color || '#1a1f2e';
			row.querySelector('[data-k=bg_color2]').value = data.bg_color2 || '#0d1019';
			row.querySelector('[data-k=text_color]').value = data.text_color || '#ffffff';
			row.querySelector('[data-k=shadow]').checked = (data.shadow || 'Y') !== 'N';
			setBannerImage(row, 'image', data.image || '', false);
			setBannerImage(row, 'point_image', data.point_image || '', false);
			setLangValue(row, 'title', data.title || '');
			setLangValue(row, 'text', data.text || '');

			function refreshBg() {
				var type = row.querySelector('[data-k=bg_type]').value;
				row.querySelector('.zmc-bgcolors').style.display = type === 'image' ? 'none' : '';
				row.querySelector('[data-img=image]').style.display = type === 'image' ? '' : 'none';
			}
			row.querySelector('[data-k=bg_type]').addEventListener('change', refreshBg);
			refreshBg();

			row.querySelector('[data-del]').addEventListener('click', function () { row.remove(); });
			rowsEl.appendChild(row);
			bindBannerImage(row, refreshBg);
			// 버튼을 공용 다국어 패널에 잇는다 (실패해도 나머지 행은 그려져야 한다)
			try {
				if (window.zlfBind) {
					row.querySelectorAll('[data-lf-open]').forEach(window.zlfBind);
				}
			} catch (e) {}
		}

		var initial = [];
		try { initial = JSON.parse(jsonEl.value) || []; } catch (e) {}
		initial.forEach(addRow);
		if (addBtn) addBtn.addEventListener('click', function () { addRow(); });

		// 지역 추가 배송비 편집 (배너와 같은 방식)
		var zoneRows = document.getElementById('zmcZoneRows');
		var zonesJson = document.getElementById('zmcZonesJson');
		var zoneAdd = document.getElementById('zmcZoneAdd');
		// 국가와 시·도는 목록에서 고른다. 손으로 적게 하면 구매자가 고른 값과 어긋난다
		var zoneCountries = {!! $zmc_country_json !!};
		var zoneRegionData = {!! $zmc_region_json !!};
		function zoneOptions(map, selected, placeholder) {
			var html = '<option value="">' + placeholder + '</option>';
			for (var code in map) {
				html += '<option value="' + code + '"' + (code === selected ? ' selected' : '') + '>' + map[code] + '</option>';
			}
			return html;
		}
		function addZone(data) {
			data = data || {};
			var country = String(data.country || 'KR').toUpperCase();
			var region = String(data.region || data.regions || '');
			var row = document.createElement('div');
			row.className = 'zmc-zone-row';
			row.innerHTML =
				'<select data-k="country" class="zmc-zone-country">' + zoneOptions(zoneCountries, country, {!! json_encode(lang('commerce.cfg_zone_country_ph')) !!}) + '</select>' +
				'<span class="zmc-pick zmc-zone-region"><input type="text" data-k="region-text" placeholder="' + {!! json_encode(lang('commerce.cfg_zone_region_ph')) !!} + '" /><input type="hidden" data-k="region" data-pick-value="1" /></span>' +
				'<input type="text" data-k="zips" class="zmc-zone-zips" placeholder="' + {!! json_encode(lang('commerce.cfg_zone_zips_ph')) !!} + '" />' +
				'<input type="number" data-k="fee" placeholder="' + {!! json_encode(lang('commerce.cfg_zone_fee_ph')) !!} + '" min="0" step="0.01" style="width:120px" />' +
				'<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger" data-del="1">' + {!! json_encode(lang('commerce.admin_item_edit_170')) !!} + '</button>';
			row.querySelector('[data-k=zips]').value = country === 'KR' ? (data.zips || '') : '';
			row.querySelector('[data-k=fee]').value = data.fee || '';

						var countryEl = row.querySelector('[data-k=country]');
			var regionWrap = row.querySelector('.zmc-zone-region');
			var regionText = row.querySelector('[data-k=region-text]');
			var regionEl = row.querySelector('[data-k=region]');
			var zipsEl = row.querySelector('[data-k=zips]');

			var regionPick = window.zmcPickBox ? zmcPickBox(regionText, {
				items: [],
				empty: {!! json_encode(lang('commerce.cfg_zone_search_empty')) !!}
			}) : null;

			function syncScope(keep) {
				var cc = String(countryEl.value || '').toUpperCase();
				var list = zoneRegionData[cc] || [];
								regionWrap.hidden = !list.length;
				zipsEl.hidden = (cc !== 'KR');
				if (regionPick) { regionPick.setItems(list); }
				if (!list.length) { regionEl.value = ''; }
				if (cc !== 'KR') { zipsEl.value = ''; }
				if (keep && regionPick) { regionPick.setValue(keep); }
			}
			countryEl.addEventListener('change', function () { syncScope(''); });
			syncScope(region);

			row.querySelector('[data-del]').addEventListener('click', function () { row.remove(); });

			// 구매 금액 구간. 기준액을 넘긴 구간 중 가장 높은 것이 적용된다
			var tierWrap = document.createElement('div');
			tierWrap.className = 'zmc-tier-wrap';
			var tierList = document.createElement('div');
			tierList.className = 'zmc-tier-list';
			var tierAdd = document.createElement('button');
			tierAdd.type = 'button';
			tierAdd.className = 'rsva-btn rsva-btn-sm';
			tierAdd.textContent = {!! json_encode(lang('commerce.cfg_zone_tier_add')) !!};
			function addTier(tier) {
				tier = tier || {};
				var t = document.createElement('div');
				t.className = 'zmc-tier';
				t.innerHTML =
					'<input type="number" data-t="from" min="0" step="0.01" placeholder="' + {!! json_encode(lang('commerce.cfg_zone_tier_from')) !!} + '" />' +
					'<span class="zmc-tier-arrow">&rarr;</span>' +
					'<input type="number" data-t="fee" min="0" step="0.01" placeholder="' + {!! json_encode(lang('commerce.cfg_zone_tier_fee')) !!} + '" />' +
					'<button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger" data-tdel="1">' + {!! json_encode(lang('commerce.admin_item_edit_170')) !!} + '</button>';
				t.querySelector('[data-t=from]').value = tier.from || '';
				t.querySelector('[data-t=fee]').value = tier.fee || '';
				t.querySelector('[data-tdel]').addEventListener('click', function () { t.remove(); });
				tierList.appendChild(t);
			}
			(data.tiers || []).forEach(addTier);
			tierAdd.addEventListener('click', function () { addTier(); });
			tierWrap.appendChild(tierList);
			tierWrap.appendChild(tierAdd);
			row.appendChild(tierWrap);

			zoneRows.appendChild(row);
		}
		if (zoneRows && zonesJson) {
			var zoneInitial = [];
			try { zoneInitial = JSON.parse(zonesJson.value) || []; } catch (e) {}
			zoneInitial.forEach(addZone);
			if (zoneAdd) zoneAdd.addEventListener('click', function () { addZone(); });
		}

		var form = jsonEl.closest('form');
		if (form) {
			form.addEventListener('submit', function () {
				var out = [];
				rowsEl.querySelectorAll('.zmc-banner-card').forEach(function (row) {
					var item = {};
					Object.keys(row.zmcExtra || {}).forEach(function (k) { item[k] = row.zmcExtra[k]; });
					item.image = row.querySelector('[data-k=image]').value.trim();
					item.point_image = row.querySelector('[data-k=point_image]').value.trim();
					item.url = row.querySelector('[data-k=url]').value.trim();
					item.bg_type = row.querySelector('[data-k=bg_type]').value;
					item.bg_color = row.querySelector('[data-k=bg_color]').value;
					item.bg_color2 = row.querySelector('[data-k=bg_color2]').value;
					item.text_color = row.querySelector('[data-k=text_color]').value;
					item.shadow = row.querySelector('[data-k=shadow]').checked ? 'Y' : 'N';
					delete item.bg_type_before;
					// 다국어를 연결했으면 코어 규약값으로, 아니면 입력한 글자 그대로
					['title', 'text'].forEach(function (key) {
						var code = row.querySelector('[data-k=' + key + '_code]').value.trim();
						item[key] = code ? (LANG_PREFIX + code) : row.querySelector('[data-k=' + key + ']').value.trim();
					});
					if (item.image || item.title || item.text || item.point_image) out.push(item);
				});
				jsonEl.value = JSON.stringify(out);

				if (zoneRows && zonesJson) {
					var zones = [];
					zoneRows.querySelectorAll('.zmc-zone-row').forEach(function (row) {
						var zoneCountry = row.querySelector('[data-k=country]').value.trim().toUpperCase();
						var isKR = zoneCountry === 'KR';
						var zone = {
														country: zoneCountry,
							region: row.querySelector('[data-k=region]').value.trim(),
							zips: isKR ? row.querySelector('[data-k=zips]').value.trim() : '',
							fee: row.querySelector('[data-k=fee]').value.trim(),
							tiers: []
						};
						row.querySelectorAll('.zmc-tier').forEach(function (t) {
							var from = t.querySelector('[data-t=from]').value.trim();
							var tfee = t.querySelector('[data-t=fee]').value.trim();
							if (from !== '' && tfee !== '') zone.tiers.push({ from: from, fee: tfee });
						});
						// 국내는 시·도나 우편번호 중 하나는 있어야 하고, 해외는 국가만으로 성립한다
						var hasRule = isKR ? (zone.region || zone.zips) : !!zoneCountry;
												var hasFee = parseFloat(zone.fee) > 0 || zone.tiers.length > 0;
						if (hasRule && hasFee) zones.push(zone);
					});
					zonesJson.value = JSON.stringify(zones);
				}
			});
		}
	})();
	</script>
	<script>
	// 거래명세서 로고 — 배너와 같은 업로드 통로를 쓴다
	(function () {
		var pick = document.getElementById('zmcLogoPick');
		var file = document.getElementById('zmcLogoFile');
		var thumb = document.getElementById('zmcLogoThumb');
		var url = document.getElementById('zmcLogoUrl');
		if (!pick || !file || !thumb || !url) return;

		function apply(value) {
			url.value = value || '';
			thumb.style.backgroundImage = value ? "url('" + value + "')" : '';
			thumb.classList.toggle('is-empty', !value);
		}
		pick.addEventListener('click', function () { file.click(); });
		document.getElementById('zmcLogoClear').addEventListener('click', function () { apply(''); });
		file.addEventListener('change', function () {
			var f = file.files && file.files[0];
			if (!f) return;
			var label = pick.textContent;
			pick.textContent = {!! json_encode(lang('commerce.admin_item_edit_180')) !!};
			pick.disabled = true;
			var fd = new FormData();
			fd.append('file', f);
			fetch('./?module=commerce&act=procCommerceAdminUploadBanner', { method: 'POST', body: fd, credentials: 'same-origin' })
				.then(function (res) { return res.json(); })
				.then(function (ret) {
					if (ret && !ret.error && ret.url) { apply(ret.url); }
					else { alert((ret && ret.message) || 'error'); }
				})
				.catch(function () { alert('error'); })
				.then(function () { pick.textContent = label; pick.disabled = false; file.value = ''; });
		});
	})();
	</script>
</div>