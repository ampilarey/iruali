@extends('layouts.app')

@section('title', __('Set up your account').' - iruali')

@php $field = 'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500'; @endphp

@section('content')
<div class="min-h-[60vh] flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6" data-account-setup>
        <div class="text-center">
            <h1 class="mt-6 text-3xl font-extrabold text-gray-900">{{ __('Set up your account') }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ __('Your name for deliveries, and a password so next time you can sign in without waiting for a code.') }}</p>
        </div>
        <form method="POST" action="{{ route('account.setup.store') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100">
            @csrf
            <div>
                <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Your name') }}</label>
                <input id="name" name="name" required maxlength="255" autocomplete="name" value="{{ old('name', $user->name) }}" class="{{ $field }} @error('name') border-red-500 @enderror">
                @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                <input id="email" name="email" type="email" autocomplete="email" dir="ltr" value="{{ old('email', $user->email) }}" class="{{ $field }} @error('email') border-red-500 @enderror">
                <p class="mt-1 text-xs text-gray-500">{{ __('For receipts by email. Without one, we send updates by text message.') }}</p>
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" class="{{ $field }} @error('password') border-red-500 @enderror">
                <p class="mt-1 text-xs text-gray-500">{{ __('At least 8 characters with upper and lower case letters, a number and a symbol.') }}</p>
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="{{ $field }}">
            </div>
            <button type="submit" name="action" value="save" class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Save and continue') }}</button>
            <button type="submit" name="action" value="later" formnovalidate class="w-full rounded-md border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50" data-setup-later>{{ __('Set a password later') }}</button>
        </form>
    </div>
</div>
@endsection
