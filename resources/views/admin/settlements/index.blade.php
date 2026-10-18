@extends('layouts.admin')

@section('title', 'Settlements')

@section('content')
<div class="max-w-6xl mx-auto py-6 px-4 space-y-4">
    <div>
        <h1 class="text-xl font-bold lg:text-2xl">Settlements</h1>
        <p class="text-sm text-gray-500">Import the BML account statement (CSV from internet banking). Each line of money in is matched to an order by its card transaction, an order number in the description, or the exact amount of one unpaid bank transfer.</p>
    </div>
    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>@endif

    <form method="POST" action="{{ route('admin.settlements.import') }}" enctype="multipart/form-data" class="flex flex-wrap items-end gap-2 rounded-lg bg-white p-4 shadow text-sm">
        @csrf
        <label class="block text-xs font-medium text-gray-700">Statement (CSV)<input type="file" name="statement" accept=".csv,text/csv" required class="mt-1 block text-sm"></label>
        <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Import</button>
        @error('statement')<p class="w-full text-xs text-red-700">{{ $message }}</p>@enderror
    </form>

    <nav class="flex flex-wrap gap-2 text-sm">
        @foreach(['unmatched' => 'Not matched', 'matched' => 'Matched', 'ignored' => 'Set aside', 'all' => 'All'] as $key => $label)
            <a href="{{ route('admin.settlements', ['status' => $key]) }}" @class(['rounded-full px-3 py-1', 'bg-primary-600 text-white' => $status === $key, 'bg-white text-gray-700 shadow' => $status !== $key])>{{ $label }} @if($key !== 'all')({{ (int) ($counts[$key] ?? 0) }})@endif</a>
        @endforeach
    </nav>

    <div class="overflow-x-auto rounded-lg bg-white shadow">
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">Date</th><th class="p-3 text-start">Description</th><th class="p-3 text-end">Amount</th><th class="p-3 text-start">Order</th><th class="p-3"></th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($lines as $line)
                    <tr data-statement-line>
                        <td class="p-3 whitespace-nowrap">{{ $line->posted_on->format('j M Y') }}</td>
                        <td class="p-3">{{ $line->description }}@if($line->reference)<br><span class="text-xs text-gray-500">{{ $line->reference }}</span>@endif</td>
                        <td class="p-3 text-end whitespace-nowrap">MVR {{ number_format((float) $line->amount, 2) }}</td>
                        <td class="p-3">
                            @if($line->order)
                                <a href="{{ route('admin.orders.show', $line->order) }}" class="font-mono text-primary-700 hover:underline">{{ $line->order->order_number }}</a>
                                <span class="block text-xs text-gray-500">{{ ['card' => 'card payment', 'transfer' => 'order number in the description', 'amount' => 'same amount (check)', 'manual' => 'matched by hand'][$line->match_kind] ?? '' }} · {{ $line->order->payment_status }}</span>
                            @else
                                <form method="POST" action="{{ route('admin.settlements.match', $line) }}" class="flex gap-1">@csrf
                                    <input name="order_number" placeholder="ORD-…" class="w-32 rounded border border-gray-300 px-2 py-1 text-xs" aria-label="Order number">
                                    <button class="rounded border border-gray-300 px-2 py-1 text-xs hover:bg-gray-50">Match</button></form>
                            @endif
                        </td>
                        <td class="p-3 text-end whitespace-nowrap">
                            @if($line->order && $line->order->payment_status !== 'paid' && $line->order->payment_method !== 'bml' && $line->order->status !== 'cancelled')
                                <form method="POST" action="{{ route('admin.settlements.confirm', $line) }}" class="inline">@csrf<button class="rounded-lg bg-primary-600 px-2 py-1 text-xs font-semibold text-white hover:bg-primary-700">Confirm payment</button></form>
                            @endif
                            <form method="POST" action="{{ route('admin.settlements.ignore', $line) }}" class="inline">@csrf<button class="ms-1 text-xs text-gray-500 underline">{{ $line->status === 'ignored' ? 'Bring back' : 'Set aside' }}</button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-3 text-gray-500">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $lines->links() }}
</div>
@endsection
