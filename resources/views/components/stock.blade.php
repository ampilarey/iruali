@props(['quantity', 'product' => null])
{{-- With the product: "Coming soon" before it goes on sale, "Pre-order" when it is out of stock but the shop takes pre-orders --}}
@php
    $comingSoon = $product?->isComingSoon();
    $preorderDate = ! $comingSoon && $product && $quantity <= 0 && $product->preorder_enabled && app(\App\Services\PreorderService::class)->isPreorderable($product) ? $product->preorderShipDate() : null;
@endphp
@if($comingSoon)
    <p {{ $attributes->merge(['class' => 'text-xs font-semibold text-sun-ink']) }} data-coming-soon-badge>{{ $product->available_on ? __('Coming :date', ['date' => $product->available_on->translatedFormat('j M')]) : __('Coming soon') }}</p>
@elseif($preorderDate)
    <p {{ $attributes->merge(['class' => 'text-xs font-semibold text-primary']) }} data-preorder-badge>{{ __('Pre-order · ships around :date', ['date' => $preorderDate->translatedFormat('j M')]) }}</p>
@elseif($quantity <= 0)
    <p {{ $attributes->merge(['class' => 'text-xs font-semibold text-danger']) }}>{{ __('Out of stock') }}</p>
@elseif($quantity <= 5)
    <p {{ $attributes->merge(['class' => 'text-xs font-semibold text-sun-ink']) }}>{{ __('Only :count left', ['count' => $quantity]) }}</p>
@else
    <p {{ $attributes->merge(['class' => 'text-xs font-semibold text-success flex items-center gap-1']) }}><x-icon name="check" class="w-3.5 h-3.5" />{{ __('In stock') }}</p>
@endif
