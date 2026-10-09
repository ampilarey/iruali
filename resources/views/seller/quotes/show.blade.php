@extends('layouts.app')

@section('title', $quote->label())

@php
    use App\Enums\QuoteStatus;
    $canQuote = $quote->hasStatus(QuoteStatus::New, QuoteStatus::Quoted);
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $listPrice = $quote->product ? (float) $quote->product->final_price : null;
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => $quote->label()])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('seller.quotes') }}" class="text-sm text-gray-600 hover:text-primary hover:underline">{{ __('All quote requests') }}</a>
            <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $quote->statusBadge() }}" data-quote-status>{{ $quote->statusLabel() }}</span>
        </div>

        {{-- Where it stands --}}
        <section class="rounded-lg bg-white p-5 text-sm shadow" data-quote-next>
            @if($quote->hasStatus(QuoteStatus::New))
                <p class="font-semibold text-gray-900">{{ __(':business is waiting for your price.', ['business' => $quote->business_name]) }}</p>
                <p class="mt-1 text-gray-600">{{ __('Send a quote below, ask something in the messages, or decline with a reason. Requests without a reply for :days days show up for iruali\'s team.', ['days' => \App\Services\QuoteService::WAITING_DAYS]) }}</p>
            @elseif($quote->hasStatus(QuoteStatus::Quoted))
                <p class="font-semibold text-gray-900">{{ __('Quote sent. Waiting for the customer to accept it.') }}</p>
                <p class="mt-1 text-gray-600">{{ __('You can still change it until they accept. They have been emailed.') }}</p>
            @elseif($quote->hasStatus(QuoteStatus::Accepted))
                <p class="font-semibold text-gray-900">{{ __('Accepted on :date: the quote is in the customer\'s cart.', ['date' => $quote->accepted_at?->translatedFormat('j M Y')]) }}</p>
                <p class="mt-1 text-gray-600">{{ __('Stock is not held for it, so keep enough on hand. You get the usual new-order email when they check out.') }}</p>
            @elseif($quote->hasStatus(QuoteStatus::Ordered))
                <p class="font-semibold text-gray-900">{{ __('Ordered on :date.', ['date' => $quote->ordered_at?->translatedFormat('j M Y')]) }}</p>
                @if($quote->order)<a href="{{ route('seller.orders.show', $quote->order) }}" class="mt-1 inline-block font-semibold text-primary-700 hover:underline">{{ __('Order :number', ['number' => $quote->order->order_number]) }}</a>@endif
            @elseif($quote->hasStatus(QuoteStatus::Declined))
                <p class="font-semibold text-gray-900">{{ $quote->declinedByLabel('seller') }}@if($quote->declined_at) · {{ $quote->declined_at->translatedFormat('j M Y') }}@endif</p>
                @if($quote->decline_reason)<p class="mt-1 whitespace-pre-line break-words text-gray-700" dir="auto">{{ $quote->decline_reason }}</p>@endif
            @elseif($quote->hasStatus(QuoteStatus::Expired))
                <p class="font-semibold text-gray-900">{{ __('This quote expired on :date without being ordered.', ['date' => $quote->valid_until?->translatedFormat('j M Y')]) }}</p>
            @endif
        </section>

        @include('quotes._quote_box', ['quote' => $quote, 'viewer' => 'seller'])
        @include('quotes._summary', ['quote' => $quote, 'viewer' => 'seller'])

        @if($canQuote)
            <section class="rounded-lg bg-white p-5 shadow" data-quote-reply>
                <h2 class="text-base font-semibold text-gray-900">{{ $quote->hasStatus(QuoteStatus::Quoted) ? __('Change your quote') : __('Send a quote') }}</h2>
                @if($listPrice !== null)<p class="text-sm text-gray-500">{{ __('Your listed price is :price each.', ['price' => \App\Support\Money::format($listPrice)]) }}</p>@endif
                <form method="POST" action="{{ route('seller.quotes.quote', $quote) }}" class="mt-4 space-y-4">
                    @csrf
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div>
                            <label for="unit_price" class="block text-sm font-medium text-gray-700">{{ __('Unit price (MVR)') }}</label>
                            <input id="unit_price" name="unit_price" type="number" step="0.01" min="0.01" max="999999.99" required dir="ltr" class="{{ $field }}" value="{{ old('unit_price', $quote->unit_price ?? $listPrice) }}">
                            @if($errors->quote->has('unit_price'))<p class="mt-1 text-sm text-red-600">{{ $errors->quote->first('unit_price') }}</p>@endif
                        </div>
                        <div>
                            <label for="quote_quantity" class="block text-sm font-medium text-gray-700">{{ __('Quantity you can supply') }}</label>
                            <input id="quote_quantity" name="quantity" type="number" step="1" min="1" max="{{ \App\Services\QuoteService::MAX_QUANTITY }}" required dir="ltr" class="{{ $field }}" value="{{ old('quantity', $quote->quoted_quantity ?? $quote->quantity) }}">
                            @if($errors->quote->has('quantity'))<p class="mt-1 text-sm text-red-600">{{ $errors->quote->first('quantity') }}</p>@endif
                        </div>
                        <div>
                            <label for="valid_until" class="block text-sm font-medium text-gray-700">{{ __('Valid until') }}</label>
                            <input id="valid_until" name="valid_until" type="date" required dir="ltr" min="{{ today()->toDateString() }}" max="{{ today()->addDays(\App\Services\QuoteService::MAX_VALID_DAYS)->toDateString() }}" class="{{ $field }}"
                                   value="{{ old('valid_until', $quote->valid_until && ! $quote->isExpired() ? $quote->valid_until->toDateString() : today()->addDays(\App\Services\QuoteService::DEFAULT_VALID_DAYS)->toDateString()) }}">
                            @if($errors->quote->has('valid_until'))<p class="mt-1 text-sm text-red-600">{{ $errors->quote->first('valid_until') }}</p>@endif
                        </div>
                    </div>
                    <div>
                        <label for="quote_message" class="block text-sm font-medium text-gray-700">{{ __('Message to the customer') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                        <textarea id="quote_message" name="message" rows="3" maxlength="{{ \App\Services\QuoteService::MESSAGE_MAX_LENGTH }}" placeholder="{{ __('e.g. delivery in 10 days by the next boat; price includes packing') }}" class="{{ $field }}">{{ old('message', $quote->shop_message) }}</textarea>
                        @if($errors->quote->has('message'))<p class="mt-1 text-sm text-red-600">{{ $errors->quote->first('message') }}</p>@endif
                    </div>
                    <p class="text-xs text-gray-500">{{ __('The customer sees the total and can accept it into their cart until the end of the valid-until day. Your shop codes and multi-buy offers do not apply on top of a quote. iruali\'s commission and your earnings are worked out on the quoted price.') }}</p>
                    <div class="flex justify-end">
                        <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ $quote->hasStatus(QuoteStatus::Quoted) ? __('Update quote') : __('Send quote') }}</button>
                    </div>
                </form>

                <details class="mt-4 border-t border-gray-100 pt-3" @if($errors->decline->any()) open @endif>
                    <summary class="cursor-pointer text-sm text-gray-600 hover:text-red-700">{{ __('Decline this request') }}</summary>
                    <form method="POST" action="{{ route('seller.quotes.decline', $quote) }}" class="mt-3 space-y-2">
                        @csrf
                        <label for="decline_reason" class="block text-sm text-gray-700">{{ __('Reason (the customer sees it)') }}</label>
                        <textarea id="decline_reason" name="reason" rows="2" maxlength="500" required class="{{ $field }}" placeholder="{{ __('e.g. We cannot supply this quantity before that date.') }}">{{ old('reason') }}</textarea>
                        @if($errors->decline->has('reason'))<p class="text-sm text-red-600">{{ $errors->decline->first('reason') }}</p>@endif
                        <button type="submit" class="rounded-lg border border-red-300 px-4 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Decline request') }}</button>
                    </form>
                </details>
            </section>
        @endif

        @include('quotes._thread', ['quote' => $quote, 'role' => 'seller', 'action' => route('seller.quotes.messages', $quote)])
    </div>
</div>
@endsection
