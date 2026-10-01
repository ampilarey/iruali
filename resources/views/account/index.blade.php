@extends('layouts.app')

@section('title', __('My Account'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-dark mb-8">{{ __('My Account') }}</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <aside class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center gap-4 mb-6">
                        <span class="w-14 h-14 shrink-0 rounded-full bg-primary-50 text-primary font-display text-xl font-bold flex items-center justify-center" aria-hidden="true">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <div class="min-w-0">
                            <h2 class="text-lg font-semibold text-dark truncate">{{ $user->name }}</h2>
                            <p class="text-sm text-gray-500 truncate" dir="ltr">{{ $user->email }}</p>
                        </div>
                    </div>
                    <nav class="space-y-1" aria-label="{{ __('Account') }}">
                        <a href="{{ route('account') }}" class="block px-4 py-2 text-primary bg-primary/10 rounded-lg font-medium" aria-current="page">{{ __('Profile') }}</a>
                        <a href="{{ route('orders') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('My Orders') }}</a>
                        <a href="{{ route('wishlist') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Wishlist') }}</a>
                        <a href="{{ route('account.rewards') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Rewards') }}</a>
                        <a href="#security" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Security') }}</a>
                    </nav>
                </div>
            </aside>

            <div class="lg:col-span-2 space-y-6">
                @unless($user->isEmailVerified())
                    <div class="rounded-lg border border-sun bg-sun-soft px-4 py-3 text-sm text-sun-ink flex flex-wrap items-center justify-between gap-2">
                        <span>{{ __('Your email is not verified yet.') }}</span>
                        <a href="{{ route('verification.notice') }}" class="font-semibold underline">{{ __('Verify now') }}</a>
                    </div>
                @endunless

                <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <div class="flex items-center justify-between gap-3 mb-5">
                        <h2 class="text-xl font-semibold text-dark">{{ __('Profile Information') }}</h2>
                        <a href="{{ route('account.edit') }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Edit') }}</a>
                    </div>
                    <dl class="grid gap-4 sm:grid-cols-2 text-sm">
                        <div><dt class="text-gray-500">{{ __('Name') }}</dt><dd class="text-gray-900">{{ $user->name }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Email') }}</dt><dd class="text-gray-900" dir="ltr">{{ $user->email }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Phone') }}</dt><dd class="text-gray-900" dir="ltr">{{ $user->phone ?: __('Not added') }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Language') }}</dt><dd class="text-gray-900">{{ ($user->preferred_language ?? 'en') === 'dv' ? 'ދިވެހި' : 'English' }}</dd></div>
                        <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('Delivery address') }}</dt>
                            <dd class="text-gray-900">@if($user->address){{ $user->address }}@if($user->city), {{ $user->city }}@endif @if($user->state){{ $user->state }}@endif @if($user->postal_code){{ $user->postal_code }}@endif @else{{ __('Not added') }}@endif</dd></div>
                        <div><dt class="text-gray-500">{{ __('Member Since') }}</dt><dd class="text-gray-900">{{ $user->created_at->translatedFormat('j F Y') }}</dd></div>
                        <div><dt class="text-gray-500">{{ __('Loyalty Points') }}</dt><dd class="text-gray-900">{{ max(0, (int) $user->loyalty_points) }} · <a href="{{ route('account.rewards') }}" class="text-primary hover:underline">{{ __('Rewards') }}</a></dd></div>
                    </dl>
                </section>

                <section id="security" class="bg-white rounded-lg shadow-sm border border-gray-100 p-6 scroll-mt-36 space-y-6">
                    <h2 class="text-xl font-semibold text-dark">{{ __('Security') }}</h2>

                    <form method="POST" action="{{ route('account.password') }}" class="space-y-3 max-w-md">
                        @csrf @method('PUT')
                        <h3 class="font-medium text-gray-900">{{ __('Change password') }}</h3>
                        <div>
                            <label for="current_password" class="block text-sm text-gray-700">{{ __('Current password') }}</label>
                            <input id="current_password" name="current_password" type="password" autocomplete="current-password" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('current_password') border-red-500 @enderror">
                            @error('current_password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password" class="block text-sm text-gray-700">{{ __('New password') }}</label>
                            <input id="password" name="password" type="password" autocomplete="new-password" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500 @error('password') border-red-500 @enderror">
                            <p class="mt-1 text-xs text-gray-500">{{ __('At least 8 characters with upper and lower case letters, a number and a symbol.') }}</p>
                            @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                        <div>
                            <label for="password_confirmation" class="block text-sm text-gray-700">{{ __('Confirm password') }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Change password') }}</button>
                    </form>

                    <form method="POST" action="{{ route('account.marketing') }}" class="border-t border-gray-100 pt-5 flex flex-wrap items-center justify-between gap-3">
                        @csrf @method('PUT')
                        <div>
                            <h3 class="font-medium text-gray-900">{{ __('Marketing emails') }}</h3>
                            <p class="text-sm text-gray-600">{{ $user->marketing_opt_out_at ? __('Off. You only get emails about your orders.') : __('On. Offers and reminders about items left in your cart.') }}</p>
                        </div>
                        <input type="hidden" name="marketing_emails" value="{{ $user->marketing_opt_out_at ? 1 : 0 }}">
                        <button type="submit" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ $user->marketing_opt_out_at ? __('Turn on') : __('Turn off') }}</button>
                    </form>

                    <div class="border-t border-gray-100 pt-5 flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h3 class="font-medium text-gray-900">{{ __('Two-step sign-in') }}</h3>
                            <p class="text-sm text-gray-600">{{ $user->isTwoFactorEnabled() ? __('On. A code from your authenticator app is needed to sign in.') : __('Off. Add a code from an authenticator app to protect your account.') }}</p>
                        </div>
                        <a href="{{ route('profile.2fa.setup') }}" class="rounded-lg border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ $user->isTwoFactorEnabled() ? __('Manage') : __('Turn on') }}</a>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
@endsection
