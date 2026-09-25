<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Tracking
{
	public const LEVEL_NAMES = [
		1 => '접수', 2 => '집화 완료', 3 => '배송 중', 4 => '지점 도착', 5 => '배송 출발', 6 => '배송 완료',
	];

	protected const COURIER_CODES = [
		'CJ' => '04', '대한통운' => '04', '씨제이' => '04',
		'한진' => '05',
		'롯데' => '08', '현대' => '08',
		'우체국' => '01',
		'로젠' => '06',
		'경동' => '23',
		'대신' => '22',
		'일양' => '11',
		'합동' => '32',
		'CU' => '46', '편의점' => '24', 'GS' => '24',
	];

	public static function courierCode(string $company): string
	{
		$company = trim($company);
		if ($company === '')
		{
			return '';
		}
		if (preg_match('/^\d+$/', $company))
		{
			return str_pad($company, 2, '0', \STR_PAD_LEFT);
		}
		$courier = Courier::find($company);
		if ($courier && $courier['code'] !== '')
		{
			return $courier['code'];
		}
		foreach (self::COURIER_CODES as $keyword => $code)
		{
			if (stripos($company, $keyword) !== false)
			{
				return $code;
			}
		}
		return '';
	}

	public static function fetch(string $company, string $invoice): ?object
	{
		$key = trim((string)(Config::getConfig()->sweettracker_api_key ?? ''));
		$code = self::courierCode($company);
		$invoice = preg_replace('/[^0-9a-zA-Z]/', '', $invoice);
		if ($key === '' || $code === '' || $invoice === '')
		{
			return null;
		}
		try
		{
			$response = \Zittme\Framework\HTTP::get('https://info.sweettracker.co.kr/api/v1/trackingInfo', [
				't_key' => $key,
				't_code' => $code,
				't_invoice' => $invoice,
			], [], [], ['timeout' => 4]);
			$data = json_decode((string)$response->getBody(), true);
			if (!is_array($data) || !empty($data['code']))
			{
				return null;
			}
			$level = (int)($data['level'] ?? 0);
			$details = [];
			foreach (is_array($data['trackingDetails'] ?? null) ? $data['trackingDetails'] : [] as $d)
			{
				$details[] = [
					'time' => (string)($d['timeString'] ?? ''),
					'where' => (string)($d['where'] ?? ''),
					'kind' => (string)($d['kind'] ?? ''),
					'level' => (int)($d['level'] ?? 0),
				];
			}
			$last = count($details) ? end($details) : [];
			return (object)[
				'level' => $level,
				'complete' => ($data['completeYN'] ?? 'N') === 'Y' || ($data['complete'] ?? false) === true || $level >= 6,
				'status_name' => self::LEVEL_NAMES[$level] ?? '조회됨',
				'where' => (string)($last['where'] ?? ''),
				'time' => (string)($last['time'] ?? ''),
				'details' => $details,
			];
		}
		catch (\Throwable $e)
		{
			return null;
		}
	}

	public static function getForSeller(int $order_seller_srl): ?object
	{
		$output = executeQuery('commerce.getTracking', (object)['order_seller_srl' => $order_seller_srl]);
		$row = ($output->toBool() && is_object($output->data) && !empty($output->data->tracking_srl)) ? $output->data : null;
		if (!$row)
		{
			return null;
		}
		$details = json_decode((string)$row->details, true);
		$row->details = is_array($details) ? $details : [];
		return $row;
	}

	protected static function store(int $order_seller_srl, int $order_srl, string $company, string $invoice, object $info): void
	{
		$existing = self::getForSeller($order_seller_srl);
		$args = (object)[
			'courier_code' => self::courierCode($company),
			'invoice' => mb_substr(preg_replace('/[^0-9a-zA-Z]/', '', $invoice), 0, 60),
			'level' => (int)$info->level,
			'status_name' => mb_substr((string)$info->status_name, 0, 60),
			'complete' => $info->complete ? 'Y' : 'N',
			'details' => json_encode($info->details ?? [], \JSON_UNESCAPED_UNICODE),
			'last_update' => Base::now(),
		];
		if ($existing)
		{
			$args->tracking_srl = (int)$existing->tracking_srl;
			executeQuery('commerce.updateTracking', $args);
		}
		else
		{
			$args->tracking_srl = getNextSequence();
			$args->order_seller_srl = $order_seller_srl;
			$args->order_srl = $order_srl;
			$args->regdate = Base::now();
			executeQuery('commerce.insertTracking', $args);
		}
	}

	public static function syncShipping(): void
	{
		$config = Config::getConfig();
		if (trim((string)($config->sweettracker_api_key ?? '')) === '')
		{
			return;
		}
		$now = Base::now();
		if (!empty($config->track_last_sync) && (strtotime($now) - strtotime($config->track_last_sync)) < 600)
		{
			return;
		}
		$config->track_last_sync = $now;
		\ModuleController::getInstance()->insertModuleConfig('commerce', $config);

		try
		{
			$stmt = \Zittme\Framework\DB::getInstance()->query(
				'SELECT order_seller_srl, order_srl, status, shipping_company, shipping_invoice'
				. ' FROM commerce_order_seller'
				. " WHERE status IN ('preparing', 'shipping') AND shipping_invoice != '' LIMIT 20"
			);
			foreach ($stmt as $row)
			{
				$saved = self::getForSeller((int)$row->order_seller_srl);
				if ($saved)
				{
					if ($saved->complete === 'Y')
					{
						continue;
					}
					if (!empty($saved->last_update) && (strtotime($now) - strtotime($saved->last_update)) < 7200)
					{
						continue;
					}
				}

				$info = self::fetch((string)$row->shipping_company, (string)$row->shipping_invoice);
				if (!$info)
				{
					continue;
				}
				self::store((int)$row->order_seller_srl, (int)$row->order_srl, (string)$row->shipping_company, (string)$row->shipping_invoice, $info);
				// 같은 송장을 다른 묶음에도 적었으면 자동 배송완료로 넘기지 않는다 (남의 송장 재사용으로 정산을 앞당기는 길)
				$dup = \Zittme\Framework\DB::getInstance()->query(
					'SELECT COUNT(*) AS cnt FROM commerce_order_seller WHERE shipping_company = ? AND shipping_invoice = ? AND order_seller_srl <> ?',
					[(string)$row->shipping_company, (string)$row->shipping_invoice, (int)$row->order_seller_srl]
				)->fetchAll();
				if ($info->complete && (int)($dup[0]->cnt ?? 0) === 0)
				{
					$output = executeQuery('commerce.updateOrderSellerShipping', (object)[
						'order_seller_srl' => (int)$row->order_seller_srl,
						'status' => Base::SELLER_DELIVERED,
						'from_status_list' => 'preparing,shipping',
						'delivered_date' => $now,
					]);
					if ($output->toBool())
					{
						Order::log((int)$row->order_srl, (int)$row->order_seller_srl, 'deliver', (string)$row->status, Base::SELLER_DELIVERED, 0, 'auto (tracking)');
					}
				}
				elseif ($info->level >= 2 && $row->status === Base::SELLER_PREPARING)
				{
					$output = executeQuery('commerce.updateOrderSellerShipping', (object)[
						'order_seller_srl' => (int)$row->order_seller_srl,
						'status' => Base::SELLER_SHIPPING,
						'from_status_list' => 'preparing',
						'shipped_date' => $now,
					]);
					if ($output->toBool())
					{
						Order::log((int)$row->order_srl, (int)$row->order_seller_srl, 'ship', 'preparing', Base::SELLER_SHIPPING, 0, 'auto (tracking)');
					}
				}
			}
		}
		catch (\Throwable $e)
		{
		}
	}
}
