@extends('layouts.app')

@section('title', __('You are offline'))
@section('robots', 'noindex, nofollow')

@section('content')
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-center">
    <img src="/images/brand/iruali-mark.svg" alt="" width="96" height="92" class="mx-auto h-20 w-auto opacity-80">
    <p class="mt-6 text-sm font-semibold uppercase tracking-wider text-primary">{{ __('No connection') }}</p>
    <h1 class="mt-2 font-display text-3xl font-bold text-dark">{{ __('You are offline') }}</h1>
    <p class="mt-3 text-gray-600 max-w-md mx-auto">{{ __('This page is not available without an internet connection. Check your connection and try again.') }}</p>
    <div class="mt-8 flex flex-wrap justify-center gap-3">
        <button type="button" onclick="window.location.reload()" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Try again') }}</button>
        <a href="{{ route('home') }}" class="rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-medium text-gray-700 hover:bg-gray-50">{{ __('Go to the home page') }}</a>
    </div>
</div>
@endsection
