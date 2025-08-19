@extends('layouts.app')

@section('title', 'My Orders - iruali')

@section('content')
<div class="max-w-7xl mx-auto">
    <!-- Header -->
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">My Orders</h1>
        <p class="text-gray-600">Track your order history and status</p>
    </div>

    @if($orders->count() > 0)
        <div class="space-y-6">
            @foreach($orders as $order)
            <div class="bg-white rounded-lg shadow-md p-6">
                <div class="flex justify-between items-start mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Order #{{ $order->order_number }}</h3>
                        <p class="text-sm text-gray-600">{{ $order->created_at->format('M d, Y') }}</p>
                    </div>
                    <div class="text-right">
                        <span class="inline-flex px-3 py-1 text-sm font-semibold rounded-full
                            @if($order->status === 'completed') bg-green-100 text-green-800
                            @elseif($order->status === 'pending') bg-yellow-100 text-yellow-800
                            @elseif($order->status === 'cancelled') bg-red-100 text-red-800
                            @else bg-gray-100 text-gray-800 @endif">
                            {{ ucfirst($order->status) }}
                        </span>
                        <p class="text-lg font-bold text-primary-600 mt-1 force-ltr" dir="ltr">ރ{{ number_format($order->total_amount, 2) }}</p>
                    </div>
                </div>

                <div class="border-t border-gray-200 pt-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">Shipping Address</h4>
                            <p class="text-sm text-gray-600">
                                {{ $order->shipping_address }}<br>
                                {{ $order->shipping_city }}, {{ $order->shipping_state }} {{ $order->shipping_zip }}<br>
                                {{ $order->shipping_country }}
                            </p>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-900 mb-2">Order Items</h4>
                            <div class="space-y-2">
                                @foreach($order->items->take(3) as $item)
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ $item->product->name }} x{{ $item->quantity }}</span>
                                    <span class="text-gray-900 force-ltr" dir="ltr">ރ{{ number_format($item->price * $item->quantity, 2) }}</span>
                                </div>
                                @endforeach
                                @if($order->items->count() > 3)
                                <p class="text-sm text-gray-500">+{{ $order->items->count() - 3 }} more items</p>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between items-center">
                        <a href="{{ route('orders.show', $order) }}" 
                           class="text-primary-600 hover:text-primary-700 font-medium">
                            View Order Details →
                        </a>
                        @if($order->status === 'pending')
                        <button class="text-red-600 hover:text-red-700 font-medium">
                            Cancel Order
                        </button>
                        @endif
                    </div>
                </div>
            </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="mt-8">
            {{ $orders->links() }}
        </div>
    @else
        <div class="text-center py-12">
            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
            </svg>
            <h3 class="mt-2 text-sm font-medium text-gray-900">No orders found</h3>
            <p class="mt-1 text-sm text-gray-500">Start shopping to see your order history here.</p>
            <div class="mt-6">
                <a href="{{ route('shop') }}" class="bg-primary-600 text-white px-6 py-3 rounded-lg hover:bg-primary-700 transition duration-300">
                    Start Shopping
                </a>
            </div>
        </div>
    @endif
</div>
@endsection 