{{-- Checkout summary: the shops' own discounts (multi-buy, shop codes), between the subtotal and the iruali voucher. The total above already has them taken off. --}}
@php
    $shopDealsService = app(\App\Services\ShopDiscountService::class);
    $shopDeals = $shopDeals ?? $shopDealsService->forCart($cart);
    $shopNotices = $shopDealsService->pullNotices();
@endphp
@foreach($shopNotices as $notice)
    <div class="rounded-lg bg-sun-soft px-3 py-2 text-xs text-sun-ink"><dt class="sr-only">{{ __('Shop code') }}</dt><dd>{{ __('A shop code was removed: :reason', ['reason' => $notice]) }}</dd></div>
@endforeach
@if($shopDeals['multibuy_discount'] > 0)
    <div class="flex justify-between">
        <dt class="text-gray-600">{{ __('Multi-buy savings') }}</dt>
        <dd class="font-medium text-coral" dir="ltr">−{{ \App\Support\Money::format($shopDeals['multibuy_discount']) }}</dd>
    </div>
@endif
@foreach($shopDeals['codes'] as $applied)
    <div class="flex justify-between gap-2">
        <dt class="text-gray-600">{{ __('Shop code :code (:shop)', ['code' => $applied['code']->code, 'shop' => $applied['shop']]) }}</dt>
        <dd class="font-medium text-coral" dir="ltr">−{{ \App\Support\Money::format($applied['amount']) }}</dd>
    </div>
@endforeach
