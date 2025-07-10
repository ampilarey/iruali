@extends('layouts.app')

@section('title', $category->name . ' - Iru E-commerce')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="mb-8">
        <ol class="flex items-center space-x-2 text-sm text-gray-500">
            <li><a href="{{ route('home') }}" class="hover:text-primary-600">Home</a></li>
            <li><span class="mx-2">/</span></li>
            <li><a href="{{ route('categories.index') }}" class="hover:text-primary-600">Categories</a></li>
            @foreach($parentCategories as $parent)
                <li><span class="mx-2">/</span></li>
                <li><a href="{{ route('categories.show', $parent) }}" class="hover:text-primary-600">{{ $parent->name }}</a></li>
            @endforeach
            <li><span class="mx-2">/</span></li>
            <li class="text-gray-900">{{ $category->name }}</li>
        </ol>
    </nav>

    <!-- Category Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ $category->name }}</h1>
        @if($category->description)
            <p class="text-gray-600">{{ $category->description }}</p>
        @endif
    </div>

    <!-- Subcategories -->
    @if($subcategories->count() > 0)
    <div class="mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Subcategories</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($subcategories as $subcategory)
            <a href="{{ route('categories.show', $subcategory) }}" 
               class="bg-white rounded-lg shadow-md p-4 hover:shadow-lg transition duration-300">
                <h3 class="font-semibold text-gray-900 mb-2">{{ $subcategory->name }}</h3>
                <p class="text-sm text-gray-600">{{ $subcategory->products->count() }} products</p>
            </a>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Products -->
    <div class="mb-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-gray-900">Products ({{ $products->total() }})</h2>
            
            <!-- Sort Options -->
            <div class="flex items-center space-x-4">
                <label for="sort" class="text-sm font-medium text-gray-700">Sort by:</label>
                <select id="sort" class="px-3 py-2 border border-gray-300 rounded-md text-sm">
                    <option value="latest">Latest</option>
                    <option value="price_low">Price: Low to High</option>
                    <option value="price_high">Price: High to Low</option>
                    <option value="name">Name: A to Z</option>
                </select>
            </div>
        </div>

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
                {{ $products->links() }}
            </div>
        @else
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No products found in this category</h3>
                <p class="mt-1 text-sm text-gray-500">Check back later for new products.</p>
            </div>
        @endif
    </div>
</div>
@endsection 