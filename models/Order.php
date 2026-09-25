<?php

namespace Zittme\Modules\Commerce\Models;

use Zittme\Modules\Commerce\Controllers\Base;

class Order
{
	public static function get(int $order_srl): ?object
	{
		$output = executeQuery('commerce.getOrder', (object)['order_srl' => $order_srl]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->order_srl)) ? $output->data : null;
	}

	public static function getByCode(string $code): ?object
	{
		$output = executeQuery('commerce.getOrderByCode', (object)['order_code' => $code]);
		return ($output->toBool() && is_object($output->data) && !empty($output->data->order_srl)) ? $output->data : null;
	}

	public static function getItems(int $order_srl): array
	{
		$output = executeQuery('commerce.getOrderItems', (object)['order_srl' => $order_srl]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		$rows = array_values(array_filter($data, function($row) { return !empty($row->order_item_srl); }));
		Lang::textAll($rows, ['item_name', 'option_name']);
		foreach ($rows as $row)
		{
			if ((int)($row->option_srl ?? 0) <= 0 || (int)($row->item_srl ?? 0) <= 0)
			{
				continue;
			}
			$order_item = Item::get((int)$row->item_srl);
			if (!$order_item)
			{
				continue;
			}
			$output = executeQuery('commerce.getOption', (object)['option_srl' => (int)$row->option_srl]);
			$order_option = ($output->toBool() && is_object($output->data) && !empty($output->data->option_srl)) ? $output->data : null;
			if (!$order_option || empty($order_option->combo))
			{
				continue;
			}
			$order_option->option_label = $row->option_name;
			$row->option_name = Combo::optionLabel($order_item, $order_option);
		}
		return $rows;
	}

	public static function getSellerOrders(int $order_srl): array
	{
		$output = executeQuery('commerce.getOrderSellers', (object)['order_srl' => $order_srl]);
		if (!$output->toBool() || empty($output->data))
		{
			return [];
		}
		$data = is_array($output->data) ? $output->data : [$output->data];
		return array_values(array_filter($data, function($row) { return !empty($row->order_seller_srl); }));
	}

	public static function transition(int $order_srl, array $from, string $to, array $extra = []): bool
	{
		$args = (object)array_merge([
			'order_srl' => $order_srl,
			'status' => $to,
			'from_status_list' => implode(',', $from),
		], $extra);

		$output = executeQuery('commerce.updateOrderStatusIf', $args);
		if (!$output->toBool())
		{
			return false;
		}
		return \DB::getInstance()->getAffectedRows() > 0;
	}

	public static function displayStatus(object $order, ?array $sellers = null): string
	{
		if ($order->status !== Base::ORDER_PAID)
		{
			return (string)$order->status;
		}
		$sellers = $sellers ?? self::getSellerOrders((int)$order->order_srl);
		$cur = $sellers[0] ?? null;
		$st = $cur ? (string)$cur->status : '';

		if (count($sellers))
		{
			$closed = array_filter($sellers, function ($s) { return in_array((string)$s->status, [Base::SELLER_REFUNDED, Base::SELLER_CANCELLED], true); });
			$refunded = array_filter($sellers, function ($s) { return (string)$s->status === Base::SELLER_REFUNDED; });
			if (count($closed) === count($sellers) && count($refunded))
			{
				return Base::SELLER_REFUNDED;
			}
			if (in_array($st, [Base::SELLER_REFUNDED, Base::SELLER_CANCELLED], true))
			{
				foreach ($sellers as $s)
				{
					if (!in_array((string)$s->status, [Base::SELLER_REFUNDED, Base::SELLER_CANCELLED], true))
					{
						$cur = $s;
						$st = (string)$s->status;
						break;
					}
				}
			}
		}

		if ($st === Base::SELLER_DELIVERED && self::isAutoConfirmed($cur))
		{
			return Base::SELLER_CONFIRMED;
		}

		return in_array($st, [Base::SELLER_PREPARING, Base::SELLER_SHIPPING, Base::SELLER_DELIVERED, Base::SELLER_CONFIRMED], true)
			? $st : Base::ORDER_PAID;
	}

	public static function isAutoConfirmed(?object $seller): bool
	{
		$days = max(0, (int)(Base::config()->auto_confirm_days ?? 0));
		if ($days <= 0 || !$seller)
		{
			return false;
		}
		$delivered = preg_replace('/\D/', '', (string)($seller->delivered_date ?? ''));
		if (strlen($delivered) < 8)
		{
			return false;
		}
		$at = strtotime(substr($delivered, 0, 4) . '-' . substr($delivered, 4, 2) . '-' . substr($delivered, 6, 2)
			. ' ' . substr($delivered . '000000', 8, 2) . ':' . substr($delivered . '000000', 10, 2) . ':00');
		return $at > 0 && (time() - $at) >= $days * 86400;
	}

	protected static function notifyCenter(string $kind, object $order, string $memo = ''): void
	{
		$code = (string)$order->order_code;
		$buyer = (int)($order->member_srl ?? 0);

		switch ($kind)
		{
			case 'new_order':
				Notify::toAdmins(sprintf(lang('commerce.nc_new_order'), $code), Notify::consoleUrl('orders'));
				return;

			case 'claim':
				Notify::toAdmins(sprintf(lang('commerce.nc_claim'), $code), Notify::consoleUrl('claims'));
				return;

			case 'paid':
				Notify::send($buyer, sprintf(lang('commerce.nc_paid'), $code), Notify::orderUrl($code));
				return;

			case 'shipping':
				Notify::send($buyer, sprintf(lang('commerce.nc_shipping'), $code), Notify::orderUrl($code));
				return;

			case 'delivered':
				Notify::send($buyer, sprintf(lang('commerce.nc_delivered'), $code), Notify::orderUrl($code));
				return;

			case 'claim_done':
				Notify::send($buyer, sprintf(lang('commerce.nc_claim_done'), $code), Notify::orderUrl($code));
				return;
		}
	}

	protected static function notifyEnabled(string $kind, bool $to_admin): bool
	{
		$config = Config::getConfig();
		if ($to_admin && ($config->notify_admin ?? 'N') !== 'Y')
		{
			return false;
		}
		$key = ($to_admin ? 'notify_admin_' : 'notify_buyer_') . $kind;
		return ($config->{$key} ?? 'Y') !== 'N';
	}

	public static function adminRecipients(): array
	{
		$config = Config::getConfig();
		$list = preg_split('/[\s,;]+/', (string)($config->notify_admin_email ?? '')) ?: [];

		$group_srl = (int)($config->notify_admin_group ?? 0);
		if ($group_srl > 0)
		{
			// 별칭 조인은 프레임워크의 자동 프리픽스 재작성과 충돌한다 (Grade::getForMember 와 같은 이유)
			$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
			$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare(
				'SELECT m.email_address FROM `' . $prefix . 'member_group_member` AS mg'
				. ' JOIN `' . $prefix . 'member` AS m ON m.member_srl = mg.member_srl'
				. ' WHERE mg.group_srl = ?'
			);
			if ($stmt && $stmt->execute([$group_srl]))
			{
				$list = array_merge($list, $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: []);
				$stmt->closeCursor();
			}
		}

		$list = array_map('trim', $list);
		$list = array_filter($list, function($mail) {
			return $mail !== '' && filter_var($mail, \FILTER_VALIDATE_EMAIL) !== false;
		});
		return array_values(array_unique($list));
	}

	public static function notifyMail(string $kind, ?object $order, string $memo = ''): void
	{
		if (!$order || (int)($order->order_srl ?? 0) <= 0)
		{
			return;
		}

		Deferred::call(self::class . '::notifyMailTask', [
			'kind' => $kind,
			'order_srl' => (int)$order->order_srl,
			'memo' => $memo,
		]);
	}

	public static function notifyMailTask(object $args): void
	{
		$order = self::get((int)($args->order_srl ?? 0));
		if ($order)
		{
			self::notifyMailNow((string)($args->kind ?? ''), $order, (string)($args->memo ?? ''));
		}
	}

	protected static function notifyMailNow(string $kind, object $order, string $memo = ''): void
	{
		self::notifyCenter($kind, $order, $memo);
		try
		{
			$to_admin = in_array($kind, ['new_order', 'claim'], true);
			if (!self::notifyEnabled($kind, $to_admin))
			{
				return;
			}

			$recipients = $to_admin
				? self::adminRecipients()
				: array_filter([trim((string)($order->orderer_email ?? ''))], function($mail) {
					return filter_var($mail, \FILTER_VALIDATE_EMAIL) !== false;
				});
			if (!count($recipients))
			{
				return;
			}

			$site = \Context::getSiteTitle() ?: 'Zittme';
			$subject = sprintf(lang('commerce.mail_subject_' . $kind), $site, $order->order_code);
			if ($subject === '' || strpos($subject, 'mail_subject_') !== false)
			{
				return;
			}

			$rows = [];
			$rows[] = [lang('commerce.mail_order_code'), (string)$order->order_code];
			$rows[] = [lang('commerce.mail_orderer'), $order->orderer_name
				. ($order->orderer_phone ? ' (' . $order->orderer_phone . ')' : '')];

			$item_lines = [];
			foreach (self::getItems((int)$order->order_srl) as $item)
			{
				$item_lines[] = $item->item_name . ($item->option_name ? ' / ' . $item->option_name : '') . ' x ' . (int)$item->qty;
			}
			if (count($item_lines))
			{
				$rows[] = [lang('commerce.mail_items'), $item_lines];
			}

			$rows[] = [lang('commerce.mail_payment'), shop_money_in((int)$order->payment_price, $order->currency ?? 'KRW')];

			$notes = [];
			if ($kind === 'new_order' && $order->status === Base::ORDER_PENDING)
			{
				$notes[] = lang('commerce.mail_pending_note');
			}
			if ($memo !== '')
			{
				$notes[] = $memo;
			}

			$html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.7;color:#333">';
			$html .= '<p style="margin:0 0 14px;font-size:15px;font-weight:700">' . escape($subject) . '</p>';
			$html .= '<table cellpadding="0" cellspacing="0" style="border-collapse:collapse">';
			foreach ($rows as [$label, $value])
			{
				$value_html = is_array($value)
					? implode('<br />', array_map(function($line) { return escape($line); }, $value))
					: escape((string)$value);
				$html .= '<tr>'
					. '<th style="padding:6px 14px 6px 0;text-align:left;vertical-align:top;color:#6b7684;font-weight:600;white-space:nowrap">' . escape($label) . '</th>'
					. '<td style="padding:6px 0;vertical-align:top">' . $value_html . '</td>'
					. '</tr>';
			}
			$html .= '</table>';
			foreach ($notes as $note)
			{
				$html .= '<p style="margin:14px 0 0;color:#6b7684">' . escape($note) . '</p>';
			}
			$html .= '</div>';

			foreach ($recipients as $to)
			{
				$mail = new \Zittme\Framework\Mail();
				$mail->addTo($to);
				$mail->setSubject($subject);
				$mail->setBody($html, 'text/html');
				$mail->send();
			}
		}
		catch (\Throwable $e)
		{
		}
	}

	public static function markPaid(int $order_srl): bool
	{
		$won = self::transition($order_srl, [Base::ORDER_PENDING], Base::ORDER_PAID, ['paid_date' => Base::now()]);
		$recovered = false;
		if (!$won)
		{
			$won = self::transition($order_srl, [Base::ORDER_EXPIRED], Base::ORDER_PAID, ['paid_date' => Base::now()]);
			if (!$won)
			{
				return false;
			}
			$recovered = true;
			foreach (self::getItems($order_srl) as $item)
			{
				Stock::reserve((int)$item->item_srl, (int)$item->option_srl, (int)$item->qty);
			}
		}

		executeQuery('commerce.updateOrderSellersStatus', (object)[
			'order_srl' => $order_srl,
			'status' => Base::SELLER_PAID,
			'from_status_list' => $recovered
				? implode(',', [Base::SELLER_PENDING, Base::SELLER_CANCELLED])
				: Base::SELLER_PENDING,
		]);

		foreach (self::getItems($order_srl) as $item)
		{
			if ((int)$item->item_srl > 0)
			{
				\Zittme\Framework\DB::getInstance()->query(
					'UPDATE commerce_item SET buy_count = buy_count + ? WHERE item_srl = ?',
					(int)$item->qty, (int)$item->item_srl
				);
			}
		}

		$order = self::get($order_srl);
		if ($order)
		{
			Credit::earnForOrder($order);
			Grade::recalc((int)$order->member_srl);
			self::clearCartOf($order);
		}

		self::log($order_srl, 0, 'pay', $recovered ? Base::ORDER_EXPIRED : Base::ORDER_PENDING, Base::ORDER_PAID, 0, $recovered ? 'recovered from expired (deposit confirmed)' : '');
		$pin = Pin::assignForOrder($order_srl);
		if ($pin['pin_only'] && $pin['short'] === 0)
		{
			foreach (self::getSellerOrders($order_srl) as $pin_os)
			{
				executeQuery('commerce.updateOrderSellerShipping', (object)[
					'order_seller_srl' => (int)$pin_os->order_seller_srl,
					'status' => Base::SELLER_DELIVERED,
					'from_status_list' => Base::SELLER_PAID,
					'shipping_company' => lang('commerce.pin_delivery'),
					'shipped_date' => Base::now(),
					'delivered_date' => Base::now(),
				]);
			}
		}
		self::notifyMail('paid', $order, $pin['assigned'] > 0 ? lang('commerce.pin_mail_note') : '');
		return true;
	}

	public static function clearCartOf(object $order): void
	{
		$member_srl = (int)($order->member_srl ?? 0);
		if ($member_srl <= 0)
		{
			return;
		}
		$db = \Zittme\Framework\DB::getInstance();
		foreach (self::getItems((int)$order->order_srl) as $item)
		{
			$db->query(
				'DELETE FROM commerce_cart WHERE member_srl = ? AND item_srl = ? AND option_srl = ?',
				$member_srl, (int)$item->item_srl, (int)$item->option_srl
			);
		}
	}

	public static function cancelAndRestock(int $order_srl, int $actor_srl = 0, string $memo = '', string $to = Base::ORDER_CANCELLED): bool
	{
		$won = self::transition(
			$order_srl,
			[Base::ORDER_PENDING, Base::ORDER_PAID],
			$to,
			['cancelled_date' => Base::now()]
		);
		if (!$won)
		{
			return false;
		}

		executeQuery('commerce.updateOrderSellersStatus', (object)[
			'order_srl' => $order_srl,
			'status' => Base::SELLER_CANCELLED,
			'from_status_list' => implode(',', [Base::SELLER_PENDING, Base::SELLER_PAID, Base::SELLER_PREPARING]),
		]);

		foreach (self::getItems($order_srl) as $item)
		{
			Stock::release((int)$item->item_srl, (int)$item->option_srl, (int)$item->qty);
		}

		Timesale::releaseForOrder($order_srl);
		Pin::releaseForOrder($order_srl);

		Coupon::releaseByOrder($order_srl);
		$order = self::get($order_srl);
		if ($order)
		{
			Credit::settleCancel($order);
			Grade::recalc((int)$order->member_srl);
		}

		self::log($order_srl, 0, $to === Base::ORDER_EXPIRED ? 'expire' : 'cancel', '', $to, $actor_srl, $memo);
		return true;
	}

	public static function purge(int $order_srl): bool
	{
		$order = self::get($order_srl);
		if (!$order || in_array((string)$order->status, [Base::ORDER_PAID], true))
		{
			return false;
		}

		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$handle = \Zittme\Framework\DB::getInstance()->getHandle();
		foreach (['commerce_order_item', 'commerce_order_address', 'commerce_order_log', 'commerce_order_seller', 'commerce_order'] as $table)
		{
			$stmt = $handle->prepare('DELETE FROM `' . $prefix . $table . '` WHERE order_srl = ?');
			if ($stmt)
			{
				$stmt->execute([$order_srl]);
				$stmt->closeCursor();
			}
		}
		return true;
	}

	public static function findOpenPending(int $member_srl): ?object
	{
		if ($member_srl <= 0)
		{
			return null;
		}
		$minutes = max(10, (int)(Base::config()->pending_minutes ?? 60));
		$prefix = (string)(\Zittme\Framework\Config::get('db.master.prefix') ?? '');
		$stmt = \Zittme\Framework\DB::getInstance()->getHandle()->prepare(
			'SELECT * FROM `' . $prefix . 'commerce_order`'
			. ' WHERE member_srl = ? AND status = ? AND regdate >= ?'
			. ' ORDER BY order_srl DESC LIMIT 1'
		);
		if (!$stmt || !$stmt->execute([$member_srl, Base::ORDER_PENDING, date('YmdHis', time() - 60 * $minutes)]))
		{
			return null;
		}
		$row = $stmt->fetchObject() ?: null;
		$stmt->closeCursor();
		return $row;
	}

	public static function pendingDeadline(object $order): string
	{
		if (($order->status ?? '') !== Base::ORDER_PENDING || empty($order->regdate))
		{
			return '';
		}
		$minutes = max(10, (int)(Base::config()->pending_minutes ?? 60));
		return date('YmdHis', ztime((string)$order->regdate) + 60 * $minutes);
	}

	public static function resumePayUrl(object $order): string
	{
		if (($order->status ?? '') !== Base::ORDER_PENDING || !class_exists('\Zittme\Modules\Zittme_pay\PayService'))
		{
			return '';
		}
		$pay = \Zittme\Modules\Zittme_pay\PayService::getOrderBySource('commerce', (int)$order->order_srl);
		if (!$pay || empty($pay->order_code) || !in_array((string)$pay->status, \Zittme\Modules\Zittme_pay\Models\Order::OPEN_STATUSES, true))
		{
			return '';
		}
		return \Zittme\Modules\Zittme_pay\PayService::getPayUrl((string)$pay->order_code);
	}

	public static function expireStalePending(): int
	{
		$minutes = max(10, (int)(Base::config()->pending_minutes ?? 60));
		$output = executeQuery('commerce.getExpiredPending', (object)[
			'status' => Base::ORDER_PENDING,
			'before' => date('YmdHis', time() - 60 * $minutes),
			'list_count' => 20,
		]);
		if (!$output->toBool() || empty($output->data))
		{
			return 0;
		}

		$count = 0;
		foreach (is_array($output->data) ? $output->data : [$output->data] as $row)
		{
			if (!empty($row->order_srl) && self::cancelAndRestock((int)$row->order_srl, 0, 'pending expired', Base::ORDER_EXPIRED))
			{
				$count++;
			}
		}
		return $count;
	}

	public static function log(int $order_srl, int $order_seller_srl, string $action, string $before, string $after, int $actor_srl = 0, string $memo = ''): void
	{
		executeQuery('commerce.insertOrderLog', (object)[
			'log_srl' => getNextSequence(),
			'order_srl' => $order_srl,
			'order_seller_srl' => $order_seller_srl,
			'action' => $action,
			'before_status' => $before,
			'after_status' => $after,
			'actor_srl' => $actor_srl,
			'memo' => mb_substr($memo, 0, 250),
			'regdate' => Base::now(),
		]);
	}
}
