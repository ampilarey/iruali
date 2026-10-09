{{-- Seller order page: how this part reaches the customer (delivery with the Malé time slot, or pickup
     with "Ready for pickup" and the code check), the gift details and the packing slip. The pickup
     code itself is never shown here: the customer shows it. Needs $order and $part. --}}
@php $readiness = app(\App\Services\FulfilmentService::class)->pickupReadiness($part); @endphp
<div class="rounded-lg bg-white p-5 shadow text-sm space-y-3" id="delivery">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">{{ $part->isPickup() ? __('Pickup from your shop') : __('Delivery') }}</h2>
        <a href="{{ route('seller.orders.packing-slip', $order) }}" target="_blank" rel="noopener" class="text-xs font-semibold text-primary-700 hover:underline">{{ __('Print packing slip') }}</a>
    </div>

    @if($part->isPickup())
        <p class="text-gray-900">{{ __('The customer collects these items from your shop. No delivery is needed.') }}</p>
        @if($part->pickup_address || $part->pickup_island)
            <p class="text-gray-600 flex items-start gap-1.5"><x-icon name="map-pin" class="w-4 h-4 mt-0.5 shrink-0" />{{ collect([$part->pickup_address, $part->pickup_island])->filter()->join(', ') }}</p>
        @endif

        @switch($readiness)
            @case('collected')
                <p class="rounded-lg bg-green-50 px-3 py-2 font-medium text-green-800">{{ __('Collected on :date.', ['date' => ($part->pickup_collected_at ?? $part->delivered_at)?->translatedFormat('j M Y, H:i')]) }}</p>
                @break
            @case('cancelled')
                @break
            @case('unpaid')
                <p class="rounded-lg bg-amber-50 px-3 py-2 text-amber-800">{{ __('Wait until the customer has paid. Then mark it ready for pickup and they get their pickup code.') }}</p>
                @break
            @case('ok')
                <form method="POST" action="{{ route('seller.orders.pickup.ready', $order) }}" class="space-y-1">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Ready for pickup') }}</button>
                    <p class="text-xs text-gray-500">{{ __('The customer gets an email (and a text, when SMS is set up) with a 6-digit pickup code.') }}</p>
                </form>
                @break
            @case('ready')
                <p class="text-gray-700">{{ __('Ready for pickup since :date.', ['date' => $part->pickup_ready_at?->translatedFormat('j M, H:i')]) }}</p>
                @if($part->pickupLocked())
                    <p class="rounded-lg bg-red-50 px-3 py-2 text-red-800">{{ __('Too many wrong codes. Please ask iruali support to confirm this collection.') }}</p>
                @else
                    <form method="POST" action="{{ route('seller.orders.pickup.collected', $order) }}" class="space-y-2 rounded-lg border border-gray-200 p-3">
                        @csrf
                        <label for="pickup_code" class="block text-xs font-medium text-gray-700">{{ __('When the customer comes, ask for the 6-digit pickup code from their email or text and type it here.') }}</label>
                        <div class="flex gap-2">
                            <input id="pickup_code" name="pickup_code" inputmode="numeric" pattern="[0-9 ]*" maxlength="7" autocomplete="off" dir="ltr" placeholder="123456" class="w-32 rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono tracking-widest" @error('pickup_code') aria-invalid="true" @enderror>
                            <button type="submit" class="flex-1 rounded-lg bg-primary px-3 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Confirm collection') }}</button>
                        </div>
                        @error('pickup_code')<p class="text-xs text-red-700">{{ $message }}</p>@enderror
                    </form>
                @endif
                @break
        @endswitch
        @if($part->pickup_hours)
            <p class="text-xs text-gray-500"><span class="font-medium">{{ __('Your opening hours') }}:</span> {{ $part->pickup_hours }}</p>
        @endif
    @else
        <p class="text-gray-900">{{ __('Deliver to the customer') }}@if($order->deliveryAreaLabel()) · <span class="text-gray-600">{{ $order->deliveryAreaLabel() }}</span>@endif</p>
        @if($order->deliverySlotLabel())
            <p class="rounded-lg bg-primary-50 px-3 py-2 text-gray-900 flex items-center gap-2"><x-icon name="clock" class="w-4 h-4 text-primary" /><span>{{ __('Delivery time') }}: <strong dir="ltr">{{ $order->deliverySlotLabel() }}</strong></span></p>
        @endif
        @if((float) $part->delivery_surcharge > 0)
            <p class="text-xs text-gray-500">{{ __('The customer paid :amount in bulky-item delivery charges for your items.', ['amount' => \App\Support\Money::format($part->delivery_surcharge)]) }}</p>
        @endif
    @endif

    @if($order->isGift())
        <div class="rounded-lg border border-pink-200 bg-pink-50 p-3 space-y-1">
            <p class="font-semibold text-gray-900 flex items-center gap-1.5"><x-icon name="gift" class="w-4 h-4 text-pink-600" />{{ __('This is a gift') }}</p>
            <p class="text-gray-700">{{ __('For :name', ['name' => $order->gift_receiver_name]) }}@if($order->gift_receiver_phone) · <a href="tel:{{ $order->gift_receiver_phone }}" dir="ltr" class="font-medium hover:underline">{{ $order->gift_receiver_phone }}</a>@endif</p>
            @if($order->gift_message)
                <p class="text-gray-700 whitespace-pre-line">“{{ $order->gift_message }}”</p>
            @endif
            <p class="text-xs text-gray-600">{{ $order->gift_hide_prices ? __('Pack it without prices: the packing slip leaves them out.') : __('The customer is happy for the packing slip to show prices.') }}</p>
        </div>
    @endif
</div>
