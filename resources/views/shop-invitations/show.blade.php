@extends('layouts.app')

@section('title', __('Join a shop\'s staff').' - iruali')

@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    // Links that cannot be used: what happened, in plain words
    $problems = [
        'invalid' => __('This invitation link does not work any more. It may have been replaced by a newer one: ask the shop to send it again.'),
        'revoked' => __(':shop has withdrawn this invitation.', ['shop' => $shopName]),
        'accepted' => __('This invitation has already been used.'),
        'expired' => __('This invitation has run out. Invitation links work for :days days: ask :shop to send you a new one.', ['days' => (int) config('shop_staff.invitation_days', 7), 'shop' => $shopName]),
        'shop_closed' => __(':shop is not open on iruali at the moment, so you cannot join its staff.', ['shop' => $shopName]),
        'wrong_account' => __('This invitation is for :email, but you are signed in as :current. Sign out, then open the link again and sign in as :email.', ['email' => $invitation?->email, 'current' => $signedInAs]),
        'has_shop' => __('This account has a shop of its own on iruali, so it cannot join another shop\'s staff. Ask :shop to invite a different email.', ['shop' => $shopName]),
        'staff_elsewhere' => __('This account already works for another shop. An account can be on one shop\'s staff only.'),
        'team' => __('iruali team accounts cannot join a shop\'s staff.'),
        'already_staff' => __('You are already on the staff of :shop.', ['shop' => $shopName]),
    ];
@endphp

@section('content')
<div class="min-h-[60vh] flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full rounded-lg bg-white p-6 shadow space-y-5" data-invitation-state="{{ $state }}">
        <div class="text-center">
            <p class="text-xs font-medium uppercase tracking-wider text-primary-600">{{ __('Seller Centre') }}</p>
            <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ __('Join :shop', ['shop' => $shopName]) }}</h1>
        </div>

        @if(isset($problems[$state]))
            <p class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $problems[$state] }}</p>
            @if($isMember)
                <a href="{{ route('seller.dashboard') }}" class="block w-full rounded-lg bg-primary-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-primary-700">{{ __('Open the Seller Centre') }}</a>
            @elseif($state === 'wrong_account')
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Sign Out') }}</button>
                </form>
            @endif
        @else
            <div class="rounded-lg bg-gray-50 px-4 py-3 text-sm text-gray-700">
                <p>{{ __(':name invited :email to the staff of :shop as a :role.', ['name' => $invitation->inviter->name ?? $shopName, 'email' => $invitation->email, 'shop' => $shopName, 'role' => mb_strtolower($roleLabel)]) }}</p>
                @if($roleDescription)<p class="mt-1 text-xs text-gray-500">{{ $roleDescription }}</p>@endif
                <p class="mt-1 text-xs text-gray-500">{{ __('You sign in with your own account, and you can still shop on iruali as usual.') }}</p>
            </div>

            @error('invitation')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            @if($state === 'accept')
                <form method="POST" action="{{ $actionUrl }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Accept and open the Seller Centre') }}</button>
                </form>
            @elseif($state === 'sign_in')
                <p class="text-sm text-gray-600">{{ __('There is already an iruali account for :email. Sign in with it to accept; you come back here afterwards.', ['email' => $invitation->email]) }}</p>
                <a href="{{ route('login') }}" class="block w-full rounded-lg bg-primary-600 px-4 py-2 text-center text-sm font-medium text-white hover:bg-primary-700">{{ __('Sign in') }}</a>
            @elseif($state === 'create_account')
                <form method="POST" action="{{ $actionUrl }}" class="space-y-4">
                    @csrf
                    <p class="text-sm text-gray-600">{{ __('Make your iruali account to accept. Your email is confirmed by this link.') }}</p>
                    <div>
                        <label class="block text-sm font-medium text-gray-700">{{ __('Email') }}</label>
                        <p class="mt-1 text-sm text-gray-900" dir="ltr">{{ $invitation->email }}</p>
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">{{ __('Full name') }}</label>
                        <input id="name" name="name" required maxlength="255" autocomplete="name" class="{{ $field }}" value="{{ old('name') }}">
                        @error('name')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">{{ __('Password') }}</label>
                        <input id="password" name="password" type="password" required autocomplete="new-password" class="{{ $field }}">
                        @error('password')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="password_confirmation" class="block text-sm font-medium text-gray-700">{{ __('Confirm password') }}</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="{{ $field }}">
                    </div>
                    <label class="flex items-start gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="agree_terms" value="1" required class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600">
                        <span>{{ __('I agree to the') }} <a href="{{ route('policies.terms') }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">{{ __('Terms & Conditions') }}</a> {{ __('and') }} <a href="{{ route('policies.privacy') }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">{{ __('Privacy Policy') }}</a></span>
                    </label>
                    @error('agree_terms')<p class="text-sm text-red-600">{{ $message }}</p>@enderror
                    <button type="submit" class="w-full rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Create my account and join') }}</button>
                </form>
            @endif
        @endif
    </div>
</div>
@endsection
