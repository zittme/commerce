@php
$af_cfg = Zittme\Modules\Commerce\Models\ApplyForm::config();
$af_sections = [];
foreach (Zittme\Modules\Commerce\Models\ApplyForm::SECTIONS as $af_s) { $af_sections[$af_s] = Zittme\Modules\Commerce\Models\ApplyForm::sectionLabel($af_s); }
$af_types = [];
foreach (Zittme\Modules\Commerce\Models\ApplyForm::TYPES as $af_t) { $af_types[$af_t] = lang('commerce.af_type_' . $af_t); }
$af_formats = ['' => lang('commerce.af_fmt_none')];
foreach (['clabe', 'swift', 'iban'] as $af_f) { $af_formats[$af_f] = lang('commerce.af_fmt_' . $af_f); }
$af_presets = [];
foreach (Zittme\Modules\Commerce\Models\ApplyForm::PRESETS as $af_pk => $af_pv) { $af_presets[$af_pk] = $af_pv + ['label' => lang('commerce.af_preset_' . $af_pk), 'key' => $af_pk]; }
@endphp
<style>
.af-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.af-table th { padding: 8px 10px; border-bottom: 1px solid var(--zmc-line, #e6e3dc); font-size: 12.5px; font-weight: 700; color: var(--zmc-sub, #7a7f8c); text-align: left; white-space: nowrap; }
.af-table td { padding: 8px 10px; border-bottom: 1px solid var(--zmc-line, #e6e3dc); vertical-align: middle; }
.af-table tr:last-child td { border-bottom: 0; }
.af-table td.af-c { text-align: center; width: 64px; }
.af-table input[type="text"], .af-table select { width: 100%; box-sizing: border-box; }
.af-table .af-sub { display: block; margin-top: 2px; font-size: 12px; color: var(--zmc-sub, #7a7f8c); }
.af-wrap { overflow-x: auto; }
.af-custom td { min-width: 110px; }
.af-custom td.af-del { min-width: 0; width: 1%; }
.af-tools { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
.af-empty { padding: 14px 10px; font-size: 13px; color: var(--zmc-sub, #7a7f8c); }
.cfg-page .af-h4 { margin: 22px 0 8px; font-size: 14px; font-weight: 700; }
</style>
<div class="rsva-panel">
	<h3>{{ lang('commerce.af_title') }}</h3>
	<p style="margin:-6px 0 14px;font-size:13px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.af_desc') }}</p>
	<input type="hidden" name="seller_form" value="1" />
	<div class="af-wrap">
		<table class="af-table">
			<thead><tr><th>{{ lang('commerce.af_col_field') }}</th><th>{{ lang('commerce.af_col_section') }}</th><th class="af-c">{{ lang('commerce.af_col_on') }}</th><th class="af-c">{{ lang('commerce.af_col_req') }}</th><th>{{ lang('commerce.af_col_label') }}</th></tr></thead>
			<tbody>
				@foreach ($af_cfg['builtin'] as $af_key => $af_b)
				<tr>
					<td><b>{{ $af_key === 'shop_id' ? lang('commerce.sc_shop_id') : lang('commerce.mk_f_' . $af_key) }}</b>@if ($af_b['locked'])<span class="af-sub">{{ lang('commerce.af_locked') }}</span>@endif</td>
					<td>{{ $af_sections[$af_b['section']] }}</td>
					<td class="af-c"><input type="hidden" name="af_on[{{ $af_key }}]" value="{{ $af_b['locked'] ? 'Y' : 'N' }}" /><input type="checkbox" name="af_on[{{ $af_key }}]" value="Y" @if ($af_b['on']) checked @endif @if ($af_b['locked']) disabled @endif aria-label="{{ lang('commerce.af_col_on') }}" /></td>
					<td class="af-c"><input type="hidden" name="af_req[{{ $af_key }}]" value="{{ $af_b['locked'] ? 'Y' : 'N' }}" /><input type="checkbox" name="af_req[{{ $af_key }}]" value="Y" @if ($af_b['req']) checked @endif @if ($af_b['locked']) disabled @endif aria-label="{{ lang('commerce.af_col_req') }}" /></td>
					<td><input type="text" name="af_label[{{ $af_key }}]" maxlength="60" value="{{ $af_b['label'] }}" placeholder="{{ $af_key === 'shop_id' ? lang('commerce.sc_shop_id') : lang('commerce.mk_f_' . $af_key) }}" /></td>
				</tr>
				@endforeach
			</tbody>
		</table>
	</div>

	<h4 class="af-h4">{{ lang('commerce.af_custom_title') }}</h4>
	<p style="margin:0 0 10px;font-size:13px;color:var(--zmc-sub, #7a7f8c)">{{ lang('commerce.af_custom_desc') }}</p>
	<div class="af-wrap">
		<table class="af-table af-custom">
			<thead><tr><th>{{ lang('commerce.af_col_label') }}</th><th>{{ lang('commerce.af_col_type') }}</th><th>{{ lang('commerce.af_col_section') }}</th><th>{{ lang('commerce.af_col_format') }}</th><th>{{ lang('commerce.af_col_options') }}</th><th class="af-c">{{ lang('commerce.af_col_req') }}</th><th class="af-del"></th></tr></thead>
			<tbody id="afRows">
				@foreach ($af_cfg['custom'] as $af_c)
				<tr class="af-row">
					<td><input type="hidden" name="xc_key[]" value="{{ $af_c['key'] }}" /><input type="text" name="xc_label[]" maxlength="60" value="{{ $af_c['label'] }}" required /></td>
					<td><select name="xc_type[]">@foreach ($af_types as $af_tk => $af_tl)<option value="{{ $af_tk }}" @if ($af_c['type'] === $af_tk) selected @endif>{{ $af_tl }}</option>@endforeach</select></td>
					<td><select name="xc_section[]">@foreach ($af_sections as $af_sk => $af_sl)<option value="{{ $af_sk }}" @if ($af_c['section'] === $af_sk) selected @endif>{{ $af_sl }}</option>@endforeach</select></td>
					<td><select name="xc_format[]">@foreach ($af_formats as $af_fk => $af_fl)<option value="{{ $af_fk }}" @if ($af_c['format'] === $af_fk) selected @endif>{{ $af_fl }}</option>@endforeach</select></td>
					<td><input type="text" name="xc_options[]" maxlength="1000" value="{{ implode(', ', $af_c['options']) }}" placeholder="{{ lang('commerce.af_options_ph') }}" /></td>
					<td class="af-c"><select name="xc_req[]" aria-label="{{ lang('commerce.af_col_req') }}"><option value="N">{{ lang('commerce.admin_config_56') }}</option><option value="Y" @if ($af_c['req']) selected @endif>{{ lang('commerce.admin_config_57') }}</option></select></td>
					<td class="af-del"><button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger af-remove">{{ lang('commerce.sc_remove') }}</button></td>
				</tr>
				@endforeach
			</tbody>
		</table>
		<div class="af-empty" id="afEmpty" @if (count($af_cfg['custom'])) hidden @endif>{{ lang('commerce.af_custom_empty') }}</div>
	</div>
	<div class="af-tools">
		<button type="button" class="rsva-btn rsva-btn-sm" data-af-add="">+ {{ lang('commerce.af_add') }}</button>
		@foreach ($af_presets as $af_pk => $af_p)
		<button type="button" class="rsva-btn rsva-btn-sm" data-af-add="{{ $af_pk }}">+ {{ $af_p['label'] }}</button>
		@endforeach
	</div>
	<template id="afTpl">
		<tr class="af-row">
			<td><input type="hidden" name="xc_key[]" value="" /><input type="text" name="xc_label[]" maxlength="60" value="" required /></td>
			<td><select name="xc_type[]">@foreach ($af_types as $af_tk => $af_tl)<option value="{{ $af_tk }}">{{ $af_tl }}</option>@endforeach</select></td>
			<td><select name="xc_section[]">@foreach ($af_sections as $af_sk => $af_sl)<option value="{{ $af_sk }}">{{ $af_sl }}</option>@endforeach</select></td>
			<td><select name="xc_format[]">@foreach ($af_formats as $af_fk => $af_fl)<option value="{{ $af_fk }}">{{ $af_fl }}</option>@endforeach</select></td>
			<td><input type="text" name="xc_options[]" maxlength="1000" value="" placeholder="{{ lang('commerce.af_options_ph') }}" /></td>
			<td class="af-c"><select name="xc_req[]" aria-label="{{ lang('commerce.af_col_req') }}"><option value="N">{{ lang('commerce.admin_config_56') }}</option><option value="Y">{{ lang('commerce.admin_config_57') }}</option></select></td>
			<td class="af-del"><button type="button" class="rsva-btn rsva-btn-sm rsva-btn-danger af-remove">{{ lang('commerce.sc_remove') }}</button></td>
		</tr>
	</template>
</div>
<script>
(function () {
	var rows = document.getElementById('afRows');
	var tpl = document.getElementById('afTpl');
	var empty = document.getElementById('afEmpty');
	if (!rows || !tpl) return;
	var presets = {!! json_encode($af_presets, JSON_UNESCAPED_UNICODE + JSON_HEX_TAG) !!};
	var max = {{ Zittme\Modules\Commerce\Models\ApplyForm::MAX_CUSTOM }};
	function sync() {
		var n = rows.querySelectorAll('.af-row').length;
		if (empty) empty.hidden = n > 0;
		rows.querySelectorAll('.af-row').forEach(function (tr) {
			var type = tr.querySelector('[name="xc_type[]"]').value;
			tr.querySelector('[name="xc_options[]"]').style.visibility = type === 'select' ? '' : 'hidden';
			tr.querySelector('[name="xc_format[]"]').style.visibility = type === 'text' ? '' : 'hidden';
		});
	}
	document.querySelectorAll('[data-af-add]').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (rows.querySelectorAll('.af-row').length >= max) return;
			var frag = tpl.content.cloneNode(true);
			var tr = frag.querySelector('tr');
			var p = presets[btn.getAttribute('data-af-add')];
			if (p) {
				var used = rows.querySelector('input[name="xc_key[]"][value="' + p.key + '"]');
				tr.querySelector('[name="xc_key[]"]').value = used ? '' : p.key;
				tr.querySelector('[name="xc_label[]"]').value = p.label;
				tr.querySelector('[name="xc_type[]"]').value = p.type;
				tr.querySelector('[name="xc_section[]"]').value = p.section;
				tr.querySelector('[name="xc_format[]"]').value = p.format;
				tr.querySelector('[name="xc_req[]"]').value = p.req ? 'Y' : 'N';
			}
			rows.appendChild(frag);
			sync();
			var last = rows.querySelector('.af-row:last-child [name="xc_label[]"]');
			if (last) last.focus();
		});
	});
	rows.addEventListener('change', function (e) { if (e.target.name === 'xc_type[]') sync(); });
	rows.addEventListener('click', function (e) {
		var del = e.target.closest('.af-remove');
		if (!del) return;
		del.closest('tr').remove();
		sync();
	});
	sync();
})();
</script>
