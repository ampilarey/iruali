@extends('layouts.app')

@section('content')
<!-- Hero Banner Section -->
<section class="relative bg-gradient-to-r from-primary/10 to-accent/10 py-8 lg:py-12">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 items-center">
            <div>
                <h1 class="text-4xl lg:text-6xl font-bold text-dark mb-4">
                    Discover Amazing
                    <span class="text-primary">Products</span>
                </h1>
                <p class="text-lg text-gray-600 mb-6">Shop the latest trends with expert support and fast shipping. Find everything you need in one place.</p>
                <div class="flex flex-col sm:flex-row gap-4">
                    <a href="{{ route('shop') }}" class="bg-primary hover:bg-primary/90 text-white px-8 py-3 rounded-lg font-medium transition-colors text-center">
                        Shop Now
                    </a>
                    <a href="{{ route('products.index') }}" class="border border-primary text-primary hover:bg-primary hover:text-white px-8 py-3 rounded-lg font-medium transition-colors text-center">
                        View All Products
                    </a>
                </div>
            </div>
            <div class="relative">
                <img src="/images/hero-placeholder.svg" alt="Featured Products" class="rounded-lg shadow-lg">
                <div class="absolute -bottom-4 -left-4 bg-white rounded-lg shadow-lg p-4">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 bg-primary/10 rounded-full flex items-center justify-center">
                            <svg class="w-5 h-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-dark">Free Shipping</p>
                            <p class="text-xs text-gray-500">On orders over $49</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Featured Categories Section -->
<section class="py-12 bg-white">
    <div class="container mx-auto px-4">
        <div class="text-center mb-12">
            <h2 class="text-3xl font-bold text-dark mb-4">Shop by Category</h2>
            <p class="text-gray-600 max-w-2xl mx-auto">Explore our wide range of categories and find exactly what you're looking for</p>
        </div>
        
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-6">
            @foreach($categories ?? [] as $category)
                <x-category-card :category="$category" />
            @endforeach
            
            <!-- Fallback categories if none exist -->
            @if(empty($categories))
                @php
                    $fallbackCategories = [
                        ['name' => 'Electronics', 'products_count' => 150, 'description' => 'Latest gadgets and tech'],
                        ['name' => 'Fashion', 'products_count' => 200, 'description' => 'Trendy clothing and accessories'],
                        ['name' => 'Home & Garden', 'products_count' => 120, 'description' => 'Everything for your home'],
                        ['name' => 'Sports', 'products_count' => 80, 'description' => 'Equipment and activewear'],
                        ['name' => 'Books', 'products_count' => 300, 'description' => 'Knowledge and entertainment'],
                        ['name' => 'Beauty', 'products_count' => 100, 'description' => 'Health and beauty products']
                    ];
                @endphp
                
                @foreach($fallbackCategories as $category)
                    <div class="group bg-white rounded-lg shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100 overflow-hidden">
                        <div class="relative aspect-square overflow-hidden bg-gradient-to-br from-primary/10 to-accent/10">
                            <div class="w-full h-full flex items-center justify-center">
                                <svg class="w-12 h-12 text-primary group-hover:scale-110 transition-transform duration-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="p-4 text-center">
                            <h3 class="font-semibold text-dark text-lg mb-2 group-hover:text-primary transition-colors">
                                {{ $category['name'] }}
                            </h3>
                            <p class="text-gray-600 text-sm mb-3">{{ $category['description'] }}</p>
                            <div class="text-sm text-gray-500">
                                {{ $category['products_count'] }} Products
                            </div>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

