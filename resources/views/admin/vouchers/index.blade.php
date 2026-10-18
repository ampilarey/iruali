@extends('layouts.admin')

@section('title', 'Vouchers')

@section('content')
<div class="max-w-4xl mx-auto py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-xl font-bold lg:text-2xl">Vouchers</h1>
        <a href="{{ route('admin.vouchers.create') }}" class="bg-primary-600 text-white px-4 py-2 rounded hover:bg-primary-700">+ New Voucher</a>
    </div>
    @if(session('success'))<div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    <form method="POST" action="{{ route('admin.vouchers.batch') }}" class="mb-4">
        @csrf
        <x-admin.fold title="Printed discount cards" body="space-y-3 text-sm" :open="$errors->any()" id="cards">
            <p class="text-xs text-gray-500">Make a batch of single-use codes to print and hand out (events, deliveries, partners). Each code works once.</p>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div><label for="b-name" class="block text-xs font-medium text-gray-700">Name (letters and numbers)</label><input id="b-name" name="name" required maxlength="20" value="{{ old('name') }}" placeholder="EID26" class="mt-1 w-full rounded border px-3 py-2">@error('name')<p class="text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="b-count" class="block text-xs font-medium text-gray-700">How many cards</label><input id="b-count" name="count" type="number" min="1" max="1000" required value="{{ old('count', 50) }}" class="mt-1 w-full rounded border px-3 py-2"></div>
                <div><label for="b-until" class="block text-xs font-medium text-gray-700">Valid until</label><input id="b-until" name="valid_until" type="date" required value="{{ old('valid_until') }}" class="mt-1 w-full rounded border px-3 py-2">@error('valid_until')<p class="text-xs text-red-700">{{ $message }}</p>@enderror</div>
                <div><label for="b-type" class="block text-xs font-medium text-gray-700">Type</label><select id="b-type" name="type" class="mt-1 w-full rounded border px-3 py-2"><option value="fixed">MVR off</option><option value="percent">% off</option></select></div>
                <div><label for="b-amount" class="block text-xs font-medium text-gray-700">Amount</label><input id="b-amount" name="amount" type="number" min="1" step="0.01" required value="{{ old('amount') }}" class="mt-1 w-full rounded border px-3 py-2"></div>
                <div><label for="b-min" class="block text-xs font-medium text-gray-700">Minimum order (MVR)</label><input id="b-min" name="min_order" type="number" min="0" step="0.01" value="{{ old('min_order') }}" class="mt-1 w-full rounded border px-3 py-2"></div>
            </div>
            <button class="rounded bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Make cards</button>
            @if($batches->isNotEmpty())
                <table class="mt-3 w-full text-sm">
                    <thead><tr class="text-xs uppercase text-gray-500"><th class="py-1 text-start">Batch</th><th class="py-1 text-start">Value</th><th class="py-1 text-end">Used</th><th class="py-1 text-end"></th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($batches as $b)
                            <tr>
                                <td class="py-1 font-mono">{{ $b->batch }}</td>
                                <td class="py-1">{{ $b->type === 'percent' ? (float) $b->amount.'%' : \App\Support\Money::format((float) $b->amount) }}</td>
                                <td class="py-1 text-end">{{ (int) $b->used }} / {{ (int) $b->cards }}</td>
                                <td class="py-1 text-end"><a href="{{ route('admin.vouchers.batch.print', $b->batch) }}" class="text-primary-700 hover:underline" target="_blank">Print</a> · <a href="{{ route('admin.vouchers.batch.export', $b->batch) }}" class="text-primary-700 hover:underline">CSV</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-admin.fold>
    </form>

    <div class="bg-white rounded shadow p-6 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="bg-gray-100">
                    <th class="p-2">Code</th>
                    <th class="p-2">Type</th>
                    <th class="p-2">Amount</th>
                    <th class="p-2">Min Order</th>
                    <th class="p-2">Max Uses</th>
                    <th class="p-2">Used</th>
                    <th class="p-2">Valid</th>
                    <th class="p-2">Active</th>
                    <th class="p-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($vouchers as $voucher)
                <tr>
                    <td>{{ $voucher->code }}@if($voucher->is_automatic) <span class="rounded bg-indigo-100 px-1.5 text-xs text-indigo-800">automatic</span>@endif @if($voucher->free_delivery) <span class="rounded bg-green-100 px-1.5 text-xs text-green-800">free delivery</span>@endif</td>
                    <td>{{ ucfirst($voucher->type) }}</td>
                    <td class="force-ltr" dir="ltr">{{ $voucher->type === 'percent' ? $voucher->amount . '%' : \App\Support\Money::format($voucher->amount) }}</td>
                    <td class="force-ltr" dir="ltr">{{ $voucher->min_order ? \App\Support\Money::format($voucher->min_order) : '-' }}</td>
                    <td>{{ $voucher->max_uses ?? '-' }}</td>
                    <td>{{ $voucher->used_count }}</td>
                    <td>{{ $voucher->valid_from ? $voucher->valid_from->format('Y-m-d') : '-' }} - {{ $voucher->valid_until ? $voucher->valid_until->format('Y-m-d') : '-' }}</td>
                    <td>{{ $voucher->is_active ? 'Yes' : 'No' }}</td>
                    <td>
                        <a href="{{ route('admin.vouchers.edit', $voucher) }}" class="text-primary-600 hover:underline mr-2">Edit</a>
                        <form action="{{ route('admin.vouchers.destroy', $voucher) }}" method="POST" class="inline" onsubmit="return confirm('Delete this voucher?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="mt-4">{{ $vouchers->links() }}</div>
    </div>
</div>
@endsection 