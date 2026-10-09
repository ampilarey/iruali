{{-- Cart: a line bought on a bulk quote ("Quote #123"). Its quantity is locked and its price is the quoted one, with no other
     discount on it (QuoteService); it can only be removed, and goes back in from the quote page while the quote holds. --}}
@php
    $quoteLine = $item->quoteRequest;
    $quoteProduct = $item->product;
    $quoteSeller = $quoteProduct->seller;
@endphp
<div class="p-4 flex gap-3 sm:gap-4" data-quoted-line="{{ $item->quote_request_id }}">
    <a href="{{ route('products.show', $quoteProduct) }}" class="shrink-0 w-20 h-20 sm:w-28 sm:h-28 rounded-lg overflow-hidden bg-primary-50">
        <img src="{{ $quoteProduct->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="{{ $quoteProduct->name }}" class="w-full h-full object-cover">
    </a>
    <div class="flex-1 min-w-0 grid sm:grid-cols-[1fr_auto] gap-x-4 gap-y-2">
        <div class="min-w-0">
            <a href="{{ route('products.show', $quoteProduct) }}" class="font-semibold text-dark leading-snug hover:text-primary hover:underline line-clamp-2">{{ $quoteProduct->name }}</a>
            @if($item->variant)<p class="text-sm text-gray-600">{{ $item->variant->displayNameWithKeys() }}@if($item->variant->sku) <span class="text-xs text-gray-400" dir="ltr">({{ $item->variant->sku }})</span>@endif</p>@endif
            @if($quoteSeller)<p class="text-xs text-gray-500 mt-0.5">{{ __('Sold by') }} {{ $quoteSeller->shopName() }}</p>@endif
            @if($quoteLine)
                <a href="{{ route('quotes.show', $quoteLine) }}" class="mt-1 inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 hover:underline" data-quote-label><x-icon name="tag" class="w-3.5 h-3.5" />{{ $quoteLine->label() }}</a>
                <p class="mt-0.5 text-xs text-gray-600">{{ __('Quoted price and quantity. Check out by :date.', ['date' => $quoteLine->valid_until?->translatedFormat('j M Y')]) }}</p>
            @endif
            @include('cart._holiday_item', ['seller' => $quoteSeller])
            @include('cart._delivery_surcharge', ['product' => $quoteProduct])
            <x-stock :quantity="$item->availableStock()" class="mt-1" />
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <span class="text-gray-600">{{ __('Qty') }}: <span class="font-semibold text-dark" dir="ltr">{{ $item->quantity }}</span> <span class="text-xs text-gray-500">({{ __('fixed by the quote') }})</span></span>
                <form action="{{ route('cart.remove', $item) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-gray-600 hover:text-danger hover:underline">{{ __('Remove') }}</button>
                </form>
            </div>
        </div>
        <div class="sm:text-end">
            <p class="font-bold text-dark text-lg" dir="ltr">{{ \App\Support\Money::format($item->subtotal) }}</p>
            <p class="text-xs text-gray-500"><span dir="ltr">{{ \App\Support\Money::format($item->unit_price) }}</span> {{ __('each') }}</p>
            <p class="text-xs text-gray-500">{{ __('No vouchers or shop discounts on quoted prices') }}</p>
        </div>
    </div>
</div>
