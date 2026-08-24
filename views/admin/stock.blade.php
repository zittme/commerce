@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_stock_1') }}</h3>
		<form action="./" method="get" class="rsva-filter">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="dispCommerceAdminStock" />
			<select name="f_category">
				<option value="">{{ lang('commerce.admin_stock_29') }}</option>
				@foreach ($stock_categories as $sc_srl => $sc)
				<option value="{{ $sc_srl }}" @if ((int)$stock_filters->category === (int)$sc_srl) selected @endif>{{ $sc->title }}</option>
				@endforeach
			</select>
			<select name="f_field">
				<option value="name" @if ($stock_filters->field === 'name') selected @endif>{{ lang('commerce.admin_stock_27') }}</option>
				<option value="code" @if ($stock_filters->field === 'code') selected @endif>{{ lang('commerce.admin_stock_30') }}</option>
				<option value="stock" @if ($stock_filters->field === 'stock') selected @endif>{{ lang('commerce.admin_stock_31') }}</option>
			</select>
			<input type="text" name="f_keyword" value="{{ $stock_filters->keyword }}" placeholder="{{ lang('commerce.admin_stock_32') }}" />
			<input type="date" name="f_from" value="{{ $stock_filters->from !== '' ? substr($stock_filters->from, 0, 4) . '-' . substr($stock_filters->from, 4, 2) . '-' . substr($stock_filters->from, 6, 2) : '' }}" title="{{ lang('commerce.admin_stock_33') }}" />
			<span>~</span>
			<input type="date" name="f_to" value="{{ $stock_filters->to !== '' ? substr($stock_filters->to, 0, 4) . '-' . substr($stock_filters->to, 4, 2) . '-' . substr($stock_filters->to, 6, 2) : '' }}" title="{{ lang('commerce.admin_stock_33') }}" />
			<button type="submit" class="rsva-btn">{{ lang('commerce.admin_stock_2') }}</button>
		</form>
		<p style="margin:0 0 10px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.admin_stock_3') }}</p>

		{{-- 재고 부족만 모아 보기. 여기서 바로 입고까지 끝낼 수 있다 --}}
		@if (count($stock_low_rows))
		<div class="zmi-low">
			<div class="zmi-low-head">
				<b>{{ sprintf(lang('commerce.adm_low_stock_title'), count($stock_low_rows)) }}</b>
				<span>{{ sprintf(lang('commerce.adm_low_stock_hint'), number_format($stock_low_default)) }}</span>
			</div>
			<table class="rsva-table">
				<thead><tr><th>{{ lang('commerce.admin_stock_4') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_6') }}</th><th style="text-align:right">{{ lang('commerce.adm_low_stock_limit') }}</th><th>{{ lang('commerce.admin_stock_7') }}</th><th></th></tr></thead>
				<tbody>
					@foreach ($stock_low_rows as $lr)
					@php $lr_fid = 'stLow' . (int)$lr->item_srl . '_' . (int)$lr->option_srl; @endphp
					<tr class="@if ((int)$lr->stock <= 0) is-out @endif">
						<td>{{ $lr->label }}</td>
						<td style="text-align:right;font-weight:700">{{ number_format((int)$lr->stock) }}</td>
						<td style="text-align:right;color:#8b95a1">{{ number_format((int)$lr->limit_qty) }}</td>
						<td style="white-space:nowrap">
							<input type="hidden" name="item_srl" form="{{ $lr_fid }}" value="{{ (int)$lr->item_srl }}" />
							<input type="hidden" name="option_srl" form="{{ $lr_fid }}" value="{{ (int)$lr->option_srl }}" />
							<input type="hidden" name="adjust_type" form="{{ $lr_fid }}" value="in" />
							<input type="number" name="qty" form="{{ $lr_fid }}" min="1" value="10" style="width:80px" />
						</td>
						<td><button type="submit" form="{{ $lr_fid }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.adm_low_stock_restock') }}</button></td>
					</tr>
					@endforeach
				</tbody>
			</table>
			@foreach ($stock_low_rows as $lr)
			<form id="stLow{{ (int)$lr->item_srl }}_{{ (int)$lr->option_srl }}" action="{{ getUrl('') }}" method="post">
				<input type="hidden" name="module" value="admin" />
				<input type="hidden" name="act" value="procCommerceAdminStockAdjust" />
				<input type="hidden" name="memo" value="{{ lang('commerce.adm_low_stock_restock') }}" />
				<input type="hidden" name="success_return_url" value="{{ getNotEncodedUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock') }}" />
			</form>
			@endforeach
		</div>
		@endif
		{{-- 행 인라인 조정: 셀 입력은 form 속성으로 행별 폼(테이블 밖)에 연결한다 --}}
		@foreach ($stock_items as $si)
		<form id="stAdj{{ $si->item_srl }}_0" action="./" method="post">
			<input type="hidden" name="module" value="commerce" />
			<input type="hidden" name="act" value="procCommerceAdminStockAdjust" />
			<input type="hidden" name="item_srl" value="{{ $si->item_srl }}" />
			<input type="hidden" name="option_srl" value="0" />
			<input type="hidden" name="f_keyword" value="{{ Context::get('f_keyword') }}" />
			<input type="hidden" name="f_field" value="{{ Context::get('f_field') }}" />
			<input type="hidden" name="f_category" value="{{ Context::get('f_category') }}" />
			<input type="hidden" name="memo" value="" />
		</form>
		@foreach ($stock_options_map[(int)$si->item_srl] ?? [] as $so)
		<form id="stAdj{{ $si->item_srl }}_{{ $so->option_srl }}" action="./" method="post">
			<input type="hidden" name="module" value="commerce" />
			<input type="hidden" name="act" value="procCommerceAdminStockAdjust" />
			<input type="hidden" name="item_srl" value="{{ $si->item_srl }}" />
			<input type="hidden" name="option_srl" value="{{ $so->option_srl }}" />
			<input type="hidden" name="f_keyword" value="{{ Context::get('f_keyword') }}" />
			<input type="hidden" name="f_field" value="{{ Context::get('f_field') }}" />
			<input type="hidden" name="f_category" value="{{ Context::get('f_category') }}" />
			<input type="hidden" name="memo" value="" />
		</form>
		@endforeach
		@endforeach
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stock_4') }}</th><th>{{ lang('commerce.admin_stock_5') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_6') }}</th><th style="text-align:right">{{ lang('commerce.adm_low_stock_limit') }}</th><th>{{ lang('commerce.admin_stock_7') }}</th><th>{{ lang('commerce.admin_stock_8') }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($stock_items as $si)
				@php $st_fid = 'stAdj' . $si->item_srl . '_0'; @endphp
				<tr>
					<td><strong>{{ $si->item_name }}</strong><br /><small style="color:#8b95a1">{{ ($si->use_stock ?? 'Y') === 'Y' ? lang('commerce.st_item_stock_managed') : lang('commerce.st_item_unlimited_sale') }}</small></td>
					<td>{{ lang('commerce.admin_stock_9') }}</td>
					<td style="text-align:right;font-weight:700">{{ number_format((int)$si->stock) }}</td>
					<td style="text-align:right"><input type="number" class="zmi-low-input" min="0" data-item="{{ (int)$si->item_srl }}" data-option="0" value="{{ (int)($si->low_stock ?? 0) }}" placeholder="{{ $stock_low_default }}" style="width:70px;text-align:right" /></td>
					<td style="white-space:nowrap">
						<select name="adjust_type" form="{{ $st_fid }}">
							<option value="in">{{ lang('commerce.admin_stock_10') }}</option>
							<option value="out">{{ lang('commerce.admin_stock_11') }}</option>
							<option value="loss">{{ lang('commerce.admin_stock_12') }}</option>
						</select>
						<input type="number" name="qty" form="{{ $st_fid }}" min="1" value="1" style="width:70px" />
					</td>
					<td><input type="text" class="zmi-memo" data-form="{{ $st_fid }}" maxlength="250" placeholder="{{ lang('commerce.admin_stock_28') }}" style="width:100%;min-width:120px" /></td>
					<td style="white-space:nowrap">
						<button type="submit" form="{{ $st_fid }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_stock_13') }}</button>
						<a class="rsva-btn rsva-btn-sm" href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock', 'log_item', $si->item_srl, 'f_keyword', Context::get('f_keyword')) }}">{{ lang('commerce.admin_stock_14') }}</a>
					</td>
				</tr>
				@foreach ($stock_options_map[(int)$si->item_srl] ?? [] as $so)
				@php $st_fid = 'stAdj' . $si->item_srl . '_' . $so->option_srl; @endphp
				<tr>
					<td style="padding-left:28px;color:#6b7684">{{ $so->option_label }}</td>
					<td>{{ lang('commerce.admin_stock_15') }}</td>
					<td style="text-align:right">{{ number_format((int)$so->stock) }}</td>
					<td style="text-align:right"><input type="number" class="zmi-low-input" min="0" data-item="{{ (int)$si->item_srl }}" data-option="{{ (int)$so->option_srl }}" value="{{ (int)($so->low_stock ?? 0) }}" placeholder="{{ $stock_low_default }}" style="width:70px;text-align:right" /></td>
					<td style="white-space:nowrap">
						<select name="adjust_type" form="{{ $st_fid }}">
							<option value="in">{{ lang('commerce.admin_stock_10') }}</option>
							<option value="out">{{ lang('commerce.admin_stock_11') }}</option>
							<option value="loss">{{ lang('commerce.admin_stock_12') }}</option>
						</select>
						<input type="number" name="qty" form="{{ $st_fid }}" min="1" value="1" style="width:70px" />
					</td>
					<td><input type="text" class="zmi-memo" data-form="{{ $st_fid }}" maxlength="250" style="width:100%;min-width:120px" /></td>
					<td><button type="submit" form="{{ $st_fid }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_stock_13') }}</button></td>
				</tr>
				@endforeach
				@endforeach
				@if (!count($stock_items))
				<tr><td colspan="7" style="text-align:center;color:#8b95a1;padding:30px 0">{{ lang('commerce.admin_stock_16') }}</td></tr>
				@endif
			</tbody>
		</table>

		{{-- 알림 기준은 여러 줄을 한 번에 저장한다. 줄마다 폼을 두면 화면이 계속 다시 그려진다 --}}
		<form action="{{ getUrl('') }}" method="post" id="zmiLowForm" style="margin-top:12px">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminSaveLowStock" />
			<input type="hidden" name="success_return_url" value="{{ getNotEncodedUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock', 'f_keyword', Context::get('f_keyword'), 'f_field', Context::get('f_field'), 'f_category', Context::get('f_category')) }}" />
			<input type="hidden" name="rows" value="" />
			<button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.adm_low_stock_save') }}</button>
			<small style="margin-left:8px;color:#8b95a1">{{ sprintf(lang('commerce.adm_low_stock_note'), number_format($stock_low_default)) }}</small>
		</form>
		@include('_pagenav', ['pn' => $stock_page_navigation])
	</div>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.adm_stock_log') }} @if ($stock_log_item)<small>{{ lang('commerce.admin_stock_17') }}</small> <a class="rsva-btn rsva-btn-sm" href="{{ getUrl('', 'mid', '', 'p', '', 'module', 'admin', 'act', 'dispCommerceAdminStock') }}">{{ lang('commerce.admin_stock_18') }}</a>@endif</h3>
		@if (count($stock_logs))
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stock_19') }}</th><th>{{ lang('commerce.admin_stock_20') }}</th><th>{{ lang('commerce.admin_stock_5') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_21') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_22') }}</th><th>{{ lang('commerce.admin_stock_8') }}</th></tr></thead>
			<tbody>
				@foreach ($stock_logs as $lg)
				<tr>
					<td>{{ zdate($lg->regdate, 'Y.m.d H:i') }}</td>
					<td>#{{ $lg->item_srl }}@if ((int)$lg->option_srl > 0) / {{ lang('commerce.adm_stock_option') }} {{ $lg->option_srl }}@endif</td>
					<td>
						@if ($lg->type === 'in')<span class="rsva-st rsva-st-confirmed">{{ lang('commerce.admin_stock_23') }}</span>
						@elseif ($lg->type === 'out')<span class="rsva-st">{{ lang('commerce.admin_stock_24') }}</span>
						@else<span class="rsva-st rsva-st-cancelled">{{ lang('commerce.admin_stock_25') }}</span>@endif
					</td>
					<td style="text-align:right">{{ $lg->type === 'in' ? '+' : '-' }}{{ number_format((int)$lg->qty) }}</td>
					<td style="text-align:right">{{ number_format((int)$lg->stock_after) }}</td>
					<td>{{ $lg->memo }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@include('_pagenav', ['pn' => $stock_log_navigation, 'pn_param' => 'log_page'])
		@else
		<p style="margin:0;font-size:13px;color:#8b95a1">{{ lang('commerce.admin_stock_26') }}</p>
		@endif
	</div>
</div>


<style>
.zmi-low { margin: 0 0 18px; padding: 14px 16px; border: 1px solid #f0c36d; border-radius: 10px; background: #fdf6e6; }
.zmi-low-head { display: flex; flex-wrap: wrap; align-items: baseline; gap: 10px; margin-bottom: 10px; }
.zmi-low-head b { font-size: 14px; color: #8a6100; }
.zmi-low-head span { font-size: 12.5px; color: #8a6100; }
.zmi-low .rsva-table { background: #fff; }
.zmi-low tr.is-out td { background: #fdeaea; }
</style>

<script>
(function () {
	document.querySelectorAll('.zmi-memo').forEach(function (el) {
		var sync = function () {
			var form = document.getElementById(el.getAttribute('data-form'));
			if (!form || !form.memo) { return; }
			form.memo.value = el.value;
		};
		el.addEventListener('input', sync);
		el.addEventListener('change', sync);
	});
})();
(function () {
	// 알림 기준 칸들을 모아 한 번에 보낸다
	var form = document.getElementById('zmiLowForm');
	if (!form) { return; }
	form.addEventListener('submit', function () {
		var rows = [];
		document.querySelectorAll('.zmi-low-input').forEach(function (el) {
			rows.push({
				item_srl: el.getAttribute('data-item'),
				option_srl: el.getAttribute('data-option'),
				low_stock: el.value === '' ? 0 : el.value
			});
		});
		form.rows.value = JSON.stringify(rows);
	});
})();
</script>
