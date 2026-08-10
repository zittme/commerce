@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_stock_1') }}</h3>
		<form action="./" method="get" class="rsva-filter">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="dispCommerceAdminStock" />
			<input type="text" name="f_keyword" value="{{ Context::get('f_keyword') }}" placeholder="{{ lang('commerce.admin_stock_27') }}" />
			<button type="submit" class="rsva-btn">{{ lang('commerce.admin_stock_2') }}</button>
		</form>
		<p style="margin:0 0 10px;font-size:12.5px;color:#8b95a1">{{ lang('commerce.admin_stock_3') }}</p>
		{{-- 행 인라인 조정: 셀 입력은 form 속성으로 행별 폼(테이블 밖)에 연결한다 --}}
		@foreach ($stock_items as $si)
		<form id="stAdj{{ $si->item_srl }}_0" action="./" method="post">
			<input type="hidden" name="module" value="commerce" />
			<input type="hidden" name="act" value="procCommerceAdminStockAdjust" />
			<input type="hidden" name="item_srl" value="{{ $si->item_srl }}" />
			<input type="hidden" name="option_srl" value="0" />
			<input type="hidden" name="f_keyword" value="{{ Context::get('f_keyword') }}" />
		</form>
		@foreach ($stock_options_map[(int)$si->item_srl] ?? [] as $so)
		<form id="stAdj{{ $si->item_srl }}_{{ $so->option_srl }}" action="./" method="post">
			<input type="hidden" name="module" value="commerce" />
			<input type="hidden" name="act" value="procCommerceAdminStockAdjust" />
			<input type="hidden" name="item_srl" value="{{ $si->item_srl }}" />
			<input type="hidden" name="option_srl" value="{{ $so->option_srl }}" />
			<input type="hidden" name="f_keyword" value="{{ Context::get('f_keyword') }}" />
		</form>
		@endforeach
		@endforeach
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stock_4') }}</th><th>{{ lang('commerce.admin_stock_5') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_6') }}</th><th>{{ lang('commerce.admin_stock_7') }}</th><th>{{ lang('commerce.admin_stock_8') }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($stock_items as $si)
				@php $st_fid = 'stAdj' . $si->item_srl . '_0'; @endphp
				<tr>
					<td><strong>{{ $si->item_name }}</strong><br /><small style="color:#8b95a1">{{ ($si->use_stock ?? 'Y') === 'Y' ? '재고 관리' : '무제한 판매' }}</small></td>
					<td>{{ lang('commerce.admin_stock_9') }}</td>
					<td style="text-align:right;font-weight:700">{{ number_format((int)$si->stock) }}</td>
					<td style="white-space:nowrap">
						<select name="adjust_type" form="{{ $st_fid }}">
							<option value="in">{{ lang('commerce.admin_stock_10') }}</option>
							<option value="out">{{ lang('commerce.admin_stock_11') }}</option>
							<option value="loss">{{ lang('commerce.admin_stock_12') }}</option>
						</select>
						<input type="number" name="qty" form="{{ $st_fid }}" min="1" value="1" style="width:70px" />
					</td>
					<td><input type="text" name="memo" form="{{ $st_fid }}" maxlength="250" placeholder="{{ lang('commerce.admin_stock_28') }}" style="width:100%;min-width:120px" /></td>
					<td style="white-space:nowrap">
						<button type="submit" form="{{ $st_fid }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_stock_13') }}</button>
						<a class="rsva-btn rsva-btn-sm" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminStock', 'log_item', $si->item_srl, 'f_keyword', Context::get('f_keyword')) }}">{{ lang('commerce.admin_stock_14') }}</a>
					</td>
				</tr>
				@foreach ($stock_options_map[(int)$si->item_srl] ?? [] as $so)
				@php $st_fid = 'stAdj' . $si->item_srl . '_' . $so->option_srl; @endphp
				<tr>
					<td style="padding-left:28px;color:#6b7684">{{ $so->option_label }}</td>
					<td>{{ lang('commerce.admin_stock_15') }}</td>
					<td style="text-align:right">{{ number_format((int)$so->stock) }}</td>
					<td style="white-space:nowrap">
						<select name="adjust_type" form="{{ $st_fid }}">
							<option value="in">{{ lang('commerce.admin_stock_10') }}</option>
							<option value="out">{{ lang('commerce.admin_stock_11') }}</option>
							<option value="loss">{{ lang('commerce.admin_stock_12') }}</option>
						</select>
						<input type="number" name="qty" form="{{ $st_fid }}" min="1" value="1" style="width:70px" />
					</td>
					<td><input type="text" name="memo" form="{{ $st_fid }}" maxlength="250" style="width:100%;min-width:120px" /></td>
					<td><button type="submit" form="{{ $st_fid }}" class="rsva-btn rsva-btn-sm rsva-btn-primary">{{ lang('commerce.admin_stock_13') }}</button></td>
				</tr>
				@endforeach
				@endforeach
				@if (!count($stock_items))
				<tr><td colspan="6" style="text-align:center;color:#8b95a1;padding:30px 0">{{ lang('commerce.admin_stock_16') }}</td></tr>
				@endif
			</tbody>
		</table>
	</div>

	<div class="rsva-panel">
		<h3>이동 기록 @if ($stock_log_item)<small>{{ lang('commerce.admin_stock_17') }}</small> <a class="rsva-btn rsva-btn-sm" href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminStock') }}">{{ lang('commerce.admin_stock_18') }}</a>@endif</h3>
		@if (count($stock_logs))
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_stock_19') }}</th><th>{{ lang('commerce.admin_stock_20') }}</th><th>{{ lang('commerce.admin_stock_5') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_21') }}</th><th style="text-align:right">{{ lang('commerce.admin_stock_22') }}</th><th>{{ lang('commerce.admin_stock_8') }}</th></tr></thead>
			<tbody>
				@foreach ($stock_logs as $lg)
				<tr>
					<td>{{ zdate($lg->regdate, 'Y.m.d H:i') }}</td>
					<td>#{{ $lg->item_srl }}@if ((int)$lg->option_srl > 0) / 옵션 {{ $lg->option_srl }}@endif</td>
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
		@else
		<p style="margin:0;font-size:13px;color:#8b95a1">{{ lang('commerce.admin_stock_26') }}</p>
		@endif
	</div>
</div>

