<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Item as ItemModel;

/**
 * 리뷰·문의 (proc).
 */
class Review extends Base
{
	/**
	 * 결제 완료 주문에 이 상품이 포함되어 있는지 (구매자 검증).
	 *
	 * @param int $member_srl
	 * @param int $item_srl
	 * @return bool
	 */
	public static function hasPurchased(int $member_srl, int $item_srl): bool
	{
		if ($member_srl <= 0 || $item_srl <= 0)
		{
			return false;
		}
		// 구매확정(하위주문 confirmed)한 상품만 리뷰 가능.
		// 별칭 조인은 자동 프리픽스 재작성과 충돌하므로 PDO 핸들로 직접 실행한다 (Grade::getForMember 와 같은 이유)
		$prefix = (string)(\Rhymix\Framework\Config::get('db.master.prefix') ?? '');
		$stmt = \Rhymix\Framework\DB::getInstance()->getHandle()->prepare(
			'SELECT 1 FROM `' . $prefix . 'commerce_order_item` AS oi'
			. ' JOIN `' . $prefix . 'commerce_order` AS o ON o.order_srl = oi.order_srl'
			. ' JOIN `' . $prefix . 'commerce_order_seller` AS os ON os.order_seller_srl = oi.order_seller_srl'
			. ' WHERE o.member_srl = ? AND oi.item_srl = ? AND o.status = ? AND os.status = ? LIMIT 1'
		);
		$found = false;
		if ($stmt && $stmt->execute([$member_srl, $item_srl, 'paid', 'confirmed']))
		{
			$found = (bool)$stmt->fetchColumn();
			$stmt->closeCursor();
		}
		return $found;
	}

	/**
	 * 지금 이 상품에 리뷰를 쓸 수 있는가 (아직 리뷰 안 쓴 확정 주문이 있는가).
	 */
	public static function canReviewNow(int $member_srl, int $item_srl): bool
	{
		return self::resolveReviewOrder($member_srl, $item_srl) > 0;
	}

	/**
	 * 리뷰를 붙일 주문건을 정한다.
	 *
	 * 지정한 주문이 있으면 그 주문이 이 회원의 확정 주문이고 이 상품을 담고 있는지 확인한다.
	 * 지정이 없으면 아직 리뷰를 안 쓴 확정 주문 중 최근 것을 고른다. 없으면 0.
	 *
	 * @return int 리뷰를 붙일 order_srl (자격 없으면 0)
	 */
	protected static function resolveReviewOrder(int $member_srl, int $item_srl, int $want_order_srl = 0): int
	{
		if ($member_srl <= 0 || $item_srl <= 0)
		{
			return 0;
		}
		$prefix = (string)(\Rhymix\Framework\Config::get('db.master.prefix') ?? '');
		$sql = 'SELECT o.order_srl FROM `' . $prefix . 'commerce_order_item` AS oi'
			. ' JOIN `' . $prefix . 'commerce_order` AS o ON o.order_srl = oi.order_srl'
			. ' JOIN `' . $prefix . 'commerce_order_seller` AS os ON os.order_seller_srl = oi.order_seller_srl'
			. ' WHERE o.member_srl = ? AND oi.item_srl = ? AND o.status = ? AND os.status = ?';
		$args = [$member_srl, $item_srl, 'paid', 'confirmed'];
		if ($want_order_srl > 0)
		{
			$sql .= ' AND o.order_srl = ?';
			$args[] = $want_order_srl;
		}
		$sql .= ' ORDER BY o.order_srl DESC';
		$stmt = \Rhymix\Framework\DB::getInstance()->getHandle()->prepare($sql);
		// 코어는 버퍼링 없는 쿼리를 쓴다. 커서를 연 채로 다른 쿼리를 부를 수 없어 먼저 전부 읽는다
		$candidates = [];
		if ($stmt && $stmt->execute($args))
		{
			$candidates = $stmt->fetchAll(\PDO::FETCH_COLUMN, 0) ?: [];
			$stmt->closeCursor();
		}
		foreach ($candidates as $candidate)
		{
			$candidate = (int)$candidate;
			if ($candidate > 0 && !self::hasReviewed($member_srl, $item_srl, $candidate))
			{
				return $candidate;
			}
		}
		return 0;
	}

