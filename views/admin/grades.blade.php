@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>등급 만들기</h3>
		<p style="margin:-8px 0 14px;font-size:13px;color:#6b7684">누적 결제완료 금액이 기준액 이상이면 자동으로 해당 등급이 됩니다 (가장 높은 구간 적용). 결제·취소 시 자동 재계산되며, 등급이 오르면 지정한 쿠폰이 1회 자동 발급됩니다. <strong>등급 할인</strong>을 설정하면 해당 등급 회원의 장바구니·주문 단가에 자동 적용됩니다 (쿠폰과 중복 적용).</p>
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminInsertGrade" />
			<div style="min-width:160px"><label>등급명</label><input type="text" name="title" required placeholder="예: VIP" /></div>
			<div><label>기준 누적 금액 (원)</label><input type="number" name="min_spend" min="0" value="0" style="width:140px" /></div>
			<div><label>적립률 % (0=기본 따름, 0.01 단위)</label><input type="number" name="credit_rate" min="0" max="100" step="0.01" value="0" style="width:120px" /></div>
			<div>
				<label>등급 할인</label>
				<select name="discount_type" style="width:110px">
					<option value="">없음</option>
					<option value="percent">정률 (%)</option>
					<option value="amount">정액 (원)</option>
				</select>
			</div>
			<div><label>할인값</label><input type="number" name="discount_value" min="0" step="0.01" value="0" style="width:100px" /></div>
			<div style="min-width:180px">
				<label>등급 달성 쿠폰</label>
				<select name="coupon_srl" style="width:100%">
					<option value="0">없음</option>
					@foreach ($grade_coupons as $c)
					<option value="{{ $c->coupon_srl }}">{{ $c->title }}</option>
					@endforeach
				</select>
			</div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">추가</button></div>
		</form>
	</div>

	@if (empty($grades))
	<p class="rsva-empty">등급이 없습니다. 등급을 만들면 회원의 누적 구매액에 따라 자동으로 부여됩니다.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>등급</th><th>기준 누적 금액</th><th>적립률</th><th>등급 할인</th><th>달성 쿠폰</th><th>수정</th><th></th></tr></thead>
		<tbody>
			@foreach ($grades as $g)
			<tr>
				<td style="font-weight:700">{{ $g->title }}</td>
				<td>{{ number_format($g->min_spend) }}원 이상</td>
				<td>{{ $g->credit_rate > 0 ? rtrim(rtrim(number_format((float)$g->credit_rate, 2), '0'), '.') . '%' : '기본 따름' }}</td>
				<td>
					@if (($g->discount_type ?? '') === 'percent')
						{{ rtrim(rtrim(number_format((float)$g->discount_value, 2), '0'), '.') }}% 할인
					@elseif (($g->discount_type ?? '') === 'amount')
						{{ number_format((int)$g->discount_value) }}원 할인
					@else
						-
					@endif
				</td>
				<td>
					@php $gc = null; foreach ($grade_coupons as $c) { if ((int)$c->coupon_srl === (int)$g->coupon_srl) { $gc = $c; break; } } @endphp
					{{ $gc ? $gc->title : '-' }}
				</td>
				<td>
					<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminInsertGrade" />
						<input type="hidden" name="grade_srl" value="{{ $g->grade_srl }}" />
						<div><input type="text" name="title" value="{{ $g->title }}" style="width:100px" /></div>
						<div><input type="number" name="min_spend" min="0" value="{{ $g->min_spend }}" style="width:120px" /></div>
						<div><input type="number" name="credit_rate" min="0" max="100" step="0.01" value="{{ $g->credit_rate }}" style="width:80px" /></div>
						<div>
							<select name="discount_type" style="width:96px">
								<option value="">할인 없음</option>
								<option value="percent" @if(($g->discount_type ?? '') === 'percent') selected @endif>정률 %</option>
								<option value="amount" @if(($g->discount_type ?? '') === 'amount') selected @endif>정액 원</option>
							</select>
						</div>
						<div><input type="number" name="discount_value" min="0" step="0.01" value="{{ (float)($g->discount_value ?? 0) }}" style="width:86px" /></div>
						<div>
							<select name="coupon_srl" style="width:140px">
								<option value="0">쿠폰 없음</option>
								@foreach ($grade_coupons as $c)
								<option value="{{ $c->coupon_srl }}" @if((int)$c->coupon_srl === (int)$g->coupon_srl) selected @endif>{{ $c->title }}</option>
								@endforeach
							</select>
						</div>
						<div><button type="submit" class="rsva-btn rsva-btn-sm">저장</button></div>
					</form>
				</td>
				<td>
					<form action="{{ getUrl('') }}" method="post" style="display:inline" onsubmit="return confirm('등급을 삭제할까요? 해당 등급 회원은 다음 재계산 때 다른 등급으로 이동합니다.')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminDeleteGrade" />
						<input type="hidden" name="grade_srl" value="{{ $g->grade_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