<!-- Promotional Banner Section -->
<section class="py-12 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            @if(isset($banners) && $banners->count() > 0)
                @foreach($banners->take(2) as $banner)
                    <x-banner :banner="$banner" />
                @endforeach
            @else
                <!-- Fallback promotional banners -->
                <div class="relative overflow-hidden rounded-lg shadow-lg bg-gradient-to-r from-primary to-accent">
                    <div class="absolute inset-0 bg-black/20"></div>
                    <div class="relative p-8 text-white">
                        <p class="text-sm font-medium text-primary-200 mb-2">Limited Time Offer</p>
                        <h2 class="text-2xl md:text-3xl font-bold mb-3">Flash Sale - Up to 50% Off</h2>
                        <p class="text-sm md:text-base text-gray-200 mb-4">Don't miss out on incredible deals on selected products. Limited time only!</p>
                        <a href="{{ route('shop') }}" class="inline-flex items-center px-6 py-3 bg-white text-primary font-medium rounded-lg hover:bg-gray-100 transition-colors">
                            Shop Now
                            <svg class="w-3 h-3 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
                
                <div class="relative overflow-hidden rounded-lg shadow-lg bg-gradient-to-r from-accent to-primary">
                    <div class="absolute inset-0 bg-black/20"></div>
                    <div class="relative p-8 text-white">
                        <p class="text-sm font-medium text-accent-200 mb-2">New Arrivals</p>
                        <h2 class="text-2xl md:text-3xl font-bold mb-3">Latest Products Just In</h2>
                        <p class="text-sm md:text-base text-gray-200 mb-4">Discover the newest additions to our collection. Fresh styles and innovative products.</p>
                        <a href="{{ route('products.index') }}" class="inline-flex items-center px-6 py-3 bg-white text-accent font-medium rounded-lg hover:bg-gray-100 transition-colors">
                            Explore
                            <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>
</section>

<!-- Featured Products Section -->
<section class="py-12 bg-white">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between mb-8">
            <div>
                <h2 class="text-3xl font-bold text-dark mb-2">Featured Products</h2>
                <p class="text-gray-600">Handpicked products you'll love</p>
            </div>
            <a href="{{ route('products.index') }}" class="text-primary hover:text-primary/80 font-medium transition-colors">
                View All Products →
            </a>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-6">
            @if(isset($featuredProducts) && $featuredProducts->count() > 0)
                @foreach($featuredProducts as $product)
                    <x-product-card :product="$product" />
                @endforeach
            @else
                <!-- Fallback featured products -->
                @php
                    $fallbackProducts = [
                        [
                            'name' => 'Wireless Bluetooth Headphones',
                            'price' => 89.99,
                            'original_price' => 129.99,
                            'brand' => 'TechAudio',
                            'average_rating' => 4.5,
                            'reviews_count' => 128,
                            'stock_quantity' => 25,
                            'flash_sale' => true,
                            'category' => (object)['name' => 'Electronics'],
                            'image' => '/images/products/headphones.svg'
                        ],
                        [
                            'name' => 'Premium Cotton T-Shirt',
                            'price' => 24.99,
                            'original_price' => null,
                            'brand' => 'FashionCo',
                            'average_rating' => 4.2,
                            'reviews_count' => 89,
                            'stock_quantity' => 50,
                            'flash_sale' => false,
                            'is_new' => true,
                            'category' => (object)['name' => 'Fashion'],
                            'image' => '/images/products/denim-jacket.svg'
                        ],
                        [
                            'name' => 'Smart Fitness Watch',
                            'price' => 199.99,
                            'original_price' => 299.99,
                            'brand' => 'FitTech',
                            'average_rating' => 4.7,
                            'reviews_count' => 256,
                            'stock_quantity' => 15,
                            'flash_sale' => true,
                            'category' => (object)['name' => 'Electronics'],
                            'image' => '/images/products/smartphone.svg'
                        ],
                        [
                            'name' => 'Organic Coffee Beans',
                            'price' => 19.99,
                            'original_price' => null,
                            'brand' => 'BrewMaster',
                            'average_rating' => 4.8,
                            'reviews_count' => 342,
                            'stock_quantity' => 100,
                            'flash_sale' => false,
                            'category' => (object)['name' => 'Food & Beverage'],
                            'image' => '/images/products/coffee-table.svg'
                        ],
                        [
                            'name' => 'Professional Camera Lens',
                            'price' => 599.99,
                            'original_price' => 799.99,
                            'brand' => 'PhotoPro',
                            'average_rating' => 4.9,
                            'reviews_count' => 67,
                            'stock_quantity' => 8,
                            'flash_sale' => true,
                            'category' => (object)['name' => 'Electronics'],
                            'image' => '/images/products/camera.svg'
                        ]
                    ];
                @endphp
                
                @foreach($fallbackProducts as $product)
                    <div class="group bg-white rounded-lg shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100 overflow-hidden">
                        <div class="relative aspect-square overflow-hidden bg-gray-50">
                            <img src="{{ $product['image'] ?? '/images/product-placeholder.svg' }}" alt="{{ $product['name'] }}" class="w-full h-48 object-cover rounded-lg">
                            
                            <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-300 flex items-center justify-center">
                                <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex space-x-2">
                                    <button class="w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center hover:bg-primary hover:text-white transition-colors" title="Quick View">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                    </button>
                                    <button class="w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center hover:bg-primary hover:text-white transition-colors" title="Add to Wishlist">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div class="absolute top-2 left-2 flex flex-col space-y-1">
                                @if($product['flash_sale'])
                                    <span class="bg-danger text-white text-xs px-2 py-1 rounded-full font-medium">Flash Sale</span>
                                @endif
                                @if(isset($product['is_new']) && $product['is_new'])
                                    <span class="bg-primary text-white text-xs px-2 py-1 rounded-full font-medium">New</span>
                                @endif
                            </div>

                            <div class="absolute top-2 right-2">
                                @if($product['stock_quantity'] > 0)
                                    <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full font-medium">In Stock</span>
                                @else
                                    <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full font-medium">Out of Stock</span>
                                @endif
                            </div>
                        </div>

                        <div class="p-4">
                            <p class="text-xs text-gray-500 mb-1">{{ $product['category']->name }}</p>
                            <h3 class="font-medium text-dark text-sm mb-2 line-clamp-2 group-hover:text-primary transition-colors">
                                {{ $product['name'] }}
                            </h3>
                            <p class="text-xs text-gray-600 mb-2">{{ $product['brand'] }}</p>
                            
                            <div class="flex items-center mb-2">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3 h-3 {{ $i <= $product['average_rating'] ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-xs text-gray-500 ml-1">({{ $product['reviews_count'] }})</span>
                            </div>

                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    @if($product['original_price'] && $product['original_price'] > $product['price'])
                                        <span class="text-lg font-bold text-dark">${{ number_format($product['price'], 2) }}</span>
                                        <span class="text-sm text-gray-500 line-through">${{ number_format($product['original_price'], 2) }}</span>
                                        <span class="text-xs bg-red-100 text-red-600 px-1 py-0.5 rounded">
                                            {{ round((($product['original_price'] - $product['price']) / $product['original_price']) * 100) }}% OFF
                                        </span>
                                    @else
                                        <span class="text-lg font-bold text-dark">${{ number_format($product['price'], 2) }}</span>
                                    @endif
                                </div>
                            </div>

                            <button class="w-full bg-primary hover:bg-primary/90 text-white py-2 px-4 rounded-lg text-sm font-medium transition-colors flex items-center justify-center space-x-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                                </svg>
                                <span>Add to Cart</span>
                            </button>
                        </div>
                    </div>
                @endforeach
            @endif
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="py-12 bg-gray-50">
    <div class="container mx-auto px-4">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="text-center">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-dark mb-2">Free Shipping</h3>
                <p class="text-gray-600 text-sm">Free shipping on orders over $49</p>
            </div>
            
            <div class="text-center">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-dark mb-2">Quality Guarantee</h3>
                <p class="text-gray-600 text-sm">30-day money back guarantee</p>
            </div>
            
            <div class="text-center">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636l-3.536 3.536m0 5.656l3.536 3.536M9.172 9.172L5.636 5.636m3.536 9.192L5.636 18.364M12 2.25a9.75 9.75 0 109.75 9.75A9.75 9.75 0 0012 2.25z"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-dark mb-2">24/7 Support</h3>
                <p class="text-gray-600 text-sm">Expert support available anytime</p>
            </div>
            
            <div class="text-center">
                <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1"></path>
                    </svg>
                </div>
                <h3 class="text-lg font-semibold text-dark mb-2">Secure Payment</h3>
                <p class="text-gray-600 text-sm">100% secure payment processing</p>
            </div>
        </div>
    </div>
</section>

<!-- Newsletter Section -->
<section class="py-12 bg-primary">
    <div class="container mx-auto px-4 text-center">
        <h2 class="text-3xl font-bold text-white mb-4">Stay Updated</h2>
        <p class="text-primary-100 mb-8 max-w-2xl mx-auto">Subscribe to our newsletter for the latest products, exclusive deals, and updates delivered straight to your inbox.</p>
        <form class="max-w-md mx-auto flex gap-4">
            <input type="email" placeholder="Enter your email address" 
                   class="flex-1 px-4 py-3 rounded-lg border-0 focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-primary text-dark">
            <button type="submit" class="bg-white text-primary hover:bg-gray-100 px-6 py-3 rounded-lg font-medium transition-colors">
                Subscribe
            </button>
        </form>
    </div>
</section>
@endsection 