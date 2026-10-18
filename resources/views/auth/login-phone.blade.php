@extends('layouts.app')

@section('title', __('Continue with your mobile number').' - iruali')

@php $field = 'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500'; @endphp

@section('content')
<div class="min-h-[60vh] flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6" data-login-phone="{{ $pending['step'] ?? 'phone' }}">
        <div class="text-center">
            <h1 class="mt-6 text-3xl font-extrabold text-gray-900">{{ __('Continue with your mobile number') }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ __('New to iruali? We text you a code and your account is ready. No email needed.') }}</p>
        </div>

        @if(session('status'))<p class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif

        @if(($pending['step'] ?? null) === 'password')
            <form method="POST" action="{{ route('login.phone.password') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                @csrf
                <p class="text-sm text-gray-700">{{ __('Signing in as :phone', ['phone' => $pending['shown']]) }} · <a href="{{ route('login.phone', ['change' => 1]) }}" class="font-medium text-primary-600">{{ __('Change') }}</a></p>
                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                    <input id="password" name="password" type="password" autocomplete="current-password" required autofocus class="{{ $field }} @error('password') border-red-500 @enderror">
                    @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Sign in') }}</button>
            </form>
            <form method="POST" action="{{ route('login.phone.resend') }}" class="text-center">
                @csrf
                <button class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ __('Forgot password? Text me a code') }}</button>
            </form>
        @elseif(($pending['step'] ?? null) === 'code')
            <form method="POST" action="{{ route('login.phone.verify') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                @csrf
                <p class="text-sm text-gray-700">{{ __('Enter the code we texted to :to.', ['to' => $pending['shown']]) }} · <a href="{{ route('login.phone', ['change' => 1]) }}" class="font-medium text-primary-600">{{ __('Change') }}</a></p>
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
                    <label for="phone" class="block text-sm font-medium text-gray-700">{{ __('Mobile number') }}</label>
                    <input id="phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required autofocus dir="ltr" value="{{ old('phone') }}" placeholder="777 1234" class="{{ $field }} @error('phone') border-red-500 @enderror">
                    @error('phone')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @unless($smsAvailable)<p class="mt-1 text-xs text-amber-700">{{ __('Codes by text message are not available right now. Accounts with a password can still sign in.') }}</p>@endunless
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-900">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    {{ __('Remember me') }}
                </label>
                <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Continue') }}</button>
            </form>
        @endif

        <p class="text-center text-sm text-gray-600"><a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-500">{{ __('Sign in with your email instead') }}</a></p>
    </div>
</div>
@endsection