	/**
	 * 리뷰 등록 — 구매자만.
	 */
	public function procCommerceReviewInsert()
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || !$logged_info->member_srl)
		{
			return new \BaseObject(-1, 'msg_shop_login_required');
		}
		$item_srl = (int)\Context::get('item_srl');
		$item = ItemModel::get($item_srl);
		if (!$item)
		{
			return new \BaseObject(-1, 'msg_shop_no_item');
		}
		$member_srl = (int)$logged_info->member_srl;
		// 리뷰는 주문건 단위다. 어느 주문에 대한 리뷰인지 정하고, 그 주문이 확정 상태인지 확인한다
		$order_srl = self::resolveReviewOrder($member_srl, $item_srl, (int)\Context::get('order_srl'));
		if ($order_srl <= 0)
		{
			return new \BaseObject(-1, 'msg_shop_review_buyer_only');
		}
		if (self::hasReviewed($member_srl, $item_srl, $order_srl))
		{
			return new \BaseObject(-1, 'msg_shop_review_already');
		}
		$content = trim((string)\Context::get('content'));
		if ($content === '')
		{
			return new \BaseObject(-1, 'msg_shop_need_content');
		}
		$rating = (int)\Context::get('rating');
		$rating = max(1, min(5, $rating ?: 5));

		// 첨부 (procCommerceReviewUpload 로 받은 URL 목록, 최대 5개)
		$images = json_decode((string)\Context::get('images'), true);
		$images = is_array($images) ? array_values(array_filter(array_map('strval', $images), function($u) {
			return str_starts_with($u, './files/attach/commerce/review/');
		})) : [];
		$images = array_slice($images, 0, 5);

		$output = executeQuery('commerce.insertReview', (object)[
			'review_srl' => getNextSequence(),
			'item_srl' => $item_srl,
			'member_srl' => $member_srl,
			'order_srl' => $order_srl,
			'nick_name' => (string)$logged_info->nick_name,
			'rating' => $rating,
			'content' => $content,
			'images' => count($images) ? json_encode($images, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES) : '',
			'regdate' => self::now(),
		]);
		if (!$output->toBool())
		{
			return $output;
		}

		// 리뷰 적립은 주문건마다 지급한다
		$config = self::config();
		$reward = count($images)
			? (int)($config->review_credit_photo ?? 0)
			: (int)($config->review_credit_text ?? 0);
		if ($reward > 0)
		{
			\Zittme\Modules\Commerce\Models\Credit::add($member_srl, $reward, 'earn', $order_srl, '리뷰 작성 적립');
		}

		$this->setMessage($reward > 0 ? sprintf(lang('commerce.admin_msg_9'), number_format($reward)) : 'msg_shop_review_added');
		$this->redirectToItem($item_srl);
	}

	/**
	 * 회원이 이 주문건의 이 상품에 리뷰를 썼는가.
	 *
	 * 리뷰는 주문건 단위다. 같은 상품을 다시 사면 그 주문으로 또 쓸 수 있다.
	 * order_srl 이 0 이면 상품 단위로 본다 (컬럼 도입 전 리뷰 호환).
	 */
	public static function hasReviewed(int $member_srl, int $item_srl, int $order_srl = 0): bool
	{
		$prefix = (string)(\Rhymix\Framework\Config::get('db.master.prefix') ?? '');
		$sql = 'SELECT 1 FROM `' . $prefix . 'commerce_review` WHERE member_srl = ? AND item_srl = ?';
		$args = [$member_srl, $item_srl];
		if ($order_srl > 0)
		{
			// 주문 연결이 없는 옛 리뷰(0/NULL)는 어느 주문이든 작성한 것으로 본다.
			// 보정이 아직 안 돈 사이트에서 이미 쓴 리뷰가 다시 뜨는 것을 막는다
			$sql .= ' AND (order_srl = ? OR order_srl = 0 OR order_srl IS NULL)';
			$args[] = $order_srl;
		}
		$stmt = \Rhymix\Framework\DB::getInstance()->getHandle()->prepare($sql . ' LIMIT 1');
		$found = false;
		if ($stmt && $stmt->execute($args))
		{
			$found = (bool)$stmt->fetchColumn();
			$stmt->closeCursor();
		}
		return $found;
	}

	/**
	 * 이 주문에서 아직 리뷰를 안 쓴 상품 목록.
	 *
	 * @return array<int, string> item_srl => 상품명
	 */
	public static function unreviewedItems(int $member_srl, int $order_srl): array
	{
		$result = [];
		if ($member_srl <= 0 || $order_srl <= 0)
		{
			return $result;
		}
		foreach (\Zittme\Modules\Commerce\Models\Order::getItems($order_srl) as $row)
		{
			$item_srl = (int)($row->item_srl ?? 0);
			if ($item_srl > 0 && !isset($result[$item_srl]) && !self::hasReviewed($member_srl, $item_srl, $order_srl))
			{
				$result[$item_srl] = (string)$row->item_name;
			}
		}
		return $result;
	}

	/**
	 * 리뷰 사진·영상 업로드 — 구매확정 회원만, URL 을 JSON 으로 돌려준다.
	 */
	public function procCommerceReviewUpload()
	{
		header('Content-Type: application/json; charset=utf-8');
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || !$logged_info->member_srl)
		{
			echo json_encode(['error' => 1, 'message' => '로그인이 필요합니다.']); exit;
		}
		$item_srl = (int)\Context::get('item_srl');
		if (!self::hasPurchased((int)$logged_info->member_srl, $item_srl))
		{
			echo json_encode(['error' => 1, 'message' => '구매확정한 상품에만 첨부할 수 있습니다.']); exit;
		}
		$file = $_FILES['file'] ?? null;
		if (!$file || !is_uploaded_file($file['tmp_name'] ?? ''))
		{
			echo json_encode(['error' => 1, 'message' => '파일이 없습니다.']); exit;
		}
		if ((int)$file['size'] > 20 * 1024 * 1024)
		{
			echo json_encode(['error' => 1, 'message' => '20MB 이하 파일만 올릴 수 있습니다.']); exit;
		}
		$ext = strtolower(pathinfo((string)$file['name'], PATHINFO_EXTENSION));
		if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'webm'], true))
		{
			echo json_encode(['error' => 1, 'message' => '이미지(jpg/png/gif/webp) 또는 영상(mp4/mov/webm)만 올릴 수 있습니다.']); exit;
		}
		$dir = \RX_BASEDIR . 'files/attach/commerce/review/' . date('Ym') . '/';
		\Rhymix\Framework\Storage::createDirectory($dir);
		$name = \Rhymix\Framework\Security::getRandom(24, 'alnum') . '.' . $ext;
		if (!move_uploaded_file($file['tmp_name'], $dir . $name))
		{
			echo json_encode(['error' => 1, 'message' => '저장에 실패했습니다.']); exit;
		}
		echo json_encode(['error' => 0, 'url' => './files/attach/commerce/review/' . date('Ym') . '/' . $name]); exit;
	}

	/**
	 * 리뷰 삭제 — 본인 또는 관리자.
	 */
	public function procCommerceReviewDelete()
	{
		$review_srl = (int)\Context::get('review_srl');
		$row = self::findRow('commerce_review', 'review_srl', $review_srl);
		if (!$row)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		if (!self::canManage((int)$row->member_srl))
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		executeQuery('commerce.deleteReview', (object)['review_srl' => $review_srl]);
		$this->setMessage('success_deleted');
		$this->redirectToItem((int)$row->item_srl);
	}

	/**
	 * 문의 등록 — 회원만, 비밀글 지원.
	 */
	public function procCommerceInquiryInsert()
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || !$logged_info->member_srl)
		{
			return new \BaseObject(-1, 'msg_shop_login_required');
		}
		$item_srl = (int)\Context::get('item_srl');
		if (!ItemModel::get($item_srl))
		{
			return new \BaseObject(-1, 'msg_shop_no_item');
		}
		$content = trim((string)\Context::get('content'));
		if ($content === '')
		{
			return new \BaseObject(-1, 'msg_shop_need_content');
		}

		$output = executeQuery('commerce.insertInquiry', (object)[
			'inquiry_srl' => getNextSequence(),
			'item_srl' => $item_srl,
			'member_srl' => (int)$logged_info->member_srl,
			'nick_name' => (string)$logged_info->nick_name,
			'is_secret' => \Context::get('is_secret') === 'Y' ? 'Y' : 'N',
			'content' => $content,
			'regdate' => self::now(),
		]);
		if (!$output->toBool())
		{
			return $output;
		}
		$this->setMessage('msg_shop_inquiry_added');
		$this->redirectToItem($item_srl);
	}

	/**
	 * 문의 삭제 — 본인 또는 관리자.
	 */
	public function procCommerceInquiryDelete()
	{
		$inquiry_srl = (int)\Context::get('inquiry_srl');
		$row = self::findRow('commerce_inquiry', 'inquiry_srl', $inquiry_srl);
		if (!$row)
		{
			return new \BaseObject(-1, 'msg_invalid_request');
		}
		if (!self::canManage((int)$row->member_srl))
		{
			throw new \Zittme\Framework\Exceptions\NotPermitted;
		}
		executeQuery('commerce.deleteInquiry', (object)['inquiry_srl' => $inquiry_srl]);
		$this->setMessage('success_deleted');
		$this->redirectToItem((int)$row->item_srl);
	}

	/**
	 * 본인 또는 관리자인지.
	 */
	protected static function canManage(int $owner_srl): bool
	{
		$logged_info = \Context::get('logged_info');
		if (!$logged_info || !$logged_info->member_srl)
		{
			return false;
		}
		return $logged_info->is_admin === 'Y' || (int)$logged_info->member_srl === $owner_srl;
	}

	/**
	 * srl 로 단일 행 조회.
	 */
	protected static function findRow(string $table, string $key, int $srl)
	{
		if ($srl <= 0)
		{
			return null;
		}
		$prefix = (string)(\Rhymix\Framework\Config::get('db.master.prefix') ?? '');
		$stmt = \Rhymix\Framework\DB::getInstance()->getHandle()->prepare(
			'SELECT * FROM `' . $prefix . $table . '` WHERE `' . $key . '` = ? LIMIT 1'
		);
		$row = null;
		if ($stmt && $stmt->execute([$srl]))
		{
			$row = $stmt->fetchObject() ?: null;
			$stmt->closeCursor();
		}
		return $row;
	}

	/**
	 * 상품 상세로 복귀.
	 */
	protected function redirectToItem(int $item_srl): void
	{
		// 콘솔 등 다른 화면에서 부르면 그 화면으로 복귀한다
		$return_url = (string)\Context::get('success_return_url');
		if ($return_url !== '')
		{
			$this->setRedirectUrl($return_url);
			return;
		}
		$mid = (string)\Context::get('mid') ?: (self::getDefaultInstance()->mid ?? self::DEFAULT_MID);
		$this->setRedirectUrl(getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceItem', 'item_srl', $item_srl));
	}
}
