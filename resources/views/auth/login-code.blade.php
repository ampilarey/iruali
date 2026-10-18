@extends('layouts.app')

@section('title', __('Sign in with a code').' - iruali')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6" data-login-code>
        <div class="text-center">
            <h1 class="mt-6 text-3xl font-extrabold text-gray-900">{{ __('Sign in with a code') }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ __('No password needed: we send a 6-digit code to your phone or email.') }}</p>
        </div>

        @if(session('status'))
            <p class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif

        @if($pending)
            {{-- Step 2: the code --}}
            <form method="POST" action="{{ route('login.code.verify') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100" data-login-code-verify>
                @csrf
                <p class="text-sm text-gray-700">{{ $pending['channel'] === 'sms' ? __('Enter the code we texted to :to.', ['to' => $pending['shown']]) : __('Enter the code we emailed to :to.', ['to' => $pending['shown']]) }}</p>
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">{{ __('Code') }}</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required autofocus dir="ltr"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-center text-2xl tracking-[.4em] focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('code') border-red-500 @enderror">
                    @error('code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Sign in') }}</button>
                <p class="text-xs text-gray-500">{{ __('The code works for 10 minutes. Nothing came? Check the number or email, wait a minute and ask again.') }}</p>
                <p class="text-sm"><a href="{{ route('login.code', ['change' => 1]) }}" class="font-medium text-primary-600 hover:text-primary-500">{{ __('Use another number or email, or send a new code') }}</a></p>
            </form>
        @else
            {{-- Step 1: where to send it --}}
            <form method="POST" action="{{ route('login.code.send') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100" data-login-code-send>
                @csrf
                <div>
                    <label for="login" class="block text-sm font-medium text-gray-700">{{ $smsAvailable ? __('Mobile number or email') : __('Email address') }}</label>
                    <input id="login" name="login" type="text" autocomplete="username" required dir="ltr" value="{{ old('login') }}"
                           placeholder="{{ $smsAvailable ? '777 1234 / you@example.com' : 'you@example.com' }}"
                           class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('login') border-red-500 @enderror">
                    @error('login')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    @if($smsAvailable)<p class="mt-1 text-xs text-gray-500">{{ __('By text message to a mobile number you have verified on your account, or by email.') }}</p>@endif
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-900">
                    <input type="checkbox" name="remember" value="1" class="h-4 w-4 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                    {{ __('Remember me') }}
                </label>
                <button type="submit" class="w-full rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Send me a code') }}</button>
            </form>
        @endif

        <p class="text-center text-sm text-gray-600"><a href="{{ route('login.email') }}" class="font-medium text-primary-600 hover:text-primary-500">{{ __('Sign in with your password instead') }}</a></p>
    </div>
</div>
@endsection
