@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Shop payouts'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow"><p class="text-sm text-gray-500">Ready to pay shops</p><p class="mt-1 text-2xl font-bold text-green-700">{{ Money::format($totals['available']) }}</p></div>
            <div class="rounded-lg bg-white p-5 shadow"><p class="text-sm text-gray-500">Pending (not delivered or unpaid)</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ Money::format($totals['pending']) }}</p></div>
            <div class="rounded-lg bg-white p-5 shadow"><p class="text-sm text-gray-500">Paid to shops</p><p class="mt-1 text-2xl font-bold text-gray-900">{{ Money::format($totals['paid']) }}</p></div>
            <div class="rounded-lg bg-white p-5 shadow"><p class="text-sm text-gray-500">iruali commission (delivered)</p><p class="mt-1 text-2xl font-bold text-primary-700">{{ Money::format($totals['commission']) }}</p></div>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('admin.payout-batches.create') }}" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('New payout batch') }}</a>
            <a href="{{ route('admin.payout-batches.index') }}" class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Payout batches') }}</a>
            <span class="text-xs text-gray-500">{{ __('Pay several shops in one BML bulk transfer file.') }}</span>
        </div>
        @include('admin.payouts._verification_rule')
        <p class="text-sm text-gray-600">Shops earn their item sales minus commission. Earnings are payable once a shop's part is delivered and the customer's payment is confirmed. Default commission: <strong>{{ rtrim(rtrim(number_format($defaultRate, 2), '0'), '.') }}%</strong> (change it in <a href="{{ route('admin.settings') }}" class="text-primary-700 hover:underline">Settings</a>).</p>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-3 text-left">Shop</th><th class="px-4 py-3 text-left">Commission</th><th class="px-4 py-3 text-right">Ready</th><th class="px-4 py-3 text-right">Pending</th><th class="px-4 py-3 text-right">Paid</th><th class="px-4 py-3 text-left">Bank account</th><th class="px-4 py-3"></th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($sellers as $seller)
                            <tr>
                                <td class="px-4 py-3"><p class="font-medium text-gray-900">{{ $seller->business_name ?: $seller->name }}</p><p class="text-xs text-gray-500">{{ $seller->email }}</p></td>
                                <td class="px-4 py-3">
                                    <form method="POST" action="{{ route('admin.sellers.commission', $seller) }}" class="flex items-center gap-1">
                                        @csrf
                                        <label for="rate-{{ $seller->id }}" class="sr-only">Commission for {{ $seller->business_name ?: $seller->name }}</label>
                                        <input id="rate-{{ $seller->id }}" name="commission_rate" type="number" step="0.01" min="0" max="100" value="{{ $seller->commission_rate !== null ? rtrim(rtrim(number_format((float) $seller->commission_rate, 2), '0'), '.') : '' }}" placeholder="{{ rtrim(rtrim(number_format($defaultRate, 2), '0'), '.') }}" class="w-20 rounded-lg border border-gray-300 px-2 py-1 text-sm">
                                        <span class="text-gray-500">%</span>
                                        <button class="rounded-lg border border-gray-300 px-2 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50">Save</button>
                                    </form>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-green-700">{{ Money::format($seller->balances['available']) }}</td>
                                <td class="px-4 py-3 text-right">{{ Money::format($seller->balances['pending']) }}</td>
                                <td class="px-4 py-3 text-right">{{ Money::format($seller->balances['paid']) }}</td>
                                <td class="px-4 py-3 text-xs text-gray-600">
                                    @if($seller->bankAccount)
                                        {{ $seller->bankAccount->account_name }}<br>{{ $seller->bankAccount->bankName() }} · <span class="font-mono" dir="ltr">{{ $seller->bankAccount->account_number }}</span>
                                        <form method="POST" action="{{ route('admin.sellers.bank.verify', $seller) }}" class="mt-1">
                                            @csrf
                                            <button class="rounded-full px-2 py-0.5 text-xs font-medium {{ $seller->bankAccount->isVerified() ? 'bg-green-100 text-green-800 hover:bg-green-200' : 'bg-amber-100 text-amber-800 hover:bg-amber-200' }}" title="{{ __('Click to change') }}">{{ $seller->bankAccount->isVerified() ? __('Verified') : __('Not verified') }}</button>
                                        </form>
                                    @else
                                        <span class="text-amber-700">{{ __('Not added yet') }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @if($seller->balances['available'] > 0)
                                        @if($seller->bankAccount && ! \App\Models\SellerVerification::payoutHeld($seller))
                                            <a href="{{ route('admin.payouts.create', $seller) }}" class="rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-primary-700">Pay out</a>
                                        @elseif($seller->bankAccount)
                                            <span class="text-xs text-amber-700" title="{{ app(\App\Services\PayoutService::class)->payoutBlockedReason($seller) }}" data-payout-held-shop>{{ __('Held: business not verified') }}</span>
                                        @else
                                            <span class="text-xs text-amber-700" title="{{ __('This shop has not added a bank account yet, so it cannot be paid out.') }}">{{ __('No bank account') }}</span>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-10 text-center text-gray-500">No shops yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">Recent payouts</h2></div>
            <ul class="divide-y divide-gray-100 text-sm">
                @forelse($recent as $payout)
                    <li class="px-5 py-3 flex flex-wrap items-center justify-between gap-3">
                        <span><a href="{{ route('admin.payouts.show', $payout) }}" class="font-medium text-primary-700 hover:underline">#{{ $payout->id }}</a> · {{ $payout->seller?->business_name ?: $payout->seller?->name }} · @if($payout->isPaid()){{ $payout->paid_at?->format('d M Y') }}@else<span class="text-amber-700">{{ __('In batch') }} {{ $payout->batch?->reference }}</span>@endif @if($payout->reference) · ref {{ $payout->reference }}@endif</span>
                        <span class="font-semibold">{{ Money::format($payout->amount) }}</span>
                    </li>
                @empty
                    <li class="px-5 py-6 text-center text-gray-500">No payouts yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
@endsection
