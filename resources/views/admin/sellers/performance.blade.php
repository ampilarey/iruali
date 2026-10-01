@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __(':shop performance', ['shop' => $seller->shopName()]), 'back' => route('admin.sellers')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="flex flex-wrap items-center gap-3 text-sm text-gray-600">
            <span>{{ $seller->email }}</span>
            @if($seller->phone)<span>· {{ $seller->phone }}</span>@endif
            <span>· {{ __('Commission') }} {{ rtrim(rtrim(number_format($seller->effectiveCommissionRate(), 2), '0'), '.') }}%</span>
            <a href="{{ route('admin.payouts.create', $seller) }}" class="ms-auto rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('Pay out') }}</a>
            <a href="{{ route('sellers.show', $seller) }}" target="_blank" rel="noopener" class="rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ __('View shop') }} ↗</a>
        </div>

        @include('seller.partials.performance', ['report' => $report])

        <div class="overflow-hidden rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">{{ __('Late shipments') }}</h2><p class="text-xs text-gray-500">{{ __('Paid orders not shipped within :n days of payment, last :days days.', ['n' => $report['late_days'], 'days' => $report['window_days']]) }}</p></div>
            <div class="overflow-x-auto"><table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                    <tr><th class="px-4 py-2 text-start">{{ __('Order') }}</th><th class="px-4 py-2 text-start">{{ __('Paid') }}</th><th class="px-4 py-2 text-start">{{ __('Shipped') }}</th><th class="px-4 py-2 text-start">{{ __('Status') }}</th><th class="px-4 py-2 text-end">{{ __('Days late') }}</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($report['late']['parts'] as $part)
                        @php $paid = \Illuminate\Support\Carbon::parse($part->order_paid_at); $deadline = $paid->copy()->addDays($report['late_days']); @endphp
                        <tr>
                            <td class="px-4 py-2"><a href="{{ route('admin.orders.show', $part->order_id) }}" class="font-medium text-primary-700 hover:underline">#{{ $part->order?->order_number }}</a></td>
                            <td class="px-4 py-2 text-gray-600">{{ $paid->format('d M Y') }}</td>
                            <td class="px-4 py-2 {{ $part->shipped_at ? 'text-gray-600' : 'text-red-700 font-medium' }}">{{ $part->shipped_at ? $part->shipped_at->format('d M Y') : __('Not shipped yet') }}</td>
                            <td class="px-4 py-2"><span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $part->status_badge }}">{{ \App\Support\OrderStatus::label($part->status) }}</span></td>
                            <td class="px-4 py-2 text-end">{{ (int) $deadline->diffInDays($part->shipped_at ?? now()) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-gray-500">{{ __('No late shipments.') }}</td></tr>
                    @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
