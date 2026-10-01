@extends('layouts.app')

@section('title', __('Two-step sign-in'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-2xl mx-auto space-y-6">
        <h1 class="text-3xl font-bold text-dark">{{ __('Two-step sign-in') }}</h1>

        @if($user->isTwoFactorEnabled())
            @if(! empty($recoveryCodes))
                <section class="rounded-lg border border-sun bg-sun-soft p-6 space-y-3">
                    <h2 class="text-lg font-semibold text-sun-ink">{{ __('Save your recovery codes') }}</h2>
                    <p class="text-sm text-sun-ink">{{ __('Each code works once if you lose your phone. They are shown only now. Keep them somewhere safe.') }}</p>
                    <ul class="grid grid-cols-2 gap-2 font-mono text-sm text-dark" dir="ltr">
                        @foreach($recoveryCodes as $code)<li class="rounded bg-white px-3 py-1.5 select-all">{{ $code }}</li>@endforeach
                    </ul>
                </section>
            @endif
            <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 space-y-4">
                <p class="flex items-center gap-2 text-green-700 font-medium"><x-icon name="check" class="w-5 h-5" />{{ __('Two-step sign-in is on.') }}</p>
                <form method="POST" action="{{ route('profile.2fa.disable') }}" class="space-y-3 max-w-sm">
                    @csrf
                    <label for="current_password" class="block text-sm text-gray-700">{{ __('Enter your password to turn it off') }}</label>
                    <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('current_password') border-red-500 @enderror">
                    @error('current_password')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-lg border border-danger px-4 py-2 text-sm font-semibold text-danger hover:bg-danger-50">{{ __('Turn off two-step sign-in') }}</button>
                </form>
            </section>
        @else
            <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 space-y-5">
                <ol class="list-decimal ps-5 space-y-1 text-sm text-gray-700">
                    <li>{{ __('Install an authenticator app (Google Authenticator, Microsoft Authenticator or Authy).') }}</li>
                    <li>{{ __('Scan this code with the app, or type the key in by hand.') }}</li>
                    <li>{{ __('Enter the 6-digit code the app shows.') }}</li>
                </ol>
                <div class="flex flex-wrap items-center gap-6">
                    <div class="rounded-lg border border-gray-200 p-2 bg-white" role="img" aria-label="{{ __('QR code for your authenticator app') }}">{!! $qr !!}</div>
                    <div class="text-sm space-y-2">
                        <p class="text-gray-600">{{ __('Key') }}</p>
                        <p class="font-mono text-base tracking-wider select-all break-all" dir="ltr">{{ chunk_split($secret, 4, ' ') }}</p>
                        <a href="{{ $otpauth }}" class="text-primary font-medium hover:underline">{{ __('Open in authenticator app') }}</a>
                    </div>
                </div>
                <form method="POST" action="{{ route('profile.2fa.enable') }}" class="space-y-3 max-w-sm">
                    @csrf
                    <label for="code" class="block text-sm font-medium text-gray-700">{{ __('Code from the app') }}</label>
                    <input id="code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6" required dir="ltr" class="block w-full rounded-md border border-gray-300 px-3 py-2 text-center text-2xl tracking-[.4em] focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('code') border-red-500 @enderror">
                    @error('code')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Turn on two-step sign-in') }}</button>
                </form>
            </section>
        @endif
        <p><a href="{{ route('account') }}" class="text-sm text-primary hover:underline">{{ __('← Back to my account') }}</a></p>
    </div>
</div>
@endsection
