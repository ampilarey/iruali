@extends('layouts.app')

@section('title', __('Verify your email'))

@section('content')
<div class="flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6">
        <div>
            <h1 class="text-center text-3xl font-extrabold text-gray-900">{{ $phonePending ? __('Verify your email and phone') : __('Verify your email') }}</h1>
            @if($user->isEmailVerified())
                <p class="mt-2 text-center text-sm text-gray-600">{{ __('Your email is verified. Thank you!') }}</p>
            @else
                <p class="mt-2 text-center text-sm text-gray-600">{{ __('We emailed a 6-digit code to :email. Enter it below.', ['email' => $user->email]) }}</p>
            @endif
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif

        @if($user->isEmailVerified())
            <div class="bg-white shadow rounded-lg p-6 text-center space-y-3">
                <p class="flex items-center justify-center gap-2 text-green-700 font-medium"><x-icon name="check" class="w-5 h-5" />{{ __('Email verified') }}</p>
                <a href="{{ route('account') }}" class="inline-block rounded-md bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Go to my account') }}</a>
            </div>
        @else
            <div class="bg-white shadow rounded-lg p-6 space-y-5">
                <form action="{{ route('auth.verify.email.otp') }}" method="POST" class="space-y-3">
                    @csrf
                    <label for="code" class="block text-sm font-medium text-gray-700">{{ __('Verification code') }}</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required dir="ltr"
                           class="block w-full rounded-md border border-gray-300 px-3 py-2 text-center text-2xl tracking-[.4em] text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('code') border-red-500 @enderror">
                    @error('code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="w-full flex justify-center py-2 px-4 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">{{ __('Verify email') }}</button>
                </form>

                <form action="{{ route('auth.send.email.otp') }}" method="POST" class="text-center">
                    @csrf
                    <p class="text-sm text-gray-600">{{ __("Didn't get it? Check your spam folder, or") }}
                        <button type="submit" class="font-medium text-primary-600 hover:text-primary-500 underline">{{ __('send a new code') }}</button></p>
                </form>

                @unless($phonePending)
                <p class="text-center text-sm text-gray-500 border-t border-gray-100 pt-4">
                    {{ __('You can keep shopping and verify later.') }}
                    <a href="{{ session('url.intended', route('home')) }}" class="font-medium text-primary-600 hover:text-primary-500">{{ __('Continue') }}</a>
                </p>
                @endunless
            </div>
        @endif

        @if($phonePending)
            <div class="bg-white shadow rounded-lg p-6 space-y-5" id="phone">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">{{ __('Verify your phone') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ __('We texted a 6-digit code to :phone. Enter it below to get order updates by SMS.', ['phone' => $user->phone]) }}</p>
                </div>
                <form action="{{ route('auth.verify.phone.otp') }}" method="POST" class="space-y-3">
                    @csrf
                    <label for="phone_code" class="block text-sm font-medium text-gray-700">{{ __('SMS code') }}</label>
                    <input id="phone_code" name="phone_code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required dir="ltr"
                           class="block w-full rounded-md border border-gray-300 px-3 py-2 text-center text-2xl tracking-[.4em] text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('phone_code') border-red-500 @enderror">
                    @error('phone_code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="w-full flex justify-center py-2 px-4 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">{{ __('Verify phone') }}</button>
                </form>
                <form action="{{ route('auth.send.phone.otp') }}" method="POST" class="text-center">
                    @csrf
                    <p class="text-sm text-gray-600">{{ __("Didn't get the text?") }}
                        <button type="submit" class="font-medium text-primary-600 hover:text-primary-500 underline">{{ __('send a new code') }}</button></p>
                </form>
                <p class="text-center text-sm text-gray-500 border-t border-gray-100 pt-4">
                    {{ __('You can keep shopping and verify later.') }}
                    <a href="{{ session('url.intended', route('home')) }}" class="font-medium text-primary-600 hover:text-primary-500">{{ __('Continue') }}</a>
                </p>
            </div>
        @endif
    </div>
</div>
@endsection
