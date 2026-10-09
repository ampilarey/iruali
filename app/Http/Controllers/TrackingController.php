<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Services\FulfilmentService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;

/**
 * Delivery details on a shop part (courier and tracking, or boat/flight and expected date),
 * edited by the shop or an admin without changing the part's status.
 */
class TrackingController extends Controller
{
    /**
     * Validation for the tracking fields, shared with the status forms.
     */
    public static function rules(): array
    {
        return [
            'courier' => 'nullable|string|max:100',
            'tracking_number' => 'nullable|string|max:100',
            'tracking_url' => 'nullable|url:http,https|max:500',
            'vessel_or_flight' => 'nullable|string|max:150',
            'expected_delivery_date' => 'nullable|date|after_or_equal:'.now()->subYear()->toDateString(),
        ];
    }

    public function updateAsSeller(Request $request, Order $order, SellerOrder $part, FulfilmentService $fulfilment)
    {
        abort_unless($part->order_id === $order->id && $part->seller_id === CurrentShop::id(), 403);

        return $this->update($request, $part, $fulfilment);
    }

    public function updateAsAdmin(Request $request, Order $order, SellerOrder $part, FulfilmentService $fulfilment)
    {
        abort_unless($request->user()->isAdmin(), 403);
        abort_unless($part->order_id === $order->id, 404);

        return $this->update($request, $part, $fulfilment);
    }

    protected function update(Request $request, SellerOrder $part, FulfilmentService $fulfilment)
    {
        $data = $request->validate(self::rules());

        $fulfilment->updateTracking($part, $data);

        return back()->with('success', 'Delivery details saved.');
    }
}
