<?php

use Zittme\Modules\Commerce\Models\Money as CommerceMoney;

require_once __DIR__ . '/compat.php';

if (!function_exists('shop_money_in'))
{
	function shop_money_in($minor, string $currency = ''): string
	{
		$currency = strtoupper(trim($currency)) ?: CommerceMoney::current();
		return CommerceMoney::format((int)$minor, $currency);
	}
}

if (!function_exists('shop_money'))
{
	function shop_money($amount): string
	{
		return CommerceMoney::text((int)$amount);
	}
}

if (!function_exists('shop_item_price'))
{
	function shop_item_price(object $item, string $kind = 'effective'): string
	{
		$fields = ['price' => 'disp_price', 'sale' => 'disp_sale_price', 'effective' => 'disp_effective', 'grade' => 'grade_price'];
		$field = $fields[$kind] ?? 'disp_effective';
		$currency = (string)($item->disp_currency ?? '');
		if ($currency !== '' && isset($item->$field))
		{
			return CommerceMoney::formatItem((int)$item->$field, $currency);
		}

		$legacy = ['price' => 'price', 'sale' => 'sale_price', 'effective' => 'price', 'grade' => 'grade_price'];
		return CommerceMoney::textItem((int)($item->{$legacy[$kind] ?? 'price'} ?? 0));
	}
}

if (!function_exists('shop_item_on_sale'))
{
	function shop_item_on_sale(object $item): bool
	{
		if (isset($item->disp_price))
		{
			return (int)$item->disp_sale_price > 0 && (int)$item->disp_sale_price < (int)$item->disp_price;
		}
		return (int)($item->sale_price ?? 0) > 0 && (int)$item->sale_price < (int)($item->price ?? 0);
	}
}

if (!function_exists('shop_money_base'))
{
	function shop_money_base($amount): string
	{
		return CommerceMoney::format((int)$amount, CommerceMoney::base());
	}
}

if (!function_exists('shop_timesale_badge'))
{
	function shop_timesale_badge(object $item, string $mode = 'card'): string
	{
		$ts = $item->timesale ?? null;
		$info = $item->timesale_info ?? null;
		if (!$ts && !$info)
		{
			return '';
		}
		static $loaded = false;
		if (!$loaded)
		{
			$loaded = true;
			\Context::addHtmlFooter('<style>.shp-ts{display:inline-flex;align-items:center;gap:5px;margin-top:6px;padding:3px 8px;border-radius:4px;background:#b3261e;color:#fff;font-size:12px;font-weight:700;font-variant-numeric:tabular-nums;line-height:1.4}.shp-ts.is-soon{background:#26345c}.shp-ts.is-done{background:#8b95a1}.shp-ts small{font-weight:500;opacity:.9}.shp-ts-box{display:flex;flex-wrap:wrap;align-items:center;gap:6px 14px;margin:10px 0 14px;padding:12px 14px;border-radius:8px;background:#fdecea;color:#8c1d18;font-size:14px}.shp-ts-box b{font-size:18px;font-variant-numeric:tabular-nums}.shp-ts-box.is-soon{background:#eef1f8;color:#26345c}.shp-ts-bar{flex-basis:100%;height:6px;border-radius:3px;background:rgba(0,0,0,.08);overflow:hidden}.shp-ts-bar i{display:block;height:100%;background:currentColor}</style>'
				. '<script>(function(){function f(s){s=Math.max(0,Math.floor(s));var d=Math.floor(s/86400),h=Math.floor(s%86400/3600),m=Math.floor(s%3600/60),x=s%60;var t=(h<10?"0":"")+h+":"+(m<10?"0":"")+m+":"+(x<10?"0":"")+x;return d>0?d+"' . lang('commerce.ts_day') . ' "+t:t}function tick(){var now=Date.now()/1000;document.querySelectorAll("[data-ts-end]").forEach(function(el){var left=+el.getAttribute("data-ts-end")-now;var b=el.querySelector("[data-ts-clock]");if(b)b.textContent=f(left);if(left<=0&&!el.classList.contains("is-done")){el.classList.add("is-done");if(b)b.textContent="' . lang('commerce.ts_ended') . '";}});}tick();setInterval(tick,1000);})();</script>');
		}
		$left = ($ts ?: $info)->left;
		$qty = $left > 0 ? ' <small>' . sprintf(lang('commerce.ts_left_qty'), number_format($left)) . '</small>' : '';
		if ($ts)
		{
			if ($mode === 'detail')
			{
				$bar = '';
				if ((int)$ts->qty_limit > 0)
				{
					$pct = (int)round(min(100, (int)$ts->sold_qty / max(1, (int)$ts->qty_limit) * 100));
					$bar = '<span class="shp-ts-bar"><i style="width:' . $pct . '%"></i></span>';
				}
				return '<div class="shp-ts-box" data-ts-end="' . (int)$ts->ends_at . '"><span>' . escape(lang('commerce.ts_label')) . ' · ' . escape((string)$ts->title) . '</span><span>' . escape(lang('commerce.ts_ends_in')) . ' <b data-ts-clock></b></span>' . ($left > 0 ? '<span>' . sprintf(lang('commerce.ts_left_qty'), number_format($left)) . '</span>' : '') . ($ts->per_member > 0 ? '<span>' . sprintf(lang('commerce.ts_per_member_note'), (int)$ts->per_member) . '</span>' : '') . $bar . '</div>';
			}
			return '<span class="shp-ts" data-ts-end="' . (int)$ts->ends_at . '">' . escape(lang('commerce.ts_label')) . ' <span data-ts-clock></span>' . $qty . '</span>';
		}
		if ($info->starts_at > 0 && $info->starts_at - time() < 86400 * 2)
		{
			$when = date('m.d H:i', (int)$info->starts_at);
			return $mode === 'detail'
				? '<div class="shp-ts-box is-soon"><span>' . escape(lang('commerce.ts_label')) . ' · ' . escape((string)$info->title) . '</span><span>' . sprintf(escape(lang('commerce.ts_soon')), $when) . '</span></div>'
				: '<span class="shp-ts is-soon">' . sprintf(escape(lang('commerce.ts_soon')), $when) . '</span>';
		}
		if ($info->left === 0 && $info->ends_at)
		{
			return '<span class="shp-ts is-done">' . escape(lang('commerce.ts_sold_out')) . '</span>';
		}
		return '';
	}
}

