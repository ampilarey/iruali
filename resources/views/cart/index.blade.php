@extends('layouts.app')

@section('title', 'Shopping Cart - iruali')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">{{ __('app.cart') }}</h1>
        <p class="text-gray-600">{{ __('app.cart_review') }}</p>
    </div>

    @if($cart->items->count() > 0)
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Cart Items -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md">
                    <div class="p-6 border-b border-gray-200">
                        <h2 class="text-xl font-semibold text-gray-900">{{ __('app.cart_items', ['count' => $cart->item_count]) }}</h2>
                    </div>
                    <div class="divide-y divide-gray-200">
                        @foreach($cart->items as $item)
                        <div class="p-6">
                            <div class="flex items-center space-x-4">
                                <!-- Product Image -->
                                <div class="flex-shrink-0">
                                    @if($item->product->mainImage)
                                        <img src="{{ $item->product->mainImage->url }}" alt="{{ $item->product->name }}" 
                                             class="w-20 h-20 object-cover rounded-lg">
                                    @else
                                        <div class="w-20 h-20 bg-gray-200 rounded-lg flex items-center justify-center">
                                            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </div>
                                    @endif
                                </div>

                                <!-- Product Details -->
                                <div class="flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        <a href="{{ route('products.show', $item->product) }}" class="hover:text-primary-600">
                                            {{ $item->product->name }}
                                        </a>
                                    </h3>
                                    <p class="text-sm text-gray-600">{{ $item->product->category->name }}</p>
                                    @if($item->variant)
                                        <p class="text-sm text-gray-500">Variant: {{ $item->variant->name }}</p>
                                    @endif
                                </div>

                                <!-- Quantity -->
                                <div class="flex items-center space-x-2">
                                    <button class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-50" 
                                            onclick="updateQuantity({{ $item->id }}, -1)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                        </svg>
                                    </button>
                                    <span class="w-12 text-center" id="quantity-{{ $item->id }}">{{ $item->quantity }}</span>
                                    <button class="w-8 h-8 rounded-full border border-gray-300 flex items-center justify-center hover:bg-gray-50" 
                                            onclick="updateQuantity({{ $item->id }}, 1)">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </button>
                                </div>

                                <!-- Price -->
                                <div class="text-right">
                                    <p class="text-lg font-semibold text-gray-900 force-ltr" dir="ltr">ރ{{ number_format($item->subtotal, 2) }}</p>
                                    <p class="text-sm text-gray-500 force-ltr" dir="ltr">ރ{{ number_format($item->price, 2) }} each</p>
                                </div>

                                <!-- Remove Button -->
                                <div>
                                    <form action="{{ route('cart.remove', $item) }}" method="POST" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-red-500 hover:text-red-700">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <!-- Cart Actions -->
                    <div class="p-6 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <form action="{{ route('cart.clear') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-red-600 hover:text-red-700 font-medium">
                                    {{ __('app.clear_cart') }}
                                </button>
                            </form>
                            <a href="{{ route('shop') }}" class="text-primary-600 hover:text-primary-700 font-medium">
                                {{ __('app.continue_shopping') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Voucher Code -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">{{ __('app.order_summary') }}</h2>
                    
                    <!-- Voucher Code -->
                    <div class="mb-6">
                        @if($voucher)
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-green-700 font-semibold">Voucher Applied: {{ $voucher->code }}</span>
                                <form action="{{ route('cart.removeVoucher') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:underline ml-2">Remove</button>
                                </form>
                            </div>
                        @else
                            <form action="{{ route('cart.applyVoucher') }}" method="POST" class="flex items-center space-x-2">
                                @csrf
                                <input type="text" name="voucher_code" placeholder="Voucher code" class="px-3 py-2 border rounded-md" required>
                                <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded hover:bg-primary-700">Apply</button>
                            </form>
                            @error('voucher_code')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>
                    <!-- Order Summary -->
                    <div class="space-y-3 mb-6">
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ __('app.subtotal') }}</span>
                            <span class="font-medium force-ltr" dir="ltr">ރ{{ number_format($cart->total, 2) }}</span>
                        </div>
                        @if($voucher && $discount > 0)
                        <div class="flex justify-between">
                            <span class="text-green-700">Voucher Discount</span>
                            <span class="font-medium text-green-700 force-ltr" dir="ltr">-ރ{{ number_format($discount, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ __('app.shipping') }}</span>
                            <span class="font-medium force-ltr" dir="ltr">ރ0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">{{ __('app.tax') }}</span>
                            <span class="font-medium force-ltr" dir="ltr">ރ0.00</span>
                        </div>
                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between">
                                <span class="text-lg font-semibold">{{ __('app.total') }}</span>
                                <span class="text-lg font-semibold text-primary-600 force-ltr" dir="ltr">ރ{{ number_format($cart->total - $discount, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <a href="{{ route('checkout.index') }}" class="block w-full bg-primary-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-primary-700 transition duration-300 text-center">
                        {{ __('app.proceed_to_checkout') }}
                    </a>

                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-500">{{ __('app.secure_checkout') }}</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <!-- Empty Cart -->
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4m0 0L7 13m0 0l-2.5 5M7 13l2.5 5m6-5v6a2 2 0 01-2 2H9a2 2 0 01-2-2v-6m6 0V9a2 2 0 00-2-2H9a2 2 0 00-2 2v4.01"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">{{ __('app.cart_empty_title') }}</h3>
            <p class="mt-1 text-sm text-gray-500">{{ __('app.cart_empty_description') }}</p>
            <div class="mt-6">
                <a href="{{ route('shop') }}" class="bg-primary-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-primary-700 transition duration-300">
                    {{ __('app.start_shopping') }}
                </a>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
function updateQuantity(itemId, change) {
    const quantityElement = document.getElementById(`quantity-${itemId}`);
    const currentQuantity = parseInt(quantityElement.textContent);
    const newQuantity = Math.max(1, currentQuantity + change);
    
    fetch(`/cart/update/${itemId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
        },
        body: JSON.stringify({
            quantity: newQuantity
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            quantityElement.textContent = newQuantity;
            // Update cart total if needed
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error updating quantity:', error);
    });
}
</script>
@endpush
@endsection 