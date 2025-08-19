@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Breadcrumb -->
        <nav class="flex mb-8" aria-label="Breadcrumb">
            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                <li class="inline-flex items-center">
                    <a href="{{ route('home') }}" class="text-gray-700 hover:text-gray-900">
                        <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z"></path>
                        </svg>
                        Home
                    </a>
                </li>
                <li>
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <a href="{{ route('categories.show', $product->category) }}" class="text-gray-700 hover:text-gray-900 ml-1 md:ml-2">
                            {{ $product->category->name }}
                        </a>
                    </div>
                </li>
                <li aria-current="page">
                    <div class="flex items-center">
                        <svg class="w-6 h-6 text-gray-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path>
                        </svg>
                        <span class="text-gray-500 ml-1 md:ml-2">{{ $product->name }}</span>
                    </div>
                </li>
            </ol>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Product Images -->
            <div class="space-y-4">
                @if($product->mainImage)
                <div class="aspect-w-1 aspect-h-1 w-full">
                    <img src="{{ $product->mainImage->url }}" alt="{{ $product->name }}" 
                         class="w-full h-full object-cover rounded-lg shadow-lg">
                </div>
                @else
                <div class="aspect-w-1 aspect-h-1 w-full bg-gray-200 rounded-lg flex items-center justify-center">
                    <span class="text-gray-400">No image available</span>
                </div>
                @endif

                @if($product->images && $product->images->count() > 1)
                <div class="grid grid-cols-4 gap-2">
                    @foreach($product->images->take(4) as $image)
                    <div class="aspect-w-1 aspect-h-1">
                        <img src="{{ $image->url }}" alt="{{ $product->name }}" 
                             class="w-full h-full object-cover rounded-lg cursor-pointer hover:opacity-75">
                    </div>
                    @endforeach
                </div>
                @endif
            </div>

            <!-- Product Info -->
            <div class="space-y-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">{{ $product->name }}</h1>
                    <p class="text-lg text-gray-600 mt-2">{{ $product->category->name }}</p>
                </div>

                <!-- Price -->
                <div class="flex items-center space-x-4">
                    @if($product->is_on_sale)
                    <span class="text-3xl font-bold text-red-600">${{ number_format($product->sale_price, 2) }}</span>
                    <span class="text-xl text-gray-500 line-through">${{ number_format($product->price, 2) }}</span>
                    <span class="bg-red-100 text-red-800 text-sm font-medium px-2.5 py-0.5 rounded">
                        {{ $product->discount_percentage }}% OFF
                    </span>
                    @else
                    <span class="text-3xl font-bold text-gray-900">${{ number_format($product->price, 2) }}</span>
                    @endif
                </div>

                <!-- Stock Status -->
                <div class="flex items-center space-x-2">
                    @if($product->is_in_stock)
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                        In Stock ({{ $product->stock_quantity }})
                    </span>
                    @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                        Out of Stock
                    </span>
                    @endif
                </div>

                <!-- Description -->
                @if($product->description)
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Description</h3>
                    <div class="text-gray-600 prose max-w-none">
                        {!! $product->description !!}
                    </div>
                </div>
                @endif

                <!-- Add to Cart -->
                @if($product->is_in_stock)
                <form action="{{ route('cart.add') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="product_id" value="{{ $product->id }}">
                    
                    <div class="flex items-center space-x-4">
                        <label for="quantity" class="text-sm font-medium text-gray-700">Quantity:</label>
                        <select name="quantity" id="quantity" class="border border-gray-300 rounded-md px-3 py-2">
                            @for($i = 1; $i <= min(10, $product->stock_quantity); $i++)
                            <option value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </div>

                    <button type="submit" class="w-full bg-primary hover:bg-primary-hover text-white font-medium py-3 px-6 rounded-lg transition duration-200">
                        Add to Cart
                    </button>
                </form>
                @endif

                <!-- Additional Info -->
                <div class="border-t pt-6 space-y-4">
                    @if($product->sku)
                    <div class="flex justify-between">
                        <span class="text-gray-600">SKU:</span>
                        <span class="text-gray-900">{{ $product->sku }}</span>
                    </div>
                    @endif

                    @if($product->brand)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Brand:</span>
                        <span class="text-gray-900">{{ $product->brand }}</span>
                    </div>
                    @endif

                    @if($product->model)
                    <div class="flex justify-between">
                        <span class="text-gray-600">Model:</span>
                        <span class="text-gray-900">{{ $product->model }}</span>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Related Products -->
        @if($relatedProducts && $relatedProducts->count() > 0)
        <div class="mt-16">
            <h2 class="text-2xl font-bold text-gray-900 mb-8">Related Products</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                @foreach($relatedProducts as $relatedProduct)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-200">
                    @if($relatedProduct->mainImage)
                    <img src="{{ $relatedProduct->mainImage->url }}" alt="{{ $relatedProduct->name }}" 
                         class="w-full h-48 object-cover">
                    @else
                    <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                        <span class="text-gray-400">No image</span>
                    </div>
                    @endif
                    
                    <div class="p-4">
                        <h3 class="font-medium text-gray-900 mb-2">{{ $relatedProduct->name }}</h3>
                        <p class="text-gray-600 text-sm mb-2">{{ $relatedProduct->category->name }}</p>
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-gray-900">${{ number_format($relatedProduct->price, 2) }}</span>
                            <a href="{{ route('products.show', $relatedProduct) }}" 
                               class="text-primary hover:text-primary-hover text-sm font-medium">
                                View Details
                            </a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
</div>
@endsection 