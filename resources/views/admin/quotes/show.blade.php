@extends('layouts.app')

@section('title', $quote->label())

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => $quote->label(), 'back' => route('admin.quotes')])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
        <section class="rounded-lg bg-white p-5 text-sm shadow" data-quote-next>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <p class="text-gray-700">
                    {{ __(':business asked :shop', ['business' => $quote->business_name, 'shop' => $quote->shopName()]) }}
                    · {{ $quote->created_at->format('d M Y, H:i') }}
                    @if($quote->seller)· <a href="{{ route('sellers.show', $quote->seller) }}" target="_blank" rel="noopener" class="text-primary-700 hover:underline">{{ __('Shop page') }}</a> · <span dir="ltr">{{ $quote->seller->email }}</span>@if($quote->seller->phone) · <span dir="ltr">{{ $quote->seller->phone }}</span>@endif @endif
                </p>
                <span class="rounded-full px-3 py-1 text-sm font-semibold {{ $quote->statusBadge() }}" data-quote-status>{{ $quote->statusLabel() }}</span>
            </div>
            @if($quote->hasStatus(\App\Enums\QuoteStatus::Declined))
                <p class="mt-2 font-semibold text-gray-900">{{ $quote->declinedByLabel('admin') }}@if($quote->declined_at) · {{ $quote->declined_at->format('d M Y') }}@endif</p>
                @if($quote->decline_reason)<p class="mt-1 whitespace-pre-line break-words text-gray-700" dir="auto">{{ $quote->decline_reason }}</p>@endif
            @endif
            @if($quote->order)
                <a href="{{ route('admin.orders.show', $quote->order) }}" class="mt-2 inline-block font-semibold text-primary-700 hover:underline">{{ __('Order :number', ['number' => $quote->order->order_number]) }}</a>
            @endif
        </section>

        @include('quotes._quote_box', ['quote' => $quote, 'viewer' => 'admin'])
        @include('quotes._summary', ['quote' => $quote, 'viewer' => 'admin'])
        @include('quotes._thread', ['quote' => $quote, 'role' => 'admin', 'action' => route('admin.quotes.messages', $quote)])

        @if($quote->hasStatus(\App\Enums\QuoteStatus::New, \App\Enums\QuoteStatus::Quoted, \App\Enums\QuoteStatus::Accepted))
            <section class="rounded-lg bg-white p-5 shadow" data-quote-close>
                <h2 class="text-base font-semibold text-gray-900">{{ __('Close this request') }}</h2>
                <p class="text-sm text-gray-600">{{ __('For a shop that cannot be reached or a quote made by mistake. The customer and the shop are emailed the reason, and a quote in the customer\'s cart comes out of it.') }}</p>
                <form method="POST" action="{{ route('admin.quotes.close', $quote) }}" class="mt-3 space-y-2" onsubmit="return confirm('{{ __('Close this request?') }}')">
                    @csrf
                    <label for="close-reason" class="block text-sm text-gray-700">{{ __('Reason') }}</label>
                    <textarea id="close-reason" name="reason" rows="2" maxlength="500" required class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">{{ old('reason') }}</textarea>
                    @error('reason')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-lg border border-red-300 px-4 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">{{ __('Close request') }}</button>
                </form>
            </section>
        @endif
    </div>
</div>
@endsection
