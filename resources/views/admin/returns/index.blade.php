@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Returns'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
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
