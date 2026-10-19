{{-- The sign-in steps (mobile number or email → password or a code), on the sign-in page and inside
     checkout ($inCheckout: the steps come back to checkout, and a registered customer may also ask
     for a code by SMS there instead of the password). --}}
@php
    $inCheckout = $inCheckout ?? false;
    $changeUrl = $inCheckout ? route('checkout', ['change' => 1]) : route('login', ['change' => 1]);
    $field = 'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500';
@endphp
        @if(session('status'))<p class="rounded-md bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</p>@endif
        @error('login')<p class="rounded-md bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ $message }}</p>@enderror

        @if(($pending['step'] ?? null) === 'password')
            <form method="POST" action="{{ route('login.phone.password') }}" class="space-y-4 rounded-lg bg-white p-6 shadow-sm border border-gray-100">
                @csrf
                <p class="text-sm text-gray-700">{{ __('Signing in as :phone', ['phone' => $pending['shown']]) }} · <a href="{{ $changeUrl }}" class="font-medium text-primary-600">{{ __('Change') }}</a></p>
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
                @if($inCheckout && ($pending['checkout'] ?? false) && ($pending['sms_code'] ?? false))
                    <form method="POST" action="{{ route('login.phone.sms-code') }}" data-sms-code>
                        @csrf
                        <button class="text-sm font-medium text-primary-600 hover:text-primary-500">{{ __('Text me a code instead') }}</button>
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
                <p class="text-sm text-gray-700">{{ ($pending['channel'] ?? 'sms') === 'mail' ? __('Enter the code we emailed to :to.', ['to' => $pending['shown_to'] ?? $pending['shown']]) : __('Enter the code we texted to :to.', ['to' => $pending['shown_to'] ?? $pending['shown']]) }} · <a href="{{ $changeUrl }}" class="font-medium text-primary-600">{{ __('Change') }}</a></p>
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
                @if($inCheckout)<input type="hidden" name="checkout" value="1">@endif
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

