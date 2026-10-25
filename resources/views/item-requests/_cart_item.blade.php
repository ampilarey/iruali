{{-- Cart: an item we are bringing for the customer (an accepted item request, ItemRequestService). Its quantity and price
     are fixed by our price; it can only be removed, and goes back in from the request page while the price holds. --}}
@php
    $requestProduct = $item->product;
    $itemRequest = $requestProduct->itemRequest;
@endphp
<div class="p-4 flex gap-3 sm:gap-4" data-item-request-line="{{ $requestProduct->item_request_id }}">
    <a href="{{ $itemRequest ? route('item-requests.show', $itemRequest) : '#' }}" class="shrink-0 w-20 h-20 sm:w-28 sm:h-28 rounded-lg overflow-hidden bg-primary-50">
        <img src="{{ $requestProduct->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="{{ $requestProduct->name }}" class="w-full h-full object-cover">
    </a>
    <div class="flex-1 min-w-0 grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-x-4 gap-y-2">
        <div class="min-w-0">
            <p class="font-semibold text-dark leading-snug line-clamp-2">{{ $requestProduct->name }}</p>
            @if($itemRequest)
                <a href="{{ route('item-requests.show', $itemRequest) }}" class="mt-1 inline-flex items-center gap-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 hover:underline" data-item-request-label><x-icon name="box" class="w-3.5 h-3.5" />{{ $itemRequest->label() }}</a>
                <p class="mt-0.5 text-xs text-gray-600">{{ __('Brought in for you: ships about :days days after you order.', ['days' => $itemRequest->ships_in_days]) }}</p>
            @endif
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
                <span class="text-gray-600">{{ __('Qty') }}: <span class="font-semibold text-dark" dir="ltr">{{ $item->quantity }}</span> <span class="text-xs text-gray-500">({{ __('fixed by our price') }})</span></span>
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
        </div>
    </div>
</div>
