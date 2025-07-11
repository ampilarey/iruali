@extends('layouts.app')

@section('title', 'Search Results - iruali')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Search Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Search Results</h1>
        <p class="text-gray-600">
            Found {{ $products->total() }} results for "<strong>{{ request('q') }}</strong>"
        </p>
    </div>

    <!-- Search Form -->
    <div class="mb-8">
        <form action="{{ route('search.results') }}" method="GET" class="max-w-2xl">
            <div class="flex">
                <input type="text" 
                       name="q" 
                       value="{{ request('q') }}" 
                       placeholder="Search products..." 
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-l-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                <button type="submit" 
                        class="px-6 py-3 bg-primary-600 text-white rounded-r-lg hover:bg-primary-700 transition duration-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </button>
            </div>
        </form>
    </div>

    <!-- Filters -->
    <div class="mb-8">
        <div class="flex flex-wrap items-center gap-4">
            <span class="text-sm font-medium text-gray-700">Filters:</span>
            
            <!-- Price Range -->
            <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600">Price:</label>
                <select name="price_range" class="px-3 py-1 border border-gray-300 rounded text-sm">
                    <option value="">All Prices</option>
                    <option value="0-50" {{ request('price_range') == '0-50' ? 'selected' : '' }}>$0 - $50</option>
                    <option value="50-100" {{ request('price_range') == '50-100' ? 'selected' : '' }}>$50 - $100</option>
                    <option value="100-200" {{ request('price_range') == '100-200' ? 'selected' : '' }}>$100 - $200</option>
                    <option value="200+" {{ request('price_range') == '200+' ? 'selected' : '' }}>$200+</option>
                </select>
            </div>

            <!-- Category -->
            <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600">Category:</label>
                <select name="category" class="px-3 py-1 border border-gray-300 rounded text-sm">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Sort -->
            <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600">Sort:</label>
                <select name="sort" class="px-3 py-1 border border-gray-300 rounded text-sm">
                    <option value="relevance" {{ request('sort') == 'relevance' ? 'selected' : '' }}>Relevance</option>
                    <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                    <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
                    <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>Newest</option>
                </select>
            </div>

            <!-- Clear Filters -->
            <a href="{{ route('search.results', ['q' => request('q')]) }}" 
               class="text-sm text-primary-600 hover:text-primary-700">
                Clear Filters
            </a>
        </div>
    </div>

    <!-- Results -->
    @if($products->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($products as $product)
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
                        <div class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded text-sm font-semibold">
                            -{{ $product->discount_percentage }}%
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
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                        <a href="{{ route('products.show', $product) }}" class="hover:text-primary-600">
                            {{ $product->name }}
                        </a>
                    </h3>
                    <p class="text-sm text-gray-600 mb-2">{{ Str::limit($product->description, 80) }}</p>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            @if($product->is_on_sale)
                                <span class="text-lg font-bold text-primary-600">ރ{{ number_format($product->final_price, 2) }}</span>
                                <span class="text-sm text-gray-500 line-through">ރ{{ number_format($product->price, 2) }}</span>
                            @else
                                <span class="text-lg font-bold text-primary-600">ރ{{ number_format($product->price, 2) }}</span>
                            @endif
                        </div>
                        <form action="{{ route('cart.add') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $product->id }}">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded-lg hover:bg-primary-700 transition duration-300">
                                Add to Cart
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $products->appends(request()->query())->links() }}
        </div>
    @else
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No products found</h3>
            <p class="mt-1 text-sm text-gray-500">Try adjusting your search terms or filters.</p>
            <div class="mt-6">
                <a href="{{ route('shop.index') }}" class="text-primary-600 hover:text-primary-700 font-medium">
                    Browse all products →
                </a>
            </div>
        </div>
    @endif
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handle filter changes
    const filterSelects = document.querySelectorAll('select[name="price_range"], select[name="category"], select[name="sort"]');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            const form = document.createElement('form');
            form.method = 'GET';
            form.action = '{{ route("search.results") }}';
            
            // Add search query
            const queryInput = document.createElement('input');
            queryInput.type = 'hidden';
            queryInput.name = 'q';
            queryInput.value = '{{ request("q") }}';
            form.appendChild(queryInput);
            
            // Add all filter values
            filterSelects.forEach(s => {
                if (s.value) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = s.name;
                    input.value = s.value;
                    form.appendChild(input);
                }
            });
            
            document.body.appendChild(form);
            form.submit();
        });
    });
});
</script>
@endsection 