{{-- Product page buy box: "Request a bulk quote" for businesses (App\Services\QuoteService). Guests are sent to sign in first;
     a customer with an open request for the product gets a link to it. Hidden on the shop's own products and while it is on holiday. --}}
@php
    $quoteService = app(\App\Services\QuoteService::class);
    $quoteViewer = auth()->user();
    $quoteBlocked = $quoteService->requestProblem($quoteViewer, $product) !== null;
    $openQuote = $quoteViewer && ! $quoteBlocked ? $quoteService->openRequest($quoteViewer, $product) : null;
@endphp
@unless($quoteBlocked)
    <div class="mt-3 rounded-lg border border-gray-200 px-3 py-2.5 text-sm" data-bulk-quote>
        <p class="flex items-center gap-1.5 font-semibold text-dark"><x-icon name="box" class="w-4 h-4 shrink-0 text-primary" />{{ __('Buying in bulk for a business?') }}</p>
        <p class="mt-0.5 text-xs text-gray-600">{{ __('Ask :shop for a price on :count or more.', ['shop' => $product->seller->shopName(), 'count' => $quoteService->minimumFor($product)]) }}</p>
        @if($openQuote)
            <a href="{{ route('quotes.show', $openQuote) }}" class="mt-2 inline-flex items-center gap-1 font-semibold text-primary hover:underline">{{ __('See your quote request') }} ({{ $openQuote->statusLabel() }})</a>
        @else
            <a href="{{ route('quotes.create', ['product' => $product->id]) }}" class="mt-2 flex items-center justify-center gap-2 h-10 rounded-lg border border-primary text-primary font-semibold hover:bg-primary-50" data-request-quote>{{ $quoteViewer ? __('Request a bulk quote') : __('Sign in to request a bulk quote') }}</a>
        @endif
    </div>
@endunless
