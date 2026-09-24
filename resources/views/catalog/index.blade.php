@extends('layouts.app')

@php
    $query = request()->query();
    $url = fn (array $changes) => request()->url().'?'.http_build_query(array_filter(array_merge(\Illuminate\Support\Arr::except($query, 'page'), $changes), fn ($v) => $v !== null && $v !== ''));
    $seller = $seller ?? null;
    $category = $category ?? null;
    $subcategories = $subcategories ?? collect();
    $subtitle = $subtitle ?? null;
@endphp

@section('content')
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-4 lg:py-6">
        <!-- Breadcrumb -->
        <nav class="text-xs sm:text-sm text-gray-500 mb-3 overflow-x-auto whitespace-nowrap scrollbar-hide" aria-label="{{ __('Breadcrumb') }}">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="hover:text-primary hover:underline">{{ __('Home') }}</a></li>
                @foreach($crumbs as $crumb)
                    <li class="flex items-center gap-1.5"><x-icon name="chevron-right" class="w-3 h-3 rtl:rotate-180" />
                        @if($crumb['url'])<a href="{{ $crumb['url'] }}" class="hover:text-primary hover:underline">{{ $crumb['label'] }}</a>@else<span>{{ $crumb['label'] }}</span>@endif
                    </li>
                @endforeach
                <li class="flex items-center gap-1.5"><x-icon name="chevron-right" class="w-3 h-3 rtl:rotate-180" /><span class="text-dark font-medium">{{ $title }}</span></li>
            </ol>
        </nav>

        <!-- Page heading -->
        @if($seller)
            <div class="bg-white border border-gray-200 rounded-xl p-4 lg:p-6 mb-5 flex items-center gap-4">
                <span class="w-14 h-14 lg:w-16 lg:h-16 shrink-0 rounded-xl bg-primary text-white font-display font-bold text-2xl flex items-center justify-center">{{ mb_strtoupper(mb_substr($title, 0, 1)) }}</span>
                <div class="min-w-0">
                    <h1 class="font-display text-xl lg:text-3xl font-bold text-dark truncate">{{ $title }}</h1>
                    <p class="text-sm text-gray-600 flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                        @if($seller->city)<span class="inline-flex items-center gap-1"><x-icon name="map-pin" class="w-4 h-4" />{{ $seller->city }}</span>@endif
                        <span class="inline-flex items-center gap-1 text-success font-medium"><x-icon name="shield" class="w-4 h-4" />{{ __('Reviewed seller') }}</span>
                        <span>{{ trans_choice(':count product|:count products', $products->total(), ['count' => $products->total()]) }}</span>
                    </p>
                    @if($seller->business_description)<p class="text-sm text-gray-600 mt-2 line-clamp-2">{{ $seller->business_description }}</p>@endif
                </div>
            </div>
        @else
            <div class="mb-4">
                <h1 class="font-display text-2xl lg:text-3xl font-bold text-dark">{{ $title }}</h1>
                @if($subtitle)<p class="text-sm text-gray-600 mt-1 max-w-3xl">{{ $subtitle }}</p>@endif
            </div>
        @endif

        @if($subcategories->isNotEmpty())
            <div class="flex gap-2 overflow-x-auto scrollbar-hide mb-4">
                @foreach($subcategories as $sub)
                    <a href="{{ route('categories.show', $sub) }}" class="shrink-0 px-4 py-2 rounded-full bg-white border border-gray-200 text-sm font-medium hover:border-primary hover:text-primary">{{ $sub->localized_name }}</a>
                @endforeach
            </div>
        @endif

        <div class="lg:grid lg:grid-cols-[16rem_1fr] lg:gap-6">
            <!-- Filters: a sidebar on desktop, a drawer on mobile -->
            <div data-filters class="hidden lg:block fixed inset-0 z-[60] lg:static lg:z-auto" role="dialog" aria-label="{{ __('Filters') }}">
                <div data-filters-close class="absolute inset-0 bg-reef/60 lg:hidden"></div>
                <aside class="absolute inset-y-0 end-0 w-[88%] max-w-sm bg-white flex flex-col lg:static lg:w-auto lg:max-w-none lg:bg-transparent">
                    <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 lg:hidden">
                        <p class="font-semibold text-lg">{{ __('Filters') }}</p>
                        <button type="button" data-filters-close class="p-1" aria-label="{{ __('Close') }}"><x-icon name="x" class="w-6 h-6" /></button>
                    </div>
                    <form method="GET" action="{{ request()->url() }}" data-filter-form class="flex-1 overflow-y-auto lg:overflow-visible p-4 lg:p-0 space-y-4">
                        @foreach(['q', 'sort', 'view', 'per_page'] as $keep)
                            @if(request()->filled($keep))<input type="hidden" name="{{ $keep }}" value="{{ request($keep) }}">@endif
                        @endforeach

                        @unless($facets['category_locked'])
                            @if($facets['categories']->isNotEmpty())
                                <fieldset class="bg-white lg:border lg:border-gray-200 lg:rounded-xl lg:p-4">
                                    <legend class="font-semibold text-sm mb-2 lg:float-left lg:w-full">{{ __('Department') }}</legend>
                                    <div class="space-y-1 text-sm clear-both">
                                        <label class="flex items-center gap-2 py-1 cursor-pointer">
                                            <input type="radio" name="category" value="" class="text-primary focus:ring-primary" @checked(! request('category')) data-autosubmit>
                                            <span class="flex-1">{{ __('All departments') }}</span>
                                        </label>
                                        @foreach($facets['categories'] as $dept)
                                            <label class="flex items-center gap-2 py-1 cursor-pointer">
                                                <input type="radio" name="category" value="{{ $dept->slug }}" class="text-primary focus:ring-primary" @checked(request('category') === $dept->slug) data-autosubmit>
                                                <span class="flex-1">{{ $dept->localized_name }}</span>
                                                <span class="text-xs text-gray-400">{{ $dept->facet_count }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                        @endunless

                        <fieldset class="bg-white lg:border lg:border-gray-200 lg:rounded-xl lg:p-4">
                            <legend class="font-semibold text-sm mb-2 lg:float-left lg:w-full">{{ __('Price') }} <span class="font-normal text-gray-500">(MVR)</span></legend>
                            <div class="flex items-center gap-2 clear-both">
                                <label class="sr-only" for="min_price">{{ __('Min') }}</label>
                                <input id="min_price" type="number" inputmode="numeric" min="0" name="min_price" value="{{ request('min_price') }}" placeholder="{{ $facets['price_min'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-primary">
                                <span class="text-gray-400">–</span>
                                <label class="sr-only" for="max_price">{{ __('Max') }}</label>
                                <input id="max_price" type="number" inputmode="numeric" min="0" name="max_price" value="{{ request('max_price') }}" placeholder="{{ $facets['price_max'] }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-primary">
                                <button type="submit" class="shrink-0 px-3 py-2 rounded-lg bg-gray-100 hover:bg-gray-200 text-sm font-semibold" aria-label="{{ __('Apply price') }}"><x-icon name="chevron-right" class="w-4 h-4 rtl:rotate-180" /></button>
                            </div>
                        </fieldset>

                        <fieldset class="bg-white lg:border lg:border-gray-200 lg:rounded-xl lg:p-4 space-y-1 text-sm">
                            <legend class="font-semibold text-sm mb-2 lg:float-left lg:w-full">{{ __('Availability & offers') }}</legend>
                            <label class="flex items-center gap-2 py-1 cursor-pointer clear-both">
                                <input type="checkbox" name="in_stock" value="1" class="rounded text-primary focus:ring-primary" @checked(request()->boolean('in_stock')) data-autosubmit>
                                <span class="flex-1">{{ __('In stock only') }}</span><span class="text-xs text-gray-400">{{ $facets['in_stock_count'] }}</span>
                            </label>
                            @unless($facets['deals_locked'])
                                <label class="flex items-center gap-2 py-1 cursor-pointer">
                                    <input type="checkbox" name="deals" value="1" class="rounded text-primary focus:ring-primary" @checked(request()->boolean('deals')) data-autosubmit>
                                    <span class="flex-1">{{ __('On sale') }}</span><span class="text-xs text-gray-400">{{ $facets['deals_count'] }}</span>
                                </label>
                            @endunless
                        </fieldset>

                        <fieldset class="bg-white lg:border lg:border-gray-200 lg:rounded-xl lg:p-4 space-y-1 text-sm">
                            <legend class="font-semibold text-sm mb-2 lg:float-left lg:w-full">{{ __('Customer rating') }}</legend>
                            @foreach([4, 3] as $stars)
                                <label class="flex items-center gap-2 py-1 cursor-pointer clear-both">
                                    <input type="radio" name="rating" value="{{ $stars }}" class="text-primary focus:ring-primary" @checked((int) request('rating') === $stars) data-autosubmit>
                                    <x-rating :value="$stars" :count="null" />
                                    <span>{{ __('& up') }}</span>
                                </label>
                            @endforeach
                        </fieldset>

                        @if($facets['brands']->isNotEmpty())
                            <fieldset class="bg-white lg:border lg:border-gray-200 lg:rounded-xl lg:p-4 text-sm" data-collapsible>
                                <legend class="font-semibold text-sm mb-2 lg:float-left lg:w-full">{{ __('Brand') }}</legend>
                                <div class="space-y-1 clear-both">
                                    @foreach($facets['brands'] as $brand => $count)
                                        <label class="flex items-center gap-2 py-1 cursor-pointer {{ $loop->index >= 6 ? 'hidden' : '' }}" @if($loop->index >= 6) data-more @endif>
                                            <input type="checkbox" name="brand[]" value="{{ $brand }}" class="rounded text-primary focus:ring-primary" @checked(in_array($brand, (array) request('brand', []), true)) data-autosubmit>
                                            <span class="flex-1 truncate">{{ $brand }}</span><span class="text-xs text-gray-400">{{ $count }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @if($facets['brands']->count() > 6)
                                    <button type="button" data-show-more class="mt-1 text-sm font-semibold text-primary hover:underline">{{ __('Show all') }} ({{ $facets['brands']->count() }})</button>
                                @endif
                            </fieldset>
                        @endif

                        @if($facets['sellers']->isNotEmpty())
                            <fieldset class="bg-white lg:border lg:border-gray-200 lg:rounded-xl lg:p-4 text-sm" data-collapsible>
                                <legend class="font-semibold text-sm mb-2 lg:float-left lg:w-full">{{ __('Shop') }}</legend>
                                <div class="space-y-1 clear-both">
                                    @foreach($facets['sellers'] as $s)
                                        <label class="flex items-center gap-2 py-1 cursor-pointer {{ $loop->index >= 6 ? 'hidden' : '' }}" @if($loop->index >= 6) data-more @endif>
                                            <input type="checkbox" name="seller[]" value="{{ $s->id }}" class="rounded text-primary focus:ring-primary" @checked(in_array((string) $s->id, array_map('strval', (array) request('seller', [])), true)) data-autosubmit>
                                            <span class="flex-1 min-w-0"><span class="block truncate">{{ $s->business_name ?: $s->name }}</span>@if($s->city)<span class="block text-xs text-gray-500 truncate">{{ $s->city }}</span>@endif</span>
                                            <span class="text-xs text-gray-400">{{ $s->facet_count }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                @if($facets['sellers']->count() > 6)
                                    <button type="button" data-show-more class="mt-1 text-sm font-semibold text-primary hover:underline">{{ __('Show all') }} ({{ $facets['sellers']->count() }})</button>
                                @endif
                            </fieldset>
                        @endif
                    </form>
                    <div class="lg:hidden border-t border-gray-200 p-4 flex gap-3">
                        <a href="{{ request()->url().(request('q') ? '?q='.urlencode(request('q')) : '') }}" class="flex-1 text-center py-3 rounded-lg border border-gray-300 font-semibold">{{ __('Clear all') }}</a>
                        <button type="button" data-filters-close class="flex-1 py-3 rounded-lg bg-primary text-white font-semibold">{{ __('Show :count results', ['count' => $products->total()]) }}</button>
                    </div>
                </aside>
            </div>

            <!-- Results -->
            <section class="min-w-0" aria-label="{{ __('Results') }}">
                <!-- Toolbar -->
                <div class="bg-white border border-gray-200 rounded-xl px-3 py-2.5 flex flex-wrap items-center gap-2 lg:gap-4 mb-3">
                    <p class="text-sm text-gray-600 me-auto w-full sm:w-auto">
                        @if($products->total() > 0)
                            {{ __(':from–:to of :total results', ['from' => $products->firstItem(), 'to' => $products->lastItem(), 'total' => $products->total()]) }}
                        @else
                            {{ __('No results') }}
                        @endif
                    </p>
                    <button type="button" data-filters-open class="shrink-0 lg:hidden inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-gray-300 text-sm font-semibold">
                        <x-icon name="filter" class="w-4 h-4" />{{ __('Filters') }}@if($active->count())<span class="min-w-5 h-5 px-1 rounded-full bg-primary text-white text-[11px] flex items-center justify-center">{{ $active->count() }}</span>@endif
                    </button>
                    <label class="flex-1 min-w-0 sm:flex-none flex items-center gap-2 text-sm">
                        <span class="hidden sm:inline text-gray-600">{{ __('Sort by') }}</span>
                        <select data-nav-select class="w-full sm:w-auto rounded-lg border border-gray-300 bg-white ps-3 pe-8 text-sm py-2 focus:border-primary focus:ring-primary" aria-label="{{ __('Sort by') }}">
                            @foreach($sorts as $key => $label)
                                <option value="{{ $url(['sort' => $key]) }}" @selected($sort === $key)>{{ __($label) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="hidden md:flex items-center gap-2 text-sm">
                        <span class="text-gray-600">{{ __('Show') }}</span>
                        <select data-nav-select class="rounded-lg border border-gray-300 bg-white ps-3 pe-8 text-sm py-2 focus:border-primary focus:ring-primary" aria-label="{{ __('Results per page') }}">
                            @foreach(\App\Services\CatalogService::PER_PAGE as $n)
                                <option value="{{ $url(['per_page' => $n]) }}" @selected($perPage === $n)>{{ $n }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="shrink-0 flex rounded-lg border border-gray-300 overflow-hidden" role="group" aria-label="{{ __('View') }}">
                        <a href="{{ $url(['view' => null]) }}" class="p-2 {{ $view === 'grid' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}" aria-label="{{ __('Grid view') }}" @if($view === 'grid') aria-current="true" @endif><x-icon name="grid" class="w-4 h-4" /></a>
                        <a href="{{ $url(['view' => 'list']) }}" class="p-2 {{ $view === 'list' ? 'bg-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}" aria-label="{{ __('List view') }}" @if($view === 'list') aria-current="true" @endif><x-icon name="list" class="w-4 h-4" /></a>
                    </div>
                </div>

                @if($active->isNotEmpty())
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        @foreach($active as $chip)
                            <a href="{{ $chip['url'] }}" class="inline-flex items-center gap-1.5 ps-3 pe-2 py-1 rounded-full bg-primary-50 text-primary-800 text-sm font-medium hover:bg-primary-100">
                                {{ $chip['label'] }}<x-icon name="x" class="w-3.5 h-3.5" /><span class="sr-only">{{ __('Remove filter') }}</span>
                            </a>
                        @endforeach
                        <a href="{{ request()->url().(request('q') ? '?q='.urlencode(request('q')) : '') }}" class="text-sm font-semibold text-primary hover:underline">{{ __('Clear all') }}</a>
                    </div>
                @endif

                @if($products->isEmpty())
                    <div class="bg-white border border-gray-200 rounded-xl p-10 text-center">
                        <span class="mx-auto w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center mb-4"><x-icon name="search" class="w-7 h-7" /></span>
                        <p class="font-semibold text-lg">{{ __('No products match') }}</p>
                        <p class="text-gray-600 text-sm mt-1">{{ __('Try removing a filter or searching for something else.') }}</p>
                        <a href="{{ route('shop') }}" class="inline-block mt-5 px-5 py-2.5 rounded-lg bg-primary text-white font-semibold">{{ __('Shop all products') }}</a>
                    </div>
                @elseif($view === 'list')
                    <div class="space-y-3">
                        @foreach($products as $product)
                            <x-product-card :product="$product" layout="list" />
                        @endforeach
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-4">
                        @foreach($products as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    </div>
                @endif

                @if($products->hasPages())
                    <div class="mt-6">{{ $products->onEachSide(1)->links() }}</div>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var panel = document.querySelector('[data-filters]');
        function setFilters(open) {
            panel.classList.toggle('hidden', !open);
            document.documentElement.classList.toggle('overflow-hidden', open);
        }
        document.querySelectorAll('[data-filters-open]').forEach(function (b) { b.addEventListener('click', function () { setFilters(true); }); });
        document.querySelectorAll('[data-filters-close]').forEach(function (b) { b.addEventListener('click', function () { setFilters(false); }); });

        // A ticked filter applies straight away (the page reloads with the new results).
        var form = document.querySelector('[data-filter-form]');
        form.querySelectorAll('[data-autosubmit]').forEach(function (input) {
            input.addEventListener('change', function () { form.submit(); });
        });

        document.querySelectorAll('[data-show-more]').forEach(function (b) {
            b.addEventListener('click', function () {
                b.closest('[data-collapsible]').querySelectorAll('[data-more]').forEach(function (el) { el.classList.remove('hidden'); });
                b.remove();
            });
        });

        document.querySelectorAll('[data-nav-select]').forEach(function (s) {
            s.addEventListener('change', function () { window.location = s.value; });
        });
    })();
</script>
@endpush
