@extends('layouts.app')

@php
    $images = $product->getRelation('images')->sortByDesc('is_main')->values();
    $mainImage = $images->first()?->url ?? '/images/product-placeholder.svg';
    $seller = $product->seller;
    $sellerName = $seller ? ($seller->business_name ?: $seller->name) : null;
    $reviews = $product->reviews;
    $rating = round((float) $reviews->avg('rating'), 1);
    $stock = (int) $product->stock_quantity;
    $specs = array_filter([
        __('Brand') => $product->brand,
        __('Model') => $product->model,
        __('SKU') => $product->sku,
        __('Department') => $product->category?->localized_name,
        __('Weight') => $product->weight ? rtrim(rtrim(number_format((float) $product->weight, 2), '0'), '.').' kg' : null,
        __('Dimensions') => $product->dimensions,
        __('Sold by') => $sellerName,
        __('Ships from') => $seller?->city,
    ]);
@endphp

@section('content')
<div class="bg-white">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-3 lg:py-5">
        <!-- Breadcrumb -->
        <nav class="text-xs sm:text-sm text-gray-500 mb-3 lg:mb-4 overflow-x-auto whitespace-nowrap scrollbar-hide" aria-label="{{ __('Breadcrumb') }}">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="hover:text-primary hover:underline">{{ __('Home') }}</a></li>
                @if($product->category?->parent)
                    <li class="flex items-center gap-1.5"><x-icon name="chevron-right" class="w-3 h-3 rtl:rotate-180" /><a href="{{ route('categories.show', $product->category->parent) }}" class="hover:text-primary hover:underline">{{ $product->category->parent->localized_name }}</a></li>
                @endif
                @if($product->category)
                    <li class="flex items-center gap-1.5"><x-icon name="chevron-right" class="w-3 h-3 rtl:rotate-180" /><a href="{{ route('categories.show', $product->category) }}" class="hover:text-primary hover:underline">{{ $product->category->localized_name }}</a></li>
                @endif
                <li class="flex items-center gap-1.5 min-w-0"><x-icon name="chevron-right" class="w-3 h-3 rtl:rotate-180" /><span class="text-dark truncate">{{ $product->name }}</span></li>
            </ol>
        </nav>

        <!-- Title block (shown above the gallery, as on big camera stores) -->
        <div class="mb-4 lg:mb-6">
            @if($product->brand)<p class="text-xs sm:text-sm font-semibold uppercase tracking-wide text-primary">{{ $product->brand }}</p>@endif
            <h1 class="font-display text-xl sm:text-2xl lg:text-3xl font-bold text-dark leading-tight">{{ $product->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs sm:text-sm text-gray-600">
                <a href="#reviews" class="flex items-center gap-1.5 hover:text-primary">
                    <x-rating :value="$rating" :count="null" />
                    <span>{{ $reviews->count() ? trans_choice(':count review|:count reviews', $reviews->count(), ['count' => $reviews->count()]) : __('No reviews yet') }}</span>
                </a>
                <span>{{ __('SKU') }}: <span class="font-medium text-dark" dir="ltr">{{ $product->sku }}</span></span>
                @if($product->model)<span>{{ __('Model') }}: <span class="font-medium text-dark" dir="ltr">{{ $product->model }}</span></span>@endif
            </div>
        </div>

        <div class="grid lg:grid-cols-[minmax(0,1fr)_22rem] xl:grid-cols-[minmax(0,1fr)_24rem] gap-6 lg:gap-8 items-start">
            <div class="min-w-0">
                <div class="grid md:grid-cols-2 gap-6 lg:gap-8">
                    <!-- Gallery -->
                    <div>
                        <div class="relative aspect-square rounded-xl bg-primary-50 overflow-hidden border border-gray-200">
                            <img data-gallery-main src="{{ $mainImage }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                            @if($product->is_on_sale)
                                <span dir="ltr" class="absolute top-3 start-3 bg-coral text-white text-xs font-bold px-2 py-1 rounded">&minus;{{ $product->discount_percentage }}%</span>
                            @endif
                        </div>
                        @if($images->count() > 1)
                            <div class="mt-3 flex gap-2 overflow-x-auto scrollbar-hide">
                                @foreach($images as $image)
                                    <button type="button" data-gallery-thumb="{{ $image->url }}" class="shrink-0 w-16 h-16 rounded-lg overflow-hidden border-2 {{ $loop->first ? 'border-primary' : 'border-gray-200' }} hover:border-primary" aria-label="{{ __('Show image :n', ['n' => $loop->iteration]) }}">
                                        <img src="{{ $image->url }}" alt="" class="w-full h-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <!-- Key features -->
                    <div>
                        <h2 class="font-semibold text-dark mb-2">{{ __('Key features') }}</h2>
                        @php
                            $lines = collect(preg_split('/(?<=[.!?])\s+|\n+/u', (string) $product->localized_description))->map(fn ($l) => trim($l))->filter()->take(5);
                        @endphp
                        @if($lines->isNotEmpty())
                            <ul class="space-y-2 text-sm text-gray-700">
                                @foreach($lines as $line)
                                    <li class="flex gap-2"><x-icon name="check" class="w-4 h-4 mt-0.5 shrink-0 text-primary" /><span>{{ $line }}</span></li>
                                @endforeach
                            </ul>
                        @else
                            <p class="text-sm text-gray-500">{{ __('The seller hasn\'t added a description yet.') }}</p>
                        @endif

                        @if($specs)
                            <dl class="mt-5 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1.5 text-sm">
                                @foreach(array_slice($specs, 0, 4, true) as $label => $value)
                                    <dt class="text-gray-500">{{ $label }}</dt><dd class="font-medium text-dark">{{ $value }}</dd>
                                @endforeach
                            </dl>
                            <a href="#specs" class="inline-block mt-2 text-sm font-semibold text-primary hover:underline">{{ __('See all specs') }}</a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Buy box -->
            <aside id="buy-box" class="lg:sticky lg:top-[132px] bg-white border border-gray-200 rounded-xl p-4 lg:p-5 shadow-sm">
                <x-price :product="$product" size="xl" />
                <x-stock :quantity="$stock" class="mt-2 !text-sm" />

                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm space-y-2">
                    <p class="flex gap-2"><x-icon name="truck" class="w-5 h-5 shrink-0 text-primary" />
                        <span>{{ __('Delivery :fee in Greater Malé, :islands to other islands.', ['fee' => \App\Support\Money::format($delivery['male']), 'islands' => \App\Support\Money::format($delivery['islands'])]) }}
                            @if($delivery['free_over'] > 0)<span class="block text-success font-medium">{{ __('Free delivery on orders over :amount', ['amount' => \App\Support\Money::format($delivery['free_over'])]) }}</span>@endif
                        </span>
                    </p>
                    <p class="flex gap-2"><x-icon name="bank" class="w-5 h-5 shrink-0 text-primary" /><span>{{ __('Cash on delivery or bank transfer') }}</span></p>
                </div>

                @if($stock > 0)
                    <form action="{{ route('cart.add') }}" method="POST" class="mt-4" id="buy-form">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        <label for="quantity" class="text-sm font-medium text-gray-700">{{ __('Quantity') }}</label>
                        <div class="mt-1 flex gap-2">
                            <div class="flex items-center rounded-lg border border-gray-300" data-qty>
                                <button type="button" data-qty-step="-1" class="w-10 h-11 flex items-center justify-center text-gray-600 hover:text-primary" aria-label="{{ __('Decrease quantity') }}"><x-icon name="minus" class="w-4 h-4" /></button>
                                <input id="quantity" name="quantity" type="number" inputmode="numeric" min="1" max="{{ $stock }}" value="1" class="w-12 h-11 border-0 text-center font-semibold focus:ring-0 [appearance:textfield] [&::-webkit-inner-spin-button]:appearance-none">
                                <button type="button" data-qty-step="1" class="w-10 h-11 flex items-center justify-center text-gray-600 hover:text-primary" aria-label="{{ __('Increase quantity') }}"><x-icon name="plus" class="w-4 h-4" /></button>
                            </div>
                            <button type="submit" class="flex-1 inline-flex items-center justify-center gap-2 h-11 rounded-lg bg-primary hover:bg-primary-hover text-white font-semibold">
                                <x-icon name="cart" class="w-5 h-5" />{{ __('Add to cart') }}
                            </button>
                        </div>
                    </form>
                @else
                    <p class="mt-4 rounded-lg bg-danger-50 text-danger text-sm font-medium p-3">{{ __('This item is out of stock. Add it to your wishlist and check back soon.') }}</p>
                @endif

                <form action="{{ route('wishlist.add', $product) }}" method="POST" class="mt-2">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-lg border border-gray-300 font-semibold text-dark hover:bg-gray-50">
                        <x-icon name="heart" class="w-5 h-5" />{{ __('Add to Wishlist') }}
                    </button>
                </form>

                @if($seller)
                    <div class="mt-4 pt-4 border-t border-gray-100 flex items-center gap-3">
                        <span class="w-11 h-11 shrink-0 rounded-lg bg-primary text-white font-display font-bold text-lg flex items-center justify-center">{{ mb_strtoupper(mb_substr($sellerName, 0, 1)) }}</span>
                        <div class="min-w-0 flex-1 text-sm">
                            <p class="text-gray-500">{{ __('Sold by') }}</p>
                            <a href="{{ route('sellers.show', $seller) }}" class="font-semibold text-dark hover:text-primary hover:underline truncate block">{{ $sellerName }}</a>
                            @if($seller->city)<p class="text-xs text-gray-500">{{ $seller->city }}</p>@endif
                        </div>
                        <a href="{{ route('sellers.show', $seller) }}" class="text-sm font-semibold text-primary hover:underline shrink-0">{{ __('Visit shop') }}</a>
                    </div>
                @endif
            </aside>
        </div>
    </div>

    <!-- Section tabs -->
    <div class="sticky top-[104px] lg:top-[115px] z-30 bg-white border-y border-gray-200 mt-4">
        <nav class="max-w-7xl mx-auto px-4 lg:px-6 flex gap-6 text-sm font-semibold overflow-x-auto scrollbar-hide" aria-label="{{ __('Product sections') }}">
            <a href="#overview" class="py-3 border-b-2 border-transparent hover:border-primary hover:text-primary whitespace-nowrap">{{ __('Overview') }}</a>
            <a href="#specs" class="py-3 border-b-2 border-transparent hover:border-primary hover:text-primary whitespace-nowrap">{{ __('Specs') }}</a>
            <a href="#reviews" class="py-3 border-b-2 border-transparent hover:border-primary hover:text-primary whitespace-nowrap">{{ __('Reviews') }} ({{ $reviews->count() }})</a>
            @if($moreFromSeller->isNotEmpty())<a href="#more-from-seller" class="py-3 border-b-2 border-transparent hover:border-primary hover:text-primary whitespace-nowrap">{{ __('More from this shop') }}</a>@endif
        </nav>
    </div>

    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 lg:py-8 space-y-10">
        <section id="overview" class="scroll-mt-40 max-w-3xl">
            <h2 class="font-display text-xl font-bold text-dark mb-3">{{ __('Overview') }}</h2>
            @if($product->localized_description)
                <div class="text-gray-700 leading-relaxed">{!! nl2br(e($product->localized_description)) !!}</div>
            @else
                <p class="text-gray-500">{{ __('The seller hasn\'t added a description yet.') }}</p>
            @endif
        </section>

        <section id="specs" class="scroll-mt-40 max-w-3xl">
            <h2 class="font-display text-xl font-bold text-dark mb-3">{{ __('Specs') }}</h2>
            <table class="w-full text-sm border border-gray-200 rounded-xl overflow-hidden">
                <tbody class="divide-y divide-gray-200">
                    @foreach($specs as $label => $value)
                        <tr class="odd:bg-gray-50">
                            <th scope="row" class="text-start font-medium text-gray-600 px-4 py-2.5 w-2/5">{{ $label }}</th>
                            <td class="px-4 py-2.5 text-dark">{{ $value }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>

        <section id="reviews" class="scroll-mt-40 max-w-3xl">
            <h2 class="font-display text-xl font-bold text-dark mb-3">{{ __('Reviews') }}</h2>
            @if($reviews->isEmpty())
                <p class="text-gray-500">{{ __('No reviews yet') }}</p>
            @else
                <div class="flex items-center gap-4 mb-5">
                    <p class="font-display text-4xl font-bold">{{ number_format($rating, 1) }}</p>
                    <div>
                        <x-rating :value="$rating" :count="null" size="lg" />
                        <p class="text-sm text-gray-500">{{ trans_choice(':count review|:count reviews', $reviews->count(), ['count' => $reviews->count()]) }}</p>
                    </div>
                </div>
                <ul class="divide-y divide-gray-200 border-y border-gray-200">
                    @foreach($reviews->take(10) as $review)
                        <li class="py-4">
                            <div class="flex items-center gap-2">
                                <x-rating :value="$review->rating" :count="null" />
                                <span class="text-sm font-semibold">{{ $review->user?->name ?? $review->reviewer_name }}</span>
                                <span class="text-xs text-gray-500">{{ $review->created_at?->format('j M Y') }}</span>
                            </div>
                            <p class="mt-1.5 text-sm text-gray-700">{{ $review->comment }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        @if($moreFromSeller->isNotEmpty())
            <section id="more-from-seller" class="scroll-mt-40">
                <x-product-row :title="__('More from :shop', ['shop' => $sellerName])" :products="$moreFromSeller" :link="route('sellers.show', $seller)" />
            </section>
        @endif

        @if($relatedProducts->isNotEmpty())
            <x-product-row :title="__('Related Products')" :products="$relatedProducts" :link="$product->category ? route('categories.show', $product->category) : null" />
        @endif

        @if($recentlyViewed->isNotEmpty())
            <x-product-row :title="__('Recently viewed')" :products="$recentlyViewed" compact />
        @endif
    </div>
</div>

<!-- Sticky buy bar (mobile) -->
@if($stock > 0)
    <div class="lg:hidden fixed inset-x-0 bottom-16 z-40 bg-white border-t border-gray-200 px-4 py-2.5 flex items-center gap-3 shadow-[0_-4px_12px_rgba(15,42,58,0.08)]" style="margin-bottom: env(safe-area-inset-bottom);">
        <x-price :product="$product" class="min-w-0 [&_p]:whitespace-nowrap [&_p]:truncate" />
        <button type="submit" form="buy-form" class="ms-auto shrink-0 whitespace-nowrap inline-flex items-center gap-2 h-11 px-4 rounded-lg bg-primary text-white font-semibold">
            <x-icon name="cart" class="w-5 h-5" />{{ __('Add to cart') }}
        </button>
    </div>
    <div class="lg:hidden h-16" aria-hidden="true"></div>
@endif
@endsection

@push('scripts')
<script>
    (function () {
        var main = document.querySelector('[data-gallery-main]');
        document.querySelectorAll('[data-gallery-thumb]').forEach(function (thumb) {
            thumb.addEventListener('click', function () {
                main.src = thumb.dataset.galleryThumb;
                document.querySelectorAll('[data-gallery-thumb]').forEach(function (t) {
                    t.classList.toggle('border-primary', t === thumb);
                    t.classList.toggle('border-gray-200', t !== thumb);
                });
            });
        });

        document.querySelectorAll('[data-qty]').forEach(function (box) {
            var input = box.querySelector('input');
            box.querySelectorAll('[data-qty-step]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var v = (parseInt(input.value, 10) || 1) + parseInt(b.dataset.qtyStep, 10);
                    input.value = Math.max(1, Math.min(parseInt(input.max, 10) || 999, v));
                });
            });
        });
    })();
</script>
@endpush
