@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => 'Returns'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600">Returns customers asked for on your orders. iruali reviews each one. When a return is approved, your share of the items (their price less commission) is taken from your next payout.</p>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-left">Requested</th><th class="px-4 py-3 text-left">Order</th><th class="px-4 py-3 text-left">Items</th><th class="px-4 py-3 text-left">Reason</th><th class="px-4 py-3 text-right">Value</th><th class="px-4 py-3 text-left">Status</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($returns as $return)
                            <tr>
                                <td class="px-4 py-3 text-gray-600">{{ $return->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-3"><a href="{{ route('seller.orders.show', $return->order) }}" class="font-medium text-primary-700 hover:underline">#{{ $return->order?->order_number }}</a></td>
                                <td class="px-4 py-3">{{ $return->items->map(fn ($l) => ($l->orderItem?->product?->name ?? 'Product').' × '.$l->quantity)->join(', ') }}</td>
                                <td class="px-4 py-3">{{ $return->reasonLabel() }}@if($return->details)<span class="block text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($return->details, 120) }}</span>@endif @if($return->photo_path)<a href="{{ route('returns.photo', $return) }}" target="_blank" rel="noopener" class="block text-xs text-primary-700 hover:underline">View photo</a>@endif</td>
                                <td class="px-4 py-3 text-right">{{ Money::format($return->items_value) }}</td>
                                <td class="px-4 py-3"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $return->status_badge }}">{{ ucfirst($return->status) }}</span>@if($return->admin_note)<span class="block text-xs text-gray-500">{{ $return->admin_note }}</span>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No returns.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3">{{ $returns->links() }}</div>
        </div>
    </div>
</div>
@endsection
