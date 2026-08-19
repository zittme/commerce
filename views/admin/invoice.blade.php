@php
	$biz = $shop_config;
	$zmi_status = ['pending'=>lang('commerce.st_order_pending'),'paid'=>lang('commerce.st_order_paid'),'cancelled'=>lang('commerce.st_order_cancelled'),'failed'=>lang('commerce.st_order_failed'),'expired'=>lang('commerce.st_order_expired')];
@endphp

<div class="zmi-page">
	<div class="zmi-toolbar">
		@if (count($invoices) > 1)<span class="zmi-count">{{ sprintf(lang('commerce.st_unit_count'), count($invoices)) }}</span>@endif
		<button type="button" class="zmi-btn zmi-btn-primary" onclick="window.print()">{{ lang('commerce.admin_invoice_1') }}</button>
		<button type="button" class="zmi-btn" onclick="window.close()">{{ lang('commerce.admin_invoice_2') }}</button>
	</div>

	@foreach ($invoices as $inv)
	@php
		$order = $inv->order;
		$order_items = $inv->items;
		$order_address = $inv->address;
		$discount = (int)$order->discount_total;
		$credit = (int)$order->credit_used;
		$tax = $inv->tax;
		$show_tax = $tax->enabled;
		$ship = null;
	@endphp
	{{-- 템플릿 @php 안에서는 루프를 쓰지 않는다 (템플릿 v2 규약) --}}
	@foreach ($inv->sellers as $os)
		@if ($ship === null && $os->shipping_invoice)
		@php $ship = $os; @endphp
		@endif
	@endforeach
	<div class="zmi-sheet">
		<div class="zmi-head">
			<h1>{{ lang('commerce.admin_invoice_3') }}</h1>
			<div class="zmi-code">
				<div><b>{{ $order->order_code }}</b></div>
				<div>{{ substr((string)$order->regdate, 0, 4) }}-{{ substr((string)$order->regdate, 4, 2) }}-{{ substr((string)$order->regdate, 6, 2) }}</div>
			</div>
		</div>

		@if ($biz->biz_name || $biz->biz_number || $biz->biz_address || $biz->biz_tel)
		<table class="zmi-tbl zmi-biz">
			<tbody>
				@if ($biz->biz_name || $biz->biz_ceo)
				<tr>
					<th>{{ lang('commerce.admin_invoice_4') }}</th><td>{{ $biz->biz_name }}</td>
					<th>{{ lang('commerce.admin_invoice_5') }}</th><td>{{ $biz->biz_ceo }}</td>
				</tr>
				@endif
				@if ($biz->biz_number || $biz->biz_tel)
				<tr>
					<th>{{ lang('commerce.admin_invoice_6') }}</th><td>{{ $biz->biz_number }}</td>
					<th>{{ lang('commerce.admin_invoice_7') }}</th><td>{{ $biz->biz_tel }}</td>
				</tr>
				@endif
				@if ($biz->biz_address)
				<tr><th>{{ lang('commerce.admin_invoice_8') }}</th><td colspan="3">{{ $biz->biz_address }}</td></tr>
				@endif
			</tbody>
		</table>
		@endif

		<div class="zmi-cols">
			<div class="zmi-col">
				<h2>{{ lang('commerce.admin_invoice_9') }}</h2>
				<table class="zmi-tbl">
					<tbody>
						{{-- 주문번호는 머리글 오른쪽에 크게 나온다. 여기 또 적지 않는다 --}}
						<tr><th>{{ lang('commerce.admin_invoice_11') }}</th><td>{{ $zmi_status[$order->status] ?? $order->status }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_12') }}</th><td>{{ $order->orderer_name }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_13') }}</th><td>{{ $order->orderer_phone }}</td></tr>
						@if ($order->orderer_email)
						<tr><th>{{ lang('commerce.admin_invoice_14') }}</th><td>{{ $order->orderer_email }}</td></tr>
						@endif
					</tbody>
				</table>
			</div>
			<div class="zmi-col">
				<h2>{{ lang('commerce.admin_invoice_15') }}</h2>
				<table class="zmi-tbl">
					<tbody>
						@if ($order_address)
						@if (($order_address->country ?? 'KR') !== 'KR')
						<tr><th>{{ lang('commerce.admin_invoice_16') }}</th><td>{{ $inv->country_name }}</td></tr>
						@endif
						<tr><th>{{ lang('commerce.admin_invoice_17') }}</th><td>{{ $order_address->receiver_name }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_13') }}</th><td>{{ $inv->phone_text }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_8') }}</th><td>{{ $inv->address_text }}</td></tr>
						<tr><th>{{ lang('commerce.admin_invoice_18') }}</th><td>{{ $order_address->delivery_memo }}</td></tr>
						@else
						<tr><td colspan="2">{{ lang('commerce.admin_invoice_19') }}</td></tr>
						@endif
						@if ($ship)
						<tr><th>{{ lang('commerce.admin_invoice_20') }}</th><td>{{ $ship->shipping_company }} {{ $ship->shipping_invoice }}</td></tr>
						@endif
					</tbody>
				</table>
			</div>
		</div>

		<h2>{{ lang('commerce.adm_invoice_items') }} @if ($show_tax && $tax->zero_rated)<span class="zmi-tag">{{ lang('commerce.admin_invoice_21') }}</span>@endif</h2>
		<table class="zmi-tbl zmi-items">
			@php
				$zmi_has_sku = false;
				foreach ($order_items as $zmi_sku_row)
				{
					$zmi_has_sku = $zmi_has_sku || trim((string)($zmi_sku_row->sku ?? '')) !== '';
				}
				// 상품명 줄이 표 전체를 가로지른다. 아래 줄의 칸 수와 맞춰야 어긋나지 않는다
				$zmi_cols = 4 + ($zmi_has_sku ? 1 : 0) + ($show_tax ? 2 : 0);
			@endphp
			<thead>
				<tr>
					<th class="zmi-c" style="width:34px">No</th>
					@if ($zmi_has_sku)<th class="zmi-c" style="width:110px">SKU</th>@endif
					<th class="zmi-c" style="width:52px">{{ lang('commerce.admin_invoice_23') }}</th>
					<th class="zmi-r" style="width:92px">{{ lang('commerce.admin_invoice_24') }}</th>
					@if ($show_tax)
					<th class="zmi-r" style="width:100px">{{ lang('commerce.admin_invoice_25') }}</th>
					<th class="zmi-r" style="width:84px">{{ lang('commerce.admin_invoice_26') }}</th>
					@endif
					<th class="zmi-r">{{ lang('commerce.admin_invoice_27') }}</th>
				</tr>
			</thead>
			<tbody>
				@foreach ($order_items as $i => $oi)
				{{-- 상품명이 길면 한 줄짜리 표가 통째로 높아진다. 이름을 윗줄로 빼 상품마다 두 줄로 맞춘다 --}}
				<tr class="zmi-item-name">
					<td colspan="{{ $zmi_cols }}">
						{{ $oi->item_name }}@if (($oi->tax_type ?? 'taxable') === 'free')<span class="zmi-tag">{{ lang('commerce.admin_invoice_28') }}</span>@endif
						@if ($oi->option_name)<span class="zmi-opt">{{ $oi->option_name }}</span>@endif
					</td>
				</tr>
				<tr class="zmi-item-num">
					<td class="zmi-c">{{ $i + 1 }}</td>
					@if ($zmi_has_sku)<td class="zmi-c">{{ $oi->sku ?? '' }}</td>@endif
					<td class="zmi-c">{{ $oi->qty }}</td>
					<td class="zmi-r">{{ shop_money_in($oi->price, $order->currency ?? 'KRW') }}</td>
					@if ($show_tax)
					<td class="zmi-r">{{ shop_money_in($tax->lines[$i]->supply, $order->currency ?? 'KRW') }}</td>
					<td class="zmi-r">{{ $tax->lines[$i]->free ? '-' : shop_money_in($tax->lines[$i]->vat, $order->currency ?? 'KRW') }}</td>
					@endif
					<td class="zmi-r">{{ shop_money_in($oi->subtotal, $order->currency ?? 'KRW') }}</td>
				</tr>
				@endforeach
			</tbody>
		</table>

		{{-- 항목마다 한 줄을 쓰면 장수가 늘어난다. 두 칸씩 접어 담고 결제 금액만 따로 둔다 --}}
		<div class="zmi-sum">
			<div class="zmi-sum-grid">
				@if ($show_tax)
				<div class="zmi-sum-cell"><span>{{ lang('commerce.admin_invoice_29') }}</span><b>{{ shop_money_in($tax->taxable_supply, $order->currency ?? 'KRW') }}</b></div>
				@if ($tax->free_supply > 0)
				<div class="zmi-sum-cell"><span>{{ $tax->zero_rated ? lang('commerce.adm_supply_zero') : lang('commerce.adm_supply_free') }}</span><b>{{ shop_money_in($tax->free_supply, $order->currency ?? 'KRW') }}</b></div>
				@endif
				<div class="zmi-sum-cell"><span>{{ lang('commerce.admin_invoice_30') }}</span><b>{{ shop_money_in($tax->delivery_supply, $order->currency ?? 'KRW') }}</b></div>
				<div class="zmi-sum-cell"><span>{{ lang('commerce.admin_invoice_31') }}</span><b>{{ shop_money_in($tax->total_vat, $order->currency ?? 'KRW') }}</b></div>
				@else
				<div class="zmi-sum-cell"><span>{{ lang('commerce.admin_invoice_32') }}</span><b>{{ shop_money_in($order->item_total, $order->currency ?? 'KRW') }}</b></div>
				<div class="zmi-sum-cell"><span>{{ lang('commerce.admin_invoice_33') }}</span><b>{{ shop_money_in($order->delivery_fee_total, $order->currency ?? 'KRW') }}</b></div>
				@endif
				@if ($discount > 0)
				<div class="zmi-sum-cell"><span>{{ lang('commerce.admin_invoice_34') }}</span><b>-{{ shop_money_in($discount, $order->currency ?? 'KRW') }}</b></div>
				@endif
				@if ($credit > 0)
				<div class="zmi-sum-cell"><span>{{ lang('commerce.admin_invoice_35') }}</span><b>-{{ shop_money_in($credit, $order->currency ?? 'KRW') }}</b></div>
				@endif
			</div>
			<div class="zmi-sum-total"><span>{{ lang('commerce.admin_invoice_36') }}</span><b>{{ shop_money_in($order->payment_price, $order->currency ?? 'KRW') }}</b></div>
		</div>
		@if ($show_tax && !$tax->zero_rated)
		<p class="zmi-taxnote">{{ sprintf($tax->included ? lang('commerce.adm_tax_note_incl') : lang('commerce.adm_tax_note_excl'), (int)round($tax->rate * 100)) }}</p>
		@endif

		@if ($order->memo)
		<div class="zmi-memo"><b>{{ lang('commerce.admin_invoice_37') }}</b> {{ $order->memo }}</div>
		@endif

		@if ($biz->biz_note || !empty($biz->biz_logo))
		<div class="zmi-foot">
			@if ($biz->biz_note)
			<div class="zmi-note">{{ $biz->biz_note }}</div>
			@endif
			@if (!empty($biz->biz_logo))
			<img class="zmi-logo" src="{{ $biz->biz_logo }}" alt="{{ $biz->biz_name }}" />
			@endif
		</div>
		@endif
	</div>
	@endforeach
