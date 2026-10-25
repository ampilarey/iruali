@extends('layouts.admin')

@section('title', 'Item requests')

@php
    $labels = ['open' => 'Open', 'new' => 'Waiting for a price', 'quoted' => 'Priced', 'accepted' => 'In cart', 'ordered' => 'Ordered', 'declined' => 'Declined', 'cancelled' => 'Cancelled', 'all' => 'All'];
@endphp

{{-- Admin → Item requests (admins only): customers asking iruali to bring an item it doesn't list (ItemRequestService). --}}
@section('content')
<div class="min-h-screen bg-gray-100 pb-6 lg:pb-12">
    @include('admin.payouts._header', ['title' => 'Item requests'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="max-w-3xl text-sm text-gray-600">Customers asking us to bring an item we don't list ("Can't find it? We'll bring it for you"). Open a request to send a price (it becomes a pre-order only that customer can buy) or to decline it. {{ $waiting }} waiting for a price.</p>

        <nav class="flex flex-wrap gap-2 text-sm" aria-label="Show">
            @foreach($labels as $key => $label)
                <a href="{{ route('admin.item-requests', ['status' => $key]) }}" class="rounded-full px-3 py-1.5 font-medium {{ $tab === $key ? 'bg-primary-600 text-white' : 'bg-white text-gray-700 shadow hover:bg-gray-50' }}" @if($tab === $key) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-3 text-start">Request</th>
                            <th class="px-4 py-3 text-start">Customer</th>
                            <th class="px-4 py-3 text-start">Item</th>
                            <th class="px-4 py-3 text-end">Qty</th>
                            <th class="px-4 py-3 text-start">Island</th>
                            <th class="px-4 py-3 text-start">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($requests as $itemRequest)
                            <tr data-admin-item-request="{{ $itemRequest->id }}">
                                <td class="px-4 py-3"><a href="{{ route('admin.item-requests.show', $itemRequest) }}" class="font-semibold text-primary-700 hover:underline">#{{ $itemRequest->id }}</a><div class="text-xs text-gray-500">{{ $itemRequest->created_at->format('j M Y H:i') }}</div></td>
                                <td class="px-4 py-3">{{ $itemRequest->user?->name }}<div class="text-xs text-gray-500">{{ $itemRequest->user?->phone }}</div></td>
                                <td class="px-4 py-3 max-w-xs"><span class="line-clamp-2">{{ $itemRequest->title }}</span>@if($itemRequest->link)<a href="{{ $itemRequest->link }}" target="_blank" rel="noopener nofollow" class="text-xs text-primary-700 hover:underline">{{ parse_url($itemRequest->link, PHP_URL_HOST) }} ↗</a>@endif</td>
                                <td class="px-4 py-3 text-end">{{ $itemRequest->quantity }}</td>
                                <td class="px-4 py-3">{{ $itemRequest->island?->englishName() ?? '—' }}</td>
                                <td class="px-4 py-3"><span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold">{{ $itemRequest->isExpired() ? 'Price expired' : ($labels[$itemRequest->status] ?? $itemRequest->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-8 text-center text-gray-500">No requests here.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{ $requests->links() }}
    </div>
</div>
@endsection
