@extends('layouts.app')

@section('title', __('Sign in').' - iruali')

@php $field = 'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500'; @endphp

@section('content')
<div class="min-h-[60vh] flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6" data-login-phone="{{ $pending['step'] ?? 'phone' }}">
        <div class="text-center">
            <h1 class="mt-6 text-3xl font-extrabold text-gray-900">{{ __('Sign in or create an account') }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ __('Use your mobile number or email. New to iruali? Type your mobile number: we text you a code and your account is ready.') }}</p>
        </div>

        @if(session('status'))<p class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
        @error('login')<p class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

        @if(($pending['step'] ?? null) === 'password')
            <form method="POST" action="{{ route('login.phone.password') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                @csrf
                <p class="text-sm text-gray-700">{{ __('Signing in as :phone', ['phone' => $pending['shown']]) }} · <a href="{{ route('login', ['change' => 1]) }}" class="font-medium text-primary-600">{{ __('Change') }}</a></p>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required autofocus class="{{ $field }} @error('password') border-red-500 @enderror">
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Sign in') }}</button>
            </form>
            <div class="flex flex-col items-center gap-2">
                @if($pending['email_code'] ?? false)
                    <form method="POST" action="{{ route('login.phone.email-code') }}" data-email-code>
                        @csrf
                        <button class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ __('Email me a code instead') }}</button>
                    </form>
                @endif
                <form method="POST" action="{{ route('login.phone.resend') }}">
                    @csrf
                    <button class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ __('Forgot password?') }}</button>
                </form>
            </div>
        @elseif(($pending['step'] ?? null) === 'code')
            <form method="POST" action="{{ route('login.phone.verify') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                @csrf
                <p class="text-sm text-gray-700">{{ ($pending['channel'] ?? 'sms') === 'mail' ? __('Enter the code we emailed to :to.', ['to' => $pending['shown_to'] ?? $pending['shown']]) : __('Enter the code we texted to :to.', ['to' => $pending['shown_to'] ?? $pending['shown']]) }} · <a href="{{ route('login', ['change' => 1]) }}" class="font-medium text-primary-600">{{ __('Change') }}</a></p>
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">{{ __('Code') }}</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus dir="ltr"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-center text-2xl tracking-[.4em] focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('code') border-red-500 @enderror">
                    @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Continue') }}</button>
                <p class="text-xs text-gray-500">{{ __('The code works for 10 minutes.') }}</p>
            </form>
            <form method="POST" action="{{ route('login.phone.resend') }}" class="text-center">
                @csrf
                <button class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ __('Send a new code') }}</button>
            </form>
        @else
            <form method="POST" action="{{ route('login.phone.start') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                @csrf
                <div>
                    <label for="login" class="block text-sm font-medium text-gray-700">{{ __('Mobile number or email') }}</label>
                    <input id="login" name="login" type="text" autocomplete="username" required autofocus dir="ltr" value="{{ old('login') }}" placeholder="777 1234" class="{{ $field }} @error('login') border-red-500 @enderror">
                    @unless($smsAvailable)<p class="mt-1 text-xs text-amber-700">{{ __('Codes by text message are not available right now. Accounts with a password or an email can still sign in.') }}</p>@endunless
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-900">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    {{ __('Remember me') }}
                </label>
                <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Continue') }}</button>
            </form>
        @endif

        @unless($pending)
            @include('auth._social')
        @endunless
        <p class="text-center text-sm text-gray-600">{{ __('Staff, or an older account with email?') }} <a href="{{ route('login.email') }}" class="font-medium text-primary-600 hover:text-primary-500" data-login-email-link>{{ __('Sign in with email') }}</a></p>
    </div>
</div>
@endsection
