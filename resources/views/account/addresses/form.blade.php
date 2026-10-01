@extends('layouts.app')

@section('title', $address->exists ? __('Edit address') : __('Add address'))

@php $f = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-dark mb-6">{{ $address->exists ? __('Edit address') : __('Add address') }}</h1>

        <form method="POST" action="{{ $address->exists ? route('account.addresses.update', $address) : route('account.addresses.store') }}" class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 space-y-5">
            @csrf
            @if($address->exists) @method('PUT') @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="label" class="block text-sm font-medium text-gray-700">{{ __('Label') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="label" name="label" maxlength="50" value="{{ old('label', $address->label) }}" placeholder="{{ __('e.g. Home, Office, Mum\'s house') }}" class="{{ $f }}">
                    @error('label')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="recipient_name" class="block text-sm font-medium text-gray-700">{{ __('Deliver to (name)') }}</label>
                    <input id="recipient_name" name="recipient_name" required maxlength="120" autocomplete="shipping name" value="{{ old('recipient_name', $address->recipient_name ?? auth()->user()->name) }}" class="{{ $f }}">
                    @error('recipient_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('Phone for delivery') }}</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" dir="ltr" required autocomplete="shipping tel" value="{{ old('phone', $address->phone ?? auth()->user()->phone) }}" placeholder="7771234" class="{{ $f }}">
                <p class="mt-1 text-xs text-gray-500">{{ __('The courier will call this number when your order arrives.') }}</p>
                @error('phone')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <x-island-picker :islands-by-atoll="$islandsByAtoll" :selected-id="old('island_id', $address->island_id)" :island="old('island', $address->island)" :atoll="old('atoll', $address->atoll)" prefix="addr" />

            <div>
                <label for="house_name_or_street" class="block text-sm font-medium text-gray-700">{{ __('House name / street') }}</label>
                <input id="house_name_or_street" name="house_name_or_street" required maxlength="255" autocomplete="shipping address-line1" value="{{ old('house_name_or_street', $address->house_name_or_street) }}" placeholder="{{ __('e.g. M. Blue House, Majeedhee Magu') }}" class="{{ $f }}">
                @error('house_name_or_street')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="ward" class="block text-sm font-medium text-gray-700">{{ __('Ward / area') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="ward" name="ward" maxlength="100" value="{{ old('ward', $address->ward) }}" placeholder="{{ __('e.g. Henveiru') }}" class="{{ $f }}">
                </div>
                <div>
                    <label for="postal_code" class="block text-sm font-medium text-gray-700">{{ __('Postal code') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="postal_code" name="postal_code" maxlength="20" dir="ltr" autocomplete="shipping postal-code" value="{{ old('postal_code', $address->postal_code) }}" placeholder="20026" class="{{ $f }}">
                </div>
            </div>

            <p class="text-sm text-gray-600">{{ __('Country') }}: <span class="font-medium text-gray-900">{{ __('Maldives') }}</span> <span class="text-xs text-gray-500">({{ __('We deliver within the Maldives only.') }})</span></p>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $address->is_default)) class="rounded text-primary-600 focus:ring-primary-500">
                {{ __('Use as my default address') }}
            </label>

            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Save address') }}</button>
                <a href="{{ route('account.addresses') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
