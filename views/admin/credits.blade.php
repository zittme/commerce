@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>회원 적립금 조회·조정</h3>
		<form action="{{ getUrl('') }}" method="get" class="rsva-inline" style="margin-bottom:12px">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="dispCommerceAdminCredits" />
			<div><label>아이디 또는 이메일</label><input type="text" name="f_target" value="{{ $f_target }}" style="width:200px" /></div>
			<div><button type="submit" class="rsva-btn">조회</button></div>
		</form>

		@if ($f_target !== '' && !$credit_member)
		<p class="rsva-empty">회원을 찾을 수 없습니다.</p>
		@elseif ($credit_member)
		<p style="margin:0 0 10px;font-size:14px"><strong>{{ $credit_member->nick_name }}</strong> ({{ $credit_member->user_id ?: $credit_member->email_address }}) — 잔액 <strong style="color:#2677e3">{{ number_format($credit_balance) }}원</strong></p>
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminAdjustCredit" />
			<input type="hidden" name="target" value="{{ $f_target }}" />
			<div><label>조정액 (음수 = 회수)</label><input type="number" name="amount" required style="width:120px" /></div>
			<div style="min-width:200px"><label>메모</label><input type="text" name="memo" placeholder="예: 이벤트 지급" /></div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">적용</button></div>
		</form>

		@if (!empty($credit_logs))
		<table class="rsva-table" style="margin-top:14px">
			<thead><tr><th>일시</th><th>변동</th><th>잔액</th><th>구분</th><th>주문</th><th>메모</th></tr></thead>
			<tbody>
				@foreach ($credit_logs as $cl)
				<tr>
					<td><small>{{ zdate($cl->regdate, 'Y.m.d H:i') }}</small></td>
					<td style="{{ $cl->amount >= 0 ? 'color:#2677e3' : 'color:#c0392b' }};font-weight:600">{{ $cl->amount >= 0 ? '+' : '' }}{{ number_format($cl->amount) }}</td>
					<td>{{ number_format($cl->balance_after) }}</td>
					<td>{{ ['earn'=>'적립','spend'=>'사용','refund'=>'환불','earn_cancel'=>'적립 회수','admin'=>'관리자'][$cl->type] ?? $cl->type }}</td>
					<td>{{ $cl->order_srl > 0 ? $cl->order_srl : '-' }}</td>
					<td>{{ $cl->memo ?: '-' }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
		@endif
	</div>

	<div class="rsva-panel">
		<h3>최근 적립금 변동 (전체)</h3>
		@if (empty($recent_logs))
		<p class="rsva-empty">적립금 이력이 없습니다.</p>
		@else
		<table class="rsva-table">
			<thead><tr><th>일시</th><th>회원</th><th>변동</th><th>잔액</th><th>구분</th></tr></thead>
			<tbody>
				@foreach ($recent_logs as $cl)
				<tr>
					<td><small>{{ zdate($cl->regdate, 'Y.m.d H:i') }}</small></td>
					<td>{{ $cl->member_srl }}</td>
					<td style="{{ $cl->amount >= 0 ? 'color:#2677e3' : 'color:#c0392b' }};font-weight:600">{{ $cl->amount >= 0 ? '+' : '' }}{{ number_format($cl->amount) }}</td>
					<td>{{ number_format($cl->balance_after) }}</td>
					<td>{{ ['earn'=>'적립','spend'=>'사용','refund'=>'환불','earn_cancel'=>'적립 회수','admin'=>'관리자'][$cl->type] ?? $cl->type }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
		<small style="display:block;margin-top:8px;color:#6b7684;font-size:12px">적립률·최소 사용 단위는 설정 탭에서 관리합니다. 적립금은 커머스 자체 원장이며 코어 포인트와 무관합니다.</small>
	</div>
</div>
