@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_qna_1') }}</h3>
		@if (!count($qna_reviews))
		<p style="color:#8b95a1">{{ lang('commerce.admin_qna_2') }}</p>
		@else
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_qna_3') }}</th><th>{{ lang('commerce.admin_qna_4') }}</th><th style="text-align:center">{{ lang('commerce.admin_qna_5') }}</th><th style="width:45%">{{ lang('commerce.admin_qna_6') }}</th><th>{{ lang('commerce.admin_qna_7') }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($qna_reviews as $rv)
				<tr>
					<td><strong>{{ $qna_item_names[(int)$rv->item_srl] ?? ('#' . $rv->item_srl) }}</strong></td>
					<td>{{ $rv->nick_name }}</td>
					<td style="text-align:center">{{ (int)$rv->rating }}/5</td>
					<td>
						<div style="white-space:pre-wrap;margin-bottom:8px">{{ $rv->content }}</div>
						<form action="./" method="post" style="display:flex;gap:8px">
							<input type="hidden" name="module" value="commerce" />
							<input type="hidden" name="act" value="procCommerceAdminReviewReply" />
							<input type="hidden" name="review_srl" value="{{ $rv->review_srl }}" />
							<textarea name="reply" rows="3" placeholder="{{ lang('commerce.admin_qna_14') }}" style="flex:1;resize:vertical">{{ $rv->reply }}</textarea>
							<button type="submit" class="rsva-btn rsva-btn-sm" style="align-self:flex-end">{{ empty($rv->reply) ? lang('commerce.adm_reply_add') : lang('commerce.adm_reply_edit') }}</button>
						</form>
					</td>
					<td><small>{{ zdate($rv->regdate, 'Y.m.d') }}</small></td>
					<td>
						<form action="./" method="post" onsubmit="return confirm('{{ lang('commerce.adm_review_delete_ask') }}')">
							<input type="hidden" name="module" value="commerce" />
							<input type="hidden" name="act" value="procCommerceReviewDelete" />
							<input type="hidden" name="review_srl" value="{{ $rv->review_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.admin_qna_8') }}</button>
						</form>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
	</div>

	<div class="rsva-panel">
		<h3>{{ lang('commerce.admin_qna_9') }}</h3>
		<form action="./" method="get" class="rsva-filter">
			<input type="hidden" name="module" value="admin" />
			<input type="hidden" name="act" value="dispCommerceAdminQna" />
			<label style="font-size:13px"><input type="checkbox" name="f_unanswered" value="Y" @if ($qna_unanswered) checked @endif /> {{ lang('commerce.admin_qna_10') }}</label>
			<button type="submit" class="rsva-btn">{{ lang('commerce.admin_qna_11') }}</button>
		</form>
		@if (!count($qna_inquiries))
		<p style="color:#8b95a1">{{ $qna_unanswered ? lang('commerce.adm_no_unanswered') : lang('commerce.adm_no_inquiries') }}</p>
		@else
		<table class="rsva-table">
			<thead><tr><th>{{ lang('commerce.admin_qna_3') }}</th><th>{{ lang('commerce.admin_qna_4') }}</th><th style="width:50%">{{ lang('commerce.admin_qna_6') }}</th><th>{{ lang('commerce.admin_qna_7') }}</th><th></th></tr></thead>
			<tbody>
				@foreach ($qna_inquiries as $iq)
				<tr>
					<td>
						<strong>{{ $qna_item_names[(int)$iq->item_srl] ?? ('#' . $iq->item_srl) }}</strong>
						@if ($iq->is_secret === 'Y')<small style="color:#8b95a1"> {{ lang('commerce.admin_qna_12') }}</small>@endif
						@if (empty($iq->answer))<small style="color:#d9534f"> {{ lang('commerce.admin_qna_13') }}</small>@endif
					</td>
					<td>{{ $iq->nick_name }}</td>
					<td>
						<div style="white-space:pre-wrap;margin-bottom:8px">{{ $iq->content }}</div>
						<form action="./" method="post" style="display:flex;gap:8px">
							<input type="hidden" name="module" value="commerce" />
							<input type="hidden" name="act" value="procCommerceAdminInquiryAnswer" />
							<input type="hidden" name="inquiry_srl" value="{{ $iq->inquiry_srl }}" />
							<textarea name="answer" rows="3" placeholder="{{ lang('commerce.admin_qna_14') }}" style="flex:1;resize:vertical">{{ $iq->answer }}</textarea>
							<button type="submit" class="rsva-btn rsva-btn-sm" style="align-self:flex-end">{{ empty($iq->answer) ? lang('commerce.adm_reply_add') : lang('commerce.adm_reply_edit') }}</button>
						</form>
					</td>
					<td><small>{{ zdate($iq->regdate, 'Y.m.d') }}</small></td>
					<td>
						<form action="./" method="post" onsubmit="return confirm('{{ lang('commerce.adm_inquiry_delete_ask') }}')">
							<input type="hidden" name="module" value="commerce" />
							<input type="hidden" name="act" value="procCommerceInquiryDelete" />
							<input type="hidden" name="inquiry_srl" value="{{ $iq->inquiry_srl }}" />
							<button type="submit" class="rsva-btn rsva-btn-sm">{{ lang('commerce.admin_qna_8') }}</button>
						</form>
					</td>
				</tr>
				@endforeach
			</tbody>
		</table>
		@endif
	</div>
</div>
