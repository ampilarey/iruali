@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Pre-orders'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600">Pre-orders still waiting for their stock. A pre-order more than {{ $lateDays }} days past its expected date is flagged late each morning and shows in the inbox until its stock arrives, the shop moves the date (the customer is emailed) or the order is cancelled (the refund is flagged as for any paid order).</p>

        <nav class="flex flex-wrap gap-2 text-sm">
            <a href="{{ route('admin.preorders') }}" class="rounded-full px-3 py-1.5 font-medium {{ $lateOnly ? 'bg-white text-gray-700 shadow hover:bg-gray-50' : 'bg-primary-600 text-white' }}">All waiting</a>
            <a href="{{ route('admin.preorders', ['late' => 1]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $lateOnly ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}">Late only</a>
        </nav>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start">Order</th>
                            <th class="px-4 py-3 text-start">Shop</th>
                            <th class="px-4 py-3 text-start">Item</th>
                            <th class="px-4 py-3 text-end">Waiting</th>
                            <th class="px-4 py-3 text-start">Expected</th>
                            <th class="px-4 py-3 text-start">Payment</th>
                            <th class="px-4 py-3 text-start">Customer</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($items as $item)
                            <tr class="{{ $item->preorder_late_at ? 'bg-red-50' : '' }}" data-preorder-row="{{ $item->id }}">
                                <td class="px-4 py-3"><a href="{{ route('admin.orders.show', $item->order) }}" class="font-medium text-primary-700 hover:underline">#{{ $item->order->order_number }}</a><span class="block text-xs text-gray-500">{{ $item->order->created_at->format('d M Y') }}</span></td>
                                <td class="px-4 py-3">{{ $item->product?->seller ? $item->product->seller->shopName() : 'iruali' }}</td>
                                <td class="px-4 py-3">{{ $item->displayName() }}</td>
                                <td class="px-4 py-3 text-end">{{ $item->preorderWaitingQuantity() }} of {{ $item->quantity }}</td>
                                <td class="px-4 py-3">
                                    {{ $item->preorder_ship_date?->format('d M Y') }}
                                    @if($item->preorder_late_at)
                                        <span class="ms-1 rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-800">Late: {{ (int) $item->preorder_ship_date?->diffInDays(today()) }} days</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Services\PaymentService::statusBadge($item->order->payment_status) }}">{{ \App\Services\PaymentService::statusLabel($item->order->payment_status) }}</span> <span class="text-xs text-gray-500">{{ Money::format($item->price * $item->quantity) }}</span></td>
                                <td class="px-4 py-3">{{ $item->order->customerName() }}<span class="block text-xs text-gray-500">{{ $item->order->customerEmail() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">{{ $lateOnly ? 'No late pre-orders.' : 'No pre-orders are waiting for stock.' }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $items->links() }}</div>
        </div>
    </div>
</div>
@endsection
