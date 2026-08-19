@include('_tabs')
@include('_langfield_assets')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_grades_1') }}</h3>
		<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">{{ lang('commerce.admin_grades_2') }} <strong>{{ lang('commerce.admin_grades_3') }}</strong>{{ lang('commerce.admin_grades_4') }}</p>
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminInsertGrade" />
			{{-- 저장한 뒤 보던 자리로 돌아온다. 없으면 쪽수와 검색 조건이 날아간다 --}}
			<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
			<div style="min-width:160px"><label>{{ lang('commerce.admin_grades_5') }}</label><span class="zlf-row-wrap"><input type="text" name="title" required placeholder="{{ lang('commerce.admin_grades_26') }}" />@include('_langfield', ['lf_name' => 'title', 'lf_value' => '', 'lf_key' => 'gradenew'])</span></div>
			<div><label>{{ lang('commerce.admin_grades_6') }}</label><input type="number" name="min_spend" min="0" value="0" style="width:140px" /></div>
			<div><label>{{ lang('commerce.admin_grades_7') }}</label><input type="number" name="credit_rate" min="0" max="100" step="0.01" value="0" style="width:120px" /></div>
			<div>
				<label>{{ lang('commerce.admin_grades_3') }}</label>
				<select name="discount_type" style="width:110px">
					<option value="">{{ lang('commerce.admin_grades_8') }}</option>
					<option value="percent">{{ lang('commerce.admin_grades_9') }}</option>
					<option value="amount">{{ lang('commerce.admin_grades_10') }}</option>
				</select>
			</div>
			<div><label>{{ lang('commerce.admin_grades_11') }}</label><input type="number" name="discount_value" min="0" step="0.01" value="0" style="width:100px" /></div>
			<div style="min-width:180px">
				<label>{{ lang('commerce.admin_grades_12') }}</label>
				<select name="coupon_srl" style="width:100%">
					<option value="0">{{ lang('commerce.admin_grades_8') }}</option>
					@foreach ($grade_coupons as $c)
					<option value="{{ $c->coupon_srl }}">{{ $c->title }}</option>
					@endforeach
				</select>
			</div>
			<div style="min-width:180px">
				<label>{{ lang('commerce.adm_grade_group') }}</label>
				<select name="group_srl" style="width:100%">
					<option value="0">{{ lang('commerce.admin_grades_8') }}</option>
					@foreach ($grade_groups as $gg)
					<option value="{{ $gg->group_srl }}">{{ Context::replaceUserLang($gg->title, true) }}</option>
					@endforeach
				</select>
			</div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">{{ lang('commerce.admin_grades_13') }}</button></div>
		</form>
		<p style="margin:12px 0 0;font-size:12.5px;color:#6b7684">{{ lang('commerce.adm_grade_group_help') }}</p>
	</div>

	@if (empty($grades))
	<p class="rsva-empty">{{ lang('commerce.admin_grades_14') }}</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>{{ lang('commerce.admin_grades_15') }}</th><th>{{ lang('commerce.admin_grades_16') }}</th><th>{{ lang('commerce.admin_grades_17') }}</th><th>{{ lang('commerce.admin_grades_3') }}</th><th>{{ lang('commerce.admin_grades_18') }}</th><th>{{ lang('commerce.adm_grade_group') }}</th><th>{{ lang('commerce.admin_grades_19') }}</th><th></th></tr></thead>
		<tbody>
			@foreach ($grades as $g)
			<tr>
				<td style="font-weight:700">{{ $g->title }}</td>
				<td>{{ sprintf(lang('commerce.adm_grade_min_spend'), shop_money_base($g->min_spend)) }}</td>
				<td>{{ $g->credit_rate > 0 ? rtrim(rtrim(number_format((float)$g->credit_rate, 2), '0'), '.') . '%' : lang('commerce.adm_grade_default_rate') }}</td>
				<td>
					@if (($g->discount_type ?? '') === 'percent')
						{{ sprintf(lang('commerce.adm_grade_discount_pct'), rtrim(rtrim(number_format((float)$g->discount_value, 2), '0'), '.')) }}
					@elseif (($g->discount_type ?? '') === 'amount')
						{{ sprintf(lang('commerce.adm_grade_discount_amt'), shop_money_base((int)$g->discount_value)) }}
					@else
						-
					@endif
				</td>
				<td>
					@php $gc = null; foreach ($grade_coupons as $c) { if ((int)$c->coupon_srl === (int)$g->coupon_srl) { $gc = $c; break; } } @endphp
					{{ $gc ? $gc->title : '-' }}
				</td>
				<td>
					@php $ggv = null; foreach ($grade_groups as $gg) { if ((int)$gg->group_srl === (int)($g->group_srl ?? 0)) { $ggv = $gg; break; } } @endphp
					{{ $ggv ? Context::replaceUserLang($ggv->title, true) : '-' }}
				</td>
				<td>
					<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminInsertGrade" />
						<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
						<input type="hidden" name="grade_srl" value="{{ $g->grade_srl }}" />
						<div class="zlf-row-wrap"><input type="text" name="title" value="{{ $g->title }}" style="width:100px" />@include('_langfield', ['lf_name' => 'title', 'lf_value' => $g->title, 'lf_key' => $g->grade_srl])</div>
						{{-- 저장은 inputToMinor 를 거친다. 화면에도 같은 단위로 되돌려 놓지 않으면 저장할 때마다 100배가 된다 --}}
						<div><input type="number" name="min_spend" min="0" step="any" value="{{ \Zittme\Modules\Commerce\Models\Money::minorToInput((int)$g->min_spend) }}" style="width:120px" /></div>
						<div><input type="number" name="credit_rate" min="0" max="100" step="0.01" value="{{ $g->credit_rate }}" style="width:80px" /></div>
						<div>
							<select name="discount_type" style="width:96px">
								<option value="">{{ lang('commerce.admin_grades_20') }}</option>
								<option value="percent" @if(($g->discount_type ?? '') === 'percent') selected @endif>{{ lang('commerce.admin_grades_21') }}</option>
								<option value="amount" @if(($g->discount_type ?? '') === 'amount') selected @endif>{{ lang('commerce.admin_grades_22') }}</option>
							</select>
						</div>
						<div><input type="number" name="discount_value" min="0" step="0.01" value="{{ ($g->discount_type ?? '') === 'amount' ? \Zittme\Modules\Commerce\Models\Money::minorToInput((int)($g->discount_value ?? 0)) : (float)($g->discount_value ?? 0) }}" style="width:86px" /></div>
						<div>
							<select name="coupon_srl" style="width:140px">
								<option value="0">{{ lang('commerce.admin_grades_23') }}</option>
								@foreach ($grade_coupons as $c)
								<option value="{{ $c->coupon_srl }}" @if((int)$c->coupon_srl === (int)$g->coupon_srl) selected @endif>{{ $c->title }}</option>
								@endforeach
							</select>
						</div>
						<div>
							<select name="group_srl" style="width:140px">
								<option value="0">{{ lang('commerce.admin_grades_23') }}</option>
								@foreach ($grade_groups as $gg)
								<option value="{{ $gg->group_srl }}" @if((int)$gg->group_srl === (int)($g->group_srl ?? 0)) selected @endif>{{ Context::replaceUserLang($gg->title, true) }}</option>
								@endforeach
							</select>
						</div>
						<div><button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.admin_grades_24') }}</button></div>
					</form>
				</td>
				<td>
					<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('{{ lang('commerce.adm_grade_delete_ask') }}')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminDeleteGrade" />
						<input type="hidden" name="success_return_url" value="{{ getUrl() }}" />
						<input type="hidden" name="grade_srl" value="{{ $g->grade_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">{{ lang('commerce.admin_grades_25') }}</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
