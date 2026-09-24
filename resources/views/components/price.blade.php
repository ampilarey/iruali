@props(['product', 'size' => 'md'])
@php
    $price = (float) $product->final_price;
    $was = $product->was_price;
    $main = ['md' => 'text-lg', 'lg' => 'text-2xl', 'xl' => 'text-3xl'][$size] ?? 'text-lg';
@endphp
<div {{ $attributes->merge(['class' => 'leading-tight']) }}>
    <p class="price font-bold {{ $main }} {{ $was ? 'text-coral' : 'text-dark' }}">{{ \App\Support\Money::format($price) }}</p>
    @if($was)
        <p class="text-xs text-gray-500">
            <span class="line-through">{{ \App\Support\Money::format($was) }}</span>
            <span class="font-semibold text-coral ms-1">{{ __('Save :amount', ['amount' => \App\Support\Money::format($product->savings)]) }}</span>
        </p>
    @endif
</div>
