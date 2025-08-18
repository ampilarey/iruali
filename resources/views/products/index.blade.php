@extends('layouts.app')

@section('content')
<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Page Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-dark mb-2">All Products</h1>
            <p class="text-gray-600">Discover our complete collection of products</p>
        </div>

        <div class="flex flex-col lg:flex-row gap-8">
            <!-- Sidebar Filters -->
            <div class="lg:w-1/4">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 sticky top-24">
                    <h2 class="text-lg font-semibold text-dark mb-4">Filters</h2>
                    
                    <!-- Search Filter -->
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-dark mb-3">Search</h3>
                        <input type="text" placeholder="Search products..." 
                               class="w-full px-3 py-2 border border-gray-200 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-sm">
                    </div>

                    <!-- Category Filter -->
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-dark mb-3">Category</h3>
                        <div class="space-y-2">
                            @foreach($categories ?? [] as $category)
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-700">{{ $category->name }}</span>
                                    <span class="ml-auto text-xs text-gray-500">({{ $category->products_count ?? 0 }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Price Range Filter -->
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-dark mb-3">Price Range</h3>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="price" class="text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">Under $25</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="price" class="text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">$25 - $50</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="price" class="text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">$50 - $100</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="price" class="text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">$100 - $250</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="price" class="text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">Over $250</span>
                            </label>
                        </div>
                    </div>

                    <!-- Brand Filter -->
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-dark mb-3">Brand</h3>
                        <div class="space-y-2">
                            @php
                                $brands = ['Apple', 'Samsung', 'Sony', 'LG', 'Nike', 'Adidas', 'Canon', 'Nikon'];
                            @endphp
                            @foreach($brands as $brand)
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    <span class="ml-2 text-sm text-gray-700">{{ $brand }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Rating Filter -->
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-dark mb-3">Rating</h3>
                        <div class="space-y-2">
                            @for($i = 5; $i >= 1; $i--)
                                <label class="flex items-center">
                                    <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary">
                                    <div class="ml-2 flex items-center">
                                        @for($j = 1; $j <= 5; $j++)
                                            <svg class="w-3 h-3 {{ $j <= $i ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                            </svg>
                                        @endfor
                                        <span class="ml-1 text-sm text-gray-700">& Up</span>
                                    </div>
                                </label>
                            @endfor
                        </div>
                    </div>

                    <!-- Availability Filter -->
                    <div class="mb-6">
                        <h3 class="text-sm font-medium text-dark mb-3">Availability</h3>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">In Stock</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">On Sale</span>
                            </label>
                            <label class="flex items-center">
                                <input type="checkbox" class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">New Arrivals</span>
                            </label>
                        </div>
                    </div>

                    <!-- Clear Filters -->
                    <button class="w-full bg-gray-100 hover:bg-gray-200 text-dark py-2 px-4 rounded-lg text-sm font-medium transition-colors">
                        Clear All Filters
                    </button>
                </div>
            </div>

            <!-- Main Content -->
            <div class="lg:w-3/4">
                <!-- Toolbar -->
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-4 mb-6">
                    <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-gray-600">{{ $products->total() ?? 0 }} products found</span>
                            <div class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">View:</span>
                                <button class="p-1 text-primary" title="Grid View">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5zM5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5zM11 5a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V5zM11 13a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                                    </svg>
                                </button>
                                <button class="p-1 text-gray-400 hover:text-primary" title="List View">
                                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <label class="flex items-center space-x-2">
                                <span class="text-sm text-gray-600">Sort by:</span>
                                <select class="border border-gray-200 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-primary focus:border-primary">
                                    <option>Featured</option>
                                    <option>Price: Low to High</option>
                                    <option>Price: High to Low</option>
                                    <option>Newest First</option>
                                    <option>Best Rating</option>
                                    <option>Most Popular</option>
                                </select>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                    @if(isset($products) && $products->count() > 0)
                        @foreach($products as $product)
                            <x-product-card :product="$product" />
                        @endforeach
                    @else
                        <!-- Fallback products -->
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
                                    'category' => (object)['name' => 'Electronics']
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
                                    'category' => (object)['name' => 'Fashion']
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
                                    'category' => (object)['name' => 'Electronics']
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
                                    'category' => (object)['name' => 'Food & Beverage']
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
                                    'category' => (object)['name' => 'Electronics']
                                ],
                                [
                                    'name' => 'Leather Wallet',
                                    'price' => 49.99,
                                    'original_price' => 79.99,
                                    'brand' => 'LeatherCraft',
                                    'average_rating' => 4.3,
                                    'reviews_count' => 156,
                                    'stock_quantity' => 30,
                                    'flash_sale' => true,
                                    'category' => (object)['name' => 'Fashion']
                                ],
                                [
                                    'name' => 'Wireless Mouse',
                                    'price' => 29.99,
                                    'original_price' => null,
                                    'brand' => 'TechGear',
                                    'average_rating' => 4.1,
                                    'reviews_count' => 203,
                                    'stock_quantity' => 75,
                                    'flash_sale' => false,
                                    'category' => (object)['name' => 'Electronics']
                                ],
                                [
                                    'name' => 'Yoga Mat',
                                    'price' => 34.99,
                                    'original_price' => 49.99,
                                    'brand' => 'FitLife',
                                    'average_rating' => 4.6,
                                    'reviews_count' => 189,
                                    'stock_quantity' => 45,
                                    'flash_sale' => true,
                                    'category' => (object)['name' => 'Sports']
                                ]
                            ];
                        @endphp
                        
                        @foreach($fallbackProducts as $product)
                            <div class="group bg-white rounded-lg shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100 overflow-hidden">
                                <div class="relative aspect-square overflow-hidden bg-gray-50">
                                    <img src="https://via.placeholder.com/400x400?text=Product" 
                                         alt="{{ $product['name'] }}" 
                                         class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
                                    
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

                <!-- Pagination -->
                @if(isset($products) && $products->hasPages())
                    <div class="mt-8">
                        {{ $products->links() }}
                    </div>
                @else
                    <!-- Fallback pagination -->
                    <div class="mt-8 flex justify-center">
                        <nav class="flex items-center space-x-2">
                            <a href="#" class="px-3 py-2 text-sm text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Previous
                            </a>
                            <a href="#" class="px-3 py-2 text-sm text-white bg-primary border border-primary rounded-lg">1</a>
                            <a href="#" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">2</a>
                            <a href="#" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">3</a>
                            <span class="px-3 py-2 text-sm text-gray-500">...</span>
                            <a href="#" class="px-3 py-2 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">10</a>
                            <a href="#" class="px-3 py-2 text-sm text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 transition-colors">
                                Next
                            </a>
                        </nav>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection 