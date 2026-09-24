@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => 'Orders'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <form method="GET" class="mb-4 flex flex-wrap gap-2">
            <select name="status" class="rounded-lg border border-gray-300 px-3 py-2 text-sm">
                <option value="">All statuses</option>
                @foreach(['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <button class="rounded-lg bg-gray-800 px-4 py-2 text-sm font-medium text-white hover:bg-gray-900">Filter</button>
        </form>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Order</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Customer</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Your items</th>
                            <th class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Your total</th>
                            <th class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-white">
                        @forelse($orders as $order)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="text-sm font-medium text-gray-900">#{{ $order->order_number }}</p>
                                    <p class="text-xs text-gray-500">{{ $order->created_at->format('d M Y, H:i') }}</p>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $order->user->name ?? 'Customer' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-700">{{ $order->items->sum('quantity') }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-900">ރ&#x200E;{{ number_format($order->items->sum(fn ($i) => $i->price * $i->quantity), 2) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-1 text-xs font-medium {{ $order->status_badge }}">{{ ucfirst($order->status) }}</span></td>
                                <td class="px-4 py-3 text-right text-sm"><a href="{{ route('seller.orders.show', $order) }}" class="font-medium text-primary-600 hover:text-primary-700">View</a></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-gray-500">No orders yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
