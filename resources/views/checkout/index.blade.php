@extends('layouts.app')

@section('title', 'Checkout - Iru E-commerce')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Checkout</h1>
        <p class="text-gray-600">Complete your order</p>
    </div>

    <form action="{{ route('checkout.store') }}" method="POST">
        @csrf
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Checkout Form -->
            <div class="space-y-6">
                <!-- Shipping Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Shipping Information</h2>
                    <div class="space-y-4">
                        <div>
                            <label for="shipping_address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                            <input type="text" id="shipping_address" name="shipping_address" required
                                   class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('shipping_address') border-red-500 @enderror"
                                   value="{{ old('shipping_address') }}">
                            @error('shipping_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="shipping_city" class="block text-sm font-medium text-gray-700 mb-1">City</label>
                                <input type="text" id="shipping_city" name="shipping_city" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('shipping_city') border-red-500 @enderror"
                                       value="{{ old('shipping_city') }}">
                                @error('shipping_city')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="shipping_state" class="block text-sm font-medium text-gray-700 mb-1">State</label>
                                <input type="text" id="shipping_state" name="shipping_state" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('shipping_state') border-red-500 @enderror"
                                       value="{{ old('shipping_state') }}">
                                @error('shipping_state')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label for="shipping_zip" class="block text-sm font-medium text-gray-700 mb-1">ZIP Code</label>
                                <input type="text" id="shipping_zip" name="shipping_zip" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('shipping_zip') border-red-500 @enderror"
                                       value="{{ old('shipping_zip') }}">
                                @error('shipping_zip')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label for="shipping_country" class="block text-sm font-medium text-gray-700 mb-1">Country</label>
                                <input type="text" id="shipping_country" name="shipping_country" required
                                       class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('shipping_country') border-red-500 @enderror"
                                       value="{{ old('shipping_country', 'United States') }}">
                                @error('shipping_country')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Payment Information -->
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Payment Method</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="card" checked
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300">
                                <span class="ml-3 text-sm font-medium text-gray-700">Credit Card</span>
                            </label>
                        </div>
                        <div>
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="paypal"
                                       class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300">
                                <span class="ml-3 text-sm font-medium text-gray-700">PayPal</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-8">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Summary</h2>
                    
                    <!-- Order Items -->
                    <div class="space-y-3 mb-6">
                        @foreach($cart->items as $item)
                        <div class="flex justify-between items-center">
                            <div class="flex items-center space-x-3">
                                @if($item->product->mainImage)
                                    <img src="{{ $item->product->mainImage->url }}" alt="{{ $item->product->name }}" class="w-12 h-12 object-cover rounded">
                                @else
                                    <div class="w-12 h-12 bg-gray-200 rounded flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                        </svg>
                                    </div>
                                @endif
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $item->product->name }}</p>
                                    <p class="text-xs text-gray-500">Qty: {{ $item->quantity }}</p>
                                </div>
                            </div>
                            <span class="text-sm font-medium text-gray-900">ރ{{ number_format($item->subtotal, 2) }}</span>
                        </div>
                        @endforeach
                    </div>

                    <!-- Loyalty Points Redemption -->
                    @if(isset($points_balance))
                    <div class="mb-6">
                        <h3 class="text-md font-semibold mb-2">Loyalty Points</h3>
                        <div class="flex items-center space-x-2 mb-2">
                            <span class="text-blue-700">Balance: {{ $points_balance }}</span>
                        </div>
                        @if($points_redeemed > 0)
                            <div class="flex items-center space-x-2 mb-2">
                                <span class="text-green-700">Redeemed: {{ $points_redeemed }} (ރ{{ number_format($points_redeemed_discount, 2) }})</span>
                                <form action="{{ route('checkout.removePoints') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-red-600 hover:underline ml-2">Remove</button>
                                </form>
                            </div>
                        @else
                            <form action="{{ route('checkout.redeemPoints') }}" method="POST" class="flex items-center space-x-2">
                                @csrf
                                <input type="number" name="points" min="1" max="{{ $points_balance }}" class="px-3 py-2 border rounded-md w-32" placeholder="Points to redeem">
                                <button type="submit" class="bg-primary-600 text-white px-4 py-2 rounded hover:bg-primary-700">Redeem</button>
                            </form>
                            @error('points')
                                <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        @endif
                    </div>
                    @endif

                    <!-- Totals -->
                    <div class="border-t border-gray-200 pt-4 space-y-3">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="font-medium">ރ{{ number_format($cart->total, 2) }}</span>
                        </div>
                        @if($points_redeemed_discount > 0)
                        <div class="flex justify-between">
                            <span class="text-blue-700">Loyalty Points Discount</span>
                            <span class="font-medium text-blue-700">-ރ{{ number_format($points_redeemed_discount, 2) }}</span>
                        </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-gray-600">Shipping</span>
                            <span class="font-medium">ރ0.00</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax</span>
                            <span class="font-medium">ރ0.00</span>
                        </div>
                        <div class="border-t border-gray-200 pt-3">
                            <div class="flex justify-between">
                                <span class="text-lg font-semibold">Total</span>
                                <span class="text-lg font-semibold text-primary-600">ރ{{ number_format($cart->total, 2) }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Place Order Button -->
                    <button type="submit" class="w-full bg-primary-600 text-white py-3 px-4 rounded-lg font-semibold hover:bg-primary-700 transition duration-300 mt-6">
                        Place Order
                    </button>

                    <div class="mt-4 text-center">
                        <p class="text-sm text-gray-500">By placing your order, you agree to our Terms of Service</p>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection 