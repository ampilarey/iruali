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
 * delivery estimates customers see), pickup from the shop (address, island, hours) and the shop's
 * own delivery fees (when iruali allows them, up to its limit).
 */
class DeliverySettingsController extends Controller
{
    public function edit(DeliveryService $delivery)
    {
        $shop = \App\Models\User::with('homeIsland')->find(CurrentShop::id());

        return view('seller.settings.delivery', [
            'setting' => SellerDeliverySetting::for(CurrentShop::id()),
            'islandsByAtoll' => $delivery->islandsByAtoll(),
            'ownFeesAllowed' => SellerDeliverySetting::ownFeesAllowed(),
            'maxFee' => SellerDeliverySetting::maxFee(),
            'homeIsland' => $shop?->homeIsland,
            'irualiFees' => [
                DeliveryService::AREA_GREATER_MALE => $delivery->areaFee(DeliveryService::AREA_GREATER_MALE),
                DeliveryService::AREA_ISLANDS => $delivery->areaFee(DeliveryService::AREA_ISLANDS),
            ],
        ]);
    }

    public function update(Request $request)
    {
        $pickup = $request->boolean('pickup_enabled');
        // Own fees can only be turned on (or changed) while iruali allows them; saved ones are kept otherwise
        $feesEditable = SellerDeliverySetting::ownFeesAllowed();
        $ownFees = $feesEditable && $request->boolean('own_fees');
        $money = 'nullable|numeric|min:0|max:'.SellerDeliverySetting::maxFee();

        $data = $request->validate([
            'ships_within_days' => 'required|integer|min:0|max:30',
            'pickup_enabled' => 'nullable|boolean',
            'pickup_address' => [Rule::requiredIf($pickup), 'nullable', 'string', 'max:255'],
            'pickup_island_id' => [Rule::requiredIf($pickup), 'nullable', 'integer', Rule::exists(Island::class, 'id')->where('is_active', true)],
            'pickup_hours' => [Rule::requiredIf($pickup), 'nullable', 'string', 'max:500'],
            'own_fees' => 'nullable|boolean',
            'fee_local' => $money,
            'fee_greater_male' => [Rule::requiredIf($ownFees), ...explode('|', $money)],
            'fee_islands' => [Rule::requiredIf($ownFees), ...explode('|', $money)],
            'free_over' => 'nullable|numeric|min:0|max:1000000',
            'min_order_amount' => 'nullable|numeric|min:0|max:100000',
            'hours' => 'nullable|array',
            'hours.*' => 'nullable|array',
            'hours.*.*' => ['nullable', 'date_format:H:i'],
        ], [
            'fee_greater_male.required' => __('Please give your fee for Greater Malé.'),
            'fee_islands.required' => __('Please give your fee for other islands.'),
            'fee_local.max' => __('A delivery fee can be at most :max.', ['max' => \App\Support\Money::format(SellerDeliverySetting::maxFee())]),
            'fee_greater_male.max' => __('A delivery fee can be at most :max.', ['max' => \App\Support\Money::format(SellerDeliverySetting::maxFee())]),
            'fee_islands.max' => __('A delivery fee can be at most :max.', ['max' => \App\Support\Money::format(SellerDeliverySetting::maxFee())]),
            'pickup_address.required' => __('Please give the address customers collect from.'),
            'pickup_island_id.required' => __('Please choose the island your shop is on.'),
            'pickup_hours.required' => __('Please give your opening hours, so customers know when to come.'),
        ]);

        // Opening hours: a day with both times, opening before closing; the rest are closed
        $hours = [];
        foreach (array_keys(SellerDeliverySetting::DAYS) as $day) {
            [$open, $close] = [(string) ($data['hours'][$day][0] ?? ''), (string) ($data['hours'][$day][1] ?? '')];
            if ($open === '' && $close === '') {
                continue;
            }
            if ($open === '' || $close === '' || $open >= $close) {
                return back()->withInput()->withErrors(['hours' => __('Give both times for :day, with the opening time before the closing time.', ['day' => __(SellerDeliverySetting::DAYS[$day])])]);
            }
            $hours[(string) $day] = [$open, $close];
        }

        $amount = fn (string $key) => isset($data[$key]) && $data[$key] !== '' ? round((float) $data[$key], 2) : null;
        $fees = $feesEditable ? [
            'own_fees' => $ownFees,
            'fee_local' => $amount('fee_local'),
            'fee_greater_male' => $amount('fee_greater_male'),
            'fee_islands' => $amount('fee_islands'),
            'free_over' => ($free = $amount('free_over')) !== null && $free > 0 ? $free : null,
        ] : [];

        SellerDeliverySetting::updateOrCreate(['seller_id' => CurrentShop::id()], $fees + [
            'ships_within_days' => (int) $data['ships_within_days'],
            'pickup_enabled' => $pickup,
            'pickup_address' => filled($data['pickup_address'] ?? null) ? trim($data['pickup_address']) : null,
            'pickup_island_id' => $data['pickup_island_id'] ?? null,
            'pickup_hours' => filled($data['pickup_hours'] ?? null) ? trim($data['pickup_hours']) : null,
            'opening_hours' => $hours ?: null,
            'min_order_amount' => ($minimum = $amount('min_order_amount')) !== null && $minimum > 0 ? $minimum : null,
        ]);

        return redirect()->route('seller.settings.delivery')->with('success', __('Delivery settings saved.'));
    }
}
