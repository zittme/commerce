@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>쿠폰 만들기</h3>
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminInsertCoupon" />
			<div style="min-width:180px"><label>이름</label><input type="text" name="title" required /></div>
			<div><label>코드 (비우면 발급 전용)</label><input type="text" name="code" placeholder="WELCOME10" style="width:130px" /></div>
			<div><label>할인 방식</label><select name="discount_type" style="width:90px"><option value="fixed">정액(원)</option><option value="percent">정률(%)</option></select></div>
			<div><label>할인값</label><input type="number" name="discount_value" min="0" required style="width:90px" /></div>
			<div><label>최대 할인액</label><input type="number" name="max_discount" min="0" value="0" style="width:100px" /></div>
			<div><label>최소 주문금액</label><input type="number" name="min_order" min="0" value="0" style="width:100px" /></div>
			<div><label>시작일</label><input type="date" name="use_start" /></div>
			<div><label>종료일</label><input type="date" name="use_end" /></div>
			<div><label>1인당 횟수</label><input type="number" name="per_member" min="1" value="1" style="width:70px" /></div>
			<div><label>전체 한도 (0=무제한)</label><input type="number" name="total_limit" min="0" value="0" style="width:90px" /></div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">저장</button></div>
		</form>
		<small style="display:block;margin-top:8px;color:#6b7684;font-size:12px">할인은 상품 금액에만 적용됩니다(배송비 제외). 정률 쿠폰은 최대 할인액으로 상한을 둘 수 있습니다.</small>
	</div>

	@if (empty($coupons))
	<p class="rsva-empty">등록된 쿠폰이 없습니다.</p>
	@else
	<table class="rsva-table">
		<thead><tr><th>이름</th><th>코드</th><th>할인</th><th>조건</th><th>기간</th><th>사용/한도</th><th>상태</th><th>발급·관리</th></tr></thead>
		<tbody>
			@foreach ($coupons as $c)
			<tr>
				<td style="font-weight:700">{{ $c->title }}</td>
				<td>{{ $c->code ?: '-' }}</td>
				<td>
					{{ $c->discount_type === 'percent' ? $c->discount_value . '%' : number_format($c->discount_value) . '원' }}
					@if ($c->discount_type === 'percent' && $c->max_discount > 0)<br /><small style="color:#9aa1ab">최대 {{ number_format($c->max_discount) }}원</small>@endif
				</td>
				<td>{{ $c->min_order > 0 ? number_format($c->min_order) . '원 이상' : '-' }}<br /><small style="color:#9aa1ab">1인 {{ $c->per_member }}회</small></td>
				<td><small>{{ $c->use_start ? zdate($c->use_start, 'Y.m.d') : '' }} ~ {{ $c->use_end ? zdate($c->use_end, 'Y.m.d') : '' }}</small></td>
				<td>{{ number_format($c->used_count) }} / {{ $c->total_limit > 0 ? number_format($c->total_limit) : '∞' }}
					<br /><small style="color:#9aa1ab">발급 {{ number_format($issue_counts[(int)$c->coupon_srl] ?? 0) }}</small></td>
				<td><span class="rsva-st {{ $c->status === 'Y' ? 'rsva-st-confirmed' : '' }}">{{ $c->status === 'Y' ? '활성' : '중지' }}</span></td>
				<td>
					<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminIssueCoupon" />
						<input type="hidden" name="coupon_srl" value="{{ $c->coupon_srl }}" />
						<div><input type="text" name="target" placeholder="아이디 또는 이메일" style="width:140px" /></div>
						<div><button type="submit" class="rsva-btn rsva-btn-sm">발급</button></div>
					</form>
					<form action="{{ getUrl('') }}" method="post" style="display:inline-block;margin-top:4px" onsubmit="return confirm('쿠폰을 삭제할까요? 발급 이력은 남습니다.')">
						<input type="hidden" name="module" value="admin" />
						<input type="hidden" name="act" value="procCommerceAdminDeleteCoupon" />
						<input type="hidden" name="coupon_srl" value="{{ $c->coupon_srl }}" />
						<button type="submit" class="rsva-btn rsva-btn-sm rsva-btn-danger">삭제</button>
					</form>
				</td>
			</tr>
			@endforeach
		</tbody>
	</table>
	@endif
</div>
