@extends('layouts.app')

@section('title', 'Products - iruali')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-6 sm:mb-8">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mb-2">All Products</h1>
        <p class="text-gray-600 text-sm sm:text-base">Discover our amazing collection of products</p>
    </div>

    <!-- Mobile Filter Toggle -->
    <div class="lg:hidden mb-4">
        <button id="mobile-filter-toggle" class="w-full bg-white border border-gray-300 rounded-lg px-4 py-3 text-left flex items-center justify-between hover:bg-gray-50">
            <span class="font-medium text-gray-900">Filters & Sort</span>
            <svg class="w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </button>
    </div>

    <!-- Mobile Filters Panel -->
    <div id="mobile-filters" class="lg:hidden mb-6 bg-white rounded-lg shadow-md p-4 hidden">
        <div class="space-y-4">
            <!-- Categories -->
            <div>
                <h4 class="font-medium text-gray-900 mb-3 text-sm">Categories</h4>
                <div class="space-y-2 max-h-32 overflow-y-auto">
                    @foreach($categories as $category)
                    <label class="flex items-center">
                        <input type="checkbox" name="category" value="{{ $category->slug }}" 
                               class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span class="ml-2 text-sm text-gray-700">{{ $category->name }}</span>
                    </label>
                    @endforeach
                </div>
            </div>

            <!-- Price Range -->
            <div>
                <h4 class="font-medium text-gray-900 mb-3 text-sm">Price Range</h4>
                <div class="grid grid-cols-2 gap-2">
                    <input type="number" placeholder="Min Price" name="min_price" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <input type="number" placeholder="Max Price" name="max_price" 
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                </div>
            </div>

            <!-- Sale Items -->
            <div>
                <label class="flex items-center">
                    <input type="checkbox" name="sale" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    <span class="ml-2 text-sm text-gray-700">Sale Items Only</span>
                </label>
            </div>

            <!-- Sort -->
            <div>
                <h4 class="font-medium text-gray-900 mb-3 text-sm">Sort By</h4>
                <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="latest">Latest</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                    <option value="name">Name: A to Z</option>
                    <option value="popular">Most Popular</option>
                </select>
            </div>

            <!-- Apply Filters -->
            <button type="submit" class="w-full bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition duration-300 text-sm">
                Apply Filters
            </button>
        </div>
    </div>

    <div class="flex flex-col lg:flex-row gap-6 lg:gap-8">
        <!-- Desktop Filters Sidebar -->
        <div class="hidden lg:block lg:w-1/4">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Filters</h3>
                
                <!-- Categories -->
                <div class="mb-6">
                    <h4 class="font-medium text-gray-900 mb-3">Categories</h4>
                    <div class="space-y-2 max-h-48 overflow-y-auto">
                        @foreach($categories as $category)
                        <label class="flex items-center">
                            <input type="checkbox" name="category" value="{{ $category->slug }}" 
                                   class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                            <span class="ml-2 text-sm text-gray-700">{{ $category->name }}</span>
                        </label>
                        @endforeach
                    </div>
                </div>

                <!-- Price Range -->
                <div class="mb-6">
                    <h4 class="font-medium text-gray-900 mb-3">Price Range</h4>
                    <div class="space-y-2">
                        <input type="number" placeholder="Min Price" name="min_price" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <input type="number" placeholder="Max Price" name="max_price" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                    </div>
                </div>

                <!-- Sale Items -->
                <div class="mb-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="sale" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                        <span class="ml-2 text-sm text-gray-700">Sale Items Only</span>
                    </label>
                </div>

                <!-- Sort -->
                <div class="mb-6">
                    <h4 class="font-medium text-gray-900 mb-3">Sort By</h4>
                    <select name="sort" class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm">
                        <option value="latest">Latest</option>
                        <option value="price_low">Price: Low to High</option>
                        <option value="price_high">Price: High to Low</option>
                        <option value="name">Name: A to Z</option>
                        <option value="popular">Most Popular</option>
                    </select>
                </div>

                <!-- Apply Filters -->
                <button type="submit" class="w-full bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition duration-300">
                    Apply Filters
                </button>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="lg:w-3/4">
            @if($products->count() > 0)
                <!-- Results Count -->
                <div class="mb-4 text-sm text-gray-600">
                    Showing {{ $products->firstItem() ?? 0 }} - {{ $products->lastItem() ?? 0 }} of {{ $products->total() }} products
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                    @foreach($products as $product)
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                        <div class="relative">
                            @if($product->mainImage)
                                <img src="{{ $product->mainImage->url }}" alt="{{ $product->name }}" class="w-full h-40 sm:h-48 object-cover">
                            @else
                                <div class="w-full h-40 sm:h-48 bg-gray-200 flex items-center justify-center">
                                    <svg class="w-10 h-10 sm:w-12 sm:h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            @endif
                            @if($product->is_on_sale)
                                <div class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded text-xs font-semibold">
                                    -{{ $product->discount_percentage }}%
                                </div>
                            @endif
                            <div class="absolute top-2 right-2">
                                <button class="text-gray-400 hover:text-red-500 transition duration-300">
                                    <svg class="w-5 h-5 sm:w-6 sm:h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div class="p-3 sm:p-4">
                            <h3 class="text-base sm:text-lg font-semibold text-gray-900 mb-2">
                                <a href="{{ route('products.show', $product) }}" class="hover:text-primary-600">
                                    {{ $product->name }}
                                </a>
                            </h3>
                            <p class="text-xs sm:text-sm text-gray-600 mb-3">{{ $product->category->name }}</p>
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 sm:gap-0">
                                <div class="flex items-center space-x-2">
                                    @if($product->is_on_sale)
                                        <span class="text-base sm:text-lg font-bold text-primary-600 force-ltr" dir="ltr">ރ&#x200E;{{ number_format($product->final_price, 2) }}</span>
                                        <span class="text-xs sm:text-sm text-gray-500 line-through force-ltr" dir="ltr">ރ&#x200E;{{ number_format($product->price, 2) }}</span>
                                    @else
                                        <span class="text-base sm:text-lg font-bold text-primary-600 force-ltr" dir="ltr">ރ&#x200E;{{ number_format($product->price, 2) }}</span>
                                    @endif
                                </div>
                                <form action="{{ route('cart.add') }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                                    <input type="hidden" name="quantity" value="1">
                                    <button type="submit" class="w-full sm:w-auto bg-primary-600 text-white px-3 sm:px-4 py-2 rounded-lg hover:bg-primary-700 transition duration-300 text-sm">
                                        Add to Cart
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-6 sm:mt-8">
                    {{ $products->links() }}
                </div>
            @else
                <div class="text-center py-12">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
                    <p class="mt-1 text-sm text-gray-500">Try adjusting your search or filter criteria.</p>
                </div>
            @endif
        </div>
    </div>
</div>

<!-- Mobile Filter JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileFilterToggle = document.getElementById('mobile-filter-toggle');
    const mobileFilters = document.getElementById('mobile-filters');
    
    if (mobileFilterToggle && mobileFilters) {
        mobileFilterToggle.addEventListener('click', function() {
            mobileFilters.classList.toggle('hidden');
            
            // Update arrow icon
            const arrow = mobileFilterToggle.querySelector('svg');
            if (mobileFilters.classList.contains('hidden')) {
                arrow.style.transform = 'rotate(0deg)';
            } else {
                arrow.style.transform = 'rotate(180deg)';
            }
        });
    }
});
</script>
@endsection 