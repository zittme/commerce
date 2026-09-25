@php
	$lf_code = Zittme\Modules\Commerce\Models\Lang::codeOf($lf_value ?? '');
	$lf_display = $lf_code !== '' ? Zittme\Modules\Commerce\Models\Lang::display($lf_code) : '';
@endphp
<input type="hidden" name="{{ $lf_name }}_langcode" value="{{ $lf_code }}" data-lf-code @if(!empty($lf_form)) form="{{ $lf_form }}" @endif />
<button type="button" class="zlf-btn" data-lf-open data-lf-display="{{ $lf_display }}" title="{{ lang('commerce.adm_lang_link') }}">
	<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M3.5 12h17"/><path d="M12 3.5c2.3 2.6 2.3 14.4 0 17"/><path d="M12 3.5c-2.3 2.6-2.3 14.4 0 17"/></svg>
</button>
