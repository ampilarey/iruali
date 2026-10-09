@extends('layouts.app')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap items-center justify-between gap-3 py-4">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-primary-600">{{ __('Settings') }}</p>
                    <h1 class="text-2xl font-bold text-gray-900">{{ __('Delivery') }}</h1>
                </div>
                <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">{{ __('Back to Dashboard') }}</a>
            </div>
        </div>
        @include('admin.partials.nav')
    </div>

    <form method="POST" action="{{ route('admin.delivery.update') }}" class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        @csrf
        @method('PUT')
        @if(session('success'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Delivery rates by area') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('The fee is charged once per order for the parts that are delivered (nothing when everything is picked up), plus any bulky-item charges. Transit days are the days on the way after the shop sends the order; the shop\'s own dispatch days are added for the estimates customers see.') }}</p>
            <p class="mt-1 text-sm text-gray-500">{{ __('Greater Malé is Malé, Hulhumalé and Villimalé. An atoll left without a fee is charged the other islands\' fee; an atoll left without days uses the other islands\' days. The atolls come from the islands list.') }}</p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-3 py-2 text-start text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Area') }}</th>
                            <th class="px-3 py-2 text-start text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Fee (MVR)') }}</th>
                            <th class="px-3 py-2 text-start text-xs font-medium uppercase tracking-wider text-gray-500">{{ __('Transit days (fewest – most)') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($rows as $i => $row)
                            <tr>
                                <td class="px-3 py-2 align-top">
                                    <input type="hidden" name="rates[{{ $i }}][area]" value="{{ $row['area'] }}">
                                    <p class="font-medium text-gray-900">{{ $row['label'] }}</p>
                                    @if($row['area'] === \App\Services\DeliveryService::AREA_ISLANDS)
                                        <p class="text-xs text-gray-500">{{ __('Every island whose atoll has no fee of its own') }}</p>
                                    @elseif($row['islands'] > 0)
                                        <p class="text-xs text-gray-500">{{ trans_choice(':count island in the list|:count islands in the list', $row['islands'], ['count' => $row['islands']]) }}</p>
                                    @endif
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <label for="rate-fee-{{ $i }}" class="sr-only">{{ __('Fee for :area', ['area' => $row['label']]) }}</label>
                                    <input id="rate-fee-{{ $i }}" name="rates[{{ $i }}][fee]" type="number" min="0" step="0.01" @if($row['fee_required']) required @endif
                                           value="{{ old("rates.$i.fee", $row['fee'] === null ? '' : rtrim(rtrim(number_format((float) $row['fee'], 2, '.', ''), '0'), '.')) }}"
                                           placeholder="{{ $row['fee_required'] ? '' : __('Other islands\' fee') }}" class="{{ $field }} max-w-[10rem]">
                                    @error("rates.$i.fee")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </td>
                                <td class="px-3 py-2 align-top">
                                    <div class="flex items-center gap-2">
                                        <label for="rate-min-{{ $i }}" class="sr-only">{{ __('Fewest days for :area', ['area' => $row['label']]) }}</label>
                                        <input id="rate-min-{{ $i }}" name="rates[{{ $i }}][min]" type="number" min="0" max="60" value="{{ old("rates.$i.min", $row['min']) }}" placeholder="{{ $row['transit'][0] }}" class="{{ $field }} w-20">
                                        <span class="text-gray-400">–</span>
                                        <label for="rate-max-{{ $i }}" class="sr-only">{{ __('Most days for :area', ['area' => $row['label']]) }}</label>
                                        <input id="rate-max-{{ $i }}" name="rates[{{ $i }}][max]" type="number" min="0" max="60" value="{{ old("rates.$i.max", $row['max']) }}" placeholder="{{ $row['transit'][1] }}" class="{{ $field }} w-20">
                                        <span class="text-xs text-gray-500">{{ __('days') }}</span>
                                    </div>
                                    @error("rates.$i.max")<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="mt-4 max-w-xs">
                <label for="free_delivery_over" class="block text-sm font-medium text-gray-700">{{ __('Free delivery over (MVR)') }}</label>
                <input id="free_delivery_over" name="free_delivery_over" type="number" min="0" step="0.01" required class="{{ $field }}" value="{{ old('free_delivery_over', $freeDeliveryOver) }}">
                <p class="mt-1 text-xs text-gray-500">{{ __('Checked against the order\'s goods after discounts. 0 always charges delivery. Bulky-item charges are never waived.') }}</p>
            </div>
        </section>

        <section class="rounded-lg bg-white p-6 shadow">
            <h2 class="text-lg font-semibold text-gray-900">{{ __('Malé delivery time slots') }}</h2>
            <p class="mt-1 text-sm text-gray-500">{{ __('When this is on, customers whose order is delivered to Greater Malé can pick a time slot at checkout. "Any time" stays the default. Full and too-late slots are shown but cannot be chosen.') }}</p>

            <label class="mt-4 flex items-start gap-3 text-sm text-gray-700">
                <input type="hidden" name="delivery_slots_enabled" value="0">
                <input type="checkbox" name="delivery_slots_enabled" value="1" @checked(old('delivery_slots_enabled', $slotsEnabled)) class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                <span class="font-medium text-gray-900">{{ __('Offer delivery time slots in Greater Malé') }}</span>
            </label>

            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="delivery_slots" class="block text-sm font-medium text-gray-700">{{ __('Time slots (one per line)') }}</label>
                    <textarea id="delivery_slots" name="delivery_slots" rows="5" dir="ltr" class="{{ $field }} font-mono">{{ old('delivery_slots', $slotList) }}</textarea>
                    <p class="mt-1 text-xs text-gray-500">{{ __('Write each slot as HH:MM-HH:MM, e.g. 09:00-12:00.') }}</p>
                </div>
                <div class="space-y-4">
                    <div>
                        <label for="delivery_slot_days_ahead" class="block text-sm font-medium text-gray-700">{{ __('Days ahead') }}</label>
                        <input id="delivery_slot_days_ahead" name="delivery_slot_days_ahead" type="number" min="0" max="14" required class="{{ $field }} max-w-[8rem]" value="{{ old('delivery_slot_days_ahead', $daysAhead) }}">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Slots are offered from today up to this many days ahead (0 = today only).') }}</p>
                    </div>
                    <div>
                        <label for="delivery_slot_cutoff_hours" class="block text-sm font-medium text-gray-700">{{ __('Same-day cut-off (hours)') }}</label>
                        <input id="delivery_slot_cutoff_hours" name="delivery_slot_cutoff_hours" type="number" min="0" max="48" required class="{{ $field }} max-w-[8rem]" value="{{ old('delivery_slot_cutoff_hours', $cutoffHours) }}">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Slots starting within this many hours are not offered.') }}</p>
                    </div>
                    <div>
                        <label for="delivery_slot_capacity" class="block text-sm font-medium text-gray-700">{{ __('Most orders per slot') }}</label>
                        <input id="delivery_slot_capacity" name="delivery_slot_capacity" type="number" min="0" max="10000" required class="{{ $field }} max-w-[8rem]" value="{{ old('delivery_slot_capacity', $capacity) }}">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Orders that are not cancelled count. 0 = no limit.') }}</p>
                    </div>
                </div>
            </div>

            @if($upcoming->isNotEmpty())
                <div class="mt-6">
                    <h3 class="text-sm font-semibold text-gray-900">{{ __('Booked so far') }}</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <tbody class="divide-y divide-gray-100">
                                @foreach($upcoming as $date => $daySlots)
                                    <tr>
                                        <th class="py-1.5 pe-4 text-start font-medium text-gray-700 whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($date)->translatedFormat('D j M') }}</th>
                                        @foreach($daySlots as $slot)
                                            <td class="py-1.5 pe-4 whitespace-nowrap {{ $slot['full'] ? 'text-red-700 font-semibold' : 'text-gray-600' }}"><span dir="ltr">{{ $slot['time'] }}</span>: {{ $slot['booked'] }}{{ $capacity > 0 ? '/'.$capacity : '' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        <div class="flex items-center justify-between gap-3">
            <p class="text-xs text-gray-500">{{ __('Changes are recorded in the audit log.') }}</p>
            <button class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Save delivery settings') }}</button>
        </div>
    </form>
</div>
@endsection
