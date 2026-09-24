@extends('layouts.app')

@php
    $items = $cart->items->filter(fn ($i) => $i->product);
    $subtotal = (float) $cart->total;
    $total = max(0, $subtotal - (float) $discount);
    $units = (int) $items->sum('quantity');
@endphp

@section('content')
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-5 lg:py-8">
        <h1 class="font-display text-2xl lg:text-3xl font-bold text-dark mb-4">{{ __('Cart') }}@if($units)<span class="text-gray-500 font-sans font-normal text-base ms-2">({{ trans_choice(':count item|:count items', $units, ['count' => $units]) }})</span>@endif</h1>

        @if($items->isEmpty())
            <div class="bg-white border border-gray-200 rounded-xl p-10 text-center">
                <span class="mx-auto w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center mb-4"><x-icon name="cart" class="w-7 h-7" /></span>
                <p class="font-semibold text-lg">{{ __('app.cart_empty_title') }}</p>
                <p class="text-gray-600 text-sm mt-1">{{ __('app.cart_empty_description') }}</p>
                <div class="mt-5 flex flex-wrap justify-center gap-3">
                    <a href="{{ route('shop') }}" class="px-5 py-2.5 rounded-lg bg-primary text-white font-semibold">{{ __('app.start_shopping') }}</a>
                    <a href="{{ route('deals') }}" class="px-5 py-2.5 rounded-lg border border-gray-300 font-semibold">{{ __('Today\'s deals') }}</a>
                </div>
                @guest
                    <p class="mt-5 text-sm text-gray-600"><a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">{{ __('Sign in') }}</a> {{ __('to see items you added before.') }}</p>
                @endguest
            </div>
        @else
            <div class="grid lg:grid-cols-[1fr_22rem] gap-5 lg:gap-6 items-start">
                <section class="bg-white border border-gray-200 rounded-xl divide-y divide-gray-200" aria-label="{{ __('Items in your cart') }}">
                    @foreach($items as $item)
                        @php $product = $item->product; $seller = $product->seller; @endphp
                        <div class="p-4 flex gap-3 sm:gap-4">
                            <a href="{{ route('products.show', $product) }}" class="shrink-0 w-20 h-20 sm:w-28 sm:h-28 rounded-lg overflow-hidden bg-primary-50">
                                <img src="{{ $product->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                            </a>
                            <div class="flex-1 min-w-0 grid sm:grid-cols-[1fr_auto] gap-x-4 gap-y-2">
                                <div class="min-w-0">
                                    <a href="{{ route('products.show', $product) }}" class="font-semibold text-dark leading-snug hover:text-primary hover:underline line-clamp-2">{{ $product->name }}</a>
                                    @if($item->variant)<p class="text-sm text-gray-600">{{ $item->variant->name }}</p>@endif
                                    @if($seller)<p class="text-xs text-gray-500 mt-0.5">{{ __('Sold by') }} {{ $seller->business_name ?: $seller->name }}</p>@endif
                                    <x-stock :quantity="(int) $product->stock_quantity" class="mt-1" />
                                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                                        <form action="{{ route('cart.update', $item) }}" method="POST" class="flex items-center gap-2">
                                            @csrf
                                            @method('PUT')
                                            <label for="qty-{{ $item->id }}" class="text-gray-600">{{ __('Qty') }}</label>
                                            <select id="qty-{{ $item->id }}" name="quantity" onchange="this.form.submit()" class="rounded-lg border border-gray-300 bg-white ps-3 pe-8 py-1.5 text-sm focus:border-primary focus:ring-primary">
                                                @for($q = 1; $q <= max($item->quantity, min(20, (int) $product->stock_quantity)); $q++)
                                                    <option value="{{ $q }}" @selected($q === (int) $item->quantity)>{{ $q }}</option>
                                                @endfor
                                            </select>
                                            <noscript><button type="submit" class="text-primary font-semibold">{{ __('Update') }}</button></noscript>
                                        </form>
                                        @auth
                                            <form action="{{ route('cart.saveForLater', $item) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="text-primary font-semibold hover:underline">{{ __('Save for later') }}</button>
                                            </form>
                                        @endauth
                                        <form action="{{ route('cart.remove', $item) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-gray-600 hover:text-danger hover:underline">{{ __('Remove') }}</button>
                                        </form>
                                    </div>
                                </div>
                                <div class="sm:text-end">
                                    <p class="font-bold text-dark text-lg">{{ \App\Support\Money::format($item->subtotal) }}</p>
                                    @if($item->quantity > 1)<p class="text-xs text-gray-500">{{ \App\Support\Money::format($product->final_price) }} {{ __('each') }}</p>@endif
                                    @if($product->was_price)<p class="text-xs font-semibold text-coral">{{ __('Save :amount', ['amount' => \App\Support\Money::format($product->savings * $item->quantity)]) }}</p>@endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                    <div class="p-4 flex items-center justify-between text-sm">
                        <a href="{{ route('shop') }}" class="font-semibold text-primary hover:underline">{{ __('app.continue_shopping') }}</a>
                        <form action="{{ route('cart.clear') }}" method="POST">
                            @csrf
                            <button type="submit" class="text-gray-600 hover:text-danger hover:underline">{{ __('app.clear_cart') }}</button>
                        </form>
                    </div>
                </section>

                <!-- Summary -->
                <aside class="lg:sticky lg:top-[132px] bg-white border border-gray-200 rounded-xl p-5 space-y-4">
                    <h2 class="font-semibold text-lg">{{ __('app.order_summary') }}</h2>

                    @if($freeOver > 0)
                        @php $left = max(0, $freeOver - $subtotal); @endphp
                        <div class="rounded-lg bg-primary-50 p-3 text-sm">
                            @if($left > 0)
                                <p>{{ __('Add :amount more for free delivery.', ['amount' => \App\Support\Money::format($left)]) }}</p>
                            @else
                                <p class="font-semibold text-success flex items-center gap-1.5"><x-icon name="check" class="w-4 h-4" />{{ __('Your order gets free delivery.') }}</p>
                            @endif
                            <div class="mt-2 h-2 rounded-full bg-white overflow-hidden" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ (int) min(100, $subtotal / $freeOver * 100) }}">
                                <div class="h-full bg-primary rounded-full" style="width: {{ min(100, $subtotal / $freeOver * 100) }}%"></div>
                            </div>
                        </div>
                    @endif

                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between"><dt class="text-gray-600">{{ __('app.subtotal') }}</dt><dd class="font-medium">{{ \App\Support\Money::format($subtotal) }}</dd></div>
                        @if($voucher && $discount > 0)
                            <div class="flex justify-between text-success"><dt>{{ __('Voucher') }} ({{ $voucher->code }})</dt><dd class="font-medium">&minus;{{ \App\Support\Money::format($discount) }}</dd></div>
                        @endif
                        <div class="flex justify-between"><dt class="text-gray-600">{{ __('Delivery') }}</dt><dd class="text-gray-600">{{ __('Calculated at checkout') }}</dd></div>
                        <div class="flex justify-between border-t border-gray-200 pt-3 text-base"><dt class="font-semibold">{{ __('Estimated total') }}</dt><dd class="font-bold">{{ \App\Support\Money::format($total) }}</dd></div>
                    </dl>

                    @if($voucher)
                        <form action="{{ route('cart.removeVoucher') }}" method="POST" class="text-sm">
                            @csrf
                            <button type="submit" class="text-danger hover:underline">{{ __('Remove voucher') }}</button>
                        </form>
                    @else
                        <form action="{{ route('cart.applyVoucher') }}" method="POST">
                            @csrf
                            <label for="voucher_code" class="text-sm font-medium text-gray-700">{{ __('Voucher code') }}</label>
                            <div class="mt-1 flex gap-2">
                                <input id="voucher_code" type="text" name="voucher_code" class="flex-1 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm uppercase focus:border-primary focus:ring-primary" required>
                                <button type="submit" class="px-4 rounded-lg border border-gray-300 font-semibold text-sm hover:bg-gray-50">{{ __('Apply') }}</button>
                            </div>
                            @error('voucher_code')<p class="text-danger text-sm mt-1">{{ $message }}</p>@enderror
                        </form>
                    @endif

                    @auth
                        <a href="{{ route('checkout') }}" class="flex items-center justify-center gap-2 w-full h-12 rounded-lg bg-primary hover:bg-primary-hover text-white font-semibold">{{ __('app.proceed_to_checkout') }}</a>
                    @else
                        <a href="{{ route('checkout') }}" class="flex items-center justify-center gap-2 w-full h-12 rounded-lg bg-primary hover:bg-primary-hover text-white font-semibold">{{ __('Sign in to check out') }}</a>
                        <p class="text-xs text-gray-500 text-center">{{ __('Your cart stays with you when you sign in.') }}</p>
                    @endauth
                    <ul class="text-xs text-gray-600 space-y-1.5">
                        <li class="flex items-center gap-2"><x-icon name="shield" class="w-4 h-4 text-primary" />{{ __('Reviewed local sellers') }}</li>
                        <li class="flex items-center gap-2"><x-icon name="bank" class="w-4 h-4 text-primary" />{{ __('Cash on delivery or bank transfer') }}</li>
                    </ul>
                </aside>
            </div>
        @endif

        @if($saved->isNotEmpty())
            <section class="mt-8">
                <h2 class="font-display text-xl font-bold text-dark mb-3">{{ __('Saved for later') }} <span class="text-gray-500 font-sans font-normal text-base">({{ $saved->count() }})</span></h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-3">
                    @foreach($saved as $s)
                        <div class="bg-white border border-gray-200 rounded-xl p-3 flex gap-3">
                            <a href="{{ route('products.show', $s->product) }}" class="shrink-0 w-20 h-20 rounded-lg overflow-hidden bg-primary-50"><img src="{{ $s->product->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="{{ $s->product->name }}" class="w-full h-full object-cover"></a>
                            <div class="min-w-0 flex-1 text-sm">
                                <a href="{{ route('products.show', $s->product) }}" class="font-semibold line-clamp-2 hover:text-primary hover:underline">{{ $s->product->name }}</a>
                                <p class="font-bold mt-0.5">{{ \App\Support\Money::format($s->product->final_price) }}</p>
                                <x-stock :quantity="(int) $s->product->stock_quantity" />
                                <div class="mt-2 flex gap-3">
                                    @if($s->product->stock_quantity > 0 && $s->product->is_active)
                                        <form action="{{ route('saved.moveToCart', $s) }}" method="POST">@csrf<button type="submit" class="font-semibold text-primary hover:underline">{{ __('Move to cart') }}</button></form>
                                    @endif
                                    <form action="{{ route('saved.remove', $s) }}" method="POST">@csrf @method('DELETE')<button type="submit" class="text-gray-600 hover:text-danger hover:underline">{{ __('Remove') }}</button></form>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</div>
@endsection
