@props(['product'])

<div class="group bg-white rounded-lg shadow-sm hover:shadow-lg transition-all duration-300 border border-gray-100 overflow-hidden">
    <!-- Product Image -->
    <div class="relative aspect-square overflow-hidden bg-gray-50">
        @php
            $imageUrl = $product->featured_image ?? 
                       ($product->images && $product->images->count() > 0 ? $product->images->first()->url : null) ?? 
                       '/images/product-placeholder.svg';
        @endphp
        <img src="{{ $imageUrl }}" 
             alt="{{ $product->name }}" 
             class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
        
        <!-- Quick Actions Overlay -->
        <div class="absolute inset-0 bg-black bg-opacity-0 group-hover:bg-opacity-10 transition-all duration-300 flex items-center justify-center">
            <div class="opacity-0 group-hover:opacity-100 transition-opacity duration-300 flex space-x-2">
                <button class="w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center hover:bg-primary hover:text-white transition-colors" 
                        title="Quick View">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                    </svg>
                </button>
                <button class="w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center hover:bg-primary hover:text-white transition-colors" 
                        title="Add to Wishlist">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Badges -->
        <div class="absolute top-2 left-2 flex flex-col space-y-1">
            @if($product->flash_sale)
                <span class="bg-danger text-white text-xs px-2 py-1 rounded-full font-medium">Flash Sale</span>
            @endif
            @if($product->is_new)
                <span class="bg-primary text-white text-xs px-2 py-1 rounded-full font-medium">New</span>
            @endif
        </div>

        <!-- Stock Status -->
        <div class="absolute top-2 right-2">
            @if($product->stock_quantity > 0)
                <span class="bg-green-500 text-white text-xs px-2 py-1 rounded-full font-medium">In Stock</span>
            @else
                <span class="bg-gray-500 text-white text-xs px-2 py-1 rounded-full font-medium">Out of Stock</span>
            @endif
        </div>
    </div>

    <!-- Product Info -->
    <div class="p-4">
        <!-- Category -->
        @if($product->category)
            <p class="text-xs text-gray-500 mb-1">{{ $product->category->name }}</p>
        @endif

        <!-- Product Name -->
        <h3 class="font-medium text-dark text-sm mb-2 line-clamp-2 group-hover:text-primary transition-colors">
            <a href="{{ route('products.show', $product->slug) }}" class="hover:underline">
                {{ $product->name }}
            </a>
        </h3>

        <!-- Brand -->
        @if($product->brand)
            <p class="text-xs text-gray-600 mb-2">{{ $product->brand }}</p>
        @endif

        <!-- Rating -->
        <div class="flex items-center mb-2">
            <div class="flex items-center">
                @for($i = 1; $i <= 5; $i++)
                    <svg class="w-3 h-3 {{ $i <= ($product->average_rating ?? 0) ? 'text-yellow-400' : 'text-gray-300' }}" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                    </svg>
                @endfor
            </div>
            <span class="text-xs text-gray-500 ml-1">({{ $product->reviews_count ?? 0 }})</span>
        </div>

        <!-- Price -->
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-2">
                @if($product->original_price && $product->original_price > $product->price)
                    <span class="text-lg font-bold text-dark">${{ number_format($product->price, 2) }}</span>
                    <span class="text-sm text-gray-500 line-through">${{ number_format($product->original_price, 2) }}</span>
                    <span class="text-xs bg-red-100 text-red-600 px-1 py-0.5 rounded">
                        {{ round((($product->original_price - $product->price) / $product->original_price) * 100) }}% OFF
                    </span>
                @else
                    <span class="text-lg font-bold text-dark">${{ number_format($product->price, 2) }}</span>
                @endif
            </div>
        </div>

        <!-- Add to Cart Button -->
        <button class="w-full bg-primary hover:bg-primary/90 text-white py-2 px-4 rounded-lg text-sm font-medium transition-colors flex items-center justify-center space-x-2 group/btn">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
            </svg>
            <span>Add to Cart</span>
        </button>
    </div>
</div> 