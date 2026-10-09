{{-- Seller order list: small badges for a part the customer picks up, a gift and a Malé time slot. Needs $part and $order. --}}
@if($part->isPickup())
    <span class="ms-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">{{ $part->isReadyForPickup() ? __('Ready for pickup') : __('Pickup') }}</span>
@endif
@if($order->isGift())
    <span class="ms-1 rounded-full bg-pink-100 px-2 py-0.5 text-xs font-medium text-pink-800">{{ __('Gift') }}</span>
@endif
@if(! $part->isPickup() && $order->delivery_slot_starts_at)
    <span class="mt-1 block text-xs text-gray-500" dir="ltr">{{ $order->deliverySlotLabel() }}</span>
@endif
