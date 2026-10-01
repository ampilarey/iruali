@extends('layouts.app')

@section('title', 'Gift cards')

@section('content')
<div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Gift cards</h1>
            <p class="text-sm text-gray-500">Outstanding balance on active cards: {{ \App\Support\Money::format($outstanding) }}.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to Dashboard</a>
    </div>

    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <div class="flex flex-wrap items-center gap-2 text-sm">
        @foreach(['' => 'All', 'pending' => 'Unpaid', 'active' => 'Active', 'redeemed' => 'Redeemed', 'expired' => 'Expired', 'cancelled' => 'Cancelled'] as $value => $label)
            <a href="{{ route('admin.gift-cards', array_filter(['status' => $value, 'q' => request('q')])) }}" class="rounded-full px-3 py-1 {{ ($status ?? '') === $value ? 'bg-primary-600 text-white' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">{{ $label }}@if($value !== '') ({{ $counts[$value] ?? 0 }})@endif</a>
        @endforeach
        <form method="GET" class="ms-auto flex gap-2">
            <input type="hidden" name="status" value="{{ $status }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Code or email" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm">
            <button class="rounded-lg bg-gray-800 px-3 py-1.5 text-sm font-medium text-white">Search</button>
        </form>
    </div>

    <div class="bg-white rounded shadow overflow-x-auto">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-100 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">Code</th><th class="p-3 text-end">Amount</th><th class="p-3 text-end">Balance</th><th class="p-3 text-start">Bought by</th><th class="p-3 text-start">Recipient</th><th class="p-3 text-start">Status</th><th class="p-3 text-start">Expires</th><th class="p-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($cards as $card)
                    <tr>
                        <td class="p-3 font-mono">{{ $card->code ?? '—' }}<br><span class="text-xs text-gray-500 font-sans">{{ $card->created_at->format('d M Y') }}@if($card->order) · <a href="{{ route('admin.orders.show', $card->order) }}" class="text-primary-600 hover:underline">{{ $card->order->order_number }}</a>@endif</span></td>
                        <td class="p-3 text-end">{{ \App\Support\Money::format($card->amount) }}</td>
                        <td class="p-3 text-end">{{ \App\Support\Money::format($card->balance) }}</td>
                        <td class="p-3">{{ $card->purchaser?->name ?? '—' }}</td>
                        <td class="p-3">{{ $card->recipient_name }}<br><span class="text-xs text-gray-500">{{ $card->recipient_email }}</span>@if($card->redeemer)<br><span class="text-xs text-green-700">Redeemed by {{ $card->redeemer->name }} {{ $card->redeemed_at?->format('d M Y') }}</span>@endif</td>
                        <td class="p-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ ['active' => 'bg-green-100 text-green-800', 'pending' => 'bg-yellow-100 text-yellow-800', 'redeemed' => 'bg-blue-100 text-blue-800', 'expired' => 'bg-gray-100 text-gray-700', 'cancelled' => 'bg-red-100 text-red-800'][$card->status] ?? 'bg-gray-100 text-gray-700' }}">{{ ucfirst($card->status) }}</span></td>
                        <td class="p-3 whitespace-nowrap">{{ $card->expires_at?->format('d M Y') ?? '—' }}</td>
                        <td class="p-3 text-end">
                            @if(in_array($card->status, ['active', 'pending'], true))
                                <form method="POST" action="{{ route('admin.gift-cards.cancel', $card) }}" onsubmit="return confirm('Cancel this gift card? It can no longer be redeemed.');">@csrf<button class="text-red-600 hover:underline">Cancel</button></form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="p-6 text-center text-gray-500">No gift cards yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-3">{{ $cards->links() }}</div>
    </div>
</div>
@endsection
