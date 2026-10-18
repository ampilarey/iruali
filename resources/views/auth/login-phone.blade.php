@extends('layouts.app')

@section('title', __('Sign in').' - iruali')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6" data-login-phone="{{ $pending['step'] ?? 'phone' }}">
        <div class="text-center">
            <h1 class="mt-6 text-3xl font-extrabold text-gray-900">{{ __('Sign in or create an account') }}</h1>
            <p class="mt-2 text-sm text-gray-600">{{ __('Use your mobile number or email. New to iruali? Type your mobile number: we text you a code and your account is ready.') }}</p>
        </div>

        @include('auth._sign-in-steps')

        @unless($pending)
            @include('auth._social')
        @endunless
        <p class="text-center text-sm text-gray-600">{{ __('Staff, or an older account with email?') }} <a href="{{ route('login.email') }}" class="font-medium text-primary-600 hover:text-primary-500" data-login-email-link>{{ __('Sign in with email') }}</a></p>
    </div>
</div>
@endsection
