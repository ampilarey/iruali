@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Search Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-900 mb-4">Search Products</h1>
            <p class="text-lg text-gray-600">Find exactly what you're looking for</p>
        </div>

        <!-- Search Form -->
        <div class="bg-white rounded-lg shadow-lg p-8 mb-8">
            <form action="{{ route('search') }}" method="GET" class="space-y-6">
                <div>
                    <label for="q" class="block text-sm font-medium text-gray-700 mb-2">Search Term</label>
                    <input type="text" 
                           id="q" 
                           name="q" 
                           value="{{ request('q') }}"
                           placeholder="Enter product name, description, SKU, or category..."
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary text-lg"
                           required>
                </div>

                <!-- Advanced Search Options -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select name="category" id="category" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-primary">
                            <option value="">All Categories</option>
                            @foreach(\App\Models\Category::active()->root()->get() as $category)
                            <option value="{{ $category->id }}" {{ request('category') == $category->id ? 'selected' : '' }}>
                                {{ $category->name }}
                            </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="min_price" class="block text-sm font-medium text-gray-700 mb-2">Min Price</label>
                        <input type="number" 
                               id="min_price" 
                               name="min_price" 
                               value="{{ request('min_price') }}"
                               placeholder="0"
                               min="0"
                               step="0.01"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>

                    <div>
                        <label for="max_price" class="block text-sm font-medium text-gray-700 mb-2">Max Price</label>
                        <input type="number" 
                               id="max_price" 
                               name="max_price" 
                               value="{{ request('max_price') }}"
                               placeholder="1000"
                               min="0"
                               step="0.01"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary focus:border-primary">
                    </div>
                </div>

                <!-- Search Button -->
                <div class="text-center">
                    <button type="submit" 
                            class="bg-primary hover:bg-primary-hover text-white font-medium py-3 px-8 rounded-lg transition duration-200 text-lg">
                        <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Search Products
                    </button>
                </div>
            </form>
        </div>

        <!-- Search Tips -->
        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
            <h3 class="text-lg font-medium text-blue-900 mb-3">Search Tips</h3>
            <ul class="text-blue-800 space-y-2">
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Use specific product names for best results</span>
                </li>
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Try searching by brand or model number</span>
                </li>
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Use category filters to narrow down results</span>
                </li>
                <li class="flex items-start">
                    <svg class="w-5 h-5 text-blue-600 mr-2 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                    </svg>
                    <span>Set price ranges to find products within your budget</span>
                </li>
            </ul>
        </div>

        <!-- Popular Searches -->
        <div class="mt-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">Popular Searches</h3>
            <div class="flex flex-wrap gap-2">
                @php
                    $popularSearches = ['headphones', 'laptop', 'smartphone', 'tshirt', 'coffee', 'yoga mat'];
                @endphp
                @foreach($popularSearches as $search)
                <a href="{{ route('search', ['q' => $search]) }}" 
                   class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-full text-sm transition duration-200">
                    {{ ucfirst($search) }}
                </a>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection
