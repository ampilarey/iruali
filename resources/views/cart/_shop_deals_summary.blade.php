{{-- The cart summary's shop-funded discounts, between the subtotal and the iruali voucher (the voucher is worked out on what is left). --}}
@if(($shopDeals['multibuy_discount'] ?? 0) > 0)
    <div class="flex justify-between text-coral"><dt>{{ __('Multi-buy savings') }}</dt><dd class="font-medium" dir="ltr">&minus;{{ \App\Support\Money::format($shopDeals['multibuy_discount']) }}</dd></div>
@endif
@foreach($shopDeals['codes'] ?? [] as $applied)
    <div class="flex justify-between gap-2 text-success"><dt>{{ __('Shop code :code (:shop)', ['code' => $applied['code']->code, 'shop' => $applied['shop']]) }}</dt><dd class="font-medium" dir="ltr">&minus;{{ \App\Support\Money::format($applied['amount']) }}</dd></div>
@endforeach
