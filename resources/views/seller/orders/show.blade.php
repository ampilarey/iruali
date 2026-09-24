@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => 'Order #' . $order->order_number])
        @slot('action')
            <a href="{{ route('seller.orders') }}" class="text-sm font-medium text-gray-600 hover:text-gray-900">← Back to orders</a>
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Your items in this order</h2>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($order->items as $item)
                    <li class="flex items-center justify-between px-5 py-3">
                        <div>
                            <p class="text-sm font-medium text-gray-900">{{ $item->product->name ?? 'Deleted product' }}</p>
                            <p class="text-xs text-gray-500">{{ $item->quantity }} × {{ \App\Support\Money::format($item->price) }}</p>
                        </div>
                        <span class="text-sm font-medium text-gray-900">{{ \App\Support\Money::format($item->price * $item->quantity) }}</span>
                    </li>
                @endforeach
            </ul>
            <div class="flex justify-between border-t border-gray-100 px-5 py-4 text-sm font-semibold text-gray-900">
                <span>Your total</span>
                <span>{{ \App\Support\Money::format($order->items->sum(fn ($i) => $i->price * $i->quantity)) }}</span>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Status</h2>
                <span class="mt-2 inline-block rounded-full px-2 py-1 text-xs font-medium {{ $order->status_badge }}">{{ ucfirst($order->status) }}</span>
                <p class="mt-2 text-xs text-gray-500">Placed {{ $order->created_at->format('d M Y, H:i') }}</p>
                <div class="mt-4">
                    @if($ownsWholeOrder)
                        @include('partials.order-status-actions', ['action' => route('seller.orders.status', $order), 'nextStatuses' => $nextStatuses])
                    @else
                        <p class="text-sm text-gray-600">This order also has items from other shops, so an admin updates its status. Pack and hand over your items as usual.</p>
                    @endif
                </div>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">Ship to</h2>
                <p class="mt-2 text-sm text-gray-900">{{ $order->user->name ?? 'Customer' }}</p>
                <p class="text-sm text-gray-700">{{ $order->shipping_address }}</p>
                <p class="text-sm text-gray-700">{{ collect([$order->shipping_city, $order->shipping_state, $order->shipping_zip])->filter()->join(', ') }}</p>
                <p class="text-sm text-gray-700">{{ $order->shipping_country }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
