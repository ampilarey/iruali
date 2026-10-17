{{-- "Doesn't deliver to <island>" on a product the customer's chosen island can't get delivered
     (shown in "Show everything", on shop pages, the wishlist, compare and recently viewed).
     Needs $product; optional $class. --}}
@php $labelIsland = app(\App\Services\ShoppingIsland::class)->labelIsland(); @endphp
@if($labelIsland && ! app(\App\Services\ShopCoverage::class)->deliversProduct($product, $labelIsland))
    <span class="inline-flex items-center gap-1 rounded bg-gray-800/90 px-1.5 py-0.5 text-[11px] font-semibold text-white {{ $class ?? '' }}" data-island-label="no">{{ __('Doesn\'t deliver to :island', ['island' => $labelIsland->localized_name]) }}</span>
@endif
