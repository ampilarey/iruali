@extends('layouts.app')

@section('title', __('Edit profile'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-2xl mx-auto">
        <h1 class="text-3xl font-bold text-dark mb-6">{{ __('Edit profile') }}</h1>

        <form method="POST" action="{{ route('account.update') }}" class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 space-y-5">
            @csrf @method('PUT')
            @php $f = 'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500'; @endphp
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Full name') }}</label>
                <input id="name" name="name" autocomplete="name" required value="{{ old('name', $user->name) }}" class="{{ $f }} @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('Mobile number') }}</label>
                <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" dir="ltr" value="{{ old('phone', $user->phone) }}" placeholder="777 1234" class="{{ $f }} @error('phone') border-red-500 @enderror">
                <p class="mt-1 text-xs text-gray-500">{{ __('The shop or courier calls this number to arrange delivery.') }}</p>
                @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <fieldset class="space-y-3">
                <legend class="text-sm font-medium text-gray-700">{{ __('Delivery address') }}</legend>
                <div>
                    <label for="address" class="block text-sm text-gray-700">{{ __('House / street') }}</label>
                    <input id="address" name="address" autocomplete="address-line1" value="{{ old('address', $user->address) }}" class="{{ $f }}">
                </div>
                <div class="grid gap-3 sm:grid-cols-3">
                    <div><label for="city" class="block text-sm text-gray-700">{{ __('Island') }}</label><input id="city" name="city" autocomplete="address-level2" value="{{ old('city', $user->city) }}" class="{{ $f }}"></div>
                    <div><label for="state" class="block text-sm text-gray-700">{{ __('Atoll') }}</label><input id="state" name="state" autocomplete="address-level1" value="{{ old('state', $user->state) }}" class="{{ $f }}"></div>
                    <div><label for="postal_code" class="block text-sm text-gray-700">{{ __('Postal code') }}</label><input id="postal_code" name="postal_code" autocomplete="postal-code" dir="ltr" value="{{ old('postal_code', $user->postal_code) }}" class="{{ $f }}"></div>
                </div>
            </fieldset>
            <div>
                <label for="preferred_language" class="block text-sm font-medium text-gray-700">{{ __('Language for emails') }}</label>
                <select id="preferred_language" name="preferred_language" class="{{ $f }}">
                    <option value="en" @selected(old('preferred_language', $user->preferred_language ?? 'en') === 'en')>English</option>
                    <option value="dv" @selected(old('preferred_language', $user->preferred_language) === 'dv')>ދިވެހި</option>
                </select>
            </div>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Save') }}</button>
                <a href="{{ route('account') }}" class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Cancel') }}</a>
            </div>
        </form>
        @include('account._business-details')
    </div>
</div>
@endsection
