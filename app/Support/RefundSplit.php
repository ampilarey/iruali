<?php

namespace App\Support;

use App\Models\Dispute;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;

/**
 * How a return or dispute refund on one shop's part is shared, the same way everywhere (the shop's
 * deduction, the GST report):
 *
 *  - Up to what the customer paid for the items (after the iruali voucher and points) the refund is
 *    for the items. That fraction of the items is the shop's sale undone: their value after the
 *    shop's own discounts, since the voucher and points were iruali's. The shop gives back that value
 *    less iruali's commission, and iruali gives up the commission.
 *  - Beyond the items, the shop's own delivery charge when it delivered the part itself (the shop's
 *    sale too, no commission on it); the rest is iruali's delivery fee.
 *
 * Several refunds on one part (a return and a dispute, two disputes) share the part between them:
 * each takes only the items and the shop's delivery that earlier refunds on the part have not
 * already taken (history() and of()'s $earlier), so a shop never gives back more than it earned on
 * the part, and iruali's delivery fee is never charged to it.
 */
class RefundSplit
{
    /**
     * @param  list<array{refund: float, paid: float, net: float}>  $earlier  refunds decided on the part before this one, oldest first
     * @return array{goods_paid: float, shop_goods: float, shop_delivery: float, commission: float, shop_gives_back: float}
     */
    public static function of(SellerOrder $part, float $refund, float $paidGoods, float $netGoods, array $earlier = []): array
    {
        $partPaid = $part->paidSubtotal();
        $taken = ['goods' => 0.0, 'delivery' => 0.0];
        foreach ($earlier as $event) {
            $step = self::step($part, $partPaid, (float) $event['refund'], (float) $event['paid'], (float) $event['net'], $taken);
            $taken['goods'] += $step['goods_paid'];
            $taken['delivery'] += $step['shop_delivery'];
        }

        return self::step($part, $partPaid, $refund, $paidGoods, $netGoods, $taken);
    }

    /**
     * Every return and dispute refund decided on the part, in the order they were decided.
     *
     * @return list<array{kind: string, id: int, at: int, refund: float, paid: float, net: float}>
     */
    public static function history(SellerOrder $part): array
    {
        $events = [];
        foreach (ReturnRequest::where('seller_order_id', $part->id)->whereIn('status', ['approved', 'refunded'])->where('refund_amount', '>', 0)->get() as $return) {
            $events[] = ['kind' => 'return', 'id' => (int) $return->id, 'at' => (int) $return->resolved_at?->getTimestamp(),
                'refund' => (float) $return->refund_amount, 'paid' => (float) $return->items_value, 'net' => $return->itemsNetValue()];
        }
        $disputes = Dispute::where('seller_order_id', $part->id)->where('amount_resolved', '>', 0)->get();
        if ($disputes->isNotEmpty()) {
            $paid = $part->paidSubtotal();
            $net = $part->netSubtotal();
            foreach ($disputes as $dispute) {
                $events[] = ['kind' => 'dispute', 'id' => (int) $dispute->id, 'at' => (int) $dispute->resolved_at?->getTimestamp(),
                    'refund' => (float) $dispute->amount_resolved, 'paid' => $paid, 'net' => $net];
            }
        }
        usort($events, fn ($a, $b) => [$a['at'], $a['kind'], $a['id']] <=> [$b['at'], $b['kind'], $b['id']]);

        return $events;
    }

    /**
     * The part's refunds decided before the given one (all of them when it is not decided yet).
     *
     * @return list<array{kind: string, id: int, at: int, refund: float, paid: float, net: float}>
     */
    public static function before(SellerOrder $part, string $kind, int $id): array
    {
        $earlier = [];
        foreach (self::history($part) as $event) {
            if ($event['kind'] === $kind && $event['id'] === $id) {
                break;
            }
            $earlier[] = $event;
        }

        return $earlier;
    }

    /**
     * Every other refund decided on the part: for a refund being decided now, all of them came first
     * (one decided in the same second included).
     *
     * @return list<array{kind: string, id: int, at: int, refund: float, paid: float, net: float}>
     */
    public static function others(SellerOrder $part, string $kind, int $id): array
    {
        return array_values(array_filter(self::history($part), fn ($event) => ! ($event['kind'] === $kind && $event['id'] === $id)));
    }

    /**
     * What the customer paid for the part's items that no refund on it has given back yet.
     */
    public static function goodsLeft(SellerOrder $part, string $exceptKind = '', int $exceptId = 0): float
    {
        $partPaid = $part->paidSubtotal();
        $taken = ['goods' => 0.0, 'delivery' => 0.0];
        foreach (self::history($part) as $event) {
            if ($event['kind'] === $exceptKind && $event['id'] === $exceptId) {
                continue;
            }
            $step = self::step($part, $partPaid, $event['refund'], $event['paid'], $event['net'], $taken);
            $taken['goods'] += $step['goods_paid'];
            $taken['delivery'] += $step['shop_delivery'];
        }

        return round(max(0, $partPaid - $taken['goods']), 2);
    }

    /**
     * @param  array{goods: float, delivery: float}  $taken  goods and shop delivery earlier refunds took
     * @return array{goods_paid: float, shop_goods: float, shop_delivery: float, commission: float, shop_gives_back: float}
     */
    protected static function step(SellerOrder $part, float $partPaid, float $refund, float $paidGoods, float $netGoods, array $taken): array
    {
        $refund = max(0, $refund);
        $paidGoods = max(0, $paidGoods);
        $goodsRoom = max(0, min($paidGoods, $partPaid - $taken['goods']));
        $goodsPaid = round(min($refund, $goodsRoom), 2);
        $shopGoods = $paidGoods > 0 ? round(max(0, $netGoods) * $goodsPaid / $paidGoods, 2) : 0.0;
        $deliveryRoom = max(0, $part->shopDeliveryCharge() - $taken['delivery']);
        $shopDelivery = round(min(max(0, $refund - $goodsPaid), $deliveryRoom), 2);
        $rate = (float) $part->commission_rate / 100;
        $commission = round($shopGoods * $rate, 2);

        return [
            'goods_paid' => $goodsPaid,
            'shop_goods' => $shopGoods,
            'shop_delivery' => $shopDelivery,
            'commission' => $commission,
            'shop_gives_back' => round(round($shopGoods * (1 - $rate), 2) + $shopDelivery, 2),
        ];
    }
}
