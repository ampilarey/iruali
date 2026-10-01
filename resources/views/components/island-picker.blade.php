@props([
    'islandsByAtoll',
    'selectedId' => null,
    'island' => null,
    'atoll' => null,
    'idName' => 'island_id',
    'islandName' => 'island',
    'atollName' => 'atoll',
    'required' => true,
    'prefix' => 'addr',
])
@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $delivery = app(\App\Services\DeliveryService::class);
    $selectedId = $selectedId ? (int) $selectedId : null;
    // A typed island that is not in the list means "other" is in use
    $other = ! $selectedId && filled($island);
@endphp
<div {{ $attributes->merge(['class' => 'space-y-3']) }} data-island-picker>
    <div>
        <label for="{{ $prefix }}-island-search" class="block text-sm font-medium text-gray-700">{{ __('Island') }}</label>
        <input id="{{ $prefix }}-island-search" type="search" data-island-search autocomplete="off" placeholder="{{ __('Search islands or atolls…') }}" class="{{ $field }}" aria-controls="{{ $prefix }}-island-select">
        <select id="{{ $prefix }}-island-select" name="{{ $idName }}" data-island-select size="6" class="{{ $field }}" aria-label="{{ __('Island') }}">
            <option value="" data-zone="" @selected(! $selectedId)>{{ __('My island is not in the list (type it below)') }}</option>
            @foreach($islandsByAtoll as $atollName => $islands)
                <optgroup label="{{ $atollName }}">
                    @foreach($islands as $isl)
                        <option value="{{ $isl->id }}" data-zone="{{ $delivery->zoneForIsland($isl) }}" data-atoll="{{ $isl->atoll }}" data-search="{{ mb_strtolower($isl->getTranslation('name', 'en', false).' '.$isl->getTranslation('name', 'dv', false).' '.$isl->atoll) }}" @selected($selectedId === $isl->id)>{{ $isl->localized_name }}</option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>
        @error($idName)<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
    </div>
    <div data-island-other class="grid gap-3 sm:grid-cols-2 {{ $other || $islandsByAtoll->isEmpty() ? '' : 'hidden' }}">
        <div>
            <label for="{{ $prefix }}-island-text" class="block text-sm font-medium text-gray-700">{{ __('Island name') }}</label>
            <input id="{{ $prefix }}-island-text" name="{{ $islandName }}" data-island-text value="{{ $island }}" @if($required && ! $selectedId) required @endif autocomplete="shipping address-level2" placeholder="{{ __('e.g. Malé') }}" class="{{ $field }}">
            @error($islandName)<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="{{ $prefix }}-atoll-text" class="block text-sm font-medium text-gray-700">{{ __('Atoll') }}</label>
            <input id="{{ $prefix }}-atoll-text" name="{{ $atollName }}" data-atoll-text value="{{ $atoll }}" autocomplete="shipping address-level1" placeholder="{{ __('e.g. Kaafu') }}" class="{{ $field }}">
            @error($atollName)<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
