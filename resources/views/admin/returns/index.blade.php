@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Returns'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        @if($refundsDue->isNotEmpty())
            <section class="rounded-lg border border-red-200 bg-red-50 p-4">
                <h2 class="font-semibold text-red-800">Refunds due ({{ $refundsDue->count() }})</h2>
                <p class="text-sm text-red-700">Money owed outside a return: a payment that arrived after the order was cancelled, a paid order that was cancelled, or a double payment. Refund it in the BML portal, then record the reference on the order.</p>
                <ul class="mt-3 divide-y divide-red-100 text-sm">
                    @foreach($refundsDue as $o)
                        <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span><a href="{{ route('admin.orders.show', $o) }}" class="font-medium text-primary-700 hover:underline">#{{ $o->order_number }}</a> · {{ $o->user?->name }} · {{ $o->refund_reason }}</span>
                            <span class="font-semibold">{{ Money::format($o->refund_amount) }}</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
        <nav class="flex flex-wrap gap-2 text-sm">
            @foreach(['open' => 'Open', 'requested' => 'New', 'approved' => 'Approved, refund due', 'refunded' => 'Refunded', 'rejected' => 'Rejected', 'all' => 'All'] as $key => $label)
                @php $n = $key === 'open' ? ($counts['requested'] ?? 0) + ($counts['approved'] ?? 0) : ($counts[$key] ?? null); @endphp
                <a href="{{ route('admin.returns', ['status' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $status === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}">{{ $label }}@if($n) ({{ $n }})@endif</a>
            @endforeach
        </nav>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-left">Requested</th><th class="px-4 py-3 text-left">Order</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Shop</th><th class="px-4 py-3 text-left">Reason</th><th class="px-4 py-3 text-right">Items</th><th class="px-4 py-3 text-right">Refund</th><th class="px-4 py-3 text-left">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($returns as $return)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600"><a href="{{ route('admin.returns.show', $return) }}" class="font-medium text-primary-700 hover:underline">{{ $return->created_at->format('d M Y') }}</a></td>
                                <td class="px-4 py-3">#{{ $return->order?->order_number }}</td>
                                <td class="px-4 py-3">{{ $return->user?->name }}</td>
                                <td class="px-4 py-3">{{ $return->sellerOrder?->shopName() }}</td>
                                <td class="px-4 py-3">{{ $return->reasonLabel() }}</td>
                                <td class="px-4 py-3 text-right">{{ Money::format($return->items_value) }}</td>
                                <td class="px-4 py-3 text-right">{{ $return->refund_amount !== null ? Money::format($return->refund_amount) : '—' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $return->status_badge }}">{{ ucfirst($return->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No returns here.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $returns->links() }}</div>
        </div>
    </div>
</div>
@endsection
