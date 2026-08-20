<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 커머스 설정.
 */
class Config
{
	/**
	 * 설정 기본값. 설정하지 않은 항목도 값이 있도록 여기에 모아 둔다.
	 */
	public const DEFAULTS = [
		'enabled' => 'Y',
		// single(독립몰) / open(오픈마켓)
		'market_mode' => 'single',
		'code_prefix' => 'O',
		// 비회원 주문 허용
		'allow_guest' => 'Y',
		// 결제 대기 주문 유지 시간(분) — 지나면 만료·재고 반환
		'pending_minutes' => 60,
		// 기본 배송비 (판매자 정책이 없을 때)
		'default_ship_fee' => 3000,
		// 이 금액 이상 무료배송 (0 = 사용 안 함)
		'free_ship_over' => 50000,
		// 도서·산간 등 지역 추가 배송비 (JSON: [{name, zips, fee}], zips는 접두/범위 쉼표 구분)
		'ship_extra_zones' => '[]',
		// 스윗트래커(스마트택배) API 키 — 입력하면 송장 기준 배송 상태 자동 추적
		'sweettracker_api_key' => '',
		// 리뷰 작성 적립금 (상품당 첫 리뷰 1회, 0 = 미지급)
		'review_credit_text' => 0,
		'review_credit_photo' => 0,
		// 취소·반품 신청 가능 기간(배송완료 후 일수)
		'claim_days' => 7,
		// 상품 상세: 우측 플로팅(스티키) 구매 박스 사용 여부
		'item_sticky' => 'N',
		// 쇼핑 메인: list(상품 목록) / home(배너·섹션 구성 홈)
		'shop_main' => 'list',
		// 카테고리 배치: top(상단 셀렉트) / side(좌측 사이드바)
		'category_layout' => 'top',
		// 쇼핑 홈 섹션 on/off 와 섹션당 노출 개수
		'home_show_recommend' => 'Y',
		'home_show_new' => 'Y',
		'home_show_popular' => 'Y',
		'home_show_sale' => 'Y',
		'home_count' => 8,
		// 쇼핑 홈 배너 (JSON 배열: [{image,title,text,url}])
		'home_banners' => '[]',
		// 개인정보
		'privacy_text' => '주문 처리를 위해 이름, 연락처, 배송지 정보를 수집합니다. 수집된 정보는 주문 이행 및 배송 목적으로만 사용됩니다.',
		'privacy_version' => '1.0',
		'retention_days' => 1825,
		// 적립금 — 자체 원장 (코어 point 는 커뮤니티 포인트라 연동하지 않는다)
		// 결제 완료 시 실결제 상품 금액의 % 적립 (0 = 사용 안 함)
		'credit_rate' => 1,
		// 최소 사용 단위 (0 = 제한 없음)
		'credit_min_use' => 0,
		// 재고 부족 알림 — 줄마다 기준을 정하지 않았을 때 쓰는 기본값. 0 = 알리지 않음
		'low_stock_default' => 5,
		'notify_low_stock' => 'Y',
		// 알림
		'notify_admin' => 'N',
		// 관리자 알림 받는 메일. 쉼표나 줄바꿈으로 여러 개
		'notify_admin_email' => '',
		// 관리자 알림을 함께 받을 회원그룹 (0 = 쓰지 않음).
		// 담당자가 바뀌어도 그룹만 관리하면 된다
		'notify_admin_group' => 0,
		// 사건별 발송 여부. admin_* 은 운영자, buyer_* 는 구매자
		'notify_admin_new_order' => 'Y',
		'notify_admin_claim' => 'Y',
		'notify_buyer_received' => 'Y',
		'notify_buyer_paid' => 'Y',
		'notify_buyer_shipping' => 'Y',
		'notify_buyer_delivered' => 'N',
		'notify_buyer_claim_done' => 'Y',
		// 거래명세서에 찍히는 판매자 정보
		'biz_name' => '',
		'biz_ceo' => '',
		'biz_number' => '',
		'biz_address' => '',
		'biz_tel' => '',
		'biz_note' => '',
		// 거래명세서 하단에 찍는 로고 이미지 주소
		'biz_logo' => '',
		// 사업자 구분 — taxable(과세) / exempt(면세) / simplified(간이)
		'biz_tax_mode' => 'taxable',
		// 부가세율 (%)
		'vat_rate' => 10,
		// 상품 가격이 부가세 포함가인지 (Y = 역산, N = 표시가에 가산)
		'price_includes_tax' => 'Y',
		// 쇼핑몰이 자리한 나라. 배송지가 이 나라면 국내 주문으로 본다.
		// 주소에 나라 이름을 붙일지, 수출(영세율)로 볼지가 여기서 갈린다
		'base_country' => 'KR',
		// 해외 배송 — 켜면 주문서에 배송 국가를 고르게 하고, 국외 주문은 영세율로 본다
		'allow_overseas' => 'N',
		// 배송지 입력 방식 — kr(우편번호 검색) / intl(자유 입력) / both(국가에 따라 전환)
		'address_mode' => 'kr',
		// 받는 사람 연락처에 국가번호 칸을 둘지 — auto(해외배송 켤 때만) / Y / N
		'use_phone_cc' => 'auto',
		// 주·도를 반드시 고르게 할지. 추가 배송비를 주·도로 나눠 받는 곳에서 쓴다
		'require_state' => 'N',
		'use_coupon' => 'Y',
		'use_credit' => 'Y',
		// 배송완료 뒤 며칠이 지나면 구매확정으로 볼지. 0 = 쓰지 않음(구매자가 직접 누른다).
		// 청약철회 기간이 나라마다 달라 기본은 꺼 둔다
		'auto_confirm_days' => 0,
		// 다통화 판매 — KRW 외 판매 통화 목록 (예: ['USD', 'JPY']). 환율은 짓미페이 공용 환율을 쓴다
		'currencies' => [],
		// 외화 가격 미등록 상품 처리 — convert(환율 자동 환산) / none(그 통화로 판매 안 함)
		'currency_fallback' => 'convert',
	];

	/**
	 * @var ?object
	 */
	protected static $_config = null;

	/**
	 * 설정 읽기 (빠진 키는 기본값).
	 *
	 * @return object
	 */
	public static function getConfig(): object
	{
		if (self::$_config !== null)
		{
			return self::$_config;
		}

		$config = \ModuleModel::getModuleConfig('commerce');
		if (!is_object($config))
		{
			$config = new \stdClass;
		}
		foreach (self::DEFAULTS as $key => $value)
		{
			if (!isset($config->{$key}))
			{
				$config->{$key} = $value;
			}
		}

		return self::$_config = $config;
	}

	/**
	 * 설정 저장.
	 *
	 * @param object $config
	 * @return object
	 */
	public static function setConfig(object $config): object
	{
		$output = \ModuleController::getInstance()->updateModuleConfig('commerce', $config);
		self::$_config = null;
		return $output;
	}
}
