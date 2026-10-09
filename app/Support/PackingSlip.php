<?php

namespace App\Support;

use App\Models\Order;
use App\Models\SellerOrder;
use Illuminate\Contracts\View\View;

/**
 * The printable packing slip for one shop's part of an order: who it goes to (the gift receiver
 * for a gift), how it travels, and the items. A gift that hides prices prints without them and
 * with the gift message. The customer's own receipt is a different page and always has prices.
 */
class PackingSlip
{
    public static function view(Order $order, SellerOrder $part): View
    {
        $order->loadMissing('user');

        return view('seller.orders.packing-slip', [
            'order' => $order,
            'part' => $part,
            'items' => $part->items()->with('product')->get(),
            'hidePrices' => self::hidesPrices($order),
        ]);
    }

    public static function hidesPrices(Order $order): bool
    {
        return $order->isGift() && (bool) $order->gift_hide_prices;
    }
}
