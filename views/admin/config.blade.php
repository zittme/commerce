@include('_tabs')

<div class="rsva">
	<form action="{{ getUrl('') }}" method="post">
		<input type="hidden" name="module" value="admin" />
		<input type="hidden" name="act" value="procCommerceAdminInsertConfig" />

		<div class="rsva-panel">
			<h3>기본</h3>
			<div class="rsva-form-grid">
				<div><label>상점 기능</label><select name="enabled"><option value="Y" @if($shop_config->enabled === 'Y') selected @endif>사용</option><option value="N" @if($shop_config->enabled === 'N') selected @endif>중지</option></select></div>
				<div><label>운영 모드</label><select name="market_mode"><option value="single" @if($shop_config->market_mode === 'single') selected @endif>독립몰</option><option value="open" @if($shop_config->market_mode === 'open') selected @endif>오픈마켓 (2차)</option></select></div>
				<div><label>주문번호 접두사</label><input type="text" name="code_prefix" maxlength="5" value="{{ $shop_config->code_prefix }}" /></div>
				<div><label>비회원 주문</label><select name="allow_guest"><option value="Y" @if($shop_config->allow_guest === 'Y') selected @endif>허용</option><option value="N" @if($shop_config->allow_guest === 'N') selected @endif>회원만</option></select></div>
				<div><label>결제 대기 유지 (분)</label><input type="number" name="pending_minutes" min="10" max="1440" value="{{ $shop_config->pending_minutes }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>배송·클레임</h3>
			<div class="rsva-form-grid">
				<div><label>기본 배송비 (원)</label><input type="number" name="default_ship_fee" min="0" value="{{ $shop_config->default_ship_fee }}" /></div>
				<div><label>무료배송 기준액 (0=미사용)</label><input type="number" name="free_ship_over" min="0" value="{{ $shop_config->free_ship_over }}" /></div>
				<div><label>취소·반품 신청 기한 (배송완료 후 일)</label><input type="number" name="claim_days" min="0" max="90" value="{{ $shop_config->claim_days }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>적립금 (자체 원장 — 코어 포인트와 무관)</h3>
			<div class="rsva-form-grid">
				<div><label>적립률 (%) — 결제 완료 시 실결제 상품 금액 기준 (0=미사용, 0.01 단위)</label><input type="number" name="credit_rate" min="0" max="100" step="0.01" value="{{ $shop_config->credit_rate }}" /></div>
				<div><label>최소 사용 단위 (원, 0=제한 없음)</label><input type="number" name="credit_min_use" min="0" value="{{ $shop_config->credit_min_use }}" /></div>
			</div>
		</div>

		<div class="rsva-panel">
			<h3>결제 {{ $pay_available ? '' : '— 짓미 페이(zittme_pay)가 설치되어 있지 않아 결제를 받을 수 없습니다' }}</h3>
			<p style="margin:0;font-size:13px;color:#6b7684">결제수단·PG 설정은 짓미 페이 관리자에서 합니다.</p>
		</div>

		<div class="rsva-panel">
			<h3>개인정보·알림</h3>
			<div class="rsva-form-grid">
				<div style="grid-column:1/-1"><label>수집 동의 문구</label><textarea name="privacy_text" rows="3">{{ $shop_config->privacy_text }}</textarea></div>
				<div><label>문구 버전</label><input type="text" name="privacy_version" maxlength="20" value="{{ $shop_config->privacy_version }}" /></div>
				<div><label>주문 정보 보관 (일)</label><input type="number" name="retention_days" min="0" max="3650" value="{{ $shop_config->retention_days }}" /></div>
				<div><label>새 주문 관리자 메일</label><select name="notify_admin"><option value="N" @if($shop_config->notify_admin === 'N') selected @endif>끔</option><option value="Y" @if($shop_config->notify_admin === 'Y') selected @endif>켬</option></select></div>
				<div><label>관리자 메일 주소</label><input type="email" name="notify_admin_email" value="{{ $shop_config->notify_admin_email }}" /></div>
			</div>
		</div>

		<button type="submit" class="rsva-btn rsva-btn-primary">저장</button>
	</form>

	<div class="rsva-panel" style="margin-top:18px">
		<h3>스킨 (테마)</h3>
		@if ($shop_instance)
		<form action="{{ getUrl('') }}" method="post" class="rsva-inline">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="procCommerceAdminUpdateSkin" />
			<div style="min-width:220px">
				<label>PC 스킨</label>
				<select name="skin" style="width:100%">
					<option value="/USE_DEFAULT/" @if(($shop_instance->skin ?? '') === '/USE_DEFAULT/') selected @endif>기본 스킨 따름</option>
					@foreach ($shop_skins as $sk)
					<option value="{{ $sk->skin }}" @if(($shop_instance->skin ?? '') === $sk->skin) selected @endif>{{ $sk->title ?: $sk->skin }}</option>
					@endforeach
				</select>
			</div>
			<div style="min-width:220px">
				<label>모바일 스킨</label>
				<select name="mskin" style="width:100%">
					<option value="/USE_DEFAULT/" @if(($shop_instance->mskin ?? '') === '/USE_DEFAULT/') selected @endif>기본 스킨 따름 (없으면 PC 스킨)</option>
					@foreach ($shop_mskins as $sk)
					<option value="{{ $sk->skin }}" @if(($shop_instance->mskin ?? '') === $sk->skin) selected @endif>{{ $sk->title ?: $sk->skin }}</option>
					@endforeach
				</select>
			</div>
			<div><button type="submit" class="rsva-btn rsva-btn-primary">스킨 적용</button></div>
		</form>
		<small style="display:block;margin-top:10px;color:#6b7684;font-size:12px">쇼핑몰 테마는 레이아웃 + 커머스 스킨 + 짓미페이 스킨 + 게시판 스킨 묶음으로 배포됩니다. 스킨은 modules/commerce/skins/ 에 설치하면 여기에 나타납니다.</small>
		@else
		<p class="rsva-empty">상점 인스턴스(mid)가 없어 스킨을 설정할 수 없습니다.</p>
		@endif
	</div>

</div>
