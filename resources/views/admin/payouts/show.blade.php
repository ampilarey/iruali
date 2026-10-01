@extends('layouts.app')

@php use App\Support\Money; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => 'Payout #'.$payout->id, 'back' => route('admin.payouts')])

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="rounded-lg bg-white p-5 shadow grid gap-3 sm:grid-cols-2 text-sm">
            <div><p class="text-gray-500">Shop</p><p class="font-semibold text-gray-900">{{ $payout->seller?->business_name ?: $payout->seller?->name }}</p></div>
            <div><p class="text-gray-500">Amount</p><p class="text-2xl font-bold text-gray-900">{{ Money::format($payout->amount) }}</p></div>
            <div><p class="text-gray-500">Paid</p><p>{{ $payout->paid_at->format('d M Y, H:i') }}@if($payout->creator) by {{ $payout->creator->name }}@endif</p></div>
            <div><p class="text-gray-500">Reference</p><p class="font-mono">{{ $payout->reference ?: '—' }}</p></div>
            @if($payout->note)<div class="sm:col-span-2"><p class="text-gray-500">Note</p><p>{{ $payout->note }}</p></div>@endif
            <div class="sm:col-span-2"><a href="{{ route('admin.payouts.show', [$payout, 'export' => 'csv']) }}" class="inline-block rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">Download statement (CSV)</a></div>
        </div>
        <div class="overflow-x-auto rounded-lg bg-white shadow">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr><th class="px-4 py-2 text-left">Order</th><th class="px-4 py-2 text-right">Items</th><th class="px-4 py-2 text-right">Commission</th><th class="px-4 py-2 text-right">Shop earns</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($payout->sellerOrders as $part)
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('admin.orders.show', $part->order) }}" class="text-primary-700 hover:underline">#{{ $part->order?->order_number }}</a></td>
                            <td class="px-4 py-2 text-right">{{ Money::format($part->subtotal) }}</td>
                            <td class="px-4 py-2 text-right text-gray-600">&minus;{{ Money::format($part->commission_amount) }}</td>
                            <td class="px-4 py-2 text-right font-semibold">{{ Money::format($part->seller_earnings) }}</td>
                        </tr>
                    @endforeach
                    @foreach($payout->adjustments as $adjustment)
                        <tr>
                            <td class="px-4 py-2" colspan="3">{{ $adjustment->reason }}</td>
                            <td class="px-4 py-2 text-right font-semibold {{ $adjustment->amount < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $adjustment->amount < 0 ? '−' : '+' }}{{ Money::format(abs($adjustment->amount)) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
