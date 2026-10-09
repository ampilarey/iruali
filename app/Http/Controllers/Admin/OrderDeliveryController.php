<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Services\FulfilmentService;
use App\Support\Audit;
use App\Support\PackingSlip;

/**
 * Admin order page: confirm a pickup on the shop's behalf (the customer lost their code, or the
 * shop typed too many wrong codes), and print any part's packing slip.
 */
class OrderDeliveryController extends Controller
{
    public function collected(Order $order, SellerOrder $part, FulfilmentService $fulfilment)
    {
        abort_unless($part->order_id === $order->id, 404);

        if (! $part->isReadyForPickup() || $order->status === 'cancelled') {
            return back()->with('error', __('This part is not waiting for pickup.'));
        }

        $fulfilment->completePickup($part);
        Audit::record('pickup.confirmed', $part, ['order_number' => $order->order_number, 'shop' => $part->shopName()]);

        return back()->with('success', __('Collection confirmed. This part now counts as delivered.'));
    }

    public function packingSlip(Order $order, SellerOrder $part)
    {
        abort_unless($part->order_id === $order->id, 404);

        return PackingSlip::view($order, $part);
    }
}
