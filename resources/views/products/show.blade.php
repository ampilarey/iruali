@extends('layouts.app')

@section('content')
<div class="bg-gray-50 min-h-screen py-8">
    <div class="container mx-auto px-4">
        <!-- Breadcrumb -->
        <nav class="mb-6">
            <ol class="flex items-center space-x-2 text-sm text-gray-600">
                <li><a href="{{ route('home') }}" class="hover:text-primary transition-colors">Home</a></li>
                <li><span class="mx-2">/</span></li>
                <li><a href="{{ route('products.index') }}" class="hover:text-primary transition-colors">Products</a></li>
                <li><span class="mx-2">/</span></li>
                @if($product->category)
                    <li><a href="{{ route('categories.show', $product->category) }}" class="hover:text-primary transition-colors">{{ $product->category->name }}</a></li>
                    <li><span class="mx-2">/</span></li>
                @endif
                <li class="text-dark font-medium">{{ $product->name }}</li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-12">
            <!-- Product Images -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <div class="relative">
                    <!-- Main Image -->
                    @php
                        $mainImageUrl = $product->featured_image ?? 
                                       ($product->images && $product->images->count() > 0 ? $product->images->first()->url : null) ?? 
                                       '/images/product-detail-placeholder.svg';
                    @endphp
                    <div class="aspect-square overflow-hidden rounded-lg bg-gray-50 mb-4">
                        <img id="main-image" 
                             src="{{ $mainImageUrl }}" 
                             alt="{{ $product->name }}" 
                             class="w-full h-full object-cover">
                    </div>

                    <!-- Thumbnail Images -->
                    @if($product->images && $product->images->count() > 1)
                        <div class="grid grid-cols-4 gap-2">
                            @foreach($product->images->take(4) as $image)
                                <button onclick="changeMainImage('{{ $image->url }}')" 
                                        class="aspect-square overflow-hidden rounded-lg border-2 border-gray-200 hover:border-primary transition-colors">
                                    <img src="{{ $image->url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <!-- Zoom Indicator -->
                    <div class="absolute top-4 right-4 bg-white/80 backdrop-blur-sm rounded-full p-2">
                        <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- Product Info -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <!-- Product Header -->
                <div class="mb-6">
                    @if($product->category)
                        <p class="text-sm text-primary font-medium mb-2">{{ $product->category->name }}</p>
                    @endif
                    <h1 class="text-2xl lg:text-3xl font-bold text-dark mb-2">{{ $product->name }}</h1>
                    @if($product->brand)
                        <p class="text-lg text-gray-600 mb-2">by {{ $product->brand }}</p>
                    @endif
                    <p class="text-sm text-gray-500 mb-4">SKU: {{ $product->sku ?? 'N/A' }}</p>
                </div>

                <!-- Rating -->
                <div class="flex items-center mb-4">
                    <div class="flex items-center">
                        @for($i = 1; $i <= 5; $i++)
                            <svg class="w-5 h-5 {{ $i <= ($product->average_rating ?? 0) ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                            </svg>
                        @endfor
                    </div>
                    <span class="text-sm text-gray-600 ml-2">({{ $product->reviews_count ?? 0 }} reviews)</span>
                    <a href="#reviews" class="text-sm text-primary hover:text-primary/80 ml-4">Write a review</a>
                </div>

                <!-- Price -->
                <div class="mb-6">
                    <div class="flex items-center space-x-3">
                        @if($product->original_price && $product->original_price > $product->price)
                            <span class="text-3xl font-bold text-dark">${{ number_format($product->price, 2) }}</span>
                            <span class="text-xl text-gray-500 line-through">${{ number_format($product->original_price, 2) }}</span>
                            <span class="bg-red-100 text-red-600 px-2 py-1 rounded text-sm font-medium">
                                {{ round((($product->original_price - $product->price) / $product->original_price) * 100) }}% OFF
                            </span>
                        @else
                            <span class="text-3xl font-bold text-dark">${{ number_format($product->price, 2) }}</span>
                        @endif
                    </div>
                </div>

                <!-- Stock Status -->
                <div class="mb-6">
                    @if($product->stock_quantity > 0)
                        <div class="flex items-center text-green-600">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="font-medium">In Stock</span>
                            <span class="text-sm text-gray-600 ml-2">({{ $product->stock_quantity }} available)</span>
                        </div>
                    @else
                        <div class="flex items-center text-red-600">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span class="font-medium">Out of Stock</span>
                        </div>
                    @endif
                </div>

                <!-- Quantity Selector -->
                <div class="mb-6">
                    <label class="block text-sm font-medium text-dark mb-2">Quantity</label>
                    <div class="flex items-center space-x-3">
                        <button onclick="decreaseQuantity()" class="w-10 h-10 border border-gray-300 rounded-lg flex items-center justify-center hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                            </svg>
                        </button>
                        <input type="number" id="quantity" value="1" min="1" max="{{ $product->stock_quantity }}" 
                               class="w-20 text-center border border-gray-300 rounded-lg py-2 focus:ring-2 focus:ring-primary focus:border-primary">
                        <button onclick="increaseQuantity()" class="w-10 h-10 border border-gray-300 rounded-lg flex items-center justify-center hover:bg-gray-50 transition-colors">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="space-y-3 mb-6">
                    <button class="w-full bg-primary hover:bg-primary/90 text-white py-3 px-6 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
                        </svg>
                        <span>Add to Cart</span>
                    </button>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <button class="border border-primary text-primary hover:bg-primary hover:text-white py-3 px-6 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                            </svg>
                            <span>Wishlist</span>
                        </button>
                        <button class="border border-gray-300 text-dark hover:bg-gray-50 py-3 px-6 rounded-lg font-medium transition-colors flex items-center justify-center space-x-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.367 2.684 3 3 0 00-5.367-2.684z"></path>
                            </svg>
                            <span>Compare</span>
                        </button>
                    </div>
                </div>

                <!-- Product Highlights -->
                <div class="border-t border-gray-200 pt-6">
                    <h3 class="text-lg font-semibold text-dark mb-3">Product Highlights</h3>
                    <ul class="space-y-2 text-sm text-gray-600">
                        <li class="flex items-start">
                            <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>High-quality materials and construction</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>30-day money-back guarantee</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Free shipping on orders over $49</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Expert customer support</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Product Tabs -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-12">
            <!-- Tab Navigation -->
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6">
                    <button onclick="showTab('description')" id="tab-description" class="tab-button active py-4 px-1 border-b-2 border-primary text-primary font-medium text-sm">
                        Description
                    </button>
                    <button onclick="showTab('specifications')" id="tab-specifications" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm">
                        Specifications
                    </button>
                    <button onclick="showTab('reviews')" id="tab-reviews" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm">
                        Reviews ({{ $product->reviews_count ?? 0 }})
                    </button>
                    <button onclick="showTab('shipping')" id="tab-shipping" class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium text-sm">
                        Shipping & Returns
                    </button>
                </nav>
            </div>

            <!-- Tab Content -->
            <div class="p-6">
                <!-- Description Tab -->
                <div id="content-description" class="tab-content">
                    <h3 class="text-lg font-semibold text-dark mb-4">Product Description</h3>
                    <div class="prose max-w-none text-gray-600">
                        {!! $product->description ?? '<p>No description available for this product.</p>' !!}
                    </div>
                </div>

                <!-- Specifications Tab -->
                <div id="content-specifications" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-dark mb-4">Technical Specifications</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="text-gray-600">Brand</span>
                                <span class="font-medium">{{ $product->brand ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="text-gray-600">SKU</span>
                                <span class="font-medium">{{ $product->sku ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="text-gray-600">Weight</span>
                                <span class="font-medium">{{ $product->weight ?? 'N/A' }}</span>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="text-gray-600">Dimensions</span>
                                <span class="font-medium">{{ $product->dimensions ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="text-gray-600">Material</span>
                                <span class="font-medium">{{ $product->material ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between py-2 border-b border-gray-100">
                                <span class="text-gray-600">Warranty</span>
                                <span class="font-medium">{{ $product->warranty ?? '1 Year' }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Reviews Tab -->
                <div id="content-reviews" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-dark mb-4">Customer Reviews</h3>
                    @if($product->reviews && $product->reviews->count() > 0)
                        <div class="space-y-6">
                            @foreach($product->reviews as $review)
                                <div class="border-b border-gray-200 pb-6 last:border-b-0">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center">
                                            <div class="flex items-center">
                                                @for($i = 1; $i <= 5; $i++)
                                                    <svg class="w-4 h-4 {{ $i <= $review->rating ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                                    </svg>
                                                @endfor
                                            </div>
                                            <span class="ml-2 text-sm text-gray-600">{{ $review->user->name ?? 'Anonymous' }}</span>
                                        </div>
                                        <span class="text-sm text-gray-500">{{ $review->created_at->format('M d, Y') }}</span>
                                    </div>
                                    <h4 class="font-medium text-dark mb-1">{{ $review->title }}</h4>
                                    <p class="text-gray-600">{{ $review->comment }}</p>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-600">No reviews yet. Be the first to review this product!</p>
                    @endif
                </div>

                <!-- Shipping Tab -->
                <div id="content-shipping" class="tab-content hidden">
                    <h3 class="text-lg font-semibold text-dark mb-4">Shipping & Returns</h3>
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-medium text-dark mb-2">Shipping Information</h4>
                            <ul class="space-y-2 text-gray-600">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Free shipping on orders over $49</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Standard shipping: 3-5 business days</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Express shipping: 1-2 business days</span>
                                </li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="font-medium text-dark mb-2">Return Policy</h4>
                            <ul class="space-y-2 text-gray-600">
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>30-day money-back guarantee</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Free returns for defective items</span>
                                </li>
                                <li class="flex items-start">
                                    <svg class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Items must be in original condition</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Related Products -->
        <div class="mb-12">
            <h2 class="text-2xl font-bold text-dark mb-6">Related Products</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
                @php
                    $relatedProducts = [
                        [
                            'name' => 'Similar Product 1',
                            'price' => 79.99,
                            'original_price' => 99.99,
                            'brand' => 'BrandName',
                            'average_rating' => 4.3,
                            'reviews_count' => 45,
                            'stock_quantity' => 20,
                            'flash_sale' => false,
                            'category' => (object)['name' => 'Electronics']
                        ],
                        [
                            'name' => 'Similar Product 2',
                            'price' => 129.99,
                            'original_price' => null,
                            'brand' => 'BrandName',
                            'average_rating' => 4.7,
                            'reviews_count' => 89,
                            'stock_quantity' => 15,
                            'flash_sale' => true,
                            'category' => (object)['name' => 'Electronics']
                        ],
                        [
                            'name' => 'Similar Product 3',
                            'price' => 59.99,
                            'original_price' => 79.99,
                            'brand' => 'BrandName',
                            'average_rating' => 4.1,
                            'reviews_count' => 32,
                            'stock_quantity' => 30,
                            'flash_sale' => true,
                            'category' => (object)['name' => 'Electronics']
                        ],
                        [
                            'name' => 'Similar Product 4',
                            'price' => 199.99,
                            'original_price' => null,
                            'brand' => 'BrandName',
                            'average_rating' => 4.8,
                            'reviews_count' => 156,
                            'stock_quantity' => 8,
                            'flash_sale' => false,
                            'category' => (object)['name' => 'Electronics']
                        ]
                    ];
                @endphp
                
                @foreach($relatedProducts as $relatedProduct)
                    <div class="group bg-white rounded-lg shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100 overflow-hidden">
                        <div class="relative aspect-square overflow-hidden bg-gray-50">
                            <img src="/images/product-placeholder.svg" 
                                 alt="{{ $relatedProduct['name'] }}" 
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
                                @if($relatedProduct['flash_sale'])
                                    <span class="bg-danger text-white text-xs px-2 py-1 rounded-full font-medium">Flash Sale</span>
                                @endif
                            </div>

                            <div class="absolute top-2 right-2">
                                @if($relatedProduct['stock_quantity'] > 0)
                                    <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full font-medium">In Stock</span>
                                @else
                                    <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full font-medium">Out of Stock</span>
                                @endif
                            </div>
                        </div>

                        <div class="p-4">
                            <p class="text-xs text-gray-500 mb-1">{{ $relatedProduct['category']->name }}</p>
                            <h3 class="font-medium text-dark text-sm mb-2 line-clamp-2 group-hover:text-primary transition-colors">
                                {{ $relatedProduct['name'] }}
                            </h3>
                            <p class="text-xs text-gray-600 mb-2">{{ $relatedProduct['brand'] }}</p>
                            
                            <div class="flex items-center mb-2">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                        <svg class="w-3 h-3 {{ $i <= $relatedProduct['average_rating'] ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                        </svg>
                                    @endfor
                                </div>
                                <span class="text-xs text-gray-500 ml-1">({{ $relatedProduct['reviews_count'] }})</span>
                            </div>

                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    @if($relatedProduct['original_price'] && $relatedProduct['original_price'] > $relatedProduct['price'])
                                        <span class="text-lg font-bold text-dark">${{ number_format($relatedProduct['price'], 2) }}</span>
                                        <span class="text-sm text-gray-500 line-through">${{ number_format($relatedProduct['original_price'], 2) }}</span>
                                        <span class="text-xs bg-red-100 text-red-600 px-1 py-0.5 rounded">
                                            {{ round((($relatedProduct['original_price'] - $relatedProduct['price']) / $relatedProduct['original_price']) * 100) }}% OFF
                                        </span>
                                    @else
                                        <span class="text-lg font-bold text-dark">${{ number_format($relatedProduct['price'], 2) }}</span>
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
            </div>
        </div>
    </div>
</div>

<script>
function changeMainImage(src) {
    document.getElementById('main-image').src = src;
}

function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(content => content.classList.add('hidden'));
    
    // Remove active class from all tab buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active', 'border-primary', 'text-primary');
        button.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Show selected tab content
    document.getElementById('content-' + tabName).classList.remove('hidden');
    
    // Add active class to selected tab button
    const activeButton = document.getElementById('tab-' + tabName);
    activeButton.classList.add('active', 'border-primary', 'text-primary');
    activeButton.classList.remove('border-transparent', 'text-gray-500');
}

function increaseQuantity() {
    const quantityInput = document.getElementById('quantity');
    const max = parseInt(quantityInput.getAttribute('max'));
    const currentValue = parseInt(quantityInput.value);
    if (currentValue < max) {
        quantityInput.value = currentValue + 1;
    }
}

function decreaseQuantity() {
    const quantityInput = document.getElementById('quantity');
    const currentValue = parseInt(quantityInput.value);
    if (currentValue > 1) {
        quantityInput.value = currentValue - 1;
    }
}
</script>
@endsection 