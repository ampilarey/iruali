{{-- Product form: "Bulk quotes", the smallest quantity a business can ask a quote for (QuoteService; empty: the default). --}}
@php $quoteDefault = \App\Services\QuoteService::DEFAULT_MIN_QUANTITY; @endphp
<fieldset class="rounded-lg border border-gray-200 p-4" data-quote-settings>
    <legend class="px-1 text-sm font-medium text-gray-700">{{ __('Bulk quotes') }}</legend>
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="quote_min_quantity" class="block text-sm font-medium text-gray-700">{{ __('Smallest quantity for a quote') }}</label>
            <input id="quote_min_quantity" name="quote_min_quantity" type="number" min="2" max="{{ \App\Services\QuoteService::MAX_QUANTITY }}" step="1" dir="ltr" placeholder="{{ $quoteDefault }}"
                   value="{{ old('quote_min_quantity', $product->quote_min_quantity) }}" aria-describedby="quote_min_quantity-hint"
                   class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500 @error('quote_min_quantity') border-red-500 @enderror">
            @error('quote_min_quantity')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <p id="quote_min_quantity-hint" class="sm:col-span-2 self-end text-xs text-gray-500">{{ __('Businesses can ask you for a price on this many or more (the product page shows "Request a bulk quote"). Leave empty for :count. You reply under Quote requests; nothing is sold until the customer accepts your quote and checks out.', ['count' => $quoteDefault]) }}</p>
    </div>
</fieldset>
