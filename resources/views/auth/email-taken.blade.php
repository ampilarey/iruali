@extends('layouts.app')

@section('title', __('This email is on another account').' - iruali')
@section('robots', 'noindex, nofollow')

@section('content')
<div class="min-h-[60vh] flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6" data-email-taken>
        <div class="text-center">
            <h1 class="mt-6 text-2xl font-extrabold text-gray-900">{{ __('This email is on another account') }}</h1>
            <p class="mt-3 text-sm text-gray-600">{!! __('We confirmed that :email is yours. It is typed on an account that signs in with the mobile number :phone, and was never confirmed there, so we have not signed you in to that account.', ['email' => '<bdi dir="ltr" class="font-semibold text-gray-900">'.e($email).'</bdi>', 'phone' => '<bdi dir="ltr" class="font-semibold text-gray-900">'.e($phone).'</bdi>']) !!}</p>
        </div>
        <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-200 space-y-3">
            <h2 class="text-base font-semibold text-gray-900">{{ __('It is my account') }}</h2>
            <p class="text-sm text-gray-600">{{ __('Sign in with that mobile number, then confirm the email in My Account.') }}</p>
            <a href="{{ route('login') }}" class="flex w-full min-h-11 items-center justify-center rounded-md bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Sign in with the mobile number') }}</a>
        </div>
        <div class="rounded-lg bg-white p-5 shadow-sm border border-gray-200 space-y-3">
            <h2 class="text-base font-semibold text-gray-900">{{ __('It is not my account') }}</h2>
            <p class="text-sm text-gray-600">{{ __('We take your email off it, and you open your own account with it.') }}</p>
            <form method="POST" action="{{ route('login.email-taken.release') }}">
                @csrf
                <button type="submit" class="flex w-full min-h-11 items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50">{{ __('Take my email off it') }}</button>
            </form>
        </div>
    </div>
</div>
@endsection
