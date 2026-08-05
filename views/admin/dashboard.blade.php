@include('_tabs')

<div class="rsva">
	<div class="rsva-panel">
		<h3>시작하기</h3>
		<p style="margin:0;font-size:13px;color:#6b7684;line-height:1.8">
			1. <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminCategories') }}">카테고리</a>를 만들고
			2. <a href="{{ getUrl('', 'module', 'admin', 'act', 'dispCommerceAdminItemEdit') }}">상품을 등록</a>하세요.
			상점 주소는 <strong>/{{ $shop_mid }}</strong> 입니다. 주문·매출 요약은 주문 기능(C6)과 함께 채워집니다.
		</p>
	</div>
</div>
