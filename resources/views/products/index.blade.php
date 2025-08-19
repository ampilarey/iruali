@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">All Products</h1>
            <p class="text-gray-600 mt-2">Discover our complete collection</p>
        </div>

        <!-- Filters and Products Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Sidebar Filters -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow p-6 sticky top-4">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Filters</h3>
                    
                    <!-- Categories -->
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Categories</h4>
                        <div class="space-y-2">
                            @foreach($categories as $category)
                            <label class="flex items-center">
                                <input type="checkbox" name="category[]" value="{{ $category->id }}" 
                                       class="rounded border-gray-300 text-primary focus:ring-primary">
                                <span class="ml-2 text-sm text-gray-700">{{ $category->name }}</span>
                            </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Price Range -->
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Price Range</h4>
                        <div class="space-y-3">
                            <div>
                                <label for="min_price" class="block text-sm text-gray-700 mb-1">Min Price</label>
                                <input type="number" id="min_price" name="min_price" placeholder="0" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            </div>
                            <div>
                                <label for="max_price" class="block text-sm text-gray-700 mb-1">Max Price</label>
                                <input type="number" id="max_price" name="max_price" placeholder="1000" 
                                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            </div>
                        </div>
                    </div>

                    <!-- Sort -->
                    <div class="mb-6">
                        <h4 class="text-sm font-medium text-gray-900 mb-3">Sort By</h4>
                        <select name="sort" class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm">
                            <option value="latest">Latest</option>
                            <option value="price_low">Price: Low to High</option>
                            <option value="price_high">Price: High to Low</option>
                            <option value="name">Name: A to Z</option>
                            <option value="popular">Most Popular</option>
                        </select>
                    </div>

                    <!-- Apply Filters Button -->
                    <button type="button" onclick="applyFilters()" 
                            class="w-full bg-primary hover:bg-primary-hover text-white font-medium py-2 px-4 rounded-md transition duration-200">
                        Apply Filters
                    </button>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="lg:col-span-3">
                @if($products->count() > 0)
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    @foreach($products as $product)
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-200">
                        @if($product->mainImage)
                        <img src="{{ $product->mainImage->url }}" alt="{{ $product->name }}" 
                             class="w-full h-48 object-cover">
                        @else
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                            <span class="text-gray-400">No image</span>
                        </div>
                        @endif
                        
                        <div class="p-4">
                            <p class="text-sm text-gray-500 mb-1">{{ $product->category->name }}</p>
                            <h3 class="font-medium text-gray-900 mb-2 line-clamp-2">
                                <a href="{{ route('products.show', $product) }}" class="hover:text-primary">
                                    {{ $product->name }}
                                </a>
                            </h3>
                            
                            @if($product->brand)
                            <p class="text-sm text-gray-600 mb-2">{{ $product->brand }}</p>
                            @endif

                            <!-- Rating -->
                            @if($product->average_rating > 0)
                            <div class="flex items-center mb-2">
                                <div class="flex items-center">
                                    @for($i = 1; $i <= 5; $i++)
                                    <svg class="w-4 h-4 {{ $i <= $product->average_rating ? 'text-yellow-400' : 'text-gray-300' }}" 
                                         fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                                    </svg>
                                    @endfor
                                </div>
                                <span class="text-sm text-gray-500 ml-1">({{ $product->reviews_count ?? 0 }})</span>
                            </div>
                            @endif

                            <!-- Price -->
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-2">
                                    @if($product->is_on_sale)
                                    <span class="text-lg font-bold text-red-600">${{ number_format($product->sale_price, 2) }}</span>
                                    <span class="text-sm text-gray-500 line-through">${{ number_format($product->price, 2) }}</span>
                                    @else
                                    <span class="text-lg font-bold text-gray-900">${{ number_format($product->price, 2) }}</span>
                                    @endif
                                </div>
                                
                                @if($product->is_on_sale)
                                <span class="bg-red-100 text-red-800 text-xs font-medium px-2 py-1 rounded">
                                    {{ $product->discount_percentage }}% OFF
                                </span>
                                @endif
                            </div>

                            <!-- Stock Status -->
                            <div class="flex items-center justify-between mb-3">
                                @if($product->is_in_stock)
                                <span class="text-sm text-green-600">In Stock</span>
                                @else
                                <span class="text-sm text-red-600">Out of Stock</span>
                                @endif
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex space-x-2">
                                @if($product->is_in_stock)
                                <form action="{{ route('cart.add') }}" method="POST" class="flex-1">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" 
                                            class="w-full bg-primary hover:bg-primary-hover text-white text-sm font-medium py-2 px-4 rounded-md transition duration-200">
                                        Add to Cart
                                    </button>
                                </form>
                                @else
                                <button disabled class="w-full bg-gray-300 text-gray-500 text-sm font-medium py-2 px-4 rounded-md cursor-not-allowed">
                                    Out of Stock
                                </button>
                                @endif
                                
                                <button onclick="addToWishlist({{ $product->id }})" 
                                        class="p-2 border border-gray-300 rounded-md hover:bg-gray-50 transition duration-200">
                                    <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                              d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                @if($products->hasPages())
                <div class="mt-8">
                    {{ $products->links() }}
                </div>
                @endif

                @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                              d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your filters or search terms.</p>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>

<script>
function applyFilters() {
    const categories = Array.from(document.querySelectorAll('input[name="category[]"]:checked'))
        .map(cb => cb.value);
    const minPrice = document.getElementById('min_price').value;
    const maxPrice = document.getElementById('max_price').value;
    const sort = document.querySelector('select[name="sort"]').value;

    const params = new URLSearchParams();
    if (categories.length > 0) params.append('category', categories.join(','));
    if (minPrice) params.append('min_price', minPrice);
    if (maxPrice) params.append('max_price', maxPrice);
    if (sort) params.append('sort', sort);

    const url = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
    window.location.href = url;
}

function addToWishlist(productId) {
    // TODO: Implement wishlist functionality
    console.log('Add to wishlist:', productId);
}
</script>
@endsection 