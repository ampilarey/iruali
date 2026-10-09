{{-- Cart and checkout: lines from a shop on holiday stay in the cart, but can't be ordered until the shop is back
     (placing the order refuses them: StoreOrderRequest, StoreGuestOrderRequest, OrderService). --}}
@php
    $cart->loadMissing('items.product.seller');
    $holidayLines = $cart->items->filter(fn ($line) => $line->product?->seller?->isOnHoliday());
@endphp
@if($holidayLines->isNotEmpty())
    <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900" role="alert" data-cart-holiday>
        <p class="font-semibold flex items-center gap-2"><x-icon name="clock" class="w-5 h-5 shrink-0" />{{ trans_choice('One item can\'t be ordered right now|:count items can\'t be ordered right now', $holidayLines->count(), ['count' => $holidayLines->count()]) }}</p>
        <ul class="mt-2 ps-7 space-y-1 list-disc list-inside">
            @foreach($holidayLines as $line)
                <li>{{ \App\Support\ShopHoliday::cartMessage($line->product) }}</li>
            @endforeach
        </ul>
        <p class="mt-2 ps-7 text-xs text-amber-800">
            {{ trans_choice('Save it for later or remove it to check out the rest of your cart.|Save them for later or remove them to check out the rest of your cart.', $holidayLines->count()) }}
            @unless(request()->routeIs('cart'))<a href="{{ route('cart') }}" class="ms-1 font-semibold underline hover:no-underline">{{ __('Back to my cart') }}</a>@endunless
        </p>
    </div>
@endif
