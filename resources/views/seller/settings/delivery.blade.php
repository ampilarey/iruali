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
                        <label for="pickup_hours" class="block text-sm font-medium text-gray-700">{{ __('Opening hours and instructions') }}</label>
                        <textarea id="pickup_hours" name="pickup_hours" rows="3" maxlength="500" class="{{ $field }}" placeholder="{{ __('e.g. Saturday to Thursday 9:00–21:00. Ring the bell at the side door.') }}">{{ old('pickup_hours', $setting->pickup_hours) }}</textarea>
                        <p class="mt-1 text-xs text-gray-500">{{ __('Shown to customers at checkout and on their order, and sent with their pickup code.') }}</p>
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

            <section class="rounded-lg bg-white p-6 shadow space-y-3" data-opening-hours>
                <div>
                    <h2 class="text-base font-semibold text-gray-900">{{ __('Opening hours') }}</h2>
                    <p class="text-sm text-gray-500">{{ __('The days and times you work on orders. Arrival estimates count from your next open day, and customers see when you are open. Leave a day empty when you are closed, or all days empty to not use opening hours.') }}</p>
                </div>
                <div class="space-y-2">
                    @foreach(\App\Models\SellerDeliverySetting::DAYS as $day => $dayName)
                        @php $hours = $setting->hoursOn($day); @endphp
                        <div class="grid grid-cols-1 items-center gap-2 sm:grid-cols-3">
                            <span class="text-sm font-medium text-gray-700">{{ __($dayName) }}</span>
                            <label class="text-xs text-gray-500"><span class="sr-only">{{ __($dayName) }} </span>{{ __('Opens') }}
                                <input type="time" name="hours[{{ $day }}][0]" value="{{ old('hours.'.$day.'.0', $hours[0] ?? '') }}" class="{{ $field }}"></label>
                            <label class="text-xs text-gray-500"><span class="sr-only">{{ __($dayName) }} </span>{{ __('Closes') }}
                                <input type="time" name="hours[{{ $day }}][1]" value="{{ old('hours.'.$day.'.1', $hours[1] ?? '') }}" class="{{ $field }}"></label>
                        </div>
                    @endforeach
                </div>
            </section>

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
