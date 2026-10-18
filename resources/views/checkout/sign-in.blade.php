@extends('layouts.app')

@section('title', __('Checkout').' - iruali')

{{-- Checkout for someone not signed in yet (as on Bake & Grill): the sign-in steps right here, beside
     the order. A password, or a code by email or SMS; a new number gets an account on the spot. --}}
@section('content')
<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('Checkout') }}</h1>
        <p class="text-gray-600">{{ __('Sign in with your mobile number or email to place your order. Your cart comes with you.') }}</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-5 gap-8">
        <section class="lg:col-span-3 bg-white rounded-2xl border border-gray-200 p-6 space-y-6" data-checkout-sign-in data-login-phone="{{ $pending['step'] ?? 'phone' }}">
            <div>
                <h2 class="text-xl font-semibold text-gray-900">{{ __('1. Sign in') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('New to iruali? Type your mobile number: we text you a code and your account is ready.') }}</p>
            </div>
            @include('auth._sign-in-steps', ['inCheckout' => true])
            @unless($pending)
                @include('auth._social')
            @endunless
        </section>

        <aside class="lg:col-span-2 bg-white rounded-2xl border border-gray-200 p-6 h-fit" data-checkout-summary>
            <h2 class="text-lg font-semibold text-gray-900 mb-4">{{ __('Your order') }}</h2>
            <ul class="divide-y divide-gray-100 text-sm">
                @foreach($cart->items as $item)
                    <li class="flex items-start justify-between gap-3 py-2">
                        <span class="text-gray-800">{{ $item->product?->localized_name }} <span class="text-gray-500">× {{ $item->quantity }}</span></span>
                        <span class="shrink-0 font-medium text-gray-900">{{ \App\Support\Money::format((float) $item->price * $item->quantity) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="mt-4 flex justify-between border-t border-gray-200 pt-4 font-semibold text-gray-900">
                <span>{{ __('Items') }}</span>
                <span>{{ \App\Support\Money::format((float) $goodsTotal) }}</span>
            </div>
            <p class="mt-2 text-xs text-gray-500">{{ __('Delivery and payment come next, once you are signed in.') }}</p>
            <a href="{{ route('cart') }}" class="mt-4 inline-block text-sm font-medium text-primary hover:underline">{{ __('Edit cart') }}</a>
        </aside>
    </div>
</div>
@endsection
