@extends('layouts.app')

@section('title', __('Choose a new password'))

@section('content')
<div class="flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6">
        <h1 class="text-center text-3xl font-extrabold text-gray-900">{{ __('Choose a new password') }}</h1>

        <form class="space-y-4 bg-white shadow rounded-lg p-6" action="{{ route('password.update') }}" method="POST">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email', $email) }}"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password" class="block text-sm font-medium text-gray-700">{{ __('New password') }}</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm @error('password') border-red-500 @enderror">
                <p class="mt-1 text-xs text-gray-500">{{ __('At least 8 characters with upper and lower case letters, a number and a symbol.') }}</p>
                @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm password') }}</label>
                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm">
            </div>
            <button type="submit" class="w-full flex justify-center py-2 px-4 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">{{ __('Save new password') }}</button>
        </form>
    </div>
</div>
@endsection
