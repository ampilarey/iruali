@extends('layouts.app')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Delivery & pickup')])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        @include('seller.settings._nav')

        <form method="POST" action="{{ route('seller.settings.delivery.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            @if($errors->any())
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc ps-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <section class="rounded-lg bg-white p-6 shadow space-y-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ __('How fast you send orders') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('Customers see an arrival estimate on your product pages and at checkout: these days plus the delivery time to their island.') }}</p>
                </div>
                <div class="max-w-xs">
                    <label for="ships_within_days" class="block text-sm font-medium text-gray-700">{{ __('Usually ships within (days)') }}</label>
                    <input id="ships_within_days" name="ships_within_days" type="number" min="0" max="30" required class="{{ $field }}" value="{{ old('ships_within_days', $setting->shipsWithinDays()) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('0 means the same day you get the order.') }}</p>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow space-y-4">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ __('Pick up from your shop') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('Let customers collect their order from you instead of having it delivered. They pay no delivery fee. When it is ready, press "Ready for pickup" on the order: the customer gets a 6-digit code, and you type that code when they collect.') }}</p>
                </div>
                <label class="flex items-start gap-3 text-sm text-gray-700">
                    <input type="hidden" name="pickup_enabled" value="0">
                    <input type="checkbox" name="pickup_enabled" value="1" @checked(old('pickup_enabled', $setting->pickup_enabled)) class="mt-0.5 h-5 w-5 rounded border-gray-300 text-primary-600">
                    <span class="font-medium text-gray-900">{{ __('Offer pickup from my shop') }}</span>
                </label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <label for="pickup_address" class="block text-sm font-medium text-gray-700">{{ __('Pickup address') }}</label>
                        <input id="pickup_address" name="pickup_address" maxlength="255" class="{{ $field }}" value="{{ old('pickup_address', $setting->pickup_address) }}" placeholder="{{ __('e.g. H. Coral Villa, Majeedhee Magu, ground floor') }}">
                    </div>
                    <div>
                        <label for="pickup_island_id" class="block text-sm font-medium text-gray-700">{{ __('Island') }}</label>
                        <select id="pickup_island_id" name="pickup_island_id" class="{{ $field }}">
                            <option value="">{{ __('Choose the island') }}</option>
                            @foreach($islandsByAtoll as $atoll => $islands)
                                <optgroup label="{{ $atoll }}">
                                    @foreach($islands as $island)
                                        <option value="{{ $island->id }}" @selected((int) old('pickup_island_id', $setting->pickup_island_id) === $island->id)>{{ $island->localized_name }}</option>
                                    @endforeach
                                </optgroup>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="pickup_hours" class="block text-sm font-medium text-gray-700">{{ __('Pickup instructions') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                        <textarea id="pickup_hours" name="pickup_hours" rows="2" maxlength="500" class="{{ $field }}" placeholder="{{ __('e.g. Ring the bell at the side door.') }}">{{ old('pickup_hours', $setting->pickup_hours) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Customers see your opening hours (below) and these instructions at checkout, on their order and with their pickup code.') }}</p>
                    </div>
                </div>
            </section>

            @if($ownFeesAllowed)
                <section class="rounded-lg bg-white p-6 shadow space-y-4" data-own-fees>
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">{{ __('Your delivery fees') }}</h2>
                        <p class="text-sm text-gray-500">{{ __('Deliver your orders yourself and set your own fees. Each order pays your fee for your part, and you are paid it with your earnings (no commission is taken on delivery). Bulky-item charges on your products are paid to you too.') }}</p>
                        <p class="mt-1 text-sm text-gray-500">{{ __('If you don\'t, iruali\'s fees apply (now :male to Greater Malé, :islands to other islands) and iruali keeps them.', ['male' => \App\Support\Money::format($irualiFees['greater_male']), 'islands' => \App\Support\Money::format($irualiFees['islands'])]) }}</p>
                    </div>
                    <label class="flex items-start gap-3 text-sm text-gray-700">
                        <input type="hidden" name="own_fees" value="0">
                        <input type="checkbox" name="own_fees" value="1" @checked(old('own_fees', $setting->own_fees)) class="mt-0.5 h-5 w-5 rounded border-gray-300 text-primary-600">
                        <span class="font-medium text-gray-900">{{ __('I deliver my orders myself, at my own fees') }}</span>
                    </label>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                        <div>
                            <label for="fee_local" class="block text-sm font-medium text-gray-700">{{ $homeIsland ? __('To :island (your island)', ['island' => $homeIsland->localized_name]) : __('To your own island') }}</label>
                            <input id="fee_local" name="fee_local" type="number" min="0" max="{{ $maxFee }}" step="0.01" class="{{ $field }}" value="{{ old('fee_local', $setting->fee_local) }}" placeholder="{{ __('Same as its area') }}" @disabled(! $homeIsland)>
                            @unless($homeIsland)
                                <p class="mt-1 text-xs text-gray-500">{{ __('Set your shop\'s island in your shop profile first.') }}</p>
                            @endunless
                        </div>
                        <div>
                            <label for="fee_greater_male" class="block text-sm font-medium text-gray-700">{{ __('To Greater Malé') }}</label>
                            <input id="fee_greater_male" name="fee_greater_male" type="number" min="0" max="{{ $maxFee }}" step="0.01" class="{{ $field }}" value="{{ old('fee_greater_male', $setting->fee_greater_male) }}">
                        </div>
                        <div>
                            <label for="fee_islands" class="block text-sm font-medium text-gray-700">{{ __('To other islands') }}</label>
                            <input id="fee_islands" name="fee_islands" type="number" min="0" max="{{ $maxFee }}" step="0.01" class="{{ $field }}" value="{{ old('fee_islands', $setting->fee_islands) }}">
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">{{ __('In MVR, for one order, at most :max. 0 means free delivery. Only islands you deliver to (your shop profile) can order delivery from you.', ['max' => \App\Support\Money::format($maxFee)]) }}</p>
                    <div class="max-w-xs">
                        <label for="free_over" class="block text-sm font-medium text-gray-700">{{ __('Free delivery when your items come to (MVR)') }}</label>
                        <input id="free_over" name="free_over" type="number" min="0" step="0.01" class="{{ $field }}" value="{{ old('free_over', $setting->free_over) }}" placeholder="{{ __('Never free') }}">
                        <p class="mt-1 text-xs text-gray-500">{{ __('Leave empty to always charge your fee. Bulky-item charges always apply.') }}</p>
                    </div>
                </section>
            @endif

            {{-- Opening hours: per day closed, 24 hours, or up to three times (around a break); a close
                 before the open runs past midnight. Saved by DeliverySettingsController::openingHours(). --}}
            @php
                $oldHours = old('hours');
                $dayState = function (int $day) use ($setting, $oldHours) {
                    if (is_array($oldHours)) {
                        $row = (array) ($oldHours[$day] ?? []);
                        $ranges = array_values(array_filter((array) ($row['ranges'] ?? []), fn ($r) => filled($r[0] ?? null) || filled($r[1] ?? null)));

                        return ['open' => ! empty($row['open']), 'allday' => ! empty($row['allday']), 'ranges' => $ranges];
                    }
                    $ranges = $setting->rangesOn($day);
                    $allDay = $setting->isOpenAllDay($day);

                    return ['open' => $ranges !== [], 'allday' => $allDay, 'ranges' => $allDay ? [] : array_map(fn ($r) => [$r[0], $r[1] === '24:00' ? '00:00' : $r[1]], $ranges)];
                };
                $time = 'block w-full min-w-0 rounded-lg border border-gray-300 px-2 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
            @endphp
            <section class="rounded-lg bg-white p-4 shadow space-y-4 sm:p-6" data-opening-hours>
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ __('Opening hours') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('When you work on orders and customers can collect. Arrival estimates count from your next open day. Add a second time for a break (e.g. prayer or lunch); a closing time earlier than the opening runs past midnight.') }}</p>
                </div>
                @error('hours')<p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror
                <div class="space-y-3">
                    @foreach(\App\Models\SellerDeliverySetting::DAYS as $day => $dayName)
                        @php $state = $dayState($day); $ranges = $state['ranges'] ?: [['', '']]; @endphp
                        <div class="rounded-lg border border-gray-200 p-3" data-hours-day="{{ $day }}">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-sm font-semibold text-gray-900">{{ __($dayName) }}</span>
                                <div class="flex items-center gap-4 text-sm">
                                    <label class="flex items-center gap-2"><input type="hidden" name="hours[{{ $day }}][open]" value="0"><input type="checkbox" name="hours[{{ $day }}][open]" value="1" class="rounded border-gray-300 text-primary-600" data-hours-open @checked($state['open'])> {{ __('Open') }}</label>
                                    <label class="flex items-center gap-2 {{ $state['open'] ? '' : 'hidden' }}" data-hours-allday-label><input type="hidden" name="hours[{{ $day }}][allday]" value="0"><input type="checkbox" name="hours[{{ $day }}][allday]" value="1" class="rounded border-gray-300 text-primary-600" data-hours-allday @checked($state['allday'])> {{ __('24 hours') }}</label>
                                </div>
                            </div>
                            <p class="mt-1 text-xs text-gray-500 {{ $state['open'] ? 'hidden' : '' }}" data-hours-closed>{{ __('Closed') }}</p>
                            <div class="mt-3 space-y-2 {{ $state['open'] && ! $state['allday'] ? '' : 'hidden' }}" data-hours-ranges>
                                @foreach($ranges as $i => $range)
                                    <div class="flex items-center gap-2" data-hours-range>
                                        <label class="min-w-0 flex-1"><span class="sr-only">{{ __($dayName) }} {{ __('Opens') }}</span><input type="time" name="hours[{{ $day }}][ranges][{{ $i }}][0]" value="{{ $range[0] ?? '' }}" class="{{ $time }}"></label>
                                        <span class="text-gray-400" aria-hidden="true">–</span>
                                        <label class="min-w-0 flex-1"><span class="sr-only">{{ __($dayName) }} {{ __('Closes') }}</span><input type="time" name="hours[{{ $day }}][ranges][{{ $i }}][1]" value="{{ $range[1] ?? '' }}" class="{{ $time }}"></label>
                                        <button type="button" class="shrink-0 rounded-lg px-2 py-2 text-gray-400 hover:bg-gray-100 hover:text-red-600" data-hours-remove aria-label="{{ __('Remove this time') }}">&times;</button>
                                    </div>
                                @endforeach
                                <button type="button" class="text-sm font-medium text-primary-700 hover:underline" data-hours-add>+ {{ __('Add another time (a break)') }}</button>
                            </div>
                        </div>
                    @endforeach
                </div>
                <button type="button" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 sm:w-auto" data-hours-copy>{{ __('Copy the first open day to all open days') }}</button>
            </section>
            <script>
                (function () {
                    var box = document.querySelector('[data-opening-hours]');
                    if (!box) return;
                    var max = {{ \App\Models\SellerDeliverySetting::MAX_RANGES }};
                    function sync(row) {
                        var open = row.querySelector('[data-hours-open]').checked;
                        var allday = row.querySelector('[data-hours-allday]').checked;
                        row.querySelector('[data-hours-allday-label]').classList.toggle('hidden', !open);
                        row.querySelector('[data-hours-closed]').classList.toggle('hidden', open);
                        row.querySelector('[data-hours-ranges]').classList.toggle('hidden', !open || allday);
                        row.querySelector('[data-hours-add]').classList.toggle('hidden', row.querySelectorAll('[data-hours-range]').length >= max);
                    }
                    function renumber(row) {
                        row.querySelectorAll('[data-hours-range]').forEach(function (r, i) {
                            r.querySelectorAll('input').forEach(function (input, j) {
                                input.name = 'hours[' + row.dataset.hoursDay + '][ranges][' + i + '][' + j + ']';
                            });
                        });
                    }
                    box.querySelectorAll('[data-hours-day]').forEach(function (row) {
                        row.addEventListener('change', function () { sync(row); });
                        row.querySelector('[data-hours-add]').addEventListener('click', function () {
                            var ranges = row.querySelectorAll('[data-hours-range]');
                            if (ranges.length >= max) return;
                            var copy = ranges[ranges.length - 1].cloneNode(true);
                            copy.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                            ranges[ranges.length - 1].after(copy);
                            renumber(row); sync(row);
                            copy.querySelector('input').focus();
                        });
                        row.addEventListener('click', function (e) {
                            var remove = e.target.closest('[data-hours-remove]');
                            if (!remove) return;
                            var ranges = row.querySelectorAll('[data-hours-range]');
                            var range = remove.closest('[data-hours-range]');
                            if (ranges.length > 1) range.remove(); else range.querySelectorAll('input').forEach(function (input) { input.value = ''; });
                            renumber(row); sync(row);
                        });
                        sync(row);
                    });
                    box.querySelector('[data-hours-copy]').addEventListener('click', function () {
                        var rows = Array.prototype.slice.call(box.querySelectorAll('[data-hours-day]'));
                        var first = rows.find(function (r) { return r.querySelector('[data-hours-open]').checked; });
                        if (!first) return;
                        var times = Array.prototype.map.call(first.querySelectorAll('[data-hours-range]'), function (r) {
                            return Array.prototype.map.call(r.querySelectorAll('input'), function (i) { return i.value; });
                        });
                        var allday = first.querySelector('[data-hours-allday]').checked;
                        rows.forEach(function (row) {
                            if (row === first || !row.querySelector('[data-hours-open]').checked) return;
                            row.querySelector('[data-hours-allday]').checked = allday;
                            var ranges = row.querySelectorAll('[data-hours-range]');
                            for (var i = 1; i < ranges.length; i++) ranges[i].remove();
                            var tmpl = row.querySelector('[data-hours-range]');
                            times.forEach(function (t, i) {
                                var r = i === 0 ? tmpl : tmpl.cloneNode(true);
                                if (i > 0) row.querySelectorAll('[data-hours-range]')[i - 1].after(r);
                                r.querySelectorAll('input').forEach(function (input, j) { input.value = t[j] || ''; });
                            });
                            renumber(row); sync(row);
                        });
                    });
                })();
            </script>

            <section class="rounded-lg bg-white p-6 shadow space-y-3">
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ __('Minimum order') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('Customers need at least this much of your items in one order. Leave empty for no minimum.') }}</p>
                </div>
                <div class="max-w-xs">
                    <label for="min_order_amount" class="block text-sm font-medium text-gray-700">{{ __('Minimum (MVR)') }}</label>
                    <input id="min_order_amount" name="min_order_amount" type="number" min="0" max="100000" step="0.01" class="{{ $field }}" value="{{ old('min_order_amount', $setting->min_order_amount) }}">
                </div>
            </section>

            <div class="flex justify-end">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save delivery settings') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
