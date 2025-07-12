@extends('layouts.app')

@section('title', 'My Wishlist - iruali')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">My Wishlist</h1>
        <p class="text-gray-600">Save your favorite products for later</p>
    </div>

    @if($wishlistItems->count() > 0)
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            @foreach($wishlistItems as $item)
            <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                <div class="relative">
                    @if($item->product->mainImage)
                        <img src="{{ $item->product->mainImage->url }}" alt="{{ $item->product->name }}" class="w-full h-48 object-cover">
                    @else
                        <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                        </div>
                    @endif
                    @if($item->product->is_on_sale)
                        <div class="absolute top-2 left-2 bg-red-500 text-white px-2 py-1 rounded text-sm font-semibold">
                            -{{ $item->product->discount_percentage }}%
                        </div>
                    @endif
                    <div class="absolute top-2 right-2">
                        <form action="{{ route('wishlist.remove', $item->id) }}" method="POST" class="inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-500 hover:text-red-700 transition duration-300">
                                <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                </svg>
                            </button>
                        </form>
                    </div>
                </div>
                <div class="p-4">
                    <h3 class="text-lg font-semibold text-gray-900 mb-2">
                        <a href="{{ route('products.show', $item->product) }}" class="hover:text-primary-600">
                            {{ $item->product->name }}
                        </a>
                    </h3>
                    <p class="text-sm text-gray-600 mb-4">{{ Str::limit($item->product->description, 80) }}</p>
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            @if($item->product->is_on_sale)
                                <span class="text-lg font-bold text-primary-600">ރ{{ number_format($item->product->final_price, 2) }}</span>
                                <span class="text-sm text-gray-500 line-through">ރ{{ number_format($item->product->price, 2) }}</span>
                            @else
                                <span class="text-lg font-bold text-primary-600">ރ{{ number_format($item->product->price, 2) }}</span>
                            @endif
                        </div>
                        <form action="{{ route('cart.add') }}" method="POST" class="inline">
                            @csrf
                            <input type="hidden" name="product_id" value="{{ $item->product->id }}">
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

        <!-- Actions -->
        <div class="mt-8 flex justify-between items-center">
            <div class="text-sm text-gray-600">
                {{ $wishlistItems->count() }} item(s) in your wishlist
            </div>
            <div class="flex space-x-4">
                <form action="{{ route('wishlist.clear') }}" method="POST" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="text-red-600 hover:text-red-700 font-medium">
                        Clear Wishlist
                    </button>
                </form>
                <a href="{{ route('shop.index') }}" class="text-primary-600 hover:text-primary-700 font-medium">
                    Continue Shopping
                </a>
            </div>
        </div>
    @else
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">Your wishlist is empty</h3>
            <p class="mt-1 text-sm text-gray-500">Start adding products to your wishlist to save them for later.</p>
            <div class="mt-6">
                <a href="{{ route('shop.index') }}" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition duration-300">
                    Start Shopping
                </a>
            </div>
        </div>
    @endif
</div>
@endsection 