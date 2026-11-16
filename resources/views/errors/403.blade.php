@extends('layouts.app')

@section('title', __('No access to this page'))
@section('robots', 'noindex, nofollow')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
    <img src="/images/brand/iruali-mark.svg" alt="" width="96" height="92" class="mx-auto h-20 w-auto opacity-80">
    <p class="mt-6 text-sm font-semibold uppercase tracking-wider text-primary">403</p>
    <h1 class="mt-2 font-display text-3xl font-bold text-dark">{{ __('No access to this page') }}</h1>
    <p class="mt-3 text-gray-600 max-w-md mx-auto">{{ __('Your account cannot open this page. Sign in with another account, or go back to the shop.') }}</p>
    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <a href="{{ route('home') }}" class="inline-flex items-center min-h-11 rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Go to the home page') }}</a>
        @guest
            <a href="{{ route('login') }}" class="inline-flex items-center min-h-11 rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Sign in') }}</a>
        @else
            <a href="{{ route('shop') }}" class="inline-flex items-center min-h-11 rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Browse products') }}</a>
        @endguest
    </div>
</div>
@endsection
