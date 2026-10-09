@extends('layouts.app')

@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $onHoliday = $user->isOnHoliday();
    // The back-on date has passed: holiday mode ended by itself
    $holidayEnded = $user->holiday_mode && ! $onHoliday;
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Holiday mode')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('seller.settings._nav')

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-lg p-5 shadow text-sm {{ $onHoliday ? 'border border-amber-200 bg-amber-50 text-amber-900' : 'bg-white text-gray-700' }}" data-holiday-status="{{ $onHoliday ? 'on' : 'off' }}">
            @if($onHoliday)
                <h2 class="flex items-center gap-2 font-semibold"><x-icon name="clock" class="w-5 h-5" />{{ $user->holiday_until ? __('Your shop is on holiday until :date.', ['date' => \App\Support\ShopHoliday::date($user)]) : __('Your shop is on holiday until you switch it off.') }}</h2>
                <p class="mt-1">{{ __('Customers can see your products but cannot add them to a cart or order them. Keep sending the orders you already have.') }}</p>
            @else
                <h2 class="font-semibold text-gray-900">{{ __('Going away?') }}</h2>
                <p class="mt-1">{{ __('Switch on holiday mode and customers cannot order from your shop until you are back. Your products stay listed, so people (and search engines) can still find them.') }}</p>
                @if($holidayEnded && $user->holiday_until)
                    <p class="mt-2 text-gray-500">{{ __('Your last holiday ended on :date, so customers can order again.', ['date' => \App\Support\ShopHoliday::date($user)]) }}</p>
                @endif
            @endif
        </div>

        <form method="POST" action="{{ route('seller.settings.holiday.update') }}" class="space-y-5 rounded-lg bg-white p-6 shadow" data-holiday-form>
            @csrf
            @method('PUT')

            <div class="flex items-start justify-between gap-4">
                <label for="on_holiday" class="flex-1 cursor-pointer">
                    <span class="block text-sm font-medium text-gray-900">{{ __('On holiday') }}</span>
                    <span class="block text-xs text-gray-500">{{ __('Customers cannot add your products to a cart or order them while this is on.') }}</span>
                </label>
                <input type="hidden" name="on_holiday" value="0">
                <input id="on_holiday" type="checkbox" name="on_holiday" value="1" class="mt-1 h-5 w-5 rounded border-gray-300 text-primary-600" @checked(old('on_holiday', $onHoliday ? '1' : '0') === '1')>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="holiday_until" class="block text-sm font-medium text-gray-700">{{ __('Back on') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="holiday_until" name="holiday_until" type="date" min="{{ today()->addDay()->toDateString() }}" max="{{ today()->addYear()->toDateString() }}" class="{{ $field }}" value="{{ old('holiday_until', $onHoliday ? $user->holiday_until?->toDateString() : '') }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('The first day you take orders again. Holiday mode switches off by itself on this date. Leave it empty to switch it off yourself.') }}</p>
                </div>
            </div>

            <div>
                <label for="holiday_message" class="block text-sm font-medium text-gray-700">{{ __('Message for customers') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                <textarea id="holiday_message" name="holiday_message" rows="3" maxlength="500" class="{{ $field }}" placeholder="{{ __('e.g. We are away for Eid. Orders open again soon, thank you for waiting!') }}">{{ old('holiday_message', $user->holiday_message) }}</textarea>
                <p class="mt-1 text-xs text-gray-500">{{ __('Shown on your products and your shop page while you are away. Up to 500 characters.') }}</p>
            </div>

            <div class="rounded-lg bg-gray-50 p-3 text-xs text-gray-600">
                <p class="font-medium text-gray-700">{{ __('While you are on holiday') }}</p>
                <ul class="mt-1 list-disc space-y-0.5 ps-5">
                    <li>{{ __('Your products stay visible, with an "On holiday" label.') }}</li>
                    <li>{{ __('Nobody can add them to a cart or check out; items already in carts wait there.') }}</li>
                    <li>{{ __('Orders placed before your holiday are not affected: please still send them.') }}</li>
                </ul>
            </div>

            <div class="flex justify-end border-t border-gray-100 pt-4">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save holiday settings') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
