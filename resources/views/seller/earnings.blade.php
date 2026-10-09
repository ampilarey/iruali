@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => 'Earnings'])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg bg-white p-5 shadow">
                <p class="text-sm text-gray-500">Ready for payout</p>
                <p class="mt-1 text-2xl font-bold text-green-700">{{ Money::format($balances['available']) }}</p>
                <p class="mt-1 text-xs text-gray-500">Delivered and paid by the customer{{ $balances['adjustments'] != 0 ? ', after return deductions of '.Money::format(abs($balances['adjustments'])) : '' }}.</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <p class="text-sm text-gray-500">Pending</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ Money::format($balances['pending']) }}</p>
                <p class="mt-1 text-xs text-gray-500">Not delivered or not paid yet.</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <p class="text-sm text-gray-500">Paid to you</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ Money::format($balances['paid']) }}</p>
                <p class="mt-1 text-xs text-gray-500">All payouts so far.@if($balances['processing'] > 0) {{ __(':amount is on its way in a payout batch.', ['amount' => Money::format($balances['processing'])]) }}@endif</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <p class="text-sm text-gray-500">Commission</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%</p>
                <p class="mt-1 text-xs text-gray-500">Of your item sales. Delivery fees are not included.</p>
            </div>
        </div>

        @if(! $user->bankAccount)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ __('Add your bank account so iruali can pay you. Without it your earnings stay on hold.') }} <a href="{{ route('seller.settings.bank') }}" class="font-semibold underline">{{ __('Bank account') }}</a>
            </div>
        @else
            <p class="text-sm text-gray-600">{{ __('Payouts go to') }} {{ $user->bankAccount->account_name }} · {{ $user->bankAccount->bankName() }} · <span class="font-mono" dir="ltr">{{ $user->bankAccount->maskedNumber() }}</span>.</p>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="lg:col-span-2 overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">Earnings by order</h2></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr><th class="px-4 py-2 text-left">Order</th><th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2 text-right">Items</th><th class="px-4 py-2 text-right">Commission</th><th class="px-4 py-2 text-right">You earn</th><th class="px-4 py-2 text-left">Payout</th></tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse($parts as $part)
                                @php $state = $part->earningsState(); @endphp
                                <tr>
                                    <td class="px-4 py-2"><a href="{{ route('seller.orders.show', $part->order) }}" class="font-medium text-primary-700 hover:underline">#{{ $part->order?->order_number }}</a><span class="block text-xs text-gray-500">{{ $part->created_at->format('d M Y') }}</span></td>
                                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $part->status_badge }}">{{ \App\Support\OrderStatus::label($part->status) }}</span></td>
                                    <td class="px-4 py-2 text-right">{{ Money::format($part->subtotal) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-600">&minus;{{ Money::format($part->commission_amount) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ Money::format($part->seller_earnings) }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        @if($state === 'paid_out')<span class="text-green-700 font-medium">Paid {{ $part->payout?->paid_at?->format('d M') }}</span>
                                        @elseif($state === 'processing')<span class="text-blue-700">{{ __('Payout on its way') }}</span>
                                        @elseif($state === 'available')<span class="text-green-700">Ready</span>
                                        @elseif($state === 'cancelled')<span class="text-gray-500">Cancelled</span>
                                        @else<span class="text-gray-500">Pending</span>@endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="px-4 py-10 text-center text-gray-500">No sales yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3">{{ $parts->links() }}</div>
            </div>

            <div class="overflow-hidden rounded-lg bg-white shadow self-start">
                <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">Payouts</h2></div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @forelse($payoutHistory as $payout)
                        <li class="px-5 py-3 flex justify-between gap-3">
                            <span>
                                @if($payout->isPaid()){{ $payout->paid_at?->format('d M Y') }}@else<span class="text-blue-700">{{ __('On its way') }}</span>@endif
                                @if($payout->batch)<span class="block text-xs text-gray-500">{{ __('Batch') }} {{ $payout->batch->reference }}@if($payout->batch->bank_reference) · {{ $payout->batch->bank_reference }}@endif</span>@endif
                                @if($payout->reference)<span class="block text-xs text-gray-500">Ref {{ $payout->reference }}</span>@endif
                                @include('tax.partials.payout-invoice-link', ['payout' => $payout, 'route' => 'seller.payouts.invoice', 'class' => 'block text-xs font-medium text-primary-700 hover:underline'])
                            </span>
                            <span class="font-semibold">{{ Money::format($payout->amount) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-gray-500">No payouts yet.</li>
                    @endforelse
                </ul>
                @if($adjustments->isNotEmpty())
                    <div class="border-t border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">Returns &amp; adjustments</h2><p class="text-xs text-gray-500">Your share of approved returns, taken from your next payout.</p></div>
                    <ul class="divide-y divide-gray-100 text-sm">
                        @foreach($adjustments as $adjustment)
                            <li class="px-5 py-3 flex justify-between gap-3">
                                <span>{{ $adjustment->reason }}<span class="block text-xs text-gray-500">{{ $adjustment->created_at->format('d M Y') }} · {{ $adjustment->payout ? 'Settled '.$adjustment->payout->paid_at->format('d M') : 'Next payout' }}</span></span>
                                <span class="font-semibold {{ $adjustment->amount < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $adjustment->amount < 0 ? '−' : '+' }}{{ Money::format(abs($adjustment->amount)) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
