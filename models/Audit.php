<?php

namespace Zittme\Modules\Commerce\Models;

class Audit
{
	public const SKIP = [
		'procCommerceAdminPreviewConfig', 'procCommerceAdminPreviewPromotion', 'procCommerceAdminPreviewBrand',
		'procCommerceAdminGetLangCodes', 'procCommerceAdminGetLangCode', 'procCommerceAdminUploadBanner', 'procCommerceAdminUploadItemImage',
	];

	public const VIEWS = [
		'dispCommerceAdminOrderView' => 'view',
		'dispCommerceAdminOrderInvoice' => 'view',
		'dispCommerceAdminExportOrders' => 'export',
		'dispCommerceAdminExportStats' => 'export',
	];

	protected const TARGETS = [
		'order_srl' => 'order', 'item_srl' => 'item', 'claim_srl' => 'claim', 'coupon_srl' => 'coupon', 'brand_srl' => 'brand',
		'promo_srl' => 'promo', 'category_srl' => 'category', 'grade_srl' => 'grade', 'badge_srl' => 'badge', 'option_srl' => 'option',
		'inquiry_srl' => 'inquiry', 'review_srl' => 'review', 'target_member_srl' => 'member', 'member_srl' => 'member',
	];

	protected const SECRET = '/pass|secret|token|api_?key|csrf|_rx_|^pins$|^(module|act|mid|vid|error_return_url|success_return_url|ruleset|xe_validator_id)$/i';

	protected const ITEM_FIELDS = ['item_name', 'price', 'sale_price', 'status', 'stock', 'category_srl', 'brand_srl', 'is_recommend'];

	public static function snapshot(string $act): array
	{
		try
		{
			if ($act === 'procCommerceAdminInsertItem' && ($srl = (int)\Context::get('item_srl')) > 0)
			{
				return ['item' => self::itemRow($srl)];
			}
			if (in_array($act, ['procCommerceAdminInsertConfig', 'procCommerceAdminSaveFront', 'procCommerceAdminUpdateSkin'], true))
			{
				return ['config' => (array)(\ModuleModel::getModuleConfig('commerce') ?: [])];
			}
			if (in_array($act, ['procCommerceAdminSaveStaff', 'procCommerceAdminStaffStatus', 'procCommerceAdminDeleteStaff'], true))
			{
				$s = Staff::get((int)\Context::get('target_member_srl'));
				return ['staff' => $s ? ['role' => $s->role, 'perms' => $s->perm_list, 'status' => $s->status] : null];
			}
		}
		catch (\Throwable $e) {}
		return [];
	}

	public static function after(string $act, $module, array $snap): void
	{
		try
		{
			$page = '';
			if ($act === 'dispCommerceConsole')
			{
				$page = (string)\Context::get('p');
				$map = \Zittme\Modules\Commerce\Controllers\Console::PAGES;
				$act = $map[$page] ?? '';
			}
			if ($act === '' || in_array($act, self::SKIP, true))
			{
				return;
			}
			$is_proc = strpos($act, 'procCommerceAdmin') === 0;
			$view_kind = self::VIEWS[$act] ?? '';
			if (!$is_proc && $view_kind === '')
			{
				return;
			}
			$ok = $module && method_exists($module, 'toBool') ? $module->toBool() : true;
			[$target_type, $target_srl] = self::target();
			$detail = ['params' => self::params()];
			$diff = self::diff($act, $snap, $target_srl);
			if ($diff)
			{
				$detail['changes'] = $diff;
			}
			if (!$ok)
			{
				$detail['error'] = mb_substr((string)lang((string)$module->getMessage()), 0, 200);
			}
			$kind = $view_kind !== '' ? $view_kind : (strpos($act, 'Staff') !== false ? 'staff' : 'change');
			self::write([
				'kind' => $kind,
				'act' => $act,
				'target_type' => $target_type,
				'target_srl' => $target_srl,
				'summary' => self::summary($act, $target_type, $target_srl),
				'detail' => $detail,
				'result' => $ok ? 'ok' : 'fail',
			]);
		}
		catch (\Throwable $e)
		{
		}
	}

	public static function denied(string $act): void
	{
		try
		{
			[$target_type, $target_srl] = self::target();
			self::write([
				'kind' => 'denied',
				'act' => $act,
				'target_type' => $target_type,
				'target_srl' => $target_srl,
				'summary' => self::label($act),
				'detail' => ['params' => self::params(), 'page' => (string)\Context::get('p')],
				'result' => 'denied',
			]);
		}
		catch (\Throwable $e) {}
	}

