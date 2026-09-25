<?php

namespace Zittme\Modules\Commerce\Models;

class Courier
{
	public const DEFAULTS = [
		['name' => 'CJ대한통운', 'url' => 'https://trace.cjlogistics.com/next/tracking.html?wblNo={invoice}', 'code' => '04'],
		['name' => '한진택배', 'url' => 'https://www.hanjin.com/kor/CMS/DeliveryMgr/WaybillResult.do?mCode=MN038&schLang=KR&wblnumText2={invoice}', 'code' => '05'],
		['name' => '롯데택배', 'url' => 'https://www.lotteglogis.com/home/reservation/tracking/linkView?InvNo={invoice}', 'code' => '08'],
		['name' => '우체국택배', 'url' => 'https://service.epost.go.kr/trace.RetrieveDomRigiTraceList.comm?sid1={invoice}', 'code' => '01'],
		['name' => '로젠택배', 'url' => 'https://www.ilogen.com/web/personal/trace/{invoice}', 'code' => '06'],
		['name' => '경동택배', 'url' => 'https://kdexp.com/service/delivery/etc/delivery.do?barcode={invoice}', 'code' => '23'],
		['name' => '대신택배', 'url' => 'https://www.ds3211.co.kr/freight/internalFreightSearch.ht?billno={invoice}', 'code' => '22'],
		['name' => '일양로지스', 'url' => 'https://www.ilyanglogis.com/functionality/tracking_result.asp?hawb_no={invoice}', 'code' => '11'],
		['name' => '합동택배', 'url' => 'https://www.hdexp.co.kr/basic_delivery.hd?barcode={invoice}', 'code' => '32'],
		['name' => 'CU 편의점택배', 'url' => 'https://www.cupost.co.kr/postbox/delivery/localResult.cupost?invoice_no={invoice}', 'code' => '46'],
		['name' => 'GS Postbox 택배', 'url' => 'https://www.cvsnet.co.kr/invoice/tracking.do?invoice_no={invoice}', 'code' => '24'],
	];

	public static function getList(): array
	{
		$raw = Config::getConfig()->couriers ?? '';
		$rows = is_array($raw) ? $raw : json_decode((string)$raw, true);
		$list = self::normalize(is_array($rows) ? $rows : []);
		return count($list) ? $list : self::DEFAULTS;
	}

	public static function names(): array
	{
		return array_column(self::getList(), 'name');
	}

	public static function find(string $name): ?array
	{
		$name = trim($name);
		if ($name === '')
		{
			return null;
		}
		foreach (self::getList() as $row)
		{
			if (strcasecmp($row['name'], $name) === 0)
			{
				return $row;
			}
		}
		return null;
	}

	public static function trackUrl(string $name, string $invoice): string
	{
		$row = self::find($name);
		$invoice = preg_replace('/[^0-9a-zA-Z\-]/', '', $invoice);
		if (!$row || $row['url'] === '' || $invoice === '')
		{
			return '';
		}
		$url = strpos($row['url'], '{invoice}') !== false ? str_replace('{invoice}', rawurlencode($invoice), $row['url']) : $row['url'] . rawurlencode($invoice);
		return preg_match('#^https?://#i', $url) ? $url : '';
	}

	public static function parseLines(string $text): array
	{
		$rows = [];
		foreach (preg_split('/\R/', $text) as $line)
		{
			$parts = array_map('trim', explode('|', $line));
			$rows[] = ['name' => $parts[0] ?? '', 'url' => $parts[1] ?? '', 'code' => $parts[2] ?? ''];
		}
		return self::normalize($rows);
	}

	public static function toLines(array $list): string
	{
		return implode("\n", array_map(function ($row) {
			return rtrim($row['name'] . ' | ' . $row['url'] . ($row['code'] !== '' ? ' | ' . $row['code'] : ''), ' |');
		}, $list));
	}

	protected static function normalize(array $rows): array
	{
		$list = [];
		$seen = [];
		foreach ($rows as $row)
		{
			if (!is_array($row))
			{
				continue;
			}
			$name = mb_substr(trim((string)($row['name'] ?? '')), 0, 60);
			$key = mb_strtolower($name);
			if ($name === '' || isset($seen[$key]))
			{
				continue;
			}
			$url = trim((string)($row['url'] ?? ''));
			if ($url !== '' && !preg_match('#^https?://#i', $url))
			{
				$url = '';
			}
			$code = preg_replace('/\D/', '', (string)($row['code'] ?? ''));
			$seen[$key] = true;
			$list[] = ['name' => $name, 'url' => mb_substr($url, 0, 500), 'code' => $code !== '' ? str_pad(substr($code, 0, 4), 2, '0', \STR_PAD_LEFT) : ''];
		}
		return $list;
	}
}
