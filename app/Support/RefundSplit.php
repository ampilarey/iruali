<?php

namespace App\Support;

use App\Models\SellerOrder;

/**
 * How a return or dispute refund on one shop's part is shared, the same way everywhere (the shop's
 * deduction, the GST report):
 *
 *  - Up to $paidGoods (what the customer paid for the items, after the iruali voucher and points)
 *    the refund is for the items. That fraction of the items is the shop's sale undone: their value
 *    after the shop's own discounts ($netGoods), since the voucher and points were iruali's. The shop
 *    gives back that value less iruali's commission, and iruali gives up the commission.
 *  - Beyond the items, the shop's own delivery charge when it delivered the part itself (the shop's
 *    sale too, no commission on it); the rest is iruali's delivery fee.
 *
 * With no voucher or points $paidGoods equals $netGoods, and this is what the code did before.
 */
class RefundSplit
{
    /**
     * @return array{goods_paid: float, shop_goods: float, shop_delivery: float, commission: float, shop_gives_back: float}
     */
    public static function of(SellerOrder $part, float $refund, float $paidGoods, float $netGoods): array
    {
        $refund = max(0, $refund);
        $paidGoods = max(0, $paidGoods);
        $goodsPaid = round(min($refund, $paidGoods), 2);
        $shopGoods = $paidGoods > 0 ? round(max(0, $netGoods) * $goodsPaid / $paidGoods, 2) : 0.0;
        $shopDelivery = $part->deliveryRefundShare($refund, $paidGoods);
        $commission = round($shopGoods * (float) $part->commission_rate / 100, 2);

        return [
            'goods_paid' => $goodsPaid,
            'shop_goods' => $shopGoods,
            'shop_delivery' => $shopDelivery,
            'commission' => $commission,
            'shop_gives_back' => round(round($shopGoods * (1 - (float) $part->commission_rate / 100), 2) + $shopDelivery, 2),
        ];
    }
}
