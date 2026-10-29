{{-- Where a shop is and where it delivers (App\Services\ShopCoverage): home island, one of three
     coverage types, and for the last two extra islands and/or the whole atoll. Used by Seller
     Centre → Profile, the shop application and Admin → Sellers → the shop's page.
     Needs $shop (User or null), $islandsByAtoll; optional $homeRequired, $prefix. --}}
@php
    $prefix = $prefix ?? 'shop';
    $homeRequired = $homeRequired ?? false;
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $homeId = (int) old('home_island_id', $shop?->home_island_id);
    $coverage = old('delivery_coverage', $shop?->deliveryCoverage() ?? \App\Models\User::COVERAGE_ALL);
    $extraIds = collect(old('delivery_island_ids', $shop ? $shop->deliveryIslands()->pluck('islands.id')->all() : []))->map(fn ($id) => (int) $id)->all();
    $wholeAtoll = (bool) old('delivers_to_home_atoll', $shop?->delivers_to_home_atoll);
@endphp
<div class="space-y-4" data-coverage-fields>
    <div data-island-picker>
        <label for="{{ $prefix }}-home-search" class="block text-sm font-medium text-gray-700">{{ __('Home island') }}@if($homeRequired) *@endif</label>
        <p class="text-xs text-gray-500">{{ __('The island your shop is on. Customers on this island always see your products.') }}</p>
        <input id="{{ $prefix }}-home-search" type="search" data-island-search autocomplete="off" placeholder="{{ __('Search islands or atolls…') }}" class="{{ $field }}" aria-controls="{{ $prefix }}-home-select">
        <select id="{{ $prefix }}-home-select" name="home_island_id" data-island-select size="5" class="{{ $field }}" aria-label="{{ __('Home island') }}" @if($homeRequired) required @endif>
            <option value="" @selected(! $homeId)>{{ __('Choose your island') }}</option>
            @foreach($islandsByAtoll as $atollName => $islands)
                <optgroup label="{{ $atollName }}">
                    @foreach($islands as $isl)
                        <option value="{{ $isl->id }}" data-search="{{ mb_strtolower($isl->getTranslation('name', 'en', false).' '.$isl->getTranslation('name', 'dv', false).' '.$isl->atoll.' '.$isl->atoll_dv) }}" @selected($homeId === $isl->id)>{{ $isl->localized_name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        <p class="mt-1 hidden text-sm font-medium text-primary-700" data-island-chosen data-label="{{ __('Chosen') }}" aria-live="polite"></p>
        @error('home_island_id')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </div>

    <fieldset>
        <legend class="block text-sm font-medium text-gray-700">{{ __('Where do you deliver?') }}</legend>
        <p class="text-xs text-gray-500">{{ __('Customers only see your products as deliverable on the islands you reach. Pickup from your shop is always open, wherever the customer lives.') }}</p>
        <div class="mt-2 space-y-2">
            @foreach(\App\Services\ShopCoverage::options() as $value => $option)
                <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3 hover:bg-gray-50 has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                    <input type="radio" name="delivery_coverage" value="{{ $value }}" class="mt-1" data-coverage-radio @checked($coverage === $value)>
                    <span>
                        <span class="block text-sm font-semibold text-gray-900">{{ $option['label'] }}</span>
                        <span class="block text-xs text-gray-600">{{ $option['hint'] }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        @error('delivery_coverage')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
    </fieldset>

    <div data-coverage-extra class="space-y-3 rounded-lg border border-gray-200 p-3 {{ $coverage === \App\Models\User::COVERAGE_ALL ? 'hidden' : '' }}">
        <label class="flex items-start gap-2 text-sm text-gray-700">
            <input type="hidden" name="delivers_to_home_atoll" value="0">
            <input type="checkbox" name="delivers_to_home_atoll" value="1" class="mt-0.5 rounded border-gray-300" @checked($wholeAtoll)>
            <span>{{ __('I also deliver to every island in my atoll') }}</span>
        </label>
        <div data-island-checklist>
            <label for="{{ $prefix }}-extra-search" class="block text-sm font-medium text-gray-700">{{ __('Other islands I deliver to') }}</label>
            <p class="text-xs text-gray-500">{{ __('For example islands connected to yours by ferry. Leave empty if none.') }}</p>
            <input id="{{ $prefix }}-extra-search" type="search" data-checklist-search autocomplete="off" placeholder="{{ __('Search islands or atolls…') }}" class="{{ $field }}">
            <div class="mt-2 max-h-56 overflow-y-auto rounded-lg border border-gray-200 p-2 text-sm">
                @foreach($islandsByAtoll as $atollName => $islands)
                    <div data-checklist-group>
                        <p class="mt-1 text-xs font-semibold uppercase text-gray-500">{{ $atollName }}</p>
                        @foreach($islands as $isl)
                            <label class="flex items-center gap-2 py-0.5" data-search="{{ mb_strtolower($isl->getTranslation('name', 'en', false).' '.$isl->getTranslation('name', 'dv', false).' '.$isl->atoll.' '.$isl->atoll_dv) }}">
                                <input type="checkbox" name="delivery_island_ids[]" value="{{ $isl->id }}" class="rounded border-gray-300" @checked(in_array($isl->id, $extraIds, true))>
                                <span>{{ $isl->localized_name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
            </div>
            @error('delivery_island_ids')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