	protected static function write(array $row): void
	{
		$logged = \Context::get('logged_info');
		$member_srl = $logged ? (int)$logged->member_srl : 0;
		$actor = $logged ? trim(($logged->nick_name ?? '') . ' (' . ($logged->user_id ?? $logged->email_address ?? '') . ')') : '';
		$role = Staff::role();
		$row += ['target_type' => '', 'target_srl' => 0, 'summary' => '', 'result' => 'ok'];
		$row['alert'] = $role === 'owner' ? '' : self::detectAlert($member_srl, $row);
		$now = date('YmdHis');
		\Zittme\Framework\DB::getInstance()->query(
			'INSERT INTO commerce_audit (log_srl, member_srl, actor, role, kind, act, target_type, target_srl, summary, detail, result, alert, ipaddress, user_agent, regdate) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
			[getNextSequence(), $member_srl, mb_substr($actor, 0, 120), $role, $row['kind'], $row['act'], $row['target_type'], (int)$row['target_srl'],
				mb_substr((string)$row['summary'], 0, 250), json_encode($row['detail'] ?? [], \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES), $row['result'], $row['alert'],
				mb_substr((string)(\RX_CLIENT_IP ?? ''), 0, 60), mb_substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250), $now]
		);
		if ($row['alert'] !== '')
		{
			self::mailAlert($member_srl, $actor, $role, $row);
		}
		if (mt_rand(1, 50) === 1)
		{
			self::purge();
		}
	}

	protected static function detectAlert(int $member_srl, array $row): string
	{
		$db = \Zittme\Framework\DB::getInstance();
		$count = function (string $where, array $params, int $minutes) use ($db, $member_srl): int {
			$since = date('YmdHis', time() - $minutes * 60);
			$st = $db->query('SELECT COUNT(*) FROM commerce_audit WHERE member_srl = ? AND regdate >= ? AND ' . $where, array_merge([$member_srl, $since], $params));
			return $st ? (int)self::first($st) : 0;
		};
		$act = (string)$row['act'];
		if ($row['kind'] === 'denied' && $count('kind = ?', ['denied'], 10) >= 4)
		{
			return 'denied_many';
		}
		if ($row['kind'] === 'export' && $count('kind = ?', ['export'], 10) >= 2)
		{
			return 'bulk_export';
		}
		if ($act === 'procCommerceAdminUpdateClaim' && \Context::get('claim_action') === 'approve' && $count('act = ?', [$act], 30) >= 4)
		{
			return 'many_refunds';
		}
		if (stripos($act, 'Delete') !== false && $count('act LIKE ?', ['%Delete%'], 10) >= 9)
		{
			return 'bulk_delete';
		}
		$changes = $row['detail']['changes'] ?? [];
		foreach (['price', 'sale_price'] as $f)
		{
			if (isset($changes[$f]) && (int)$changes[$f][0] > 0 && (int)$changes[$f][1] > 0 && (int)$changes[$f][1] < (int)$changes[$f][0] * 0.5)
			{
				return 'price_drop';
			}
		}
		if ($row['kind'] === 'staff')
		{
			return 'staff_change';
		}
		$hour = (int)date('G');
		if ($hour < 6 && $count('regdate >= ?', [date('Ymd') . '000000'], 24 * 60) === 0)
		{
			return 'night';
		}
		return '';
	}

	protected static function mailAlert(int $member_srl, string $actor, string $role, array $row): void
	{
		$db = \Zittme\Framework\DB::getInstance();
		$st = $db->query('SELECT COUNT(*) FROM commerce_audit WHERE member_srl = ? AND alert = ? AND regdate >= ?', [$member_srl, $row['alert'], date('YmdHis', time() - 3600)]);
		if ($st && (int)self::first($st) > 1)
		{
			return;
		}
		$to = Order::adminRecipients();
		if (!count($to))
		{
			return;
		}
		$site = \Context::getSiteTitle() ?: 'Zittme';
		$subject = sprintf(lang('commerce.au_mail_subject'), $site, lang('commerce.au_alert_' . $row['alert']));
		$link = getFullUrl('', 'module', '', 'mid', '', 'act', 'dispCommerceConsole', 'p', 'audit', 'f_member', $member_srl);
		$lines = [
			[lang('commerce.au_col_who'), $actor . ' · ' . lang('commerce.au_role_' . $role)],
			[lang('commerce.au_col_what'), (string)$row['summary']],
			[lang('commerce.au_col_when'), date('Y-m-d H:i:s')],
			[lang('commerce.au_col_ip'), (string)(\RX_CLIENT_IP ?? '')],
		];
		$html = '<div style="font-family:sans-serif;font-size:14px;line-height:1.7;color:#333"><p style="margin:0 0 12px;font-size:15px;font-weight:700">' . escape($subject) . '</p><table cellpadding="0" cellspacing="0">';
		foreach ($lines as [$k, $v])
		{
			$html .= '<tr><th style="padding:4px 14px 4px 0;text-align:left;color:#6b7684;white-space:nowrap">' . escape($k) . '</th><td style="padding:4px 0">' . escape($v) . '</td></tr>';
		}
		$html .= '</table><p style="margin:14px 0 0"><a href="' . escape($link) . '">' . escape(lang('commerce.au_mail_open')) . '</a></p></div>';
		try
		{
			foreach ($to as $addr)
			{
				$mail = new \Zittme\Framework\Mail();
				$mail->addTo($addr);
				$mail->setSubject($subject);
				$mail->setBody($html, 'text/html');
				$mail->send();
			}
		}
		catch (\Throwable $e) {}
	}

	public static function purge(): void
	{
		$days = self::retentionDays();
		\Zittme\Framework\DB::getInstance()->query('DELETE FROM commerce_audit WHERE regdate < ?', [date('YmdHis', strtotime('-' . $days . ' days'))]);
	}

	public static function retentionDays(): int
	{
		$config = \ModuleModel::getModuleConfig('commerce');
		return max(30, min(3650, (int)($config->audit_days ?? 365) ?: 365));
	}

	protected static function first($st)
	{
		$rows = $st->fetchAll(\PDO::FETCH_NUM);
		return $rows[0][0] ?? null;
	}

	protected static function target(): array
	{
		foreach (self::TARGETS as $key => $type)
		{
			$v = \Context::get($key);
			if (is_scalar($v) && (int)$v > 0)
			{
				return [$type, (int)$v];
			}
		}
		return ['', 0];
	}

	protected static function params(): array
	{
		$out = [];
		$vars = \Context::getRequestVars();
		foreach ((array)$vars as $k => $v)
		{
			if (preg_match(self::SECRET, (string)$k))
			{
				continue;
			}
			if (is_array($v) || is_object($v))
			{
				$v = json_encode($v, \JSON_UNESCAPED_UNICODE);
			}
			$v = (string)$v;
			$out[$k] = mb_strlen($v) > 300 ? mb_substr(strip_tags($v), 0, 300) . '…' : $v;
			if (count($out) >= 60)
			{
				break;
			}
		}
		return $out;
	}

	protected static function diff(string $act, array $snap, int $target_srl): array
	{
		$changes = [];
		if (array_key_exists('item', $snap) || ($act === 'procCommerceAdminInsertItem'))
		{
			$before = $snap['item'] ?? null;
			$after = $target_srl > 0 ? self::itemRow($target_srl) : null;
			foreach (self::ITEM_FIELDS as $f)
			{
				$b = $before[$f] ?? null;
				$a = $after[$f] ?? null;
				if ((string)$b !== (string)$a)
				{
					$changes[$f] = [$b, $a];
				}
			}
		}
		if (array_key_exists('config', $snap))
		{
			$before = $snap['config'];
			$after = (array)(\ModuleModel::getModuleConfig('commerce') ?: []);
			foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $k)
			{
				$b = is_scalar($before[$k] ?? null) ? (string)$before[$k] : json_encode($before[$k] ?? null, \JSON_UNESCAPED_UNICODE);
				$a = is_scalar($after[$k] ?? null) ? (string)$after[$k] : json_encode($after[$k] ?? null, \JSON_UNESCAPED_UNICODE);
				if ($b !== $a && !preg_match(self::SECRET, (string)$k))
				{
					$changes[$k] = [mb_substr($b, 0, 120), mb_substr($a, 0, 120)];
				}
			}
		}
		if (array_key_exists('staff', $snap))
		{
			$s = Staff::get((int)\Context::get('target_member_srl'));
			$after = $s ? ['role' => $s->role, 'perms' => $s->perm_list, 'status' => $s->status] : null;
			if ($snap['staff'] != $after)
			{
				$changes['staff'] = [$snap['staff'], $after];
			}
		}
		return $changes;
	}

	protected static function itemRow(int $srl): ?array
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT ' . implode(', ', self::ITEM_FIELDS) . ' FROM commerce_item WHERE item_srl = ?', [$srl]);
		$rows = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
		return $rows[0] ?? null;
	}

	protected static function summary(string $act, string $type, int $srl): string
	{
		$name = '';
		try
		{
			$db = \Zittme\Framework\DB::getInstance();
			if ($type === 'order')
			{
				$st = $db->query('SELECT order_code FROM commerce_order WHERE order_srl = ?', [$srl]);
				$name = $st ? (string)self::first($st) : '';
			}
			elseif ($type === 'item')
			{
				$st = $db->query('SELECT item_name FROM commerce_item WHERE item_srl = ?', [$srl]);
				$name = $st ? Lang::text((string)self::first($st)) : '';
			}
			elseif ($type === 'member')
			{
				$m = \MemberModel::getMemberInfoByMemberSrl($srl);
				$name = $m ? (string)$m->nick_name : '';
			}
		}
		catch (\Throwable $e) {}
		if ($name === '' && $srl > 0)
		{
			$name = '#' . $srl;
		}
		$rows = json_decode((string)\Context::get('rows'), true);
		if ($name === '' && is_array($rows))
		{
			$name = sprintf(lang('commerce.au_n_orders'), count($rows));
		}
		return trim(self::label($act) . ($name !== '' ? ' · ' . $name : ''));
	}

	public static function label(string $act): string
	{
		$key = 'commerce.au_act_' . preg_replace('/^(disp|proc)CommerceAdmin/', '$1_', $act);
		$text = lang($key);
		if ($text !== $key && strpos($text, 'au_act_') === false)
		{
			return $text;
		}
		$page = array_search($act, \Zittme\Modules\Commerce\Controllers\Console::PAGES, true);
		if ($page !== false)
		{
			$menu = lang('commerce.admin_menu_' . preg_replace('/^config_.*/', 'config', (string)$page));
			if (strpos($menu, 'admin_menu_') === false)
			{
				return sprintf(lang('commerce.au_open_page'), $menu);
			}
		}
		return $act;
	}

	public static function getList(array $f, int $page, int $per = 50): array
	{
		$where = ['1 = 1'];
		$params = [];
		if (!empty($f['member'])) { $where[] = 'member_srl = ?'; $params[] = (int)$f['member']; }
		if (!empty($f['kind'])) { $where[] = 'kind = ?'; $params[] = (string)$f['kind']; }
		if (!empty($f['alert'])) { $where[] = "alert <> ''"; }
		if (!empty($f['q'])) { $where[] = '(summary LIKE ? OR actor LIKE ? OR ipaddress LIKE ?)'; $like = '%' . $f['q'] . '%'; array_push($params, $like, $like, $like); }
		if (!empty($f['from'])) { $where[] = 'regdate >= ?'; $params[] = str_replace('-', '', $f['from']) . '000000'; }
		if (!empty($f['to'])) { $where[] = 'regdate <= ?'; $params[] = str_replace('-', '', $f['to']) . '235959'; }
		$db = \Zittme\Framework\DB::getInstance();
		$st = $db->query('SELECT COUNT(*) FROM commerce_audit WHERE ' . implode(' AND ', $where), $params);
		$total = $st ? (int)self::first($st) : 0;
		$st = $db->query('SELECT * FROM commerce_audit WHERE ' . implode(' AND ', $where) . ' ORDER BY log_srl DESC LIMIT ' . (int)$per . ' OFFSET ' . (int)(($page - 1) * $per), $params);
		return ['rows' => $st ? $st->fetchAll() : [], 'total' => $total, 'pages' => max(1, (int)ceil($total / $per))];
	}

	public static function actors(): array
	{
		$st = \Zittme\Framework\DB::getInstance()->query('SELECT member_srl, MAX(actor) AS actor, MAX(role) AS role FROM commerce_audit GROUP BY member_srl ORDER BY MAX(log_srl) DESC LIMIT 100');
		return $st ? $st->fetchAll() : [];
	}
}
