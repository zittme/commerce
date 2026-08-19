<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Config as ConfigModel;

/**
 * 설치와 업데이트.
 *
 * 설치 시 (1) 설정 기본값, (2) 기본 판매자(seller_srl 자동, 사이트 운영자),
 * (3) 기본 인스턴스(shop mid)를 만든다. 단일 인스턴스 모델.
 */
class Install extends Base
{
	/**
	 * 최초 스키마 이후 추가된 칼럼. [테이블, 칼럼, 타입, 길이]
	 * 스키마 XML 에 칼럼을 추가하면 여기에도 적어야 기존 설치본에 반영된다.
	 */
	public const ADDED_COLUMNS = [
		// POS 등 채널 확장용 — 최초 설치본에는 스키마에 포함, 기존 설치본에는 여기서 붙인다
		['commerce_order', 'channel', 'varchar', 10],
		// 옵션 2종 구분 (basic: 변형 / extra: 추가상품)
		['commerce_item_option', 'option_type', 'varchar', 10],
		// 상품에 붙일 뱃지 (badge_srl 목록, 쉼표 구분)
		['commerce_item', 'badges', 'varchar', 250],
		// 리뷰 사진·영상 첨부 (JSON URL 배열)
		['commerce_review', 'images', 'text', null],
		// 판매기간·구매수량·세금·성인·갤러리
		['commerce_item', 'images', 'text', null],
		['commerce_item', 'sale_start', 'char', 14],
		['commerce_item', 'sale_end', 'char', 14],
		['commerce_item', 'min_qty', 'int', null],
		['commerce_item', 'max_qty', 'int', null],
		['commerce_item', 'tax_type', 'varchar', 10],
		['commerce_item', 'is_adult', 'char', 1],
		// 조합형 옵션 (축 정의 + 조합 매칭 키)
		['commerce_item', 'option_axes', 'text', null],
		['commerce_item_option', 'combo', 'varchar', 250],
		['commerce_item', 'option_mode', 'varchar', 10],
		// 세금 표기 (주문 시점 과세 구분 스냅샷 + 배송 국가)
		['commerce_order_item', 'tax_type', 'varchar', 10],
		['commerce_order_address', 'country', 'varchar', 2],
		// 해외 배송지 입력
		['commerce_order_address', 'phone_cc', 'varchar', 6],
		['commerce_order_address', 'state', 'varchar', 80],
		['commerce_order_address', 'city', 'varchar', 80],
		['commerce_address', 'phone_cc', 'varchar', 6],
		['commerce_address', 'country', 'varchar', 2],
		['commerce_address', 'state', 'varchar', 80],
		['commerce_address', 'city', 'varchar', 80],
		// 적립금
		['commerce_order', 'credit_used', 'bigint', null],
		// 등급별 상품 할인 (정액/정률)
		['commerce_grade', 'discount_type', 'varchar', 10],
		['commerce_grade', 'discount_value', 'float', null],
		// 등급에 걸어 두는 코어 회원그룹
		['commerce_grade', 'group_srl', 'bigint', null],
		// 다통화 - 주문 통화와 체결 시점 환율
		['commerce_order', 'currency', 'varchar', 8],
		['commerce_order', 'exchange_rate', 'varchar', 16],
		// 가격순 정렬 전용 실판매가
		['commerce_item', 'effective_price', 'bigint', null],
		// 주문 시점 SKU 스냅샷
		['commerce_order_item', 'sku', 'varchar', 100],
		// 리뷰는 주문건 단위
		['commerce_review', 'order_srl', 'bigint', null],
	];

	/**
	 * 최초 설치.
	 */
	public function moduleInstall()
	{
		$this->prepareConfig();
		self::createDefaultSeller();
		self::createDefaultInstance();
		self::enableMemberPhoneField();
		return new \BaseObject();
	}

	/**
	 * 회원 가입폼의 연락처(phone_number) 항목을 활성화한다.
	 * 주문 시 연락처를 회원 정보에 저장하는 기능이 회원 화면에서도 보이게 하기 위함.
	 */
	protected static function enableMemberPhoneField(): void
	{
		try
		{
			$member_config = \ModuleModel::getModuleConfig('member');
			if (!is_object($member_config) || !isset($member_config->signupForm) || !is_array($member_config->signupForm))
			{
				return;
			}
			$changed = false;
			foreach ($member_config->signupForm as $field)
			{
				if (is_object($field) && ($field->name ?? '') === 'phone_number' && ($field->isUse ?? false) !== true)
				{
					$field->isUse = true;
					$changed = true;
				}
			}
			if ($changed)
			{
				\ModuleController::getInstance()->insertModuleConfig('member', $member_config);
			}
		}
		catch (\Exception $e)
		{
			// 회원 설정을 못 만져도 커머스 설치는 계속한다
		}
	}

