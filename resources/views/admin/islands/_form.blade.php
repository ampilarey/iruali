{{-- Add or correct an island (Admin → Islands). $island is null when adding. --}}
@php $field = 'w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp
<div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    <div>
        <label for="island-name-en" class="block text-xs font-medium text-gray-600">Name in English</label>
        <input id="island-name-en" name="name_en" required maxlength="100" class="{{ $field }}" value="{{ old('name_en', $island?->englishName()) }}" placeholder="e.g. Hulhumalé">
        @error('name_en')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="island-name-dv" class="block text-xs font-medium text-gray-600">Name in Dhivehi</label>
        <input id="island-name-dv" name="name_dv" maxlength="100" lang="dv" dir="rtl" class="{{ $field }}" value="{{ old('name_dv', $island?->getTranslation('name', 'dv', false)) }}">
        @error('name_dv')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="island-atoll-field" class="block text-xs font-medium text-gray-600">Atoll</label>
        <select id="island-atoll-field" name="atoll" required class="{{ $field }}">
            <option value="">Choose the atoll</option>
            @foreach($atolls as $a)<option value="{{ $a }}" @selected(old('atoll', $island?->atoll) === $a)>{{ $a }}</option>@endforeach
        </select>
        @error('atoll')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-2 gap-3">
        <div>
            <label for="island-lat" class="block text-xs font-medium text-gray-600">Latitude</label>
            <input id="island-lat" name="latitude" inputmode="decimal" dir="ltr" class="{{ $field }}" value="{{ old('latitude', $island?->latitude) }}" placeholder="4.2105">
            @error('latitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="island-lng" class="block text-xs font-medium text-gray-600">Longitude</label>
            <input id="island-lng" name="longitude" inputmode="decimal" dir="ltr" class="{{ $field }}" value="{{ old('longitude', $island?->longitude) }}" placeholder="73.5400">
            @error('longitude')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
<p class="text-xs text-gray-500">The map position is optional. With it, the island is offered by "Use my location". In Google Maps, right-click the island and click the numbers to copy them.</p>
