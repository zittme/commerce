<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 매출 통계 집계.
 *
 * 기준
 *  - 매출은 결제완료 시각(paid_date) 기준으로 잡는다. 주문 접수일이 아니다.
 *  - 취소·환불 건은 매출에서 빼고 취소액으로 따로 센다 (취소 시각 기준).
 *  - 집계 쿼리는 XML 쿼리로 표현하기 어려운 GROUP BY·기간 버킷이라 DB 를 직접 쓴다.
 */
class Stats
{
	/**
	 * 기간 버킷 단위.
	 */
	public const UNITS = ['day' => '일', 'week' => '주', 'month' => '월', 'year' => '년'];

	/**
	 * 매출로 인정하는 주문 상태.
	 */
	protected const PAID = "'paid'";

	/**
	 * 결제금액을 KRW 로 환산하는 SQL 식.
	 *
	 * 통계 원장은 KRW 기준이다. 외화 주문은 결제 시점에 박제한 환율(exchange_rate)로
	 * 되돌린다. 금액이 통화 최소단위 정수라 2자리 소수 통화는 100 으로 나눈다.
	 *
	 * @param string $col 금액 컬럼 (예: 'payment_price', 'o.payment_price', 'oi.subtotal')
	 * @param string $alias 주문 테이블 별칭 ('' 이면 별칭 없음)
	 * @return string
	 */
	protected static function krwExpr(string $col, string $alias = ''): string
	{
		$p = $alias !== '' ? $alias . '.' : '';
		return "(CASE WHEN {$p}currency IS NULL OR {$p}currency = '' OR {$p}currency = 'KRW' THEN {$col}"
			. " ELSE ROUND({$col} * CAST(NULLIF({$p}exchange_rate, '') AS DECIMAL(16,4))"
			. " / (CASE WHEN {$p}currency IN ('JPY', 'TWD', 'HUF', 'VND') THEN 1 ELSE 100 END)) END)";
	}

	/**
	 * @return \Rhymix\Framework\Helpers\DBHelper|\Rhymix\Framework\DB
	 */
	protected static function db()
	{
		return \Rhymix\Framework\DB::getInstance();
	}

	/**
	 * YYYYMMDD 를 경계값(시작 000000 / 끝 235959)으로 만든다.
	 *
	 * @param string $date
	 * @param bool $end
	 * @return string
	 */
	public static function bound(string $date, bool $end = false): string
	{
		$date = preg_replace('/[^0-9]/', '', $date);
		$date = substr($date . '00000000', 0, 8);
		return $date . ($end ? '235959' : '000000');
	}

	/**
	 * 기간 요약 — 매출·주문수·평균 주문금액·취소액.
	 *
	 * @param string $from YYYYMMDD
	 * @param string $to YYYYMMDD
	 * @return object
	 */
	public static function summary(string $from, string $to): object
	{
		$f = self::bound($from);
		$t = self::bound($to, true);

		$row = self::db()->query(
			'SELECT COUNT(*) AS cnt, COALESCE(SUM(' . self::krwExpr('payment_price') . '), 0) AS amount
			 FROM commerce_order WHERE status = ? AND paid_date BETWEEN ? AND ?',
			'paid', $f, $t
		)->fetchObject();

		$cancelled = self::db()->query(
			'SELECT COUNT(*) AS cnt, COALESCE(SUM(' . self::krwExpr('payment_price') . '), 0) AS amount
			 FROM commerce_order WHERE status = ? AND cancelled_date BETWEEN ? AND ?',
			'cancelled', $f, $t
		)->fetchObject();

		$orders = (int)($row->cnt ?? 0);
		$sales = (int)($row->amount ?? 0);

		return (object)[
			'orders' => $orders,
			'sales' => $sales,
			'average' => $orders > 0 ? (int)round($sales / $orders) : 0,
			'cancelled_orders' => (int)($cancelled->cnt ?? 0),
			'cancelled_sales' => (int)($cancelled->amount ?? 0),
		];
	}

