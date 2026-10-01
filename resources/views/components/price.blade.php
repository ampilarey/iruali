@props(['product', 'size' => 'md'])
@php
    $from = $product->from_price; // lowest variant price when the variants are priced differently
    $price = $from ?? (float) $product->final_price;
    $was = $from ? null : $product->was_price;
    $main = ['md' => 'text-lg', 'lg' => 'text-2xl', 'xl' => 'text-3xl'][$size] ?? 'text-lg';
@endphp
<div {{ $attributes->merge(['class' => 'leading-tight']) }}>
    <p class="price font-bold {{ $main }} {{ $was ? 'text-coral' : 'text-dark' }}">@if($from)<span class="text-xs font-medium text-gray-500">{{ __('From') }}</span> @endif{{ \App\Support\Money::format($price) }}</p>
    @if($was)
        <p class="text-xs text-gray-500">
            <span class="line-through">{{ \App\Support\Money::format($was) }}</span>
            <span class="font-semibold text-coral ms-1">{{ __('Save :amount', ['amount' => \App\Support\Money::format($product->savings)]) }}</span>
        </p>
    @endif
</div>
