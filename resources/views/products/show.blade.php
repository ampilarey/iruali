@extends('layouts.app')

@section('title', $product->name . ' - iruali')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Breadcrumb -->
    <nav class="mb-8">
        <ol class="flex items-center space-x-2 text-sm text-gray-500">
            <li><a href="{{ route('home') }}" class="hover:text-primary-600">{{ __('app.home') }}</a></li>
            <li><span class="mx-2">/</span></li>
            <li><a href="{{ route('categories.show', $product->category) }}" class="hover:text-primary-600">{{ $product->category->name }}</a></li>
            <li><span class="mx-2">/</span></li>
            <li class="text-gray-900">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
        <!-- Product Images -->
        <div>
            <div class="bg-white rounded-lg shadow-md p-6">
                @if($product->mainImage)
                    <img src="{{ $product->mainImage->url }}" alt="{{ $product->name }}" class="w-full h-96 object-cover rounded-lg">
                @else
                    <div class="w-full h-96 bg-gray-200 rounded-lg flex items-center justify-center">
                        <svg class="w-24 h-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                @endif
            </div>
        </div>

        <!-- Product Details -->
        <div>
            <div class="bg-white rounded-lg shadow-md p-6">
                <h1 class="text-2xl font-bold mb-2">{{ $product->name }}</h1>
                
                <!-- Price -->
                <div class="mb-6">
                    @if($product->is_on_sale)
                        <div class="flex items-center space-x-4">
                            <span class="text-3xl font-bold text-primary-600 force-ltr" dir="ltr">ރ{{ number_format($product->final_price, 2) }}</span>
                            <span class="text-xl text-gray-500 line-through force-ltr" dir="ltr">ރ{{ number_format($product->price, 2) }}</span>
                            <span class="bg-red-500 text-white px-3 py-1 rounded-full text-sm font-semibold">
                                -{{ $product->discount_percentage }}% OFF
                            </span>
                        </div>
                    @else
                        <span class="text-3xl font-bold text-primary-600 force-ltr" dir="ltr">ރ{{ number_format($product->price, 2) }}</span>
                    @endif
                </div>

                <!-- Stock Status -->
                <div class="mb-6">
                    @if($product->is_in_stock)
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            {{ __('app.in_stock') }} ({{ $product->stock_quantity }} {{ __('app.available') }})
                        </span>
                    @else
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                            <svg class="w-4 h-4 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                            </svg>
                            {{ __('app.out_of_stock') }}
                        </span>
                    @endif
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">{{ __('app.description') }}</h3>
                    <p class="text-gray-600 mb-4">{{ __('app.product_description') }}: {{ $product->description }}</p>
                </div>

                <!-- Add to Cart -->
                @if($product->is_in_stock)
                <div class="mb-6">
                    <form action="{{ route('cart.add') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->id }}">
                        
                        <div class="flex items-center space-x-4">
                            <label for="quantity" class="text-sm font-medium text-gray-700">{{ __('app.quantity') }}:</label>
                            <input type="number" name="quantity" id="quantity" value="1" min="1" max="{{ $product->stock_quantity }}"
                                   class="w-20 px-3 py-2 border border-gray-300 rounded-md text-sm">
                        </div>

                        <button type="submit" class="w-full bg-primary-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-primary-700 transition duration-300">{{ __('app.add_to_cart') }}</button>
                    </form>
                </div>
                @endif

                <!-- Product Info -->
                <div class="border-t border-gray-200 pt-6">
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="font-medium text-gray-700">{{ __('app.sku') }}:</span>
                            <span class="text-gray-600">{{ $product->sku }}</span>
                        </div>
                        <div>
                            <span class="font-medium text-gray-700">{{ __('app.category') }}:</span>
                            <span class="text-gray-600">{{ $product->category->name }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Related Products -->
    @if($relatedProducts->count() > 0)
    <div class="mt-16">
        <h2 class="text-2xl font-bold text-gray-900 mb-8">{{ __('app.related_products') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($relatedProducts as $relatedProduct)
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                <div class="relative">
                    @if($relatedProduct->mainImage)
                        <img src="{{ $relatedProduct->mainImage->url }}" alt="{{ $relatedProduct->name }}" class="w-full h-48 object-cover">
                    @else
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    @endif
                    @if($relatedProduct->is_on_sale)
                        <div class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded text-sm font-semibold">
                            -{{ $relatedProduct->discount_percentage }}%
                        </div>
                    @endif
                </div>
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                        <a href="{{ route('products.show', $relatedProduct) }}" class="hover:text-primary-600">
                            {{ $relatedProduct->name }}
                        </a>
                    </h3>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            @if($relatedProduct->is_on_sale)
                                <span class="text-lg font-bold text-primary-600 force-ltr" dir="ltr">ރ{{ number_format($relatedProduct->final_price, 2) }}</span>
                                <span class="text-sm text-gray-500 line-through force-ltr" dir="ltr">ރ{{ number_format($relatedProduct->price, 2) }}</span>
                            @else
                                <span class="text-lg font-bold text-primary-600 force-ltr" dir="ltr">ރ{{ number_format($relatedProduct->price, 2) }}</span>
                            @endif
                        </div>
                        <form action="{{ route('cart.add') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $relatedProduct->id }}">
                            <input type="hidden" name="quantity" value="1">
                            <button type="submit" class="bg-primary-600 text-white px-3 py-1 rounded text-sm hover:bg-primary-700 transition duration-300">{{ __('app.add_to_cart') }}</button>
                        </form>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection 