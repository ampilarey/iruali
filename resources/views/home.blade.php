@extends('layouts.app')

@section('content')
<!-- Hero Section -->
<section class="bg-gradient-to-r from-primary-50 to-accent-50 py-16">
    <div class="container mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-6xl font-bold text-gray-900 mb-6">
            Welcome to <span class="text-primary">iruali</span>
        </h1>
        <p class="text-xl text-gray-600 mb-8 max-w-2xl mx-auto">
            Your trusted source for premium products and expert support. Shop with confidence knowing you're getting the best deals and service.
        </p>
        <div class="flex flex-col sm:flex-row gap-4 justify-center">
            <a href="{{ route('shop') }}" class="bg-primary hover:bg-primary-600 text-white px-8 py-3 rounded-lg text-lg font-semibold transition-colors">
                Shop Now
            </a>
            <a href="{{ route('categories.index') }}" class="bg-white hover:bg-gray-50 text-primary border-2 border-primary px-8 py-3 rounded-lg text-lg font-semibold transition-colors">
                Browse Categories
            </a>
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="py-16 bg-white">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Featured Products</h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-8">
            <!-- Product 1: Headphones -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200 overflow-hidden">
                <div class="relative p-4 bg-gray-50">
                    <img src="/images/products/headphones.svg" 
                         alt="Wireless Bluetooth Headphones" 
                         class="w-full h-64 object-contain mx-auto block">
                    <div class="absolute top-2 left-2">
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full font-medium">Flash Sale</span>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 mb-2">Electronics</p>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Wireless Bluetooth Headphones</h3>
                    <p class="text-sm text-gray-600 mb-4">TechAudio</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-gray-900">$89.99</span>
                            <span class="text-lg text-gray-500 line-through">$129.99</span>
                        </div>
                        <span class="text-sm bg-red-100 text-red-600 px-2 py-1 rounded">31% OFF</span>
                    </div>
                    <button class="w-full bg-primary hover:bg-primary-600 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                        Add to Cart
                    </button>
                </div>
            </div>

            <!-- Product 2: T-Shirt -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200 overflow-hidden">
                <div class="relative p-4 bg-gray-50">
                    <img src="/images/products/denim-jacket.svg" 
                         alt="Premium Cotton T-Shirt" 
                         class="w-full h-64 object-contain mx-auto block">
                    <div class="absolute top-2 left-2">
                        <span class="bg-primary text-white text-xs px-2 py-1 rounded-full font-medium">New</span>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 mb-2">Fashion</p>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Premium Cotton T-Shirt</h3>
                    <p class="text-sm text-gray-600 mb-4">FashionCo</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-gray-900">$24.99</span>
                        </div>
                    </div>
                    <button class="w-full bg-primary hover:bg-primary-600 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                        Add to Cart
                    </button>
                </div>
            </div>

            <!-- Product 3: Smart Watch -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200 overflow-hidden">
                <div class="relative p-4 bg-gray-50">
                    <img src="/images/products/smartphone.svg" 
                         alt="Smart Fitness Watch" 
                         class="w-full h-64 object-contain mx-auto block">
                    <div class="absolute top-2 left-2">
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full font-medium">Flash Sale</span>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 mb-2">Electronics</p>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Smart Fitness Watch</h3>
                    <p class="text-sm text-gray-600 mb-4">FitTech</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-gray-900">$199.99</span>
                            <span class="text-lg text-gray-500 line-through">$299.99</span>
                        </div>
                        <span class="text-sm bg-red-100 text-red-600 px-2 py-1 rounded">33% OFF</span>
                    </div>
                    <button class="w-full bg-primary hover:bg-primary-600 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                        Add to Cart
                    </button>
                </div>
            </div>

            <!-- Product 4: Coffee Beans -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200 overflow-hidden">
                <div class="relative p-4 bg-gray-50">
                    <img src="/images/products/coffee-table.svg" 
                         alt="Organic Coffee Beans" 
                         class="w-full h-64 object-contain mx-auto block">
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 mb-2">Food & Beverage</p>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Organic Coffee Beans</h3>
                    <p class="text-sm text-gray-600 mb-4">BrewMaster</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-gray-900">$19.99</span>
                        </div>
                    </div>
                    <button class="w-full bg-primary hover:bg-primary-600 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                        Add to Cart
                    </button>
                </div>
            </div>

            <!-- Product 5: Camera Lens -->
            <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 border border-gray-200 overflow-hidden">
                <div class="relative p-4 bg-gray-50">
                    <img src="/images/products/laptop.svg" 
                         alt="Professional Camera Lens" 
                         class="w-full h-64 object-contain mx-auto block">
                    <div class="absolute top-2 left-2">
                        <span class="bg-red-500 text-white text-xs px-2 py-1 rounded-full font-medium">Flash Sale</span>
                    </div>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 mb-2">Electronics</p>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">Professional Camera Lens</h3>
                    <p class="text-sm text-gray-600 mb-4">PhotoPro</p>
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-2xl font-bold text-gray-900">$599.99</span>
                            <span class="text-lg text-gray-500 line-through">$799.99</span>
                        </div>
                        <span class="text-sm bg-red-100 text-red-600 px-2 py-1 rounded">25% OFF</span>
                    </div>
                    <button class="w-full bg-primary hover:bg-primary-600 text-white py-3 px-4 rounded-lg font-medium transition-colors">
                        Add to Cart
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-16 bg-gray-50">
    <div class="container mx-auto px-4">
        <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Why Choose iruali?</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Quality Guaranteed</h3>
                <p class="text-gray-600">All our products are carefully selected and tested for quality assurance.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">Fast Shipping</h3>
                <p class="text-gray-600">Quick and reliable shipping to get your products to you as soon as possible.</p>
            </div>
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a9.75 9.75 0 100 19.5 9.75 9.75 0 000-19.5z"></path>
                    </svg>
                </div>
                <h3 class="text-xl font-semibold text-gray-900 mb-2">24/7 Support</h3>
                <p class="text-gray-600">Our customer support team is always here to help you with any questions.</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="py-16 bg-primary">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">Ready to Start Shopping?</h2>
        <p class="text-xl text-primary-100 mb-8">Join thousands of satisfied customers who trust iruali for their shopping needs.</p>
        <a href="{{ route('shop') }}" class="bg-white hover:bg-gray-100 text-primary px-8 py-3 rounded-lg text-lg font-semibold transition-colors inline-block">
            Explore Products
        </a>
    </div>
</section>

<!-- Debug Info -->
<div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mx-4 mb-8">
    <h3 class="text-yellow-800 font-semibold mb-2">🔍 Debug Information</h3>
    <p class="text-yellow-700 text-sm mb-2">If you can't see the product images above, try these test pages:</p>
    <div class="flex flex-wrap gap-2">
        <a href="/test-standalone.html" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 px-3 py-1 rounded text-sm font-medium">
            Standalone Test
        </a>
        <a href="/test-images" class="bg-yellow-100 hover:bg-yellow-200 text-yellow-800 px-3 py-1 rounded text-sm font-medium">
            CSS Test
        </a>
    </div>
</div>
@endsection 