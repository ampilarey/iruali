@extends('layouts.app')

@php
    $labels = [
        'price_percent' => __('Change price by :percent%', ['percent' => ($value >= 0 ? '+' : '').rtrim(rtrim(number_format((float) $value, 2), '0'), '.')]),
        'stock_set' => __('Set stock to :n', ['n' => (int) $value]),
        'activate' => __('Activate'),
        'deactivate' => __('Deactivate'),
    ];
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Confirm bulk edit')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="rounded-lg bg-white shadow">
            <div class="border-b border-gray-100 px-6 py-4">
                <h2 class="text-base font-semibold text-gray-900">{{ $labels[$action] }}</h2>
                <p class="text-sm text-gray-600">{{ trans_choice('This will change :count product.|This will change :count products.', count($rows), ['count' => count($rows)]) }}</p>
            </div>
            <ul class="divide-y divide-gray-100">
                @foreach($rows as $row)
                    <li class="flex items-center justify-between gap-4 px-6 py-3 text-sm">
                        <div class="min-w-0">
                            <p class="font-medium text-gray-900 truncate">{{ $row['product']->name }}</p>
                            <p class="text-xs text-gray-500" dir="ltr">{{ $row['product']->sku }}</p>
                        </div>
                        <span class="text-gray-700 text-end whitespace-nowrap" dir="ltr">{{ $row['note'] }}</span>
                    </li>
                @endforeach
            </ul>
            <form method="POST" action="{{ route('seller.products.bulk.apply') }}" class="flex items-center justify-end gap-3 border-t border-gray-100 px-6 py-4">
                @csrf
                <input type="hidden" name="action" value="{{ $action }}">
                @if($value !== null)<input type="hidden" name="value" value="{{ $value }}">@endif
                @foreach($ids as $id)
                    <input type="hidden" name="ids[]" value="{{ $id }}">
                @endforeach
                <a href="{{ route('seller.products.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">{{ __('Cancel') }}</a>
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Apply to :count products', ['count' => count($rows)]) }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
