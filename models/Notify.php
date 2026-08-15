<?php

namespace Zittme\Modules\Commerce\Models;

/**
 * 쇼핑몰 알림 — 알림센터(ncenterlite)로 보낸다.
 *
 * 알림센터가 없는 사이트에서도 쇼핑몰은 돌아야 하므로 실패를 삼킨다.
 * 쪽지는 쓰지 않는다. 주문 알림이 쪽지함에 쌓이면 대화와 섞여 읽기 어렵다.
 */
class Notify
{
	/**
	 * 한 사람에게 보낸다.
	 *
	 * @param int $to 수신자
	 * @param string $text 짧은 요약
	 * @param string $url 누르면 갈 곳
	 * @return void
	 */
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

	/**
	 * 운영자 전원에게 보낸다.
	 *
	 * @param string $text
	 * @param string $url
	 * @return void
	 */
	public static function toAdmins(string $text, string $url = ''): void
	{
		foreach (self::adminSrls() as $srl)
		{
			self::send($srl, $text, $url);
		}
	}

	/**
	 * 최고관리자 회원 번호.
	 *
	 * @return array<int>
	 */
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

	/**
	 * 콘솔 화면 주소 (운영자 알림용).
	 *
	 * @param string $page
	 * @param array $args
	 * @return string
	 */
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

	/**
	 * 상품 상세 주소.
	 *
	 * @param int $item_srl
	 * @return string
	 */
	public static function itemUrl(int $item_srl): string
	{
		$mid = (string)(\Zittme\Modules\Commerce\Controllers\Base::getDefaultInstance()->mid ?? 'shop');
		return (string)getNotEncodedUrl('', 'mid', $mid, 'act', 'dispCommerceItem', 'item_srl', $item_srl);
	}

	/**
	 * 구매자용 주문 조회 주소.
	 *
	 * @param string $order_code
	 * @return string
	 */
	public static function orderUrl(string $order_code): string
	{
		$mid = (string)(\Zittme\Modules\Commerce\Controllers\Base::getDefaultInstance()->mid ?? 'shop');
		return (string)getNotEncodedUrl('', 'mid', $mid, 'act', 'dispCommerceMyOrders', 'code', $order_code);
	}
}
