<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Island;
use App\Models\SellerDeliverySetting;
use App\Services\DeliveryService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Seller Centre → Settings → Delivery & pickup: "usually ships within N days" (used for the
 * delivery estimates customers see) and pickup from the shop (address, island, hours).
 */
class DeliverySettingsController extends Controller
{
    public function edit(DeliveryService $delivery)
    {
        return view('seller.settings.delivery', [
            'setting' => SellerDeliverySetting::for(CurrentShop::id()),
            'islandsByAtoll' => $delivery->islandsByAtoll(),
        ]);
    }

    public function update(Request $request)
    {
        $pickup = $request->boolean('pickup_enabled');

        $data = $request->validate([
            'ships_within_days' => 'required|integer|min:0|max:30',
            'pickup_enabled' => 'nullable|boolean',
            'pickup_address' => [Rule::requiredIf($pickup), 'nullable', 'string', 'max:255'],
            'pickup_island_id' => [Rule::requiredIf($pickup), 'nullable', 'integer', Rule::exists(Island::class, 'id')->where('is_active', true)],
            'pickup_hours' => [Rule::requiredIf($pickup), 'nullable', 'string', 'max:500'],
        ], [
            'pickup_address.required' => __('Please give the address customers collect from.'),
            'pickup_island_id.required' => __('Please choose the island your shop is on.'),
            'pickup_hours.required' => __('Please give your opening hours, so customers know when to come.'),
        ]);

        SellerDeliverySetting::updateOrCreate(['seller_id' => CurrentShop::id()], [
            'ships_within_days' => (int) $data['ships_within_days'],
            'pickup_enabled' => $pickup,
            'pickup_address' => filled($data['pickup_address'] ?? null) ? trim($data['pickup_address']) : null,
            'pickup_island_id' => $data['pickup_island_id'] ?? null,
            'pickup_hours' => filled($data['pickup_hours'] ?? null) ? trim($data['pickup_hours']) : null,
        ]);

        return redirect()->route('seller.settings.delivery')->with('success', __('Delivery settings saved.'));
    }
}
