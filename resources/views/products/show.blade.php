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
            @if($product->brand)<a href="{{ route('brands.show', $product->brand) }}" class="text-xs sm:text-sm font-semibold uppercase tracking-wide text-primary hover:underline">{{ $product->brand }}</a>@endif
            <h1 class="font-display text-xl sm:text-2xl lg:text-3xl font-bold text-dark leading-tight">{{ $product->name }}</h1>
            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs sm:text-sm text-gray-600">
                <a href="#reviews" class="flex items-center gap-1.5 hover:text-primary">
                    <x-rating :value="$rating" :count="null" />
                    <span>{{ $reviews->count() ? trans_choice(':count review|:count reviews', $reviews->count(), ['count' => $reviews->count()]) : __('No reviews yet') }}</span>
                </a>
                <a href="#questions" class="hover:text-primary">{{ trans_choice(':count question|:count questions', $questions->count(), ['count' => $questions->count()]) }}</a>
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
                            <button type="button" data-zoom-open class="block w-full h-full cursor-zoom-in" aria-label="{{ __('Zoom image') }}">
                                <img data-gallery-main src="{{ $mainImage }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                            </button>
                            <span class="pointer-events-none absolute bottom-3 end-3 inline-flex items-center gap-1 rounded-full bg-white/90 px-2.5 py-1 text-xs font-medium text-gray-700 shadow-sm"><x-icon name="zoom" class="w-4 h-4" />{{ __('Click to zoom') }}</span>
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
                @if($product->deal_ends_at)
                    <x-deal-countdown :ends="$product->deal_ends_at" class="mt-2" />
                @endif
                <x-stock :quantity="$stock" class="mt-2 !text-sm" />

                <div class="mt-4 rounded-lg bg-gray-50 p-3 text-sm space-y-2">
                    <p class="flex gap-2"><x-icon name="truck" class="w-5 h-5 shrink-0 text-primary" />
                        <span>{{ __('Delivery :fee in Greater Malé, :islands to other islands.', ['fee' => \App\Support\Money::format($delivery['male']), 'islands' => \App\Support\Money::format($delivery['islands'])]) }}
                            @if($delivery['free_over'] > 0)<span class="block text-success font-medium">{{ __('Free delivery on orders over :amount', ['amount' => \App\Support\Money::format($delivery['free_over'])]) }}</span>@endif
                        </span>
                    </p>
                    <div class="flex gap-2"><x-icon name="bank" class="w-5 h-5 shrink-0 text-primary" /><div><img src="/images/card-brands.png" alt="{{ __('We accept American Express, Visa, Mastercard and Maestro') }}" width="147" height="30" class="h-8 w-auto -ms-1"><span class="block mt-1 text-xs text-gray-500">{{ __('Prices in MVR') }}</span></div></div>
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
                    <form action="{{ route('stock-alerts.store', $product) }}" method="POST" class="mt-4 rounded-lg border border-gray-200 p-3">
                        @csrf
                        <p class="text-sm font-semibold flex items-center gap-2"><x-icon name="bell" class="w-4 h-4 text-primary" />{{ __('Email me when it\'s back in stock') }}</p>
                        <label for="alert-email" class="sr-only">{{ __('Email address') }}</label>
                        <div class="mt-2 flex gap-2">
                            <input id="alert-email" type="email" name="email" required value="{{ old('email', auth()->user()?->email) }}" placeholder="{{ __('Email address') }}" class="flex-1 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-primary">
                            <button type="submit" class="px-4 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-hover">{{ __('Notify me') }}</button>
                        </div>
                        @error('email')<p class="text-danger text-xs mt-1">{{ $message }}</p>@enderror
                    </form>
                @endif

                <form action="{{ route('wishlist.add', $product) }}" method="POST" class="mt-2">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    <button type="submit" class="w-full inline-flex items-center justify-center gap-2 h-11 rounded-lg border border-gray-300 font-semibold text-dark hover:bg-gray-50">
                        <x-icon name="heart" class="w-5 h-5" />{{ __('Add to Wishlist') }}
                    </button>
                </form>

                @php
                    $shareUrl = route('products.show', $product);
                    $shareText = $product->name.' – '.\App\Support\Money::format($product->final_price);
                @endphp
                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-sm">
                    <x-compare-toggle :product="$product" />
                    <div class="flex items-center gap-1" aria-label="{{ __('Share') }}">
                        <span class="text-gray-500 text-xs me-1">{{ __('Share') }}</span>
                        <a href="https://wa.me/?text={{ urlencode($shareText.' '.$shareUrl) }}" target="_blank" rel="noopener" class="w-8 h-8 rounded-full bg-[#25D366] text-white flex items-center justify-center" aria-label="WhatsApp">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2a10 10 0 00-8.6 15.1L2 22l5-1.3A10 10 0 1012 2zm5.3 14.1c-.2.6-1.3 1.2-1.8 1.2-.5.1-1 .1-3.3-.8-2.8-1.1-4.5-4-4.7-4.2-.1-.2-1.1-1.5-1.1-2.8s.7-2 1-2.3c.2-.3.5-.3.7-.3h.5c.2 0 .4 0 .6.5l.8 2c.1.2.1.4 0 .5l-.4.6-.3.4c.1.3.6 1 1.3 1.6.9.8 1.6 1 1.9 1.2.2.1.4.1.5-.1l.7-.9c.2-.2.4-.2.6-.1l1.9.9c.3.1.4.2.5.3.1.2.1.7-.1 1.3z"/></svg>
                        </a>
                        <a href="viber://forward?text={{ rawurlencode($shareText.' '.$shareUrl) }}" class="w-8 h-8 rounded-full bg-[#7360F2] text-white flex items-center justify-center text-[10px] font-bold" aria-label="Viber">V</a>
                        <button type="button" data-copy-link="{{ $shareUrl }}" data-copied="{{ __('Link copied') }}" class="w-8 h-8 rounded-full border border-gray-300 text-gray-600 hover:text-primary flex items-center justify-center" aria-label="{{ __('Copy link') }}"><x-icon name="share" class="w-4 h-4" /></button>
                    </div>
                </div>
                @if($whatsapp)
                    <a href="https://wa.me/{{ $whatsapp }}?text={{ urlencode(__('Hi, I have a question about :product', ['product' => $product->name]).' '.$shareUrl) }}" target="_blank" rel="noopener" class="mt-3 flex items-center justify-center gap-2 h-10 rounded-lg border border-[#25D366] text-[#128C7E] text-sm font-semibold hover:bg-[#25D366]/10">
                        <x-icon name="chat" class="w-4 h-4" />{{ __('Questions? Chat on WhatsApp') }}
                    </a>
                @endif

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
            <a href="#questions" class="py-3 border-b-2 border-transparent hover:border-primary hover:text-primary whitespace-nowrap">{{ __('Q&A') }} ({{ $questions->count() }})</a>
            @if($moreFromSeller->isNotEmpty())<a href="#more-from-seller" class="py-3 border-b-2 border-transparent hover:border-primary hover:text-primary whitespace-nowrap">{{ __('More from this shop') }}</a>@endif
        </nav>
    </div>

    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-6 lg:py-8 space-y-10">
        @if($boughtTogether->isNotEmpty() && $stock > 0)
            @php $bundle = collect([$product])->merge($boughtTogether); @endphp
            <section class="rounded-xl border border-gray-200 p-4 lg:p-5" aria-labelledby="bought-together">
                <h2 id="bought-together" class="font-display text-xl font-bold text-dark mb-4">{{ __('Frequently bought together') }}</h2>
                <form action="{{ route('cart.addMany') }}" method="POST" class="flex flex-col lg:flex-row lg:items-center gap-4" data-bundle>
                    @csrf
                    <div class="flex items-center gap-2 overflow-x-auto scrollbar-hide">
                        @foreach($bundle as $item)
                            @if(! $loop->first)<x-icon name="plus" class="w-5 h-5 shrink-0 text-gray-400" />@endif
                            <label class="shrink-0 w-28 text-xs cursor-pointer">
                                <span class="relative block aspect-square rounded-lg overflow-hidden bg-primary-50 border border-gray-200">
                                    <img src="{{ $item->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="" class="w-full h-full object-cover">
                                    <input type="checkbox" name="product_ids[]" value="{{ $item->id }}" data-price="{{ $item->final_price }}" checked class="absolute top-1.5 start-1.5 rounded text-primary focus:ring-primary">
                                </span>
                                <span class="mt-1 block line-clamp-2 font-medium">{{ $loop->first ? __('This item:') : '' }} {{ $item->name }}</span>
                                <span class="font-semibold">{{ \App\Support\Money::format($item->final_price) }}</span>
                            </label>
                        @endforeach
                    </div>
                    <div class="lg:ms-auto lg:text-end">
                        <p class="text-sm text-gray-600">{{ __('Total') }}: <span class="font-bold text-lg text-dark" data-bundle-total>{{ \App\Support\Money::format($bundle->sum('final_price')) }}</span></p>
                        <button type="submit" class="mt-2 inline-flex items-center gap-2 h-10 px-5 rounded-lg bg-sun text-sun-on font-semibold hover:bg-accent-400"><x-icon name="cart" class="w-4 h-4" />{{ __('Add selected to cart') }}</button>
                    </div>
                </form>
            </section>
        @endif

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

        <section id="reviews" class="scroll-mt-40">
            <h2 class="font-display text-xl font-bold text-dark mb-4">{{ __('Reviews') }}</h2>
            <div class="grid lg:grid-cols-[18rem_1fr] gap-6 lg:gap-10">
                <div>
                    <div class="flex items-center gap-3">
                        <p class="font-display text-4xl font-bold">{{ $reviews->count() ? number_format($rating, 1) : '–' }}</p>
                        <div>
                            <x-rating :value="$rating" size="lg" />
                            <p class="text-sm text-gray-500">{{ $reviews->count() ? trans_choice(':count review|:count reviews', $reviews->count(), ['count' => $reviews->count()]) : __('No reviews yet') }}</p>
                        </div>
                    </div>
                    <ul class="mt-4 space-y-1.5 text-sm">
                        @foreach($ratingBreakdown as $stars => $count)
                            @php $pct = $reviews->count() ? round($count / $reviews->count() * 100) : 0; @endphp
                            <li class="flex items-center gap-2">
                                <span class="w-12 shrink-0 text-gray-600">{{ __(':stars star', ['stars' => $stars]) }}</span>
                                <span class="flex-1 h-2.5 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full bg-sun rounded-full" style="width: {{ $pct }}%"></span></span>
                                <span class="w-9 shrink-0 text-end text-gray-500">{{ $pct }}%</span>
                            </li>
                        @endforeach
                    </ul>

                    <div class="mt-5 rounded-xl border border-gray-200 p-4">
                        @auth
                            <details @if($errors->hasAny(['rating', 'comment', 'title'])) open @endif>
                                <summary class="cursor-pointer font-semibold text-primary">{{ $myReview ? __('Edit your review') : __('Write a review') }}</summary>
                                <form action="{{ route('reviews.store', $product) }}" method="POST" class="mt-3 space-y-3">
                                    @csrf
                                    <fieldset>
                                        <legend class="text-sm font-medium text-gray-700 mb-1">{{ __('Your rating') }}</legend>
                                        <div class="flex flex-row-reverse justify-end gap-1" dir="ltr">
                                            @for($i = 5; $i >= 1; $i--)
                                                <input type="radio" id="rate-{{ $i }}" name="rating" value="{{ $i }}" class="peer sr-only" @checked((int) old('rating', $myReview?->rating) === $i) required>
                                                <label for="rate-{{ $i }}" class="cursor-pointer text-gray-300 peer-checked:text-sun hover:text-sun [&:hover~label]:text-sun" title="{{ __(':stars star', ['stars' => $i]) }}"><x-icon name="star" class="w-7 h-7" /></label>
                                            @endfor
                                        </div>
                                        @error('rating')<p class="text-danger text-xs">{{ $message }}</p>@enderror
                                    </fieldset>
                                    <div>
                                        <label for="review-title" class="text-sm font-medium text-gray-700">{{ __('Headline') }} <span class="text-gray-400 font-normal">({{ __('optional') }})</span></label>
                                        <input id="review-title" name="title" maxlength="120" value="{{ old('title', $myReview?->title) }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-primary">
                                    </div>
                                    <div>
                                        <label for="review-comment" class="text-sm font-medium text-gray-700">{{ __('Your review') }}</label>
                                        <textarea id="review-comment" name="comment" rows="4" required minlength="10" maxlength="2000" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-primary">{{ old('comment', $myReview?->comment) }}</textarea>
                                        @error('comment')<p class="text-danger text-xs">{{ $message }}</p>@enderror
                                    </div>
                                    <button type="submit" class="w-full h-10 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-hover">{{ __('Post review') }}</button>
                                </form>
                            </details>
                        @else
                            <p class="text-sm text-gray-600">{{ __('Bought this?') }}</p>
                            <a href="{{ route('login') }}" class="mt-2 inline-flex items-center justify-center w-full h-10 rounded-lg border border-gray-300 text-sm font-semibold hover:bg-gray-50">{{ __('Sign in to write a review') }}</a>
                        @endauth
                    </div>
                </div>

                <div>
                    @if($reviews->isEmpty())
                        <p class="text-gray-500">{{ __('Be the first to review this product.') }}</p>
                    @else
                        <ul class="divide-y divide-gray-200 border-y border-gray-200">
                            @foreach($reviews->take(20) as $review)
                                <li id="review-{{ $review->id }}" class="py-4 scroll-mt-40">
                                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                        <x-rating :value="$review->rating" />
                                        @if($review->title)<span class="font-semibold text-dark">{{ $review->title }}</span>@endif
                                    </div>
                                    <p class="mt-1 text-xs text-gray-500 flex flex-wrap items-center gap-x-2">
                                        <span class="font-medium text-gray-700">{{ $review->user?->name ?? $review->reviewer_name }}</span>
                                        <span>{{ $review->created_at?->format('j M Y') }}</span>
                                        @if($review->verified_purchase)<span class="inline-flex items-center gap-1 font-semibold text-success"><x-icon name="check" class="w-3.5 h-3.5" />{{ __('Verified purchase') }}</span>@endif
                                    </p>
                                    <p class="mt-2 text-sm text-gray-700 whitespace-pre-line">{{ $review->comment }}</p>
                                    <div class="mt-2 flex items-center gap-3 text-xs text-gray-500">
                                        @if($review->helpful_count)<span>{{ trans_choice(':count person found this helpful|:count people found this helpful', $review->helpful_count, ['count' => $review->helpful_count]) }}</span>@endif
                                        @auth
                                            @if($review->user_id !== auth()->id())
                                                @if(in_array($review->id, $votedReviewIds, true))
                                                    <span class="text-success font-medium">{{ __('Thanks for your feedback') }}</span>
                                                @else
                                                    <form action="{{ route('reviews.helpful', $review) }}" method="POST">@csrf<button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full border border-gray-300 hover:border-primary hover:text-primary"><x-icon name="thumb" class="w-3.5 h-3.5" />{{ __('Helpful') }}</button></form>
                                                @endif
                                            @endif
                                        @endauth
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        </section>

        <section id="questions" class="scroll-mt-40 max-w-3xl">
            <h2 class="font-display text-xl font-bold text-dark mb-3">{{ __('Questions & answers') }}</h2>
            @auth
                <form action="{{ route('questions.store', $product) }}" method="POST" class="mb-5">
                    @csrf
                    <label for="question" class="text-sm font-medium text-gray-700">{{ __('Ask the shop a question') }}</label>
                    <div class="mt-1 flex gap-2">
                        <input id="question" name="question" required minlength="10" maxlength="500" value="{{ old('question') }}" placeholder="{{ __('e.g. Does it come in other sizes?') }}" class="flex-1 min-w-0 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-primary">
                        <button type="submit" class="px-4 rounded-lg bg-primary text-white text-sm font-semibold hover:bg-primary-hover">{{ __('Ask') }}</button>
                    </div>
                    @error('question')<p class="text-danger text-xs mt-1">{{ $message }}</p>@enderror
                </form>
            @else
                <p class="mb-5 text-sm text-gray-600"><a href="{{ route('login') }}" class="font-semibold text-primary hover:underline">{{ __('Sign in') }}</a> {{ __('to ask the shop a question.') }}</p>
            @endauth

            @forelse($questions as $question)
                <div class="py-4 border-t border-gray-200">
                    <p class="flex gap-2"><span class="font-bold text-primary shrink-0">{{ __('Q:') }}</span><span class="font-medium text-dark">{{ $question->question }}</span></p>
                    @if($question->answer)
                        <p class="mt-2 flex gap-2 text-sm"><span class="font-bold text-sun-ink shrink-0">{{ __('A:') }}</span><span class="text-gray-700">{{ $question->answer }}
                            <span class="block text-xs text-gray-500 mt-1">{{ $question->answered_by === $product->seller_id ? ($sellerName ?? __('The shop')) : 'iruali' }} &middot; {{ $question->answered_at?->format('j M Y') }}</span></span></p>
                    @else
                        <p class="mt-2 text-sm text-gray-500 ps-6">{{ __('Waiting for the shop to answer.') }}</p>
                    @endif
                    @if($isOwner)
                        <form action="{{ route('questions.answer', $question) }}" method="POST" class="mt-2 ps-6 flex gap-2">
                            @csrf
                            <label for="answer-{{ $question->id }}" class="sr-only">{{ __('Answer') }}</label>
                            <input id="answer-{{ $question->id }}" name="answer" required value="{{ $question->answer }}" class="flex-1 min-w-0 rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
                            <button type="submit" class="px-3 rounded-lg border border-gray-300 text-sm font-semibold">{{ __('Answer') }}</button>
                        </form>
                    @endif
                </div>
            @empty
                <p class="text-gray-500 text-sm">{{ __('No questions yet.') }}</p>
            @endforelse
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

