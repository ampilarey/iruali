{{-- Admin order page: the delivery as charged (area, fee, bulky-item charges), the Malé time slot and
     the gift details. Needs $order. --}}
@php
    $surcharge = (float) $order->delivery_surcharge;
    $parts = $order->sellerOrders;
    $pickups = $parts->filter(fn ($part) => $part->isPickup())->count();
@endphp
@if($order->deliveryAreaLabel() || $pickups > 0 || $order->deliverySlotLabel() || $order->isGift())
<div class="rounded-lg bg-white p-5 shadow text-sm space-y-2">
    <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ __('Delivery') }}</h2>
    @if($order->deliveryAreaLabel())
        <dl class="space-y-1">
            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Area') }}</dt><dd class="text-gray-900">{{ $order->deliveryAreaLabel() }}</dd></div>
            <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Delivery fee') }}</dt><dd class="text-gray-900">{{ \App\Support\Money::format((float) $order->shipping_amount - $surcharge) }}</dd></div>
            @if($surcharge > 0)
                <div class="flex justify-between gap-3"><dt class="text-gray-600">{{ __('Bulky-item charges') }}</dt><dd class="text-gray-900">{{ \App\Support\Money::format($surcharge) }}</dd></div>
            @endif
        </dl>
    @elseif($pickups > 0)
        <p class="text-gray-900">{{ __('Everything is picked up from the shops: no delivery fee.') }}</p>
    @endif
    @if($pickups > 0 && $pickups < $parts->count())
        <p class="text-xs text-gray-500">{{ trans_choice(':count shop part is picked up by the customer.|:count shop parts are picked up by the customer.', $pickups, ['count' => $pickups]) }}</p>
    @endif
    @if($order->deliverySlotLabel())
        <p class="rounded-md bg-primary-50 px-3 py-2 text-gray-900"><span class="font-medium">{{ __('Delivery time') }}:</span> <span dir="ltr">{{ $order->deliverySlotLabel() }}</span></p>
    @endif
    @if($order->isGift())
        <div class="rounded-md border border-pink-200 bg-pink-50 px-3 py-2 space-y-1">
            <p class="font-semibold text-gray-900">{{ __('This is a gift') }}</p>
            <p class="text-gray-700">{{ __('For :name', ['name' => $order->gift_receiver_name]) }}@if($order->gift_receiver_phone) · <span dir="ltr">{{ $order->gift_receiver_phone }}</span>@endif</p>
            @if($order->gift_message)<p class="text-gray-700 whitespace-pre-line">“{{ $order->gift_message }}”</p>@endif
            <p class="text-xs text-gray-500">{{ $order->gift_hide_prices ? __('Prices hidden on the packing slip.') : __('Prices shown on the packing slip.') }}</p>
        </div>
    @endif
</div>
@endif
