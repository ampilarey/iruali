<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Services\FulfilmentService;
use App\Support\PackingSlip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * A shop's own part of an order: get it ready for pickup, confirm the customer collected it
 * (with the 6-digit code they show), and print the packing slip. Only the shop's own part.
 */
class OrderDeliveryController extends Controller
{
    public function readyForPickup(Order $order, FulfilmentService $fulfilment)
    {
        $part = $this->ownPart($order, $fulfilment);
        $state = $fulfilment->pickupReadiness($part);

        if ($state !== 'ok' || ! $fulfilment->markReadyForPickup($part)) {
            return back()->with('error', match ($state) {
                'not_pickup' => __('The customer chose delivery for this part, not pickup.'),
                'unpaid' => __('Wait until the customer has paid before getting it ready for pickup.'),
                'ready' => __('This part is already marked ready for pickup.'),
                'collected' => __('The customer has already collected this part.'),
                'awaiting_stock' => __('These pre-order items are still waiting for their stock. Record it under Pre-orders when it arrives.'),
                default => __('This order was cancelled. Don\'t hand over these items.'),
            });
        }

        return back()->with('success', __('Marked ready for pickup. The customer has been sent their pickup code.'));
    }

    public function confirmPickup(Request $request, Order $order, FulfilmentService $fulfilment)
    {
        $part = $this->ownPart($order, $fulfilment);
        $data = $request->validate(['pickup_code' => 'required|string|max:20'], [
            'pickup_code.required' => __('Type the 6-digit code the customer shows you.'),
        ]);

        $result = $fulfilment->confirmPickup($part, $data['pickup_code']);
        $left = max(0, SellerOrder::MAX_PICKUP_ATTEMPTS - (int) $part->pickup_code_attempts);

        return match ($result) {
            'collected' => back()->with('success', __('Collection confirmed. This part now counts as delivered.')),
            'wrong' => back()->withErrors(['pickup_code' => trans_choice('That code is not right. Ask the customer for the 6-digit code in their email or text. :count try left.|That code is not right. Ask the customer for the 6-digit code in their email or text. :count tries left.', $left, ['count' => $left])]),
            'locked' => back()->withErrors(['pickup_code' => __('Too many wrong codes. Please ask iruali support to confirm this collection.')]),
            default => back()->with('error', __('This part is not waiting for pickup.')),
        };
    }

    public function packingSlip(Order $order, FulfilmentService $fulfilment)
    {
        return PackingSlip::view($order, $this->ownPart($order, $fulfilment));
    }

    protected function ownPart(Order $order, FulfilmentService $fulfilment): SellerOrder
    {
        $part = $fulfilment->partFor($order, Auth::user());
        abort_if($part === null, 404);

        return $part;
    }
}
