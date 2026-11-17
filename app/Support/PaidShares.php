<?php

namespace App\Support;

use App\Models\Order;
use App\Services\ShopDiscountService;

/**
 * What the customer actually paid for each line of an order: the line after the shop's own
 * discounts (OrderItem::netTotal()), less its share of the iruali voucher and of the points
 * redeemed. Kept on order_items.paid_total when the order is placed, so returns and disputes refund
 * what was paid for those items, never more (OrderItem::paidTotal()).
 *
 * The voucher is shared over the lines it applied to (not lines bought on a bulk quote, see
 * QuoteService::voucherBase()) in proportion to their net totals; the points over every line's
 * total after the voucher. In laari, by the largest-remainder method
 * (ShopDiscountService::allocate()), so the shares add up exactly.
 */
class PaidShares
{
    /**
     * @return array<int, float> order_item_id => paid total
     */
    public static function compute(Order $order): array
    {
        return self::shares($order)['paid'];
    }

    /**
     * Each line's paid total, and its share (in laari) of the discount the redeemed points gave: the
     * part of the points that was spent on that line (ReturnService::pointsFor()).
     *
     * @return array{paid: array<int, float>, points: array<int, int>}
     */
    public static function shares(Order $order): array
    {
        $items = $order->items()->get(['id', 'price', 'quantity', 'multibuy_discount', 'shop_code_discount', 'quote_request_id']);
        if ($items->isEmpty()) {
            return ['paid' => [], 'points' => []];
        }

        $net = [];
        $voucherWeights = [];
        foreach ($items as $item) {
            $net[$item->id] = max(0, ShopDiscountService::toLaari($item->netTotal()));
            $voucherWeights[$item->id] = $item->quote_request_id ? 0 : $net[$item->id];
        }

        $voucher = ShopDiscountService::allocate(min(ShopDiscountService::toLaari($order->voucher_discount), array_sum($voucherWeights)), $voucherWeights);
        $afterVoucher = [];
        foreach ($net as $id => $laari) {
            $afterVoucher[$id] = max(0, $laari - $voucher[$id]);
        }
        $points = ShopDiscountService::allocate(min(ShopDiscountService::toLaari($order->points_redeemed_discount), array_sum($afterVoucher)), $afterVoucher);

        $paid = [];
        foreach ($afterVoucher as $id => $laari) {
            $paid[$id] = max(0, $laari - $points[$id]) / 100;
        }

        return ['paid' => $paid, 'points' => array_map(fn ($laari) => (int) $laari, $points)];
    }

    /**
     * Work out and keep the paid totals (once the shop discounts are on the items).
     */
    public static function apply(Order $order): void
    {
        foreach (self::compute($order) as $id => $paid) {
            \App\Models\OrderItem::whereKey($id)->update(['paid_total' => $paid]);
        }
    }
}