if (!function_exists('shop_pin_block'))
{
	function shop_pin_block(object $order, object $oi): string
	{
		$item = \Zittme\Modules\Commerce\Models\Item::get((int)($oi->item_srl ?? 0));
		if (!$item || ($item->is_pin ?? 'N') !== 'Y')
		{
			return '';
		}
		static $loaded = false;
		$out = '';
		if (!$loaded)
		{
			$loaded = true;
			$gp = (string)\Context::get('gp');
			$out .= '<style>.shp-pin{margin-top:8px;padding:10px 12px;border:1px dashed #c9cfd8;border-radius:8px;background:#fafbfc;font-size:13px;line-height:1.6}.shp-pin button{padding:6px 12px;border:1px solid #26345c;border-radius:6px;background:#26345c;color:#fff;font:inherit;font-size:13px;cursor:pointer}.shp-pin ol{margin:6px 0 0;padding-left:18px}.shp-pin code{font-size:15px;font-weight:700;letter-spacing:.04em}.shp-pin small{color:#6b7684}.shp-pin .shp-pin-copy{margin-left:6px;padding:2px 8px;border-color:#c9cfd8;background:#fff;color:#333d4b;font-size:12px}</style>'
				. '<script>function shpPinReveal(btn){var box=btn.closest(".shp-pin");var gp=' . json_encode($gp) . ';if(box.dataset.guest==="Y"&&!gp){gp=prompt(' . json_encode(lang('commerce.pin_ask_password')) . ');if(!gp)return;}if(!box.dataset.revealed&&!confirm(' . json_encode(lang('commerce.pin_confirm_reveal')) . '))return;btn.disabled=true;exec_json("commerce.procCommercePinReveal",{order_code:box.dataset.code,order_item_srl:box.dataset.oi,guest_password:gp},function(res){var ol=document.createElement("ol");(res.pins||[]).forEach(function(p){var li=document.createElement("li");var c=document.createElement("code");c.textContent=p.pin;li.appendChild(c);var b=document.createElement("button");b.type="button";b.className="shp-pin-copy";b.textContent=' . json_encode(lang('commerce.pin_copy')) . ';b.onclick=function(){navigator.clipboard&&navigator.clipboard.writeText(p.pin);b.textContent=' . json_encode(lang('commerce.pin_copied')) . ';};li.appendChild(b);if(p.expire){var s=document.createElement("small");s.textContent=" "+' . json_encode(lang('commerce.pin_expire')) . '+" "+p.expire.replace(/(\\d{4})(\\d{2})(\\d{2})/,"$1.$2.$3");li.appendChild(s);}ol.appendChild(li);});btn.replaceWith(ol);},function(){btn.disabled=false;});}</script>';
		}
		if (($order->status ?? '') !== 'paid')
		{
			return $out . '<div class="shp-pin"><small>' . escape(lang('commerce.pin_after_pay')) . '</small></div>';
		}
		$count = \Zittme\Modules\Commerce\Models\Pin::countFor((int)$oi->order_item_srl);
		if ($count <= 0)
		{
			return $out . '<div class="shp-pin"><small>' . escape(lang('commerce.pin_preparing')) . '</small></div>';
		}
		return $out . '<div class="shp-pin" data-code="' . escape((string)$order->order_code) . '" data-oi="' . (int)$oi->order_item_srl . '" data-guest="' . ((int)$order->member_srl > 0 ? 'N' : 'Y') . '">'
			. '<button type="button" onclick="shpPinReveal(this)">' . escape(sprintf(lang('commerce.pin_show'), $count)) . '</button>'
			. '<br /><small>' . escape(lang('commerce.pin_notice')) . '</small></div>';
	}
}
