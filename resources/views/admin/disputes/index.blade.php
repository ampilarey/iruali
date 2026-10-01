@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Disputes'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <nav class="flex flex-wrap gap-2 text-sm">
            @foreach(['open' => 'Open', 'awaiting_customer' => 'Waiting for customer', 'awaiting_seller' => 'Waiting for shop', 'resolved' => 'Resolved', 'all' => 'All'] as $key => $label)
                @php $n = match ($key) { 'open' => collect(\App\Enums\DisputeStatus::openValues())->sum(fn ($s) => $counts[$s] ?? 0), 'resolved' => $counts->except(\App\Enums\DisputeStatus::openValues())->sum(), 'all' => null, default => $counts[$key] ?? null }; @endphp
                <a href="{{ route('admin.disputes', ['status' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $status === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}">{{ $label }}@if($n) ({{ $n }})@endif</a>
            @endforeach
        </nav>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-left">Opened</th><th class="px-4 py-3 text-left">Order</th><th class="px-4 py-3 text-left">Customer</th><th class="px-4 py-3 text-left">Shop</th><th class="px-4 py-3 text-left">Type</th><th class="px-4 py-3 text-right">Claimed</th><th class="px-4 py-3 text-right">Refunded</th><th class="px-4 py-3 text-left">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($disputes as $d)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3 text-gray-600"><a href="{{ route('admin.disputes.show', $d) }}" class="font-medium text-primary-700 hover:underline">{{ $d->opened_at->format('d M Y') }}</a></td>
                                <td class="px-4 py-3">#{{ $d->order?->order_number }}</td>
                                <td class="px-4 py-3">{{ $d->customer?->name }}</td>
                                <td class="px-4 py-3">{{ $d->seller ? ($d->seller->business_name ?: $d->seller->name) : 'iruali' }}</td>
                                <td class="px-4 py-3">{{ \App\Models\Dispute::TYPES[$d->type] ?? $d->type }}</td>
                                <td class="px-4 py-3 text-right">{{ Money::format($d->amount_claimed) }}</td>
                                <td class="px-4 py-3 text-right">{{ $d->amount_resolved !== null ? Money::format($d->amount_resolved) : '—' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $d->status_badge }}">{{ $d->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-gray-500">No disputes here.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $disputes->links() }}</div>
        </div>
    </div>
</div>
@endsection
