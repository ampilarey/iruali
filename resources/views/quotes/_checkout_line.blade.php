{{-- Checkout summary: marks a line bought on a bulk quote. --}}
@if($item->quote_request_id)
    <p class="text-xs font-semibold text-primary-700" data-quote-label>{{ __('Quote #:number', ['number' => $item->quote_request_id]) }} · {{ __('quoted price') }}</p>
@endif