	/**
	 * 업데이트가 필요한가.
	 */
	public function checkUpdate()
	{
		$config = \ModuleModel::getModuleConfig('commerce');
		if (!is_object($config) || !isset($config->enabled))
		{
			return true;
		}
		if (!self::getDefaultSeller())
		{
			return true;
		}
		if (!self::getDefaultInstance())
		{
			return true;
		}

		$oDB = \DB::getInstance();
		foreach (self::ADDED_COLUMNS as [$table, $column])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				return true;
			}
		}

		// 뒤에 추가된 테이블
		foreach (['commerce_coupon', 'commerce_coupon_issue', 'commerce_credit_balance', 'commerce_credit_log', 'commerce_grade', 'commerce_member_grade', 'commerce_stock_log', 'commerce_review', 'commerce_inquiry', 'commerce_address', 'commerce_tracking', 'commerce_promotion', 'commerce_promotion_item', 'commerce_badge', 'commerce_item_price'] as $table)
		{
			if (!$oDB->isTableExists($table))
			{
				return true;
			}
		}

		// 등급 적립률이 아직 정수형이면 소수점 변환이 필요하다
		if ($oDB->isTableExists('commerce_grade'))
		{
			try
			{
				if (self::isGradeRateInt())
				{
					return true;
				}
			}
			catch (\Exception $e)
			{
			}
		}

		// 진열 순서가 비어 있는 상품이 남아 있으면 채워야 한다
		if ($oDB->isTableExists('commerce_item') && self::hasUnorderedItems())
		{
			return true;
		}

		return false;
	}

	/**
	 * 진열 순서가 아직 0 인 상품이 있는가.
	 *
	 * 목록 기본 정렬이 진열 순서이므로, 예전에 등록된 상품도 값을 채워 줘야
	 * 등록한 차례대로 보인다.
	 *
	 * @return bool
	 */
	protected static function hasUnorderedItems(): bool
	{
		try
		{
			// 0 = 미정렬, list_order = item_srl(양수) = 과거 등록순 보정값. 둘 다 최신-앞 규약(-srl)으로 바꿔야 한다
			$stmt = \Zittme\Framework\DB::getInstance()->getHandle()
				->query('SELECT COUNT(*) FROM `' . self::dbPrefix() . 'commerce_item` WHERE list_order = 0 OR list_order = item_srl');
			if (!$stmt)
			{
				return false;
			}
			$count = (int)$stmt->fetchColumn();
			$stmt->closeCursor();
			return $count > 0;
		}
		catch (\Throwable $e)
		{
			return false;
		}
	}

	/**
	 * 업데이트 실행.
	 */
	public function moduleUpdate()
	{
		$this->prepareConfig();
		self::createDefaultSeller();
		self::createDefaultInstance();
		self::enableMemberPhoneField();

		$oDB = \DB::getInstance();
		foreach (self::ADDED_COLUMNS as [$table, $column, $type, $size])
		{
			if (!$oDB->isColumnExists($table, $column))
			{
				$oDB->addColumn($table, $column, $type, $size);
			}
		}

		// 등급 적립률 소수점 지원 (int → DECIMAL(6,2)) — 이미 변환됐으면 no-op
		if ($oDB->isTableExists('commerce_grade'))
		{
			try
			{
				if (self::isGradeRateInt())
				{
					// SHOW/ALTER 는 프레임워크의 자동 프리픽스 재작성과 충돌하므로 PDO 핸들로 직접 실행
					\Zittme\Framework\DB::getInstance()->getHandle()->exec(
						'ALTER TABLE `' . self::dbPrefix() . 'commerce_grade` MODIFY `credit_rate` DECIMAL(6,2) NOT NULL DEFAULT 0'
					);
				}
			}
			catch (\Exception $e)
			{
				// 변환 실패는 치명적이지 않다 (정수 적립률로 계속 동작)
			}
		}

		// 진열 순서가 비어 있는 상품은 최신이 앞에 오도록 -번호를 넣는다
		if ($oDB->isTableExists('commerce_item') && self::hasUnorderedItems())
		{
			try
			{
				\Zittme\Framework\DB::getInstance()->getHandle()
					->exec('UPDATE `' . self::dbPrefix() . 'commerce_item` SET list_order = -item_srl WHERE list_order = 0 OR list_order = item_srl');
			}
			catch (\Throwable $e)
			{
			}
		}

		// 연결이 비어 있는 리뷰에 확정 주문을 채운다
		Base::backfillReviewOrders();

		// 실판매가 채우기 - 신규 저장 시에는 저장 경로가 채운다
		if ($oDB->isTableExists('commerce_item'))
		{
			try
			{
				\Zittme\Framework\DB::getInstance()->getHandle()
					->exec('UPDATE `' . self::dbPrefix() . 'commerce_item` SET effective_price = CASE WHEN sale_price > 0 THEN sale_price ELSE price END WHERE effective_price = 0 OR effective_price IS NULL');
			}
			catch (\Throwable $e)
			{
			}
		}

		return new \BaseObject();
	}

	/**
	 * 캐시 재생성.
	 */
	public function recompileCache()
	{
	}

	/**
	 * DB 테이블 프리픽스.
	 *
	 * @return string
	 */
	public static function dbPrefix(): string
	{
		return (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
	}

	/**
	 * commerce_grade.credit_rate 가 아직 정수형인가 (소수점 변환 필요 여부).
	 *
	 * SHOW COLUMNS 는 프레임워크 자동 프리픽스 재작성과 충돌하므로 PDO 핸들로 직접 조회한다.
	 *
	 * @return bool
	 */
	protected static function isGradeRateInt(): bool
	{
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->query(
			'SHOW COLUMNS FROM `' . self::dbPrefix() . 'commerce_grade` LIKE \'credit_rate\''
		);
		$col = null;
		if ($stmt)
		{
			$col = $stmt->fetchObject() ?: null;
			// 뒤이어 ALTER 를 실행하므로 커서를 반드시 닫는다
			$stmt->closeCursor();
		}
		return $col && stripos((string)$col->Type, 'int') !== false;
	}

	/**
	 * 설정 기본값 저장.
	 *
	 * @return void
	 */
	protected function prepareConfig(): void
	{
		$config = \ModuleModel::getModuleConfig('commerce');
		if (!is_object($config))
		{
			$config = new \stdClass;
		}

		$changed = false;
		foreach (ConfigModel::DEFAULTS as $key => $value)
		{
			if (!isset($config->{$key}))
			{
				$config->{$key} = $value;
				$changed = true;
			}
		}

		if ($changed)
		{
			ConfigModel::setConfig($config);
		}
	}

	/**
	 * 기본 판매자(사이트 운영자)를 만든다. 이미 있으면 아무것도 하지 않는다.
	 *
	 * 독립몰 모드에서도 order_seller 계층이 항상 이 판매자를 가리킨다 —
	 * 오픈마켓 전환 시 코드 변경이 없는 이유.
	 *
	 * @return void
	 */
	protected static function createDefaultSeller(): void
	{
		if (self::getDefaultSeller())
		{
			return;
		}

		executeQuery('commerce.insertSeller', (object)[
			'seller_srl' => getNextSequence(),
			'member_srl' => 0,
			'shop_name' => \Context::getSiteTitle() ?: 'Shop',
			'commission_rate' => 0,
			'status' => 'active',
			'regdate' => self::now(),
		]);
	}

	/**
	 * 기본 인스턴스(shop mid)를 만든다. 이미 있으면 아무것도 하지 않는다.
	 *
	 * @return void
	 */
	protected static function createDefaultInstance(): void
	{
		if (self::getDefaultInstance())
		{
			return;
		}

		$mid = self::DEFAULT_MID;
		if (\ModuleModel::isIDExists($mid))
		{
			$mid = \ModuleModel::getNextAvailableMid($mid) ?: ($mid . '_' . time());
		}

		\ModuleController::getInstance()->insertModule((object)[
			'mid' => $mid,
			'module' => 'commerce',
			'browser_title' => lang('commerce.commerce') ?: 'Shop',
			'description' => '',
			'layout_srl' => -1,
			'mlayout_srl' => -1,
			'skin' => '/USE_DEFAULT/',
			'mskin' => '/USE_DEFAULT/',
			'isMenuCreate' => false,
		]);

		self::$_default_instance = null;
	}
}
