<?php

namespace Zittme\Modules\Commerce\Models;

class Stats
{
	public const UNITS = ['day' => '일', 'week' => '주', 'month' => '월', 'year' => '년'];

	protected const PAID = "'paid'";

	protected static function krwExpr(string $col, string $alias = ''): string
	{
		$p = $alias !== '' ? $alias . '.' : '';
		$base = preg_replace('/[^A-Z]/', '', Money::base()) ?: 'KRW';
		$base_factor = Money::isZeroDecimal($base) ? 1 : 100;
		return "(CASE WHEN {$p}currency IS NULL OR {$p}currency = '' OR {$p}currency = '{$base}' THEN {$col}"
			. " ELSE ROUND({$col} * CAST(NULLIF({$p}exchange_rate, '') AS DECIMAL(16,4)) * {$base_factor}"
			. " / (CASE WHEN {$p}currency IN ('KRW', 'JPY', 'TWD', 'HUF', 'VND') THEN 1 ELSE 100 END)) END)";
	}

	protected static function db()
	{
		\Zittme\Modules\Commerce\Controllers\Base::ensureCurrencySchema();
		return \Zittme\Framework\DB::getInstance();
	}

	public static function bound(string $date, bool $end = false): string
	{
		$date = preg_replace('/[^0-9]/', '', $date);
		$date = substr($date . '00000000', 0, 8);
		return $date . ($end ? '235959' : '000000');
	}

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

	public static function byRegion(string $from, string $to): array
	{
		$stmt = self::db()->query(
			'SELECT a.country AS country, a.state AS state,
			        SUBSTRING_INDEX(TRIM(a.address1), " ", 1) AS head,
			        COUNT(*) AS cnt, COALESCE(SUM(' . self::krwExpr('o.payment_price', 'o') . '), 0) AS amount
			 FROM commerce_order AS o
			 INNER JOIN commerce_order_address AS a ON a.order_srl = o.order_srl
			 WHERE o.status = ? AND o.paid_date BETWEEN ? AND ?
			 GROUP BY country, state, head',
			'paid', self::bound($from), self::bound($to, true)
		);

		$merged = [];
		while ($row = $stmt->fetchObject())
		{
			$region = self::regionLabel((string)$row->country, (string)$row->state, (string)$row->head);
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

	protected static function regionLabel(string $country, string $state, string $head): string
	{
		$country = strtoupper(trim($country)) ?: 'KR';
		$state = trim($state);

		if ($state !== '')
		{
			$name = Region::name($state);
			return $country === 'KR' ? self::normalizeRegion($name) : ($country . ' · ' . $name);
		}

		if ($country !== 'KR')
		{
			return $country;
		}
		return self::normalizeRegion($head);
	}

	public const KR_REGIONS = [
		'서울', '경기', '인천', '부산', '대구', '광주', '대전', '울산', '세종',
		'강원', '충북', '충남', '전북', '전남', '경북', '경남', '제주',
	];

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
			'unanswered' => (int)$db->query("SELECT COUNT(*) FROM commerce_inquiry WHERE answer IS NULL OR answer = ''")->fetchColumn(),
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
