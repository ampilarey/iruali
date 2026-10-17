@extends('layouts.admin')

@section('title', 'Rewards report')

@section('content')
<div class="max-w-6xl mx-auto py-8 px-4 space-y-6">
    <div class="flex justify-between items-center">
        <div>
            <h1 class="text-xl font-bold lg:text-2xl">Rewards report</h1>
            <p class="text-sm text-gray-500">Loyalty points issued, redeemed and expired, and the customers who refer the most. Point values are set under Settings.</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
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

    @if(session('success'))<div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>@endif
    @if(auth()->user()->hasRole('admin'))
        <form method="POST" action="{{ route('admin.rewards.adjust') }}">
            @csrf
            <x-admin.fold title="Adjust a customer's points" body="space-y-3 text-sm" :open="$errors->any()" id="points-adjust">
                <p class="text-xs text-gray-500">Give points (a goodwill gesture) or take them back (a mistake). The customer's ledger shows the reason; the audit log records who did it.</p>
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <div><label for="adj-email" class="block text-xs font-medium text-gray-700">Customer's email</label>
                        <input id="adj-email" name="email" type="email" required value="{{ old('email') }}" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        @error('email')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="adj-points" class="block text-xs font-medium text-gray-700">Points (minus to take away)</label>
                        <input id="adj-points" name="points" type="number" required value="{{ old('points') }}" placeholder="100 or -50" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        @error('points')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                    <div><label for="adj-reason" class="block text-xs font-medium text-gray-700">Reason</label>
                        <input id="adj-reason" name="reason" required maxlength="200" value="{{ old('reason') }}" placeholder="Sorry for the late delivery" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2">
                        @error('reason')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror</div>
                </div>
                <button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">Save</button>
            </x-admin.fold>
        </form>
    @endif

    <x-admin.fold title="Top referrers" :open="true">
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
    </x-admin.fold>

    <x-admin.fold title="Latest point movements">
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
    </x-admin.fold>
</div>
@endsection