	/**
	 * 기간 버킷별 매출 추이.
	 *
	 * @param string $from YYYYMMDD
	 * @param string $to YYYYMMDD
	 * @param string $unit day|week|month|year
	 * @return array<int, object> [{bucket, label, orders, sales}]
	 */
	public static function series(string $from, string $to, string $unit = 'day'): array
	{
		if (!isset(self::UNITS[$unit]))
		{
			$unit = 'day';
		}

		if ($unit === 'week')
		{
			$expr = "YEARWEEK(STR_TO_DATE(SUBSTRING(paid_date, 1, 8), '%Y%m%d'), 3)";
		}
		else
		{
			$len = ['day' => 8, 'month' => 6, 'year' => 4][$unit];
			$expr = 'SUBSTRING(paid_date, 1, ' . $len . ')';
		}

		$stmt = self::db()->query(
			'SELECT ' . $expr . ' AS bucket, COUNT(*) AS cnt, COALESCE(SUM(' . self::krwExpr('payment_price') . '), 0) AS amount
			 FROM commerce_order WHERE status = ? AND paid_date BETWEEN ? AND ?
			 GROUP BY bucket ORDER BY bucket ASC',
			'paid', self::bound($from), self::bound($to, true)
		);

		$rows = [];
		while ($row = $stmt->fetchObject())
		{
			$rows[] = (object)[
				'bucket' => (string)$row->bucket,
				'label' => self::label((string)$row->bucket, $unit),
				'orders' => (int)$row->cnt,
				'sales' => (int)$row->amount,
			];
		}
		return $rows;
	}

	/**
	 * 버킷 값을 사람이 읽는 표기로.
	 *
	 * @param string $bucket
	 * @param string $unit
	 * @return string
	 */
	public static function label(string $bucket, string $unit): string
	{
		if ($unit === 'day' && strlen($bucket) === 8)
		{
			return substr($bucket, 0, 4) . '-' . substr($bucket, 4, 2) . '-' . substr($bucket, 6, 2);
		}
		if ($unit === 'month' && strlen($bucket) === 6)
		{
			return substr($bucket, 0, 4) . '-' . substr($bucket, 4, 2);
		}
		if ($unit === 'week' && strlen($bucket) === 6)
		{
			return substr($bucket, 0, 4) . ' ' . (int)substr($bucket, 4, 2) . '주';
		}
		return $bucket;
	}

	/**
	 * 상품별 판매 집계.
	 *
	 * @param string $from
	 * @param string $to
	 * @param int $limit
	 * @return array<int, object> [{item_srl, item_name, qty, sales, orders}]
	 */
	public static function byItem(string $from, string $to, int $limit = 100): array
	{
		$limit = max(1, min(1000, $limit));
		$stmt = self::db()->query(
			'SELECT oi.item_srl, MIN(oi.item_name) AS item_name,
			        SUM(oi.qty) AS qty, SUM(' . self::krwExpr('oi.subtotal', 'o') . ') AS sales, COUNT(DISTINCT oi.order_srl) AS orders
			 FROM commerce_order_item AS oi
			 INNER JOIN commerce_order AS o ON o.order_srl = oi.order_srl
			 WHERE o.status = ? AND o.paid_date BETWEEN ? AND ?
			 GROUP BY oi.item_srl ORDER BY sales DESC LIMIT ' . $limit,
			'paid', self::bound($from), self::bound($to, true)
		);

		$rows = [];
		while ($row = $stmt->fetchObject())
		{
			$rows[] = (object)[
				'item_srl' => (int)$row->item_srl,
				'item_name' => (string)$row->item_name,
				'qty' => (int)$row->qty,
				'sales' => (int)$row->sales,
				'orders' => (int)$row->orders,
			];
		}
		return $rows;
	}

