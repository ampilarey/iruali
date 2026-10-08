@extends('layouts.app')

@section('title', __('Notifications'))

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-3xl font-bold text-dark mb-8">{{ __('Notifications') }}</h1>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <aside class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <nav class="space-y-1" aria-label="{{ __('Account') }}">
                        <a href="{{ route('account') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Profile') }}</a>
                        <a href="{{ route('orders') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('My Orders') }}</a>
                        <a href="{{ route('wishlist') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Wishlist') }}</a>
                        <a href="{{ route('account.brands') }}" class="block px-4 py-2 text-gray-600 hover:text-primary hover:bg-gray-50 rounded-lg">{{ __('Brands you follow') }}</a>
                        <a href="{{ route('account.notifications') }}" class="block px-4 py-2 text-primary bg-primary/10 rounded-lg font-medium" aria-current="page">{{ __('Notifications') }}</a>
                    </nav>
                </div>
            </aside>

            <div class="lg:col-span-2 space-y-6">
                <section class="bg-white rounded-lg shadow-sm border border-gray-100 p-6">
                    <h2 class="text-xl font-semibold text-dark mb-1">{{ __('How should we reach you?') }}</h2>
                    <p class="text-sm text-gray-600 mb-5">{{ __('Choose email, text message (SMS) or both for each kind of message.') }}</p>

                    @unless($smsAvailable)
                        <div class="mb-5 rounded-lg border border-sun bg-sun-soft px-4 py-3 text-sm text-sun-ink">
                            @if($user->phone)
                                {{ __('Text messages need a verified mobile number.') }}
                                @if($smsLive)<a href="{{ route('verification.notice') }}" class="font-semibold underline">{{ __('Verify your phone') }}</a>@endif
                            @else
                                {{ __('Add a mobile number to your profile to get text messages.') }}
                                <a href="{{ route('account.edit') }}" class="font-semibold underline">{{ __('Edit profile') }}</a>
                            @endif
                        </div>
                    @endunless

                    <form method="POST" action="{{ route('account.notifications.update') }}" class="space-y-5">
                        @csrf @method('PUT')
                        @foreach($types as $type => [$label, $description])
                            <fieldset class="border-t border-gray-100 pt-4 first:border-0 first:pt-0">
                                <legend class="font-medium text-gray-900">{{ $label }}</legend>
                                <p class="text-sm text-gray-500 mb-2">{{ $description }}</p>
                                <div class="flex flex-wrap gap-4 text-sm">
                                    @foreach(['email' => __('Email'), 'sms' => __('SMS'), 'both' => __('Both')] as $value => $choice)
                                        <label class="inline-flex items-center gap-2 {{ $value !== 'email' && ! $smsAvailable ? 'text-gray-400' : 'text-gray-800' }}">
                                            <input type="radio" name="{{ $type }}" value="{{ $value }}" class="text-primary focus:ring-primary"
                                                   @checked(old($type, $current[$type]) === $value) @disabled($value !== 'email' && ! $smsAvailable)>
                                            {{ $choice }}
                                        </label>
                                    @endforeach
                                </div>
                                @error($type)<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                            </fieldset>
                        @endforeach
                        <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Save settings') }}</button>
                    </form>
                </section>
                <p class="text-xs text-gray-500">{{ __('Verification codes and messages about your account security are always sent, whatever you choose here.') }}</p>
            </div>
        </div>
    </div>
</div>
@endsection