</div>




<style>
/*
 * 거래명세서 — 종이는 A4(210x297mm) 고정. 좁은 화면에서는 통째로 축소해 비율을 지킨다.
 * 칸마다 선을 두르지 않는다. 대신 덩어리는 제목·여백·바탕으로 확실히 나눈다.
 */
.zmi-page {
	--zmi-ink: #16202e;
	--zmi-sub: #6b7684;
	--zmi-line: #e4e8ed;
	--zmi-rule: #aab2bd;
	--zmi-fill: #f4f6f9;
	--zmi-accent: #2677e3;
	background: #eef1f5; min-height: 100vh; padding: 22px 0 64px;
	font-family: Pretendard, -apple-system, BlinkMacSystemFont, "Segoe UI", "Malgun Gothic", sans-serif;
	color: var(--zmi-ink);
}
.zmi-toolbar { width: 210mm; max-width: 100%; margin: 0 auto 14px; padding: 0 10px; display: flex; gap: 8px; align-items: center; justify-content: flex-end; box-sizing: border-box; }
.zmi-count { margin-right: auto; font-size: 13px; color: var(--zmi-sub); }
.zmi-btn { padding: 9px 18px; border: 1px solid #dfe3e9; border-radius: 8px; background: #fff; color: var(--zmi-ink); font-family: inherit; font-size: 14px; cursor: pointer; }
.zmi-btn:hover { border-color: #b9c0ca; }
/* 관리자 화면의 button 규칙이 글자색을 덮어써서 파란 바탕에 검은 글자가 된다 */
.zmi-page button.zmi-btn-primary,
.zmi-page button.zmi-btn-primary:hover,
.zmi-page button.zmi-btn-primary:focus { border-color: var(--zmi-accent); background: var(--zmi-accent); color: #fff; font-weight: 700; }

.zmi-sheet {
	position: relative; width: 210mm; min-height: 297mm; margin: 0 auto 20px;
	padding: 18mm 16mm 16mm; background: #fff; box-sizing: border-box;
	box-shadow: 0 1px 2px rgba(16,24,40,.07), 0 14px 40px -18px rgba(16,24,40,.4);
	display: flex; flex-direction: column;
}

/* 머리 */
.zmi-head { display: flex; align-items: baseline; justify-content: space-between; gap: 20px; padding-bottom: 7px; margin-bottom: 4px; border-bottom: 2px solid var(--zmi-ink); }
.zmi-head h1 { margin: 0; font-size: 21px; font-weight: 700; letter-spacing: 5px; }
.zmi-code { text-align: right; font-size: 11.5px; color: var(--zmi-sub); line-height: 1.7; }
.zmi-code b { display: block; font-size: 13px; font-weight: 700; color: var(--zmi-ink); letter-spacing: .4px; }

/* 구획 제목 — 아래에 짧은 선을 두어 다음 덩어리가 시작됨을 보인다 */
.zmi-sheet h2 {
	margin: 15px 0 0; padding-bottom: 5px;
	border-bottom: 1.5px solid var(--zmi-rule);
	font-size: 12.5px; font-weight: 700; letter-spacing: .5px; line-height: 1.3;
}

/* 정보 표 — 라벨 칸에만 옅은 바탕. 값과 라벨이 한눈에 갈린다 */
.zmi-tbl { width: 100%; border-collapse: collapse; font-size: 12px; }
.zmi-tbl th, .zmi-tbl td { border: 0; border-bottom: 1px solid var(--zmi-line); padding: 5px 9px; text-align: left; vertical-align: top; line-height: 1.5; }
.zmi-tbl th { width: 76px; background: var(--zmi-fill); font-weight: 600; color: var(--zmi-sub); white-space: nowrap; }
.zmi-tbl tr:last-child th, .zmi-tbl tr:last-child td { border-bottom: 0; }
.zmi-biz { margin-top: 8px; }
.zmi-biz td { padding-right: 14px; }
.zmi-cols { display: flex; gap: 16px; }
.zmi-col { flex: 1; min-width: 0; }
.zmi-col .zmi-tbl { margin-top: 8px; }

/* 품목 — 머리줄에 바탕을 깔아 표가 시작되는 지점을 확실히 한다 */
/* 폭을 정한 대로 쓴다. 남는 폭을 브라우저가 나눠 가지면 머리와 값이 어긋난다 */
.zmi-items { margin-top: 8px; table-layout: fixed; }
.zmi-items thead th { padding: 6px 8px; background: var(--zmi-fill); border-bottom: 1.5px solid var(--zmi-rule); font-size: 11.5px; font-weight: 700; color: var(--zmi-ink); text-align: center; white-space: nowrap; width: auto; }
.zmi-items thead th:last-child { text-align: right; }
.zmi-items tbody td { padding: 6px 8px; border-bottom: 1px solid var(--zmi-line); background: none; line-height: 1.45; }
.zmi-items thead th + th,
.zmi-items tbody tr.zmi-item-num td + td { border-left: 1px solid var(--zmi-line); }

/* 상품명 줄과 숫자 줄이 한 덩어리다. 사이 선을 지우고 상품 사이에만 선을 긋는다 */
.zmi-items tbody tr.zmi-item-name td {
	padding-top: 8px; padding-bottom: 2px;
	border-bottom: 0;
	border-top: 1px solid var(--zmi-line);
	font-weight: 600;
	word-break: keep-all;
	overflow-wrap: break-word;
}
.zmi-items tbody tr.zmi-item-name:first-child td { border-top: 0; }
.zmi-items tbody tr.zmi-item-num td { padding-top: 2px; padding-bottom: 8px; border-bottom: 0; }
/* 한 상품의 두 줄은 쪽을 넘길 때 갈라지면 안 된다 */
.zmi-items tbody tr.zmi-item-name { break-after: avoid; page-break-after: avoid; }
.zmi-items tbody tr:last-child td { border-bottom: 1.5px solid var(--zmi-rule) !important; }
/* 번호 칸만 옅게. 상품명 줄은 한 칸이 전부라 이 규칙이 닿으면 이름까지 옅어진다 */
.zmi-items tbody tr.zmi-item-num td:first-child { color: var(--zmi-sub); }
.zmi-tag { display: inline-block; margin-left: 5px; padding: 0 5px; border-radius: 3px; background: #e9edf2; font-size: 10px; font-weight: 600; color: var(--zmi-sub); vertical-align: middle; }
.zmi-opt { display: block; margin-top: 2px; font-size: 10.5px; line-height: 1.4; color: var(--zmi-sub); }
/* .zmi-tbl td 의 왼쪽 정렬이 더 구체적이라 클래스만으로는 이기지 못한다 */
.zmi-tbl th.zmi-c, .zmi-tbl td.zmi-c { text-align: center; }
.zmi-tbl th.zmi-r, .zmi-tbl td.zmi-r { text-align: right; }
.zmi-c { text-align: center; }
.zmi-r { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }

/* 합계 — 오른쪽에 붙는 정산 덩어리. 옅은 바탕으로 표와 분리한다 */
.zmi-sum { width: 130mm; max-width: 100%; margin: 10px 0 0 auto; background: var(--zmi-fill); }
.zmi-sum-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); }
.zmi-sum-cell {
	display: flex; align-items: baseline; justify-content: space-between; gap: 10px;
	padding: 5px 12px; border-bottom: 1px solid #dde2e8; font-size: 12px;
}
/* 홀수 개면 마지막 칸이 왼쪽에만 남는다. 오른쪽 빈자리에도 선을 이어 준다 */
.zmi-sum-cell:nth-child(odd):last-child { grid-column: 1 / -1; }
.zmi-sum-cell span { color: var(--zmi-sub); font-size: 11.5px; white-space: nowrap; }
.zmi-sum-cell b { font-weight: 600; text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
.zmi-sum-total {
	display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
	padding: 7px 12px; border-top: 1.5px solid var(--zmi-ink);
	font-size: 14.5px; font-weight: 800; color: var(--zmi-ink);
}
.zmi-sum-total b { font-variant-numeric: tabular-nums; white-space: nowrap; }
.zmi-taxnote { margin: 6px 0 0; text-align: right; font-size: 10.5px; color: var(--zmi-sub); }
.zmi-memo { margin-top: 12px; padding: 8px 12px; background: var(--zmi-fill); border-left: 2.5px solid var(--zmi-rule); font-size: 11.5px; line-height: 1.7; color: var(--zmi-sub); }
.zmi-memo b { color: var(--zmi-ink); }

/* 꼬리 */
.zmi-foot { margin-top: auto; padding-top: 10px; display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; }
.zmi-note { flex: 1; min-width: 0; padding-top: 8px; border-top: 1px solid var(--zmi-line); font-size: 10.5px; color: var(--zmi-sub); line-height: 1.6; white-space: pre-line; }
.zmi-logo { flex: 0 0 auto; max-width: 26mm; max-height: 9mm; width: auto; height: auto; object-fit: contain; opacity: .75; }

@media (max-width: 830px) {
	.zmi-page { padding: 14px 0 40px; }
	.zmi-toolbar { width: auto; }
	.zmi-sheet { transform: scale(calc((100vw - 24px) / 210mm)); transform-origin: top center; margin-bottom: calc(20px - 297mm * (1 - (100vw - 24px) / 210mm)); }
}

@media print {
	@page { size: A4; margin: 12mm 12mm; }
	.zmi-page { background: #fff; padding: 0; }
	.zmi-toolbar { display: none; }
	.zmi-sheet { width: auto; min-height: 0; margin: 0; padding: 0; box-shadow: none; transform: none; break-after: page; page-break-after: always; }
	.zmi-sheet:last-child { break-after: auto; page-break-after: auto; }
	.zmi-tbl th, .zmi-items thead th, .zmi-sum, .zmi-memo, .zmi-tag {
		-webkit-print-color-adjust: exact; print-color-adjust: exact;
	}
	.zmi-items tr { break-inside: avoid; page-break-inside: avoid; }
	.zmi-sum, .zmi-foot, .zmi-memo { break-inside: avoid; page-break-inside: avoid; }
}
</style>
