@extends('layouts.app')

@section('title', $quote->label())

@php
    use App\Enums\QuoteStatus;
    $expired = $quote->hasStatus(QuoteStatus::Expired) || ($quote->hasStatus(QuoteStatus::Quoted, QuoteStatus::Accepted) && $quote->isExpired());
    $canAccept = $quote->hasStatus(QuoteStatus::Quoted) && ! $expired;
    $canDecline = $quote->hasStatus(QuoteStatus::New, QuoteStatus::Quoted, QuoteStatus::Accepted) && ! $expired;
    $productOnSale = $quote->product && ! $quote->product->trashed() && $quote->product->is_active;
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-5">
    <nav class="text-sm text-gray-500" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ route('quotes.index') }}" class="hover:text-primary hover:underline">{{ __('My quote requests') }}</a>
    </nav>

    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-dark">{{ $quote->label() }}</h1>
            <p class="text-sm text-gray-600">{{ $quote->displayName() }} · {{ $quote->shopName() }}</p>
        </div>
        <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $expired ? QuoteStatus::Expired->badgeClass() : $quote->statusBadge() }}" data-quote-status>{{ $expired ? QuoteStatus::Expired->label() : $quote->statusLabel() }}</span>
    </div>

    {{-- Where it stands and what can be done --}}
    <section class="rounded-xl border border-gray-200 bg-white p-5 text-sm" data-quote-next>
        @if($expired)
            <p class="font-semibold text-dark">{{ __('This quote expired on :date.', ['date' => $quote->valid_until?->translatedFormat('j M Y')]) }}</p>
            <p class="mt-1 text-gray-600">{{ __('It can no longer be accepted or bought. You can ask the shop for a new one.') }}</p>
        @elseif($quote->hasStatus(QuoteStatus::New))
            <p class="font-semibold text-dark">{{ __('Waiting for :shop to reply.', ['shop' => $quote->shopName()]) }}</p>
            <p class="mt-1 text-gray-600">{{ __('We will email you when the shop sends a price. You can add a message below meanwhile.') }}</p>
        @elseif($quote->hasStatus(QuoteStatus::Quoted))
            <p class="font-semibold text-dark">{{ __(':shop sent you a quote. Accept it to put it in your cart at this price.', ['shop' => $quote->shopName()]) }}</p>
            <p class="mt-1 text-gray-600">{{ __('The quantity and price are fixed in the cart. Shop codes, multi-buy offers and iruali vouchers do not apply to quoted prices; your loyalty points and wallet do. Stock is checked when you accept and again at checkout.') }}</p>
        @elseif($quote->hasStatus(QuoteStatus::Accepted))
            <p class="font-semibold text-dark">{{ $inCart ? __('Accepted: it is in your cart.') : __('Accepted, but it is not in your cart right now.') }}</p>
            <p class="mt-1 text-gray-600">{{ __('Check out by :date; after that the quote expires and comes out of your cart. Stock is not held for it.', ['date' => $quote->valid_until?->translatedFormat('j M Y')]) }}</p>
        @elseif($quote->hasStatus(QuoteStatus::Ordered))
            <p class="font-semibold text-dark">{{ __('Ordered on :date.', ['date' => $quote->ordered_at?->translatedFormat('j M Y')]) }}</p>
            @if($quote->order)
                <a href="{{ route('orders.show', $quote->order) }}" class="mt-1 inline-block font-semibold text-primary hover:underline">{{ __('Order :number', ['number' => $quote->order->order_number]) }}</a>
            @endif
        @elseif($quote->hasStatus(QuoteStatus::Declined))
            <p class="font-semibold text-dark">{{ $quote->declinedByLabel('customer') }}@if($quote->declined_at) · {{ $quote->declined_at->translatedFormat('j M Y') }}@endif</p>
            @if($quote->decline_reason)<p class="mt-1 whitespace-pre-line break-words text-gray-700" dir="auto">{{ $quote->decline_reason }}</p>@endif
        @endif

        <div class="mt-4 flex flex-wrap items-center gap-3">
            @if($canAccept)
                <form method="POST" action="{{ route('quotes.accept', $quote) }}">
                    @csrf
                    <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-white hover:bg-primary-hover" data-accept-quote>{{ __('Accept and add to cart') }}</button>
                </form>
            @endif
            @if($quote->hasStatus(QuoteStatus::Accepted) && ! $expired)
                @if($inCart)
                    <a href="{{ route('cart') }}" class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-white hover:bg-primary-hover">{{ __('Go to cart') }}</a>
                @else
                    <form method="POST" action="{{ route('quotes.cart', $quote) }}">
                        @csrf
                        <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 font-semibold text-white hover:bg-primary-hover">{{ __('Add to cart again') }}</button>
                    </form>
                @endif
            @endif
            @if(! $quote->isOpen() && ! $quote->hasStatus(QuoteStatus::Ordered) && $productOnSale)
                <a href="{{ route('quotes.create', ['product' => $quote->product_id]) }}" class="rounded-lg border border-gray-300 px-4 py-2 font-semibold text-dark hover:bg-gray-50">{{ __('Ask for a new quote') }}</a>
            @endif
        </div>

        @if($canDecline)
            <details class="mt-4 border-t border-gray-100 pt-3">
                <summary class="cursor-pointer text-sm text-gray-600 hover:text-danger">{{ $quote->hasStatus(QuoteStatus::New) ? __('Withdraw this request') : __('Decline this quote') }}</summary>
                <form method="POST" action="{{ route('quotes.decline', $quote) }}" class="mt-3 space-y-2">
                    @csrf
                    <label for="decline-reason" class="block text-sm text-gray-700">{{ __('Tell the shop why (optional)') }}</label>
                    <textarea id="decline-reason" name="reason" rows="2" maxlength="500" class="w-full rounded-lg border-gray-300 text-sm">{{ old('reason') }}</textarea>
                    @error('reason')<p class="text-sm text-danger">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-lg border border-danger px-4 py-1.5 text-sm font-semibold text-danger hover:bg-danger-50">{{ $quote->hasStatus(QuoteStatus::New) ? __('Withdraw request') : __('Decline quote') }}</button>
                </form>
            </details>
        @endif
    </section>

    @include('quotes._quote_box', ['quote' => $quote, 'viewer' => 'customer'])
    @include('quotes._summary', ['quote' => $quote, 'viewer' => 'customer'])
    @include('quotes._thread', ['quote' => $quote, 'role' => 'customer', 'action' => route('quotes.messages', $quote)])
</div>
@endsection
