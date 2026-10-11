@extends('layouts.app')

@section('title', __('Sell on iruali'))

{{-- "Sell on iruali" while new shop applications are paused or hidden (App\Support\SellerApplications):
     the admins' message, the reopening day and "Tell me when you reopen". --}}
@section('content')
@php $dhivehi = app()->getLocale() === 'dv'; @endphp
<div class="min-h-[60vh] bg-gray-100 px-4 py-10 lg:py-16">
    <div class="max-w-xl mx-auto rounded-2xl bg-white border border-gray-200 p-6 lg:p-10 text-center" dir="{{ $dhivehi ? 'rtl' : 'ltr' }}" @if($dhivehi) lang="dv" @endif data-applications-closed>
        <span class="mx-auto w-14 h-14 rounded-2xl bg-sun-soft text-sun-ink flex items-center justify-center"><x-icon name="store" class="w-7 h-7" /></span>
        <h1 class="mt-4 font-display text-2xl lg:text-3xl font-bold text-dark">{{ __('We are not taking new shops right now') }}</h1>
        <p class="mt-2 text-gray-600 whitespace-pre-line" data-applications-message>{{ $message ?? __('We have paused new shop applications for a while.') }}</p>
        @if($reopensOn)
            <p class="mt-3 font-semibold text-dark" data-applications-reopen>{{ __('Applications reopen on :date.', ['date' => $reopensOn->translatedFormat('j F Y')]) }}</p>
        @endif

        @if(session('success'))
            <p class="mt-6 rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-sm text-success" role="status">{{ session('success') }}</p>
        @elseif($onList)
            <p class="mt-6 rounded-lg border border-success-100 bg-success-50 px-4 py-3 text-sm text-success" role="status">{{ __('You are on the list. We will email you when applications reopen.') }}</p>
        @else
            @if(session('error'))
                <p class="mt-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" role="alert">{{ session('error') }}</p>
            @endif
            <form method="POST" action="{{ route('seller.waitlist') }}" class="mt-6 space-y-3 text-start" data-waitlist-form>
                @csrf
                @if($user?->email)
                    <p class="text-sm text-gray-600 text-center">{{ __('We will email :email once, when applications reopen.', ['email' => $user->email]) }}</p>
                @else
                    <div>
                        <label for="waitlist-email" class="block text-sm font-medium text-gray-700">{{ __('Email address') }}</label>
                        <input id="waitlist-email" name="email" type="email" required maxlength="190" dir="ltr" autocomplete="email" value="{{ old('email') }}" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                        @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        <p class="mt-1 text-xs text-gray-500">{{ __('We only use it to tell you once when applications reopen.') }}</p>
                    </div>
                @endif
                <button type="submit" class="w-full rounded-lg bg-primary px-5 py-3 font-semibold text-white hover:bg-primary-hover">{{ __('Tell me when you reopen') }}</button>
            </form>
        @endif

        <a href="{{ route('home') }}" class="mt-6 inline-block text-sm font-semibold text-primary hover:underline">{{ __('Continue shopping') }}</a>
    </div>
</div>
@endsection
