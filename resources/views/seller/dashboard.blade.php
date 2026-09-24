@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => 'Dashboard'])
        @slot('action')
            <a href="{{ route('seller.products.create') }}" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">+ Add product</a>
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @if(! auth()->user()->seller_approved)
            <div class="rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
                Your seller account is awaiting admin approval. You can prepare listings now; they go live once approved.
            </div>
        @endif

        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach([
                ['Revenue', \App\Support\Money::format($stats['total_revenue']), 'From non-cancelled orders'],
                ['Orders', $stats['total_orders'], $stats['pending_orders'] . ' pending'],
                ['Products', $stats['total_products'], $stats['active_products'] . ' live · ' . $stats['pending_products'] . ' awaiting approval'],
                ['Low stock', $stats['low_stock'], 'At or below reorder point'],
            ] as [$label, $value, $hint])
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $value }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-lg bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Recent orders</h2>
                    <a href="{{ route('seller.orders') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">View all</a>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse($recent_orders as $order)
                        <li>
                            <a href="{{ route('seller.orders.show', $order) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">#{{ $order->order_number }}</p>
                                    <p class="text-xs text-gray-500">{{ $order->user->name ?? 'Customer' }} · {{ $order->created_at->format('d M Y') }}</p>
                                </div>
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $order->status_badge }}">{{ ucfirst($order->status) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">No orders yet.</li>
                    @endforelse
                </ul>
            </div>

            <div class="rounded-lg bg-white shadow">
                <div class="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">Recent products</h2>
                    <a href="{{ route('seller.products.index') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">View all</a>
                </div>
                <ul class="divide-y divide-gray-100">
                    @forelse($recent_products as $product)
                        <li>
                            <a href="{{ route('seller.products.edit', $product) }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50">
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ $product->name }}</p>
                                    <p class="text-xs text-gray-500">{{ $product->category->name ?? '—' }} · Stock {{ $product->stock_quantity }}</p>
                                </div>
                                <span class="text-sm font-medium text-gray-900">{{ \App\Support\Money::format($product->price) }}</span>
                            </a>
                        </li>
                    @empty
                        <li class="px-5 py-8 text-center text-sm text-gray-500">
                            No products yet. <a href="{{ route('seller.products.create') }}" class="font-medium text-primary-600">Add your first product</a>.
                        </li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
