@props(['product', 'layout' => 'grid', 'compact' => false])
@php
    $image = $product->mainImage?->url ?? '/images/product-placeholder.svg';
    $url = route('products.show', $product);
    $seller = $product->seller;
    $sellerName = $seller ? ($seller->business_name ?: $seller->name) : null;
    $rating = round((float) ($product->rating_avg ?? 0), 1);
    $ratingCount = (int) ($product->rating_count ?? 0);
    $stock = (int) $product->stock_quantity;
    $wasPrice = $product->was_price;
@endphp

@if($layout === 'list')
<article class="group bg-white border border-gray-200 rounded-xl p-3 sm:p-4 flex gap-3 sm:gap-5 hover:border-primary-300 hover:shadow-md transition">
    <a href="{{ $url }}" class="relative shrink-0 w-28 h-28 sm:w-44 sm:h-44 rounded-lg overflow-hidden bg-primary-50">
        <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" class="w-full h-full object-cover">
        @if($wasPrice)
            <span dir="ltr" class="absolute top-2 start-2 bg-coral text-white text-[11px] font-bold px-1.5 py-0.5 rounded">&minus;{{ $product->discount_percentage }}%</span>
        @endif
    </a>
    <div class="flex-1 min-w-0 grid sm:grid-cols-[1fr_13rem] gap-3 sm:gap-6">
        <div class="min-w-0">
            @if($product->brand)<p class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $product->brand }}</p>@endif
            <h3 class="font-semibold text-dark leading-snug line-clamp-2"><a href="{{ $url }}" class="hover:text-primary hover:underline">{{ $product->name }}</a></h3>
            <p class="mt-1 text-xs text-gray-500">{{ __('SKU') }} <span dir="ltr">{{ $product->sku }}</span>@if($product->model) &middot; {{ __('Model') }} <span dir="ltr">{{ $product->model }}</span>@endif</p>
            @if($ratingCount > 0)
                <x-rating :value="$rating" :count="$ratingCount" class="mt-1.5" />
            @endif
            @if($product->localized_description)
                <p class="hidden sm:block mt-2 text-sm text-gray-600 line-clamp-2">{{ $product->localized_description }}</p>
            @endif
            @if($sellerName)
                <p class="mt-2 text-xs text-gray-600 flex items-center gap-1.5"><x-icon name="store" class="w-3.5 h-3.5 text-gray-400" />{{ __('Sold by') }} <a href="{{ route('sellers.show', $seller) }}" class="font-semibold text-dark hover:text-primary hover:underline truncate">{{ $sellerName }}</a>@if($seller->city)<span class="truncate">&middot; {{ $seller->city }}</span>@endif</p>
            @endif
        </div>
        <div class="sm:border-s sm:border-gray-100 sm:ps-5 flex flex-col">
            <x-price :product="$product" size="lg" />
            <x-stock :quantity="$stock" class="mt-1" />
            <div class="mt-3 sm:mt-auto flex gap-2">
                <x-add-to-cart :product="$product" class="flex-1" />
                <x-wishlist-button :product="$product" />
            </div>
        </div>
    </div>
</article>
@else
<article class="group relative bg-white border border-gray-200 rounded-xl overflow-hidden flex flex-col hover:border-primary-300 hover:shadow-md transition h-full">
    <a href="{{ $url }}" class="relative block aspect-square bg-primary-50 overflow-hidden">
        <img src="{{ $image }}" alt="{{ $product->name }}" loading="lazy" class="w-full h-full object-cover group-hover:scale-[1.03] transition-transform duration-300">
        <span class="absolute top-2 start-2 flex flex-col items-start gap-1">
            @if($wasPrice)
                <span dir="ltr" class="bg-coral text-white text-[11px] font-bold px-1.5 py-0.5 rounded">&minus;{{ $product->discount_percentage }}%</span>
            @endif
            @if($product->is_featured && ! $compact)
                <span class="bg-sun text-sun-on text-[11px] font-bold px-1.5 py-0.5 rounded">{{ __('Top pick') }}</span>
            @endif
        </span>
    </a>
    @unless($compact)
        <x-wishlist-button :product="$product" floating />
    @endunless
    <div class="p-3 flex flex-col flex-1">
        @if($product->brand)<p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500 truncate">{{ $product->brand }}</p>@endif
        <h3 class="text-sm font-semibold text-dark leading-snug line-clamp-2 min-h-[2.5rem]"><a href="{{ $url }}" class="hover:text-primary hover:underline">{{ $product->name }}</a></h3>
        @if($ratingCount > 0)
            <x-rating :value="$rating" :count="$ratingCount" class="mt-1" />
        @endif
        @if($sellerName && ! $compact)
            <p class="mt-1 text-xs text-gray-500 truncate">{{ __('Sold by') }} <a href="{{ route('sellers.show', $seller) }}" class="font-medium text-gray-700 hover:text-primary hover:underline">{{ $sellerName }}</a>@if($seller->city) &middot; {{ $seller->city }}@endif</p>
        @endif
        <div class="mt-auto pt-2">
            <x-price :product="$product" />
            <x-stock :quantity="$stock" class="mt-0.5" />
            @unless($compact)
                <x-add-to-cart :product="$product" class="w-full mt-2.5" small />
            @endunless
        </div>
    </div>
</article>
@endif
