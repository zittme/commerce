<?php

namespace Zittme\Modules\Commerce\Models;

class Notify
{
	public static function send(int $to, string $text, string $url = ''): void
	{
		if ($to <= 0 || trim($text) === '')
		{
			return;
		}
		try
		{
			$oNcenter = getController('ncenterlite');
			if ($oNcenter && method_exists($oNcenter, 'sendNotification'))
			{
				$oNcenter->sendNotification(0, $to, $text, $url);
			}
		}
		catch (\Throwable $e)
		{
		}
	}

	public static function toAdmins(string $text, string $url = ''): void
	{
		foreach (self::adminSrls() as $srl)
		{
			self::send($srl, $text, $url);
		}
	}

	protected static function adminSrls(): array
	{
		static $cached = null;
		if ($cached !== null)
		{
			return $cached;
		}
		$cached = [];
		try
		{
			$output = executeQueryArray('member.getAdminList');
			foreach (($output->toBool() ? (array)$output->data : []) as $row)
			{
				$srl = (int)($row->member_srl ?? 0);
				if ($srl > 0)
				{
					$cached[] = $srl;
				}
			}
		}
		catch (\Throwable $e)
		{
		}
		return $cached;
	}

	public static function consoleUrl(string $page, array $args = []): string
	{
		$params = ['', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', $page];
		foreach ($args as $k => $v)
		{
			$params[] = (string)$k;
			$params[] = (string)$v;
		}
		return (string)call_user_func_array('getNotEncodedUrl', $params);
	}

	public static function itemUrl(int $item_srl): string
	{
		$mid = (string)(\Zittme\Modules\Commerce\Controllers\Base::getDefaultInstance()->mid ?? 'shop');
		return (string)getNotEncodedUrl('', 'mid', $mid, 'act', 'dispCommerceItem', 'item_srl', $item_srl);
	}

	public static function orderUrl(string $order_code): string
	{
		$mid = (string)(\Zittme\Modules\Commerce\Controllers\Base::getDefaultInstance()->mid ?? 'shop');
		return (string)getNotEncodedUrl('', 'mid', $mid, 'act', 'dispCommerceMyOrders', 'code', $order_code);
	}
}
