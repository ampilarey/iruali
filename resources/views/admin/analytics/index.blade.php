@extends('layouts.app')

@section('content')
@php
    $maxRevenue = max($months->max('revenue'), 1);
    $statusColors = [
        'pending' => 'bg-yellow-400', 'processing' => 'bg-blue-500', 'shipped' => 'bg-purple-500',
        'delivered' => 'bg-green-500', 'cancelled' => 'bg-red-500',
    ];
    $totalOrders = max($ordersByStatus->sum(), 1);
@endphp
<div class="min-h-screen bg-gray-100 pb-12">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Analytics</h1>
                </div>
                <div class="flex items-center">
                    <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @foreach([
                ['Revenue (all time)', \App\Support\Money::format($stats['revenue']), 'Excludes cancelled orders'],
                ['Revenue (30 days)', \App\Support\Money::format($stats['revenue_30d']), 'Average order ' . \App\Support\Money::format($stats['average_order'])],
                ['Orders', number_format($stats['total_orders']), number_format($stats['total_products']) . ' products listed'],
                ['Users', number_format($stats['total_users']), $stats['total_sellers'] . ' sellers · ' . $stats['new_users_30d'] . ' new in 30 days'],
            ] as [$label, $value, $hint])
                <div class="rounded-lg bg-white p-5 shadow">
                    <p class="text-sm font-medium text-gray-500">{{ $label }}</p>
                    <p class="mt-1 text-2xl font-semibold text-gray-900">{{ $value }}</p>
                    <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-lg bg-white p-5 shadow lg:col-span-2">
                <h2 class="text-lg font-semibold text-gray-900">Revenue, last 12 months</h2>
                <div class="mt-6 flex h-48 items-end gap-2">
                    @foreach($months as $month => $row)
                        <div class="flex h-full flex-1 flex-col justify-end" title="{{ \Illuminate\Support\Carbon::parse($month . '-01')->format('M Y') }}: {{ \App\Support\Money::format($row['revenue']) }} · {{ $row['users'] }} new users">
                            <div class="w-full rounded-t bg-primary-500" style="height: {{ max($row['revenue'] / $maxRevenue * 100, $row['revenue'] > 0 ? 2 : 0) }}%"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-2">
                    @foreach($months as $month => $row)
                        <span class="flex-1 text-center text-[10px] text-gray-500">{{ \Illuminate\Support\Carbon::parse($month . '-01')->format('M') }}</span>
                    @endforeach
                </div>
            </div>

            <div class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Orders by status</h2>
                <ul class="mt-4 space-y-3">
                    @forelse($ordersByStatus as $status => $count)
                        <li>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-700">{{ ucfirst($status) }}</span>
                                <span class="font-medium text-gray-900">{{ $count }}</span>
                            </div>
                            <div class="mt-1 h-2 rounded-full bg-gray-100">
                                <div class="h-2 rounded-full {{ $statusColors[$status] ?? 'bg-gray-400' }}" style="width: {{ $count / $totalOrders * 100 }}%"></div>
                            </div>
                        </li>
                    @empty
                        <li class="text-sm text-gray-500">No orders yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            @foreach([['Top products', $topProducts, 'Product'], ['Top sellers', $topSellers, 'Seller']] as [$title, $rows, $col])
                <div class="overflow-hidden rounded-lg bg-white shadow">
                    <div class="border-b border-gray-100 px-5 py-4">
                        <h2 class="text-lg font-semibold text-gray-900">{{ $title }}</h2>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">{{ $col }}</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">{{ $col === 'Product' ? 'Units' : 'Orders' }}</th>
                                <th class="px-5 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Revenue</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($rows as $row)
                                <tr>
                                    <td class="px-5 py-3 text-sm text-gray-900">{{ $col === 'Product' ? ($row->product->name ?? 'Deleted product') : $row->name }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-700">{{ $col === 'Product' ? $row->units : $row->orders }}</td>
                                    <td class="px-5 py-3 text-right text-sm text-gray-900">{{ \App\Support\Money::format($row->revenue) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-5 py-8 text-center text-sm text-gray-500">No sales yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
