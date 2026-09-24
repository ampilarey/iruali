@extends('layouts.app')

@section('content')
@php $max = max($months->max(), 1); @endphp
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => 'Analytics'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach([
                ['Revenue', \App\Support\Money::format($stats['total_revenue'])],
                ['Orders', $stats['total_orders']],
                ['Units sold', $stats['units_sold']],
                ['Average per order', \App\Support\Money::format($stats['average_order'])],
            ] as [$label, $value])
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="rounded-lg bg-white p-5 shadow">
            <h2 class="text-lg font-semibold text-gray-900">Revenue, last 12 months</h2>
            <div class="mt-6 flex h-48 items-end gap-2">
                @foreach($months as $month => $total)
                    <div class="flex h-full flex-1 flex-col items-center justify-end gap-1" title="{{ \Illuminate\Support\Carbon::parse($month . '-01')->format('M Y') }}: {{ \App\Support\Money::format($total) }}">
                        <div class="w-full rounded-t bg-primary-500" style="height: {{ max($total / $max * 100, $total > 0 ? 2 : 0) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex gap-2">
                @foreach($months as $month => $total)
                    <span class="flex-1 text-center text-[10px] text-gray-500">{{ \Illuminate\Support\Carbon::parse($month . '-01')->format('M') }}</span>
                @endforeach
            </div>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-lg font-semibold text-gray-900">Top products</h2>
            </div>
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Product</th>
                        <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Units</th>
                        <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Revenue</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($topProducts as $row)
                        <tr>
                            <td class="px-5 py-3 text-sm text-gray-900">{{ $row->product->name ?? 'Deleted product' }}</td>
                            <td class="px-5 py-3 text-right text-sm text-gray-700">{{ $row->units }}</td>
                            <td class="px-5 py-3 text-right text-sm text-gray-900">{{ \App\Support\Money::format($row->revenue) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-500">No sales yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