<!-- Zoomed image -->
<div data-zoom class="hidden fixed inset-0 z-[80] bg-reef/95 flex items-center justify-center p-4 overflow-hidden" role="dialog" aria-modal="true" aria-label="{{ __('Zoom image') }}">
    <button type="button" class="absolute top-4 end-4 text-white" aria-label="{{ __('Close') }}"><x-icon name="x" class="w-8 h-8" /></button>
    <img src="" alt="{{ $product->name }}" class="max-w-full max-h-full object-contain transition-transform duration-200 cursor-zoom-in">
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

        // Zoom: open the current image full screen
        var zoom = document.querySelector('[data-zoom]');
        document.querySelectorAll('[data-zoom-open]').forEach(function (b) {
            b.addEventListener('click', function () {
                zoom.querySelector('img').src = main.src;
                zoom.classList.remove('hidden');
                document.documentElement.classList.add('overflow-hidden');
            });
        });
        if (zoom) {
            var zimg = zoom.querySelector('img');
            zoom.addEventListener('click', function (e) {
                if (e.target === zimg) { zimg.classList.toggle('scale-[2]'); zimg.classList.toggle('cursor-zoom-out'); return; }
                zoom.classList.add('hidden'); zimg.classList.remove('scale-[2]'); document.documentElement.classList.remove('overflow-hidden');
            });
            zimg.addEventListener('mousemove', function (e) {
                var r = zimg.getBoundingClientRect();
                zimg.style.transformOrigin = ((e.clientX - r.left) / r.width * 100) + '% ' + ((e.clientY - r.top) / r.height * 100) + '%';
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') zoom.click(); });
        }

        document.querySelectorAll('[data-copy-link]').forEach(function (b) {
            b.addEventListener('click', function () {
                navigator.clipboard && navigator.clipboard.writeText(b.dataset.copyLink).then(function () {
                    window.NotificationManager ? window.NotificationManager.success(b.dataset.copied, '') : alert(b.dataset.copied);
                });
            });
        });

        var bundle = document.querySelector('[data-bundle]');
        if (bundle) {
            var totalEl = bundle.querySelector('[data-bundle-total]'), fmt = totalEl.textContent.replace(/[0-9.,]+/, '#');
            bundle.querySelectorAll('input[type=checkbox]').forEach(function (c) {
                c.addEventListener('change', function () {
                    var t = 0;
                    bundle.querySelectorAll('input[type=checkbox]:checked').forEach(function (x) { t += parseFloat(x.dataset.price); });
                    totalEl.textContent = fmt.replace('#', t.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
                });
            });
        }

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
