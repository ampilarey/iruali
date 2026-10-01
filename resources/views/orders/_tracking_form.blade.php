{{-- Inputs for a part's delivery details (used in the "mark as sent" and "edit details" forms). Needs $part; optional $compact. --}}
@php $v = fn ($field) => old($field, $part->{$field} instanceof \Carbon\CarbonInterface ? $part->{$field}->toDateString() : $part->{$field}); @endphp
<div class="grid gap-2 sm:grid-cols-2 text-xs">
    <div class="sm:col-span-2 font-medium text-gray-600">Courier delivery (Malé area or parcel service)</div>
    <div>
        <label for="courier-{{ $part->id }}" class="block text-gray-600">Courier</label>
        <input id="courier-{{ $part->id }}" name="courier" maxlength="100" value="{{ $v('courier') }}" placeholder="e.g. Maldives Post, Redbox" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
    </div>
    <div>
        <label for="tracking_number-{{ $part->id }}" class="block text-gray-600">Tracking number</label>
        <input id="tracking_number-{{ $part->id }}" name="tracking_number" maxlength="100" value="{{ $v('tracking_number') }}" dir="ltr" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
    </div>
    <div class="sm:col-span-2">
        <label for="tracking_url-{{ $part->id }}" class="block text-gray-600">Tracking link (optional)</label>
        <input id="tracking_url-{{ $part->id }}" name="tracking_url" type="url" maxlength="500" value="{{ $v('tracking_url') }}" dir="ltr" placeholder="https://" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
        @error('tracking_url')<p class="mt-1 text-red-700">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2 font-medium text-gray-600 pt-1">Island delivery (boat or flight)</div>
    <div>
        <label for="vessel_or_flight-{{ $part->id }}" class="block text-gray-600">Boat or flight</label>
        <input id="vessel_or_flight-{{ $part->id }}" name="vessel_or_flight" maxlength="150" value="{{ $v('vessel_or_flight') }}" placeholder="e.g. Hithadhoo ferry, Q2 flight 221" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
    </div>
    <div>
        <label for="expected_delivery_date-{{ $part->id }}" class="block text-gray-600">Expected delivery date</label>
        <input id="expected_delivery_date-{{ $part->id }}" name="expected_delivery_date" type="date" value="{{ $v('expected_delivery_date') }}" class="w-full rounded-lg border border-gray-300 px-2 py-1.5 text-sm">
        @error('expected_delivery_date')<p class="mt-1 text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
