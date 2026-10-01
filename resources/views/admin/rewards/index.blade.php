@extends('layouts.app')

@section('title', 'Rewards report')

@section('content')
<div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold">Rewards report</h1>
            <p class="text-sm text-gray-500">Loyalty points issued, redeemed and expired, and the customers who refer the most. Point values are set under Settings.</p>
        </div>
        <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to Dashboard</a>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach([
            ['Points issued', $totals['issued'], 'earned on orders '.number_format($totals['earned']).' + referrals '.number_format($totals['referral'])],
            ['Points redeemed', $totals['redeemed'], 'MVR '.number_format($totals['redeemed'], 2).' of discounts given'],
            ['Points expired', $totals['expired'], 'removed by the monthly expiry job'],
            ['Outstanding balance', $totals['outstanding'], 'points customers can still spend (MVR '.number_format($totals['outstanding'], 2).')'],
        ] as [$label, $value, $hint])
            <div class="rounded-lg bg-white p-5 shadow">
                <p class="text-sm text-gray-500">{{ $label }}</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($value) }}</p>
                <p class="mt-1 text-xs text-gray-500">{{ $hint }}</p>
            </div>
        @endforeach
    </div>
    <p class="text-xs text-gray-500">Also: {{ number_format($totals['refunded']) }} points returned on cancelled orders, {{ number_format($totals['adjustments']) }} from adjustments and opening balances.</p>

    <section class="rounded-lg bg-white shadow overflow-hidden">
        <h2 class="px-5 py-3 font-semibold text-gray-900 border-b border-gray-200">Top referrers</h2>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">Customer</th><th class="p-3 text-start">Code</th><th class="p-3 text-end">Signed up</th><th class="p-3 text-end">Rewarded</th><th class="p-3 text-end">Points earned</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($topReferrers as $user)
                    <tr>
                        <td class="p-3">{{ $user->name }}<br><span class="text-xs text-gray-500">{{ $user->email }}</span></td>
                        <td class="p-3 font-mono">{{ $user->referral_code }}</td>
                        <td class="p-3 text-end">{{ $user->referrals_count }}</td>
                        <td class="p-3 text-end">{{ $user->rewarded_referrals_count }}</td>
                        <td class="p-3 text-end">{{ number_format($referralPoints[$user->id] ?? 0) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-gray-500">Nobody has been referred yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>

    <section class="rounded-lg bg-white shadow overflow-hidden">
        <h2 class="px-5 py-3 font-semibold text-gray-900 border-b border-gray-200">Latest point movements</h2>
        <table class="w-full text-sm">
            <thead><tr class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500"><th class="p-3 text-start">When</th><th class="p-3 text-start">Customer</th><th class="p-3 text-start">Type</th><th class="p-3 text-start">Order</th><th class="p-3 text-end">Points</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                @forelse($recent as $row)
                    <tr>
                        <td class="p-3 whitespace-nowrap">{{ $row->created_at->format('d M Y H:i') }}</td>
                        <td class="p-3">{{ $row->user?->name ?? '—' }}</td>
                        <td class="p-3">{{ ucfirst($row->type) }}@if($row->note) <span class="text-xs text-gray-500">· {{ $row->note }}</span>@endif</td>
                        <td class="p-3">@if($row->order)<a href="{{ route('admin.orders.show', $row->order) }}" class="text-primary-600 hover:underline">{{ $row->order->order_number }}</a>@else—@endif</td>
                        <td class="p-3 text-end font-medium {{ $row->points < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $row->points > 0 ? '+' : '' }}{{ number_format($row->points) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center text-gray-500">No points have moved yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </section>
</div>
@endsection
