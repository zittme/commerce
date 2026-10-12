<?php

namespace Zittme\Modules\Commerce\Models;

class RecentView
{
	public const COOKIE_NAME = 'zm_shop_recent';
	public const LIMIT = 20;

	protected static function memberSrl(): int
	{
		$logged_info = \Context::get('logged_info');
		return ($logged_info && !empty($logged_info->member_srl)) ? (int)$logged_info->member_srl : 0;
	}

	protected static function cookieSrls(): array
	{
		$raw = (string)($_COOKIE[self::COOKIE_NAME] ?? '');
		$srls = array_values(array_unique(array_filter(array_map('intval', explode('.', $raw)), function ($v) { return $v > 0; })));
		return array_slice($srls, 0, self::LIMIT);
	}

	protected static function writeCookie(array $srls): void
	{
		if (headers_sent())
		{
			return;
		}
		$value = implode('.', array_slice($srls, 0, self::LIMIT));
		setcookie(self::COOKIE_NAME, $value, [
			'expires' => time() + 86400 * 30,
			'path' => '/',
			'httponly' => true,
			'samesite' => 'Lax',
			'secure' => !empty($_SERVER['HTTPS']),
		]);
		$_COOKIE[self::COOKIE_NAME] = $value;
	}

	public static function add(int $item_srl): void
	{
		if ($item_srl <= 0)
		{
			return;
		}
		$member_srl = self::memberSrl();
		if ($member_srl <= 0)
		{
			$srls = self::cookieSrls();
			array_unshift($srls, $item_srl);
			self::writeCookie(array_values(array_unique($srls)));
			return;
		}
		if (!\DB::getInstance()->isTableExists('commerce_recent_view'))
		{
			return;
		}
		executeQuery('commerce.deleteRecentView', (object)['member_srl' => $member_srl, 'item_srl' => $item_srl]);
		executeQuery('commerce.insertRecentView', (object)['member_srl' => $member_srl, 'item_srl' => $item_srl, 'regdate' => date('YmdHis')]);

		$guest = self::cookieSrls();
		if (count($guest))
		{
			$stamp = time() - 1;
			foreach (array_reverse($guest) as $srl)
			{
				if ($srl === $item_srl)
				{
					continue;
				}
				executeQuery('commerce.deleteRecentView', (object)['member_srl' => $member_srl, 'item_srl' => $srl]);
				executeQuery('commerce.insertRecentView', (object)['member_srl' => $member_srl, 'item_srl' => $srl, 'regdate' => date('YmdHis', $stamp--)]);
			}
			self::writeCookie([]);
		}

		$output = executeQuery('commerce.getRecentViews', (object)['member_srl' => $member_srl, 'list_count' => 100]);
		$rows = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];
		if (count($rows) > self::LIMIT)
		{
			$cut = $rows[self::LIMIT - 1]->regdate;
			executeQuery('commerce.deleteRecentView', (object)['member_srl' => $member_srl, 'regdate_before' => $cut]);
		}
	}

	public static function srls(int $limit = 12): array
	{
		$member_srl = self::memberSrl();
		if ($member_srl <= 0 || !\DB::getInstance()->isTableExists('commerce_recent_view'))
		{
			return array_slice(self::cookieSrls(), 0, $limit);
		}
		$output = executeQuery('commerce.getRecentViews', (object)['member_srl' => $member_srl, 'list_count' => $limit]);
		$rows = ($output->toBool() && !empty($output->data)) ? (is_array($output->data) ? $output->data : [$output->data]) : [];
		return array_values(array_map(function ($r) { return (int)$r->item_srl; }, $rows));
	}

	public static function items(int $limit = 12, int $except = 0): array
	{
		$out = [];
		foreach (self::srls($limit + 1) as $srl)
		{
			if ($srl === $except)
			{
				continue;
			}
			$item = Item::get($srl);
			if ($item && in_array($item->status, ['sale', 'soldout'], true) && ($item->hidden_by_market ?? 'N') !== 'Y')
			{
				$out[] = $item;
			}
			if (count($out) >= $limit)
			{
				break;
			}
		}
		return $out;
	}
}
