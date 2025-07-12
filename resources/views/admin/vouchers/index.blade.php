@extends('layouts.app')

@section('title', 'Vouchers')

@section('content')
<div class="max-w-4xl mx-auto py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold">Vouchers</h1>
        <a href="{{ route('admin.vouchers.create') }}" class="bg-primary-600 text-white px-4 py-2 rounded hover:bg-primary-700">+ New Voucher</a>
    </div>
    <div class="bg-white rounded shadow p-6">
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
                    <td>{{ $voucher->code }}</td>
                    <td>{{ ucfirst($voucher->type) }}</td>
                    <td class="force-ltr" dir="ltr">{{ $voucher->type === 'percent' ? $voucher->amount . '%' : 'ރ' . number_format($voucher->amount, 2) }}</td>
                    <td class="force-ltr" dir="ltr">{{ $voucher->min_order ? 'ރ' . number_format($voucher->min_order, 2) : '-' }}</td>
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