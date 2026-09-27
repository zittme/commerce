<style>
@font-face { font-family: 'Pretendard'; src: url('{{ \RX_BASEURL }}common/fonts/PretendardVariable.woff2') format('woff2-variations'); font-weight: 45 920; font-display: swap; }
html, body { margin: 0; padding: 0; background: #f7f6f3; }
.ssi { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px 16px; box-sizing: border-box; font-family: 'Pretendard', -apple-system, BlinkMacSystemFont, 'Apple SD Gothic Neo', 'Malgun Gothic', sans-serif; color: #232a3b; word-break: keep-all; }
.ssi-box { width: 100%; max-width: 440px; box-sizing: border-box; padding: 28px 28px 24px; border: 1px solid #e6e3dc; border-radius: 6px; background: #fff; }
.ssi-box small { display: block; margin: 0 0 6px; font-size: 12.5px; color: #7a7f8c; }
.ssi-box h1 { margin: 0 0 10px; font-size: 20px; font-weight: 800; letter-spacing: -0.01em; }
.ssi-box p { margin: 0 0 18px; font-size: 14px; line-height: 1.65; color: #3c4458; }
.ssi-role { display: inline-block; padding: 2px 8px; border-radius: 4px; background: #fbf1d6; font-weight: 700; }
.ssi-acts { display: flex; gap: 8px; }
.ssi-acts form { margin: 0; }
.ssi-btn { display: inline-flex; align-items: center; padding: 9px 16px; border: 1px solid #d6d2c8; border-radius: 5px; background: #fff; font: inherit; font-size: 14px; font-weight: 600; color: #232a3b; cursor: pointer; text-decoration: none; }
.ssi-btn-primary { border-color: #26345c; background: #26345c; color: #fff8e6; }
</style>
<div class="ssi">
	<div class="ssi-box">
		<small>{{ lang('commerce.sc_title') }}</small>
		@if ($ss_invite)
		<h1>{{ sprintf(lang('commerce.ss_invite_head'), $ss_shop->shop_name) }}</h1>
		<p>{{ lang('commerce.ss_invite_body') }} <span class="ssi-role">{{ lang('commerce.ss_role_' . $ss_invite->role) }}</span></p>
		<p style="font-size:13px;color:#7a7f8c">{{ lang('commerce.ss_role_desc_' . $ss_invite->role) }}</p>
		<div class="ssi-acts">
			<form action="{{ getUrl('') }}" method="post">
				<input type="hidden" name="module" value="commerce" />
				<input type="hidden" name="act" value="procCommerceSellerInviteAnswer" />
				<input type="hidden" name="answer" value="accept" />
				<button type="submit" class="ssi-btn ssi-btn-primary">{{ lang('commerce.ss_do_accept') }}</button>
			</form>
			<form action="{{ getUrl('') }}" method="post" onsubmit="return confirm({{ json_encode(lang('commerce.ss_ask_decline')) }})">
				<input type="hidden" name="module" value="commerce" />
				<input type="hidden" name="act" value="procCommerceSellerInviteAnswer" />
				<input type="hidden" name="answer" value="decline" />
				<button type="submit" class="ssi-btn">{{ lang('commerce.ss_do_decline') }}</button>
			</form>
		</div>
		@else
		<h1>{{ lang('commerce.ss_invite_title') }}</h1>
		<p>{{ lang('commerce.ss_invite_none') }}</p>
		<div class="ssi-acts"><a class="ssi-btn" href="{{ getUrl('', 'module', '', 'mid', '', 'act', '') }}">{{ lang('commerce.admin_view_site') }}</a></div>
		@endif
	</div>
</div>
