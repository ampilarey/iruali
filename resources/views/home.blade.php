@extends('layouts.app')

@section('content')
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 pt-4 lg:pt-6 pb-10 space-y-8 lg:space-y-12">

        <!-- Hero + side promos -->
        <section class="grid lg:grid-cols-3 gap-3 lg:gap-4">
            <div class="lg:col-span-2 relative overflow-hidden rounded-2xl bg-primary text-white p-6 sm:p-10 min-h-[15rem] lg:min-h-[20rem] flex flex-col justify-end">
                <svg class="absolute -top-6 -end-10 w-44 h-44 opacity-30 sm:opacity-90 sm:top-auto sm:-bottom-6 sm:end-0 sm:w-64 sm:h-64 lg:w-80 lg:h-80 text-sun" viewBox="0 0 200 200" aria-hidden="true"><path fill="currentColor" d="M20 110a80 80 0 01160 0z"/><rect x="10" y="122" width="180" height="12" rx="6" fill="#63C0B2"/><rect x="45" y="146" width="110" height="10" rx="5" fill="#9DD8CE"/></svg>
                <div class="relative max-w-md">
                    <p class="inline-block px-3 py-1 rounded-full bg-white/15 text-xs font-semibold mb-3">{{ __('Shops from every island, in one place.') }}</p>
                    <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight">{{ __('Local shops. Island delivery.') }}</h1>
                    <p class="mt-3 text-white/85 sm:text-lg">{{ __('Buy from local sellers across the Maldives and get it delivered to your island.') }}</p>
                    <div class="mt-6 flex flex-wrap gap-3">
                        <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-sun text-sun-on font-semibold hover:bg-accent-400">{{ __('Shop Now') }}<x-icon name="arrow-right" class="w-4 h-4 rtl:rotate-180" /></a>
                        <a href="{{ route('categories.index') }}" class="inline-flex items-center px-6 py-3 rounded-lg bg-white/10 border border-white/30 font-semibold hover:bg-white/20">{{ __('Browse Categories') }}</a>
                    </div>
                </div>
            </div>
            <div class="grid grid-cols-2 lg:grid-cols-1 gap-3 lg:gap-4">
                <a href="{{ route('deals') }}" class="group rounded-2xl bg-coral text-white p-5 flex flex-col justify-between min-h-[8rem] hover:bg-coral-hover">
                    <x-icon name="tag" class="w-7 h-7" />
                    <span>
                        <span class="block font-display text-xl lg:text-2xl font-bold">{{ __('Today\'s deals') }}</span>
                        <span class="text-sm text-white/85 inline-flex items-center gap-1">{{ __('Marked-down prices') }}<x-icon name="chevron-right" class="w-4 h-4 rtl:rotate-180 group-hover:translate-x-0.5 transition" /></span>
                    </span>
                </a>
                <a href="{{ route('seller.apply') }}" class="group rounded-2xl bg-reef text-white p-5 flex flex-col justify-between min-h-[8rem] hover:bg-reef-night">
                    <x-icon name="store" class="w-7 h-7 text-sun" />
                    <span>
                        <span class="block font-display text-xl lg:text-2xl font-bold">{{ __('Sell on iruali') }}</span>
                        <span class="text-sm text-white/85 inline-flex items-center gap-1">{{ __('Open your shop') }}<x-icon name="chevron-right" class="w-4 h-4 rtl:rotate-180" /></span>
                    </span>
                </a>
            </div>
        </section>

        <!-- Departments -->
        @if($departments->isNotEmpty())
            <section>
                <div class="flex items-end justify-between gap-4 mb-3">
                    <h2 class="font-display text-xl lg:text-2xl font-bold text-dark">{{ __('Shop by department') }}</h2>
                    <a href="{{ route('categories.index') }}" class="shrink-0 whitespace-nowrap text-sm font-semibold text-primary hover:underline">{{ __('See all') }}</a>
                </div>
                <div class="grid grid-cols-4 lg:grid-cols-8 gap-2 lg:gap-3">
                    @foreach($departments as $dept)
                        <a href="{{ route('categories.show', $dept) }}" class="group bg-white border border-gray-200 rounded-xl p-2.5 lg:p-4 flex flex-col items-center text-center gap-2 hover:border-primary hover:shadow-sm">
                            <span class="w-11 h-11 lg:w-14 lg:h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center group-hover:bg-primary group-hover:text-white transition"><x-icon :name="$dept->slug" class="w-5 h-5 lg:w-7 lg:h-7" /></span>
                            <span class="text-[11px] sm:text-xs lg:text-sm font-semibold leading-tight text-dark">{{ $dept->localized_name }}</span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($deals->isNotEmpty())
            <x-product-row :title="__('Today\'s deals')" :products="$deals" :link="route('deals')" />
        @endif

        @if($featured->isNotEmpty())
            <x-product-row :title="__('Featured Products')" :products="$featured" :link="route('products.index')" />
        @endif

        <!-- Shops -->
        @if($shops->isNotEmpty())
            <section>
                <h2 class="font-display text-xl lg:text-2xl font-bold text-dark mb-3">{{ __('Shops on iruali') }}</h2>
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-2.5 lg:gap-4">
                    @foreach($shops as $shop)
                        @php $shopName = $shop->business_name ?: $shop->name; @endphp
                        <a href="{{ route('sellers.show', $shop) }}" class="group bg-white border border-gray-200 rounded-xl p-3 lg:p-4 flex items-center gap-3 hover:border-primary hover:shadow-sm">
                            <span class="w-11 h-11 shrink-0 rounded-lg bg-primary text-white font-display font-bold text-lg flex items-center justify-center">{{ mb_strtoupper(mb_substr($shopName, 0, 1)) }}</span>
                            <span class="min-w-0">
                                <span class="block font-semibold text-sm text-dark truncate group-hover:text-primary">{{ $shopName }}</span>
                                <span class="block text-xs text-gray-500 truncate">{{ $shop->city ? $shop->city.' · ' : '' }}{{ trans_choice(':count product|:count products', $shop->products_count, ['count' => $shop->products_count]) }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        @if($newArrivals->isNotEmpty())
            <x-product-row :title="__('New arrivals')" :products="$newArrivals" :link="route('shop', ['sort' => 'newest'])" />
        @endif

        <div class="text-center">
            <a href="{{ route('products.index') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg border-2 border-primary text-primary font-semibold hover:bg-primary hover:text-white">{{ __('View All Products') }}<x-icon name="arrow-right" class="w-4 h-4 rtl:rotate-180" /></a>
        </div>

        <!-- Sell CTA -->
        <section class="rounded-2xl bg-white border border-gray-200 p-6 lg:p-10 flex flex-col lg:flex-row lg:items-center gap-6">
            <span class="w-14 h-14 shrink-0 rounded-2xl bg-sun-soft text-sun-ink flex items-center justify-center"><x-icon name="store" class="w-7 h-7" /></span>
            <div class="flex-1">
                <h2 class="font-display text-2xl font-bold text-dark">{{ __('Sell on iruali') }}</h2>
                <p class="text-gray-600 mt-1">{{ __('Have a shop? Reach customers on every inhabited island. Applying takes five minutes.') }}</p>
            </div>
            <a href="{{ route('seller.apply') }}" class="self-start lg:self-auto inline-flex items-center px-6 py-3 rounded-lg bg-primary hover:bg-primary-hover text-white font-semibold">{{ __('Open your shop') }}</a>
        </section>
    </div>
</div>
@endsection
