@extends('layouts.app')

@php
    $states = [
        'live' => [__('Live'), 'bg-green-100 text-green-800'],
        'paused' => [__('Paused'), 'bg-gray-200 text-gray-700'],
        'scheduled' => [__('Scheduled'), 'bg-blue-100 text-blue-800'],
        'expired' => [__('Ended'), 'bg-yellow-100 text-yellow-800'],
        'used_up' => [__('Used up'), 'bg-yellow-100 text-yellow-800'],
    ];
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => __('Discount codes')])
        @slot('action')
            <a href="{{ route('seller.discounts.create') }}" class="inline-flex items-center rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('+ New code') }}</a>
        @endslot
    @endcomponent

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
        <p class="text-sm text-gray-600">{{ __('Codes your customers enter in the cart for money off your items. Your shop pays for the discount: iruali\'s commission and your earnings are worked out on the discounted price. A customer can use one of your codes together with one iruali voucher.') }}</p>

        @if($codes->isEmpty())
            <div class="rounded-lg bg-white p-8 text-center text-gray-500 shadow">{{ __('You have no discount codes yet.') }}</div>
        @else
            <div class="overflow-hidden rounded-lg bg-white shadow">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 text-sm">
                        <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-2 text-start">{{ __('Code') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Discount') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Runs') }}</th>
                                <th class="px-4 py-2 text-end">{{ __('Uses') }}</th>
                                <th class="px-4 py-2 text-end">{{ __('Discount given') }}</th>
                                <th class="px-4 py-2 text-end">{{ __('Sales') }}</th>
                                <th class="px-4 py-2 text-start">{{ __('Status') }}</th>
                                <th class="px-4 py-2"><span class="sr-only">{{ __('Actions') }}</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach($codes as $code)
                                @php
                                    $figures = $stats[$code->id];
                                    $state = $code->state();
                                    if ($state === 'live' && $code->max_uses !== null && $figures['uses'] >= $code->max_uses) {
                                        $state = 'used_up';
                                    }
                                @endphp
                                <tr>
                                    <td class="px-4 py-3 align-top">
                                        <span class="font-mono font-semibold text-gray-900" dir="ltr">{{ $code->code }}</span>
                                        <span class="block text-xs text-gray-500">{{ $code->appliesToAllProducts() ? __('All your products') : trans_choice(':count product|:count products', $code->products_count, ['count' => $code->products_count]) }}</span>
                                    </td>
                                    <td class="px-4 py-3 align-top">
                                        <span class="font-medium text-gray-900">{{ __(':value off', ['value' => $code->valueLabel()]) }}</span>
                                        @if((float) $code->min_spend > 0)<span class="block text-xs text-gray-500">{{ __('Minimum spend :amount', ['amount' => \App\Support\Money::format($code->min_spend)]) }}</span>@endif
                                        @if($code->max_uses_per_customer)<span class="block text-xs text-gray-500">{{ trans_choice(':count use per customer|:count uses per customer', $code->max_uses_per_customer, ['count' => $code->max_uses_per_customer]) }}</span>@endif
                                    </td>
                                    <td class="px-4 py-3 align-top text-xs text-gray-600">
                                        <span class="block">{{ $code->starts_at ? __('From :date', ['date' => $code->starts_at->translatedFormat('j M Y, H:i')]) : __('From now') }}</span>
                                        <span class="block">{{ $code->ends_at ? __('Until :date', ['date' => $code->ends_at->translatedFormat('j M Y, H:i')]) : __('No end date') }}</span>
                                    </td>
                                    <td class="px-4 py-3 align-top text-end" dir="ltr">{{ $figures['uses'] }}@if($code->max_uses !== null)<span class="text-gray-500"> / {{ $code->max_uses }}</span>@endif</td>
                                    <td class="px-4 py-3 align-top text-end" dir="ltr">{{ \App\Support\Money::format($figures['discount']) }}</td>
                                    <td class="px-4 py-3 align-top text-end font-semibold" dir="ltr">{{ \App\Support\Money::format($figures['sales']) }}</td>
                                    <td class="px-4 py-3 align-top"><span class="rounded-full px-2 py-0.5 text-xs font-semibold {{ $states[$state][1] }}">{{ $states[$state][0] }}</span></td>
                                    <td class="px-4 py-3 align-top text-end whitespace-nowrap">
                                        <a href="{{ route('seller.discounts.edit', $code) }}" class="font-medium text-primary-600 hover:text-primary-700">{{ __('Edit') }}</a>
                                        <form method="POST" action="{{ route('seller.discounts.toggle', $code) }}" class="inline">
                                            @csrf
                                            <button type="submit" class="ms-3 font-medium text-gray-600 hover:text-gray-900">{{ $code->is_active ? __('Pause') : __('Resume') }}</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="text-xs text-gray-500">{{ __('Uses and sales count orders that were not cancelled. Sales are your items on those orders, after your discounts.') }}</p>
        @endif
    </div>
</div>
@endsection
