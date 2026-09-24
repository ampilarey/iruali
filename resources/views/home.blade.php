@extends('layouts.app')

@section('content')
<!-- Hero Section -->
<section class="bg-gradient-to-r from-primary-50 to-accent-50 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
            {{ __('Welcome to') }} <span class="text-primary">iruali</span>
        </h1>
        <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            {{ __('Shops from every island, in one place. Buy from local sellers across the Maldives and get it delivered to your island.') }}
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('shop') }}" class="bg-primary hover:bg-primary-600 text-white px-8 py-3 rounded-lg text-lg font-semibold transition-colors">
                {{ __('Shop Now') }}
            </a>
            <a href="{{ route('categories.index') }}" class="bg-white hover:bg-gray-50 text-primary border-2 border-primary px-8 py-3 rounded-lg text-lg font-semibold transition-colors">
                {{ __('Browse Categories') }}
            </a>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="py-16 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">{{ __('Featured Products') }}</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            @php
                // Get actual featured products from database
                $featuredProducts = \App\Models\Product::with(['category', 'mainImage'])
                    ->active()
                    ->inStock()
                    ->featured()
                    ->take(4)
                    ->get();
                    
                // If no featured products, get some active products
                if ($featuredProducts->count() == 0) {
                    $featuredProducts = \App\Models\Product::with(['category', 'mainImage'])
                        ->active()
                        ->inStock()
                        ->latest()
                        ->take(4)
                        ->get();
                }
            @endphp
            
            @foreach($featuredProducts as $product)
                <x-product-card :product="$product" />
            @endforeach
        </div>
        
        <div class="text-center mt-8">
            <a href="{{ route('products.index') }}" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md text-white bg-primary hover:bg-primary-hover transition duration-200">
                {{ __('View All Products') }}
                <svg class="ms-2 -me-1 w-5 h-5 rtl:rotate-180" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                </svg>
            </a>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-16 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">{{ __('Why shop on iruali') }}</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ __('Local sellers') }}</h3>
                <p class="text-gray-600">{{ __('Every shop is reviewed by our team before its products go live.') }}</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ __('Delivered to your island') }}</h3>
                <p class="text-gray-600">{{ __('Sellers ship by boat and air to islands across every atoll. Track your order from the moment it\'s packed.') }}</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">{{ __('Points on every order') }}</h3>
                <p class="text-gray-600">{{ __('Earn loyalty points when you shop, and more when a friend you refer places their first order.') }}</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-16 bg-primary">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">{{ __('Sell on iruali') }}</h2>
        <p class="text-xl text-primary-100 mb-8">{{ __('Have a shop? Reach customers on every inhabited island. Applying takes five minutes.') }}</p>
        <a href="{{ route('seller.apply') }}" class="bg-white hover:bg-gray-100 text-primary px-8 py-3 rounded-lg text-lg font-semibold transition-colors inline-block">
            {{ __('Open your shop') }}
        </a>
    </div>
</section>
@endsection 