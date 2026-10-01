@extends('layouts.app')

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Performance')])

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <p class="text-sm text-gray-600">{{ __('How your shop is doing. iruali looks at the same numbers; see the help centre for what counts as late and how returns are charged.') }} <a href="{{ route('seller.help.show', 'packing-shipping') }}" class="font-medium text-primary-600 hover:underline">{{ __('Packing and shipping an order') }}</a></p>

        @include('seller.partials.performance', ['report' => $report])

        @if($report['late']['parts']->isNotEmpty())
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="border-b border-gray-100 px-5 py-4"><h2 class="text-lg font-semibold text-gray-900">{{ __('Late shipments') }}</h2><p class="text-xs text-gray-500">{{ __('Paid orders not shipped within :n days of payment, last :days days.', ['n' => $report['late_days'], 'days' => $report['window_days']]) }}</p></div>
                <ul class="divide-y divide-gray-100 text-sm">
                    @foreach($report['late']['parts'] as $part)
                        <li class="flex flex-wrap items-center justify-between gap-3 px-5 py-3">
                            <span><a href="{{ route('seller.orders.show', $part->order_id) }}" class="font-medium text-primary-700 hover:underline">#{{ $part->order?->order_number }}</a> · {{ __('Paid') }} {{ \Illuminate\Support\Carbon::parse($part->order_paid_at)->format('d M') }}</span>
                            <span class="{{ $part->shipped_at ? 'text-gray-600' : 'text-red-700 font-medium' }}">{{ $part->shipped_at ? __('Shipped :date', ['date' => $part->shipped_at->format('d M')]) : __('Not shipped yet') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</div>
@endsection