	/**
	 * 배송지 시·도별 집계. 주소 첫 낱말로 판정한다.
	 *
	 * @param string $from
	 * @param string $to
	 * @return array<int, object> [{region, orders, sales}]
	 */
	public static function byRegion(string $from, string $to): array
	{
		$stmt = self::db()->query(
			'SELECT SUBSTRING_INDEX(TRIM(a.address1), " ", 1) AS region,
			        COUNT(*) AS cnt, COALESCE(SUM(' . self::krwExpr('o.payment_price', 'o') . '), 0) AS amount
			 FROM commerce_order AS o
			 INNER JOIN commerce_order_address AS a ON a.order_srl = o.order_srl
			 WHERE o.status = ? AND o.paid_date BETWEEN ? AND ?
			 GROUP BY region',
			'paid', self::bound($from), self::bound($to, true)
		);

		// 서울 / 서울시 / 서울특별시 가 따로 잡히지 않도록 표기를 하나로 모은다
		$merged = [];
		while ($row = $stmt->fetchObject())
		{
			$region = self::normalizeRegion((string)$row->region);
			if (!isset($merged[$region]))
			{
				$merged[$region] = (object)['region' => $region, 'orders' => 0, 'sales' => 0];
			}
			$merged[$region]->orders += (int)$row->cnt;
			$merged[$region]->sales += (int)$row->amount;
		}

		$rows = array_values($merged);
		usort($rows, function($a, $b) { return $b->sales <=> $a->sales; });
		return $rows;
	}

	/**
	 * 시·도 표기 정규화.
	 *
	 * @param string $region
	 * @return string
	 */
	public static function normalizeRegion(string $region): string
	{
		$region = trim($region);
		if ($region === '')
		{
			return '미상';
		}

		$map = [
			'서울' => '서울', '경기' => '경기', '인천' => '인천', '부산' => '부산', '대구' => '대구',
			'광주' => '광주', '대전' => '대전', '울산' => '울산', '세종' => '세종',
			'강원' => '강원', '충북' => '충북', '충남' => '충남', '전북' => '전북', '전남' => '전남',
			'경북' => '경북', '경남' => '경남', '제주' => '제주',
			'충청북도' => '충북', '충청남도' => '충남', '전라북도' => '전북', '전라남도' => '전남',
			'경상북도' => '경북', '경상남도' => '경남', '강원도' => '강원', '제주도' => '제주',
		];

		foreach ($map as $prefix => $name)
		{
			if (mb_strpos($region, $prefix) === 0)
			{
				return $name;
			}
		}
		return $region;
	}

	/**
	 * 대시보드용 한 묶음 — 오늘·이번 달 실적과 처리 대기 건수.
	 *
	 * @return object
	 */
	public static function dashboard(): object
	{
		$today = date('Ymd');
		$yesterday = date('Ymd', strtotime('-1 day'));
		$month_start = date('Ym') . '01';
		$last_month_start = date('Ym01', strtotime('first day of last month'));
		$last_month_end = date('Ymd', strtotime('last day of last month'));

		$db = self::db();
		$counts = (object)[
			'pending' => (int)$db->query('SELECT COUNT(*) FROM commerce_order WHERE status = ?', 'pending')->fetchColumn(),
			'to_ship' => (int)$db->query('SELECT COUNT(*) FROM commerce_order_seller WHERE status IN (?, ?)', 'paid', 'preparing')->fetchColumn(),
			'shipping' => (int)$db->query('SELECT COUNT(*) FROM commerce_order_seller WHERE status = ?', 'shipping')->fetchColumn(),
			'claims' => (int)$db->query('SELECT COUNT(*) FROM commerce_claim WHERE status = ?', 'requested')->fetchColumn(),
		];

		return (object)[
			'today' => self::summary($today, $today),
			'yesterday' => self::summary($yesterday, $yesterday),
			'month' => self::summary($month_start, $today),
			'last_month' => self::summary($last_month_start, $last_month_end),
			'counts' => $counts,
			'series' => self::series(date('Ymd', strtotime('-29 days')), $today, 'day'),
			'top_items' => self::byItem(date('Ymd', strtotime('-29 days')), $today, 5),
		];
	}
}
