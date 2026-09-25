<?php

namespace Zittme\Modules\Commerce\Controllers;

use Zittme\Modules\Commerce\Models\Cart as CartModel;
use Zittme\Modules\Commerce\Models\Order as OrderModel;

class Trigger extends Base
{
	public function triggerAfterLogin($member_info)
	{
		$member_srl = (int)($member_info->member_srl ?? 0);
		if ($member_srl > 0)
		{
			try
			{
				CartModel::mergeGuestCart($member_srl);
			}
			catch (\Throwable $e)
			{
			}
		}
		return new \BaseObject();
	}

	public function triggerModuleListInSitemap(&$moduleList)
	{
		if (is_array($moduleList))
		{
			$moduleList = array_values(array_diff($moduleList, ['commerce']));
		}
	}

	public function triggerSitemapUrls($obj)
	{
		if (!is_object($obj) || empty($obj->mids) || !is_array($obj->mids))
		{
			return;
		}
		foreach ($obj->mids as $mid)
		{
			$module_info = \ModuleModel::getModuleInfoByMid($mid);
			if (!$module_info || ($module_info->module ?? '') !== 'commerce')
			{
				continue;
			}
			$output = executeQueryArray('commerce.getItemList', (object)[
				'status_list' => ['sale', 'soldout'],
				'list_count' => 2000,
				'page' => 1,
			]);
			foreach ($output->data ?: [] as $item)
			{
				if (empty($item->item_srl))
				{
					continue;
				}
				$obj->urls[] = [
					'loc' => getNotEncodedFullUrl('', 'mid', $mid, 'act', 'dispCommerceItem', 'item_srl', $item->item_srl),
					'path' => '',
					'lastmod' => !empty($item->last_update) ? date('c', ztime($item->last_update)) : '',
					'name' => trim(strip_tags((string)($item->item_name ?? ''))),
				];
			}
		}
	}

	public function triggerPayApproved($pay_order)
	{
		if (!is_object($pay_order) || ($pay_order->source_module ?? '') !== 'commerce')
		{
			return;
		}
		$order_srl = (int)($pay_order->source_srl ?? 0);
		if ($order_srl <= 0)
		{
			return;
		}
		OrderModel::markPaid($order_srl);
	}

	public function triggerPayCancelled($pay_order)
	{
		if (!is_object($pay_order) || ($pay_order->source_module ?? '') !== 'commerce')
		{
			return;
		}
		$order_srl = (int)($pay_order->source_srl ?? 0);
		if ($order_srl <= 0)
		{
			return;
		}
		$cancelled = (int)($pay_order->cancelled_amount ?? 0);
		$amount = (int)($pay_order->amount ?? 0);
		if ($amount > 0 && $cancelled >= $amount)
		{
			OrderModel::cancelAndRestock($order_srl, 0, 'pay cancelled');
		}
	}
}
