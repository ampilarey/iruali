@extends('layouts.app')

@section('title', __('Newsletter'))

@section('content')
<div class="max-w-md mx-auto px-4 lg:px-6 py-16 text-center">
    <span class="mx-auto w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center mb-4"><x-icon name="mail" class="w-7 h-7" /></span>
    @if($subscriber)
        <h1 class="font-display text-2xl font-bold text-dark mb-2">{{ __('You are subscribed') }}</h1>
        <p class="text-gray-600">{{ __(':email will get the iruali newsletter. Every email has a link to unsubscribe.', ['email' => $subscriber->email]) }}</p>
    @else
        <h1 class="font-display text-2xl font-bold text-dark mb-2">{{ __('This link no longer works') }}</h1>
        <p class="text-gray-600">{{ __('The address was taken off the list since. Sign up again at the bottom of any page.') }}</p>
    @endif
    <div class="mt-6">
        <a href="{{ route('home') }}" class="px-5 py-2.5 rounded-lg bg-primary text-white font-semibold">{{ __('Back to shopping') }}</a>
    </div>
</div>
@endsection
