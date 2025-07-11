@extends('layouts.app')

@section('title', 'Home - iruali')

@section('content')
    <!-- Hero Section -->
    <section class="relative bg-gradient-to-r from-primary-600 to-primary-800 text-white py-20">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h1 class="text-4xl md:text-6xl font-bold mb-6">
                        {{ __('app.discover_amazing_products') }}
                    </h1>
                    <p class="text-xl mb-8 text-primary-100">
                        {{ __('app.shop_latest_trends_find_perfect_style') }}
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4">
                        <a href="{{ route('shop') }}" class="bg-white text-primary-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300 text-center">
                            {{ __('app.shop_now') }}
                        </a>
                        <a href="{{ route('products.index') }}" class="border-2 border-white text-white px-8 py-3 rounded-lg font-semibold hover:bg-white hover:text-primary-600 transition duration-300 text-center">
                            {{ __('app.view_products') }}
                        </a>
                    </div>
                </div>
                <div class="hidden lg:block">
                    <img src="https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" 
                         alt="Shopping" class="rounded-lg shadow-2xl">
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Categories -->
    @if($categories->count() > 0)
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">{{ __('app.shop_by_category') }}</h2>
                <p class="text-gray-600">{{ __('app.explore_wide_range_product_categories') }}</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($categories as $category)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                    <div class="relative h-48 bg-gray-200">
                        @if($category->image)
                            <img src="{{ $category->image }}" alt="{{ $category->name }}" class="w-full h-full object-cover">
                        @else
                            <div class="w-full h-full flex items-center justify-center bg-gray-200">
                                <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                                </svg>
                            </div>
                        @endif
                        <div class="absolute inset-0 bg-black bg-opacity-40"></div>
                        <div class="absolute bottom-4 left-4 right-4">
                            <h3 class="text-white text-xl font-semibold">{{ $category->name }}</h3>
                            <p class="text-white text-sm opacity-90">{{ $category->products->count() }} {{ __('app.products') }}</p>
                        </div>
                    </div>
                    <div class="p-6">
                        <p class="text-gray-600 mb-4">{{ Str::limit($category->description, 100) }}</p>
                        <a href="{{ route('categories.show', $category) }}" class="text-primary-600 hover:text-primary-700 font-semibold">
                            {{ __('app.view_category') }} →
                        </a>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Featured Products -->
    @if($featuredProducts->count() > 0)
    <section class="py-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">{{ __('app.featured_products') }}</h2>
                <p class="text-gray-600">{{ __('app.handpicked_products_just_for_you') }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($featuredProducts as $product)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                    <div class="relative">
                        @if($product->mainImage)
                            <img src="{{ $product->mainImage->url }}" alt="{{ $product->name }}" class="w-full h-48 object-cover">
                        @else
                            <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        @endif
                        @if($product->is_on_sale)
                            <div class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded text-xs font-semibold">
                                Flash Sale
                            </div>
                        @endif
                        <div class="absolute top-2 right-2">
                            <button class="text-gray-400 hover:text-red-500 transition duration-300">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $product->name }}</h3>
                        <p class="text-sm text-gray-600 mb-3">{{ $product->category->name }}</p>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                @if($product->is_on_sale)
                                    <span class="text-lg font-bold text-primary-600">${{ number_format($product->final_price, 2) }}</span>
                                    <span class="text-sm text-gray-500 line-through">${{ number_format($product->price, 2) }}</span>
                                @else
                                    <span class="text-lg font-bold text-primary-600">${{ number_format($product->price, 2) }}</span>
                                @endif
                            </div>
                            <button class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition duration-300">
                                {{ __('app.add_to_cart') }}
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="text-center mt-8">
                <a href="{{ route('products.index') }}" class="bg-primary-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-primary-700 transition duration-300">
                    {{ __('app.view_all_products') }}
                </a>
            </div>
        </div>
    </section>
    @endif

    <!-- Banners -->
    @if($banners->count() > 0)
    <section class="py-16">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                @foreach($banners as $banner)
                <div class="relative h-64 rounded-lg overflow-hidden">
                    <img src="{{ $banner->image }}" alt="{{ $banner->title }}" class="w-full h-full object-cover">
                    <div class="absolute inset-0 bg-black bg-opacity-40"></div>
                    <div class="absolute bottom-4 left-4 right-4">
                        <h3 class="text-white text-xl font-semibold mb-2">{{ $banner->title }}</h3>
                        <p class="text-white text-sm mb-3">{{ $banner->description }}</p>
                        @if($banner->button_text)
                            <a href="{{ $banner->button_url }}" class="bg-white text-gray-900 px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-100 transition duration-300">
                                {{ $banner->button_text }}
                            </a>
                        @endif
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    <!-- Newsletter -->
    <section class="py-16 bg-primary-600 text-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-3xl font-bold mb-4">{{ __('app.stay_updated') }}</h2>
            <p class="text-primary-100 mb-8">{{ __('app.subscribe_newsletter_latest_products_exclusive_offers') }}</p>
            <form class="flex flex-col sm:flex-row gap-4 max-w-md mx-auto">
                <input type="email" placeholder="{{ __('app.enter_your_email') }}" 
                       class="flex-1 px-4 py-3 rounded-lg text-gray-900 focus:ring-2 focus:ring-white focus:outline-none">
                <button type="submit" class="bg-white text-primary-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition duration-300">
                    {{ __('app.subscribe') }}
                </button>
            </form>
        </div>
    </section>

    <!-- Flash Sales -->
    @if(isset($flashSaleProducts) && $flashSaleProducts->count() > 0)
    <section class="py-16 bg-yellow-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-yellow-700 mb-4">{{ __('app.flash_sales') }}</h2>
                <p class="text-yellow-800">{{ __('app.limited_time_offers') }}</p>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($flashSaleProducts as $product)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                    <div class="relative">
                        @if($product->mainImage)
                            <img src="{{ $product->mainImage->url }}" alt="{{ $product->name }}" class="w-full h-48 object-cover">
                        @else
                            <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                                <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                        @endif
                        <div class="absolute top-2 left-2 bg-red-600 text-white px-2 py-1 rounded text-xs font-semibold">
                            Flash Sale
                        </div>
                        <div class="absolute top-2 right-2 bg-black bg-opacity-70 text-white px-2 py-1 rounded text-xs font-semibold countdown" data-end="{{ $product->flash_sale_ends_at }}">
                            <span class="countdown-timer">--:--:--</span>
                        </div>
                    </div>
                    <div class="p-4">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ $product->name }}</h3>
                        <p class="text-sm text-gray-600 mb-3">{{ $product->category->name }}</p>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="text-lg font-bold text-primary-600">${{ number_format($product->final_price, 2) }}</span>
                                @if($product->sale_price)
                                    <span class="text-sm text-gray-500 line-through">${{ number_format($product->price, 2) }}</span>
                                @endif
                            </div>
                            <button class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition duration-300">
                                {{ __('app.add_to_cart') }}
                            </button>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    </section>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.countdown').forEach(function(el) {
            const end = new Date(el.getAttribute('data-end')).getTime();
            const timer = el.querySelector('.countdown-timer');
            function updateCountdown() {
                const now = new Date().getTime();
                let diff = Math.max(0, end - now);
                const hours = Math.floor(diff / (1000 * 60 * 60));
                const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((diff % (1000 * 60)) / 1000);
                timer.textContent = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                if (diff > 0) {
                    setTimeout(updateCountdown, 1000);
                } else {
                    timer.textContent = 'Ended';
                }
            }
            updateCountdown();
        });
    });
    </script>
    @endif
@endsection 