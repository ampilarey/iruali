@extends('layouts.app')

@section('title', 'Order #' . $order->order_number . ' - iruali')

@section('content')
<div class="max-w-4xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex justify-between items-start">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">Order #{{ $order->order_number }}</h1>
                <p class="text-gray-600">Placed on {{ $order->created_at->format('F d, Y \a\t g:i A') }}</p>
            </div>
            <div class="text-right">
                <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full
                    @if($order->status === 'completed') bg-green-100 text-green-800
                    @elseif($order->status === 'pending') bg-yellow-100 text-yellow-800
                    @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                    @else bg-gray-100 text-gray-800 @endif">
                    {{ ucfirst($order->status) }}
                </span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Order Details -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Items</h2>
                <div class="space-y-4">
                    @foreach($order->items as $item)
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            @if($item->product->mainImage)
                                <img src="{{ $item->product->mainImage->url }}" alt="{{ $item->product->name }}" class="w-16 h-16 object-cover rounded">
                            @else
                                <div class="w-16 h-16 bg-gray-200 rounded flex items-center justify-center">
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                </div>
                            @endif
                        </div>
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900">{{ $item->product->name }}</h3>
                            <p class="text-sm text-gray-600">Quantity: {{ $item->quantity }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-semibold text-gray-900">ރ{{ number_format($item->price * $item->quantity, 2) }}</p>
                            <p class="text-sm text-gray-600">ރ{{ number_format($item->price, 2) }} each</p>
                        </div>
                    </div>
                    @endforeach
                    @if($order->loyalty_points_earned > 0)
                    <div class="flex justify-between">
                        <span class="text-blue-700">Loyalty Points Earned</span>
                        <span class="text-blue-700">+{{ $order->loyalty_points_earned }}</span>
                    </div>
                    @endif
                    @if($order->points_redeemed > 0)
                    <div class="flex justify-between">
                        <span class="text-blue-700">Points Redeemed</span>
                        <span class="text-blue-700">-{{ $order->points_redeemed }} (ރ{{ number_format($order->points_redeemed_discount, 2) }})</span>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Shipping Information -->
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Shipping Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Shipping Address</h3>
                        <p class="text-gray-600">
                            {{ $order->shipping_address }}<br>
                            {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                            {{ $order->shipping_country }}
                        </p>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 mb-2">Order Status</h3>
                        <div class="space-y-2">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm text-gray-600">Order Placed</span>
                                <span class="text-xs text-gray-400 ml-auto">{{ $order->created_at->format('M d, Y') }}</span>
                            </div>
                            @if($order->status === 'completed')
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                                <span class="text-sm text-gray-600">Order Completed</span>
                                <span class="text-xs text-gray-400 ml-auto">{{ $order->updated_at->format('M d, Y') }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="lg:col-span-1">
            <div class="bg-white rounded-lg shadow-md p-6 sticky top-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-4">Order Summary</h2>
                <div class="space-y-3">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Subtotal</span>
                        <span class="text-gray-900">ރ{{ number_format($order->total_amount + $order->voucher_discount, 2) }}</span>
                    </div>
                    @if($order->voucher_code && $order->voucher_discount > 0)
                    <div class="flex justify-between">
                        <span class="text-green-700">Voucher ({{ $order->voucher_code }})</span>
                        <span class="text-green-700">-ރ{{ number_format($order->voucher_discount, 2) }}</span>
                    </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-gray-600">Shipping</span>
                        <span class="text-gray-900">Free</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Tax</span>
                        <span class="text-gray-900">ރ0.00</span>
                    </div>
                    <hr class="my-3">
                    <div class="flex justify-between">
                        <span class="text-lg font-semibold text-gray-900">Total</span>
                        <span class="text-lg font-semibold text-primary-600">ރ{{ number_format($order->total_amount, 2) }}</span>
                    </div>
                </div>

                @if($order->status === 'pending')
                <div class="mt-6">
                    <button class="w-full bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition duration-300">
                        Cancel Order
                    </button>
                </div>
                @endif

                <div class="mt-4">
                    <a href="{{ route('orders.index') }}" class="block text-center text-primary-600 hover:text-primary-700 font-medium">
                        ← Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 