@extends('layouts.app')

@section('title', __('My quote requests'))

@section('content')
<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-dark">{{ __('My quote requests') }}</h1>
        <p class="mt-1 text-gray-600">{{ __('Bulk prices you asked shops for. Use "Request a bulk quote" on a product page to ask for another.') }}</p>
    </div>

    @if($requests->isEmpty())
        <div class="rounded-xl border border-gray-200 bg-white p-10 text-center">
            <span class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary-50 text-primary"><x-icon name="box" class="h-7 w-7" /></span>
            <p class="text-lg font-semibold">{{ __('No quote requests yet') }}</p>
            <p class="mt-1 text-sm text-gray-600">{{ __('Buying for a resort, an office or a café? Ask a shop for a price on the quantity you need.') }}</p>
            <a href="{{ route('shop') }}" class="mt-5 inline-block rounded-lg bg-primary px-5 py-2.5 font-semibold text-white">{{ __('Start shopping') }}</a>
        </div>
    @else
        <div class="space-y-3">
            @foreach($requests as $quote)
                <a href="{{ route('quotes.show', $quote) }}" class="flex gap-4 rounded-xl border border-gray-200 bg-white p-4 hover:border-primary" data-quote-row="{{ $quote->id }}">
                    <img src="{{ $quote->product?->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="" class="h-16 w-16 shrink-0 rounded-lg bg-primary-50 object-cover">
                    <div class="min-w-0 flex-1 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-semibold text-dark">{{ $quote->label() }} · {{ $quote->displayName() }}</p>
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $quote->statusBadge() }}">{{ $quote->statusLabel() }}</span>
                        </div>
                        <p class="text-gray-600">{{ $quote->shopName() }} · {{ __('Asked for: :count', ['count' => $quote->quantity]) }} · {{ $quote->created_at->translatedFormat('j M Y') }}</p>
                        @if($quote->unit_price !== null && $quote->hasStatus(\App\Enums\QuoteStatus::Quoted, \App\Enums\QuoteStatus::Accepted, \App\Enums\QuoteStatus::Ordered))
                            <p class="mt-1 font-medium text-dark" dir="ltr">{{ \App\Services\QuoteService::priceLine($quote) }}</p>
                        @endif
                        @if($quote->customer_unread > 0)
                            <p class="mt-1 text-xs font-semibold text-primary">{{ trans_choice(':count new message|:count new messages', $quote->customer_unread, ['count' => $quote->customer_unread]) }}</p>
                        @endif
                    </div>
                </a>
            @endforeach
        </div>
        <div class="mt-6">{{ $requests->links() }}</div>
    @endif
</div>
@endsection
