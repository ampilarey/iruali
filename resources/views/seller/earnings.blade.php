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
                <p class="mt-1 text-xs text-gray-500">Delivered and paid by the customer.</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <p class="text-sm text-gray-500">Pending</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ Money::format($balances['pending']) }}</p>
                <p class="mt-1 text-xs text-gray-500">Not delivered or not paid yet.</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <p class="text-sm text-gray-500">Paid to you</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ Money::format($balances['paid']) }}</p>
                <p class="mt-1 text-xs text-gray-500">All payouts so far.</p>
            </div>
            <div class="rounded-lg bg-white p-5 shadow">
                <p class="text-sm text-gray-500">Commission</p>
                <p class="mt-1 text-2xl font-bold text-gray-900">{{ rtrim(rtrim(number_format($rate, 2), '0'), '.') }}%</p>
                <p class="mt-1 text-xs text-gray-500">Of your item sales. Delivery fees are not included.</p>
            </div>
        </div>

        @if(! $user->payout_account_number)
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                Add your bank account in <a href="{{ route('seller.profile') }}" class="font-semibold underline">Profile</a> so iruali can pay you.
            </div>
        @else
            <p class="text-sm text-gray-600">Payouts go to {{ $user->payout_account_name }} · {{ $user->payout_bank_name }} · {{ $user->payout_account_number }}.</p>
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
                                    <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $part->status_badge }}">{{ ucfirst($part->status) }}</span></td>
                                    <td class="px-4 py-2 text-right">{{ Money::format($part->subtotal) }}</td>
                                    <td class="px-4 py-2 text-right text-gray-600">&minus;{{ Money::format($part->commission_amount) }}</td>
                                    <td class="px-4 py-2 text-right font-semibold">{{ Money::format($part->seller_earnings) }}</td>
                                    <td class="px-4 py-2 text-xs">
                                        @if($state === 'paid_out')<span class="text-green-700 font-medium">Paid {{ $part->payout?->paid_at?->format('d M') }}</span>
                                        @elseif($state === 'available')<span class="text-green-700">Ready</span>
                                        @elseif($state === 'cancelled')<span class="text-gray-400">Cancelled</span>
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
                            <span>{{ $payout->paid_at->format('d M Y') }}@if($payout->reference)<span class="block text-xs text-gray-500">Ref {{ $payout->reference }}</span>@endif</span>
                            <span class="font-semibold">{{ Money::format($payout->amount) }}</span>
                        </li>
                    @empty
                        <li class="px-5 py-6 text-center text-gray-500">No payouts yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
