{{-- Customer order pages: the Malé delivery time and the gift details, under the shipping address. Needs $order. --}}
@if($order->deliverySlotLabel() || $order->isGift())
    <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-6 text-sm">
        @if($order->deliverySlotLabel())
            <div>
                <h3 class="font-semibold text-gray-900 mb-1">{{ __('Delivery time') }}</h3>
                <p class="text-gray-600 flex items-center gap-1.5"><x-icon name="clock" class="w-4 h-4 text-primary" /><span dir="ltr">{{ $order->deliverySlotLabel() }}</span></p>
            </div>
        @endif
        @if($order->isGift())
            <div>
                <h3 class="font-semibold text-gray-900 mb-1 flex items-center gap-1.5"><x-icon name="gift" class="w-4 h-4 text-primary" />{{ __('This is a gift') }}</h3>
                <p class="text-gray-600">{{ __('For :name', ['name' => $order->gift_receiver_name]) }}@if($order->gift_receiver_phone) · <span dir="ltr">{{ $order->gift_receiver_phone }}</span>@endif</p>
                @if($order->gift_message)<p class="text-gray-600 whitespace-pre-line">“{{ $order->gift_message }}”</p>@endif
                @if($order->gift_hide_prices)<p class="text-xs text-gray-500">{{ __('The packing slip in the parcel has no prices.') }}</p>@endif
            </div>
        @endif
    </div>
@endif
