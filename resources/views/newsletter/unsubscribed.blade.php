@extends('layouts.app')

@section('title', __('Unsubscribed'))

@section('content')
<div class="max-w-md mx-auto px-4 lg:px-6 py-16 text-center">
    <span class="mx-auto w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center mb-4"><x-icon name="mail" class="w-7 h-7" /></span>
    <h1 class="font-display text-2xl font-bold text-dark mb-2">{{ __('You are unsubscribed') }}</h1>
    <p class="text-gray-600">{{ __(':email will not get our newsletter or offers any more. Order and account emails continue as part of the service.', ['email' => $email]) }}</p>
    <div class="mt-6 flex flex-wrap justify-center gap-3">
        <a href="{{ route('home') }}" class="px-5 py-2.5 rounded-lg bg-primary text-white font-semibold">{{ __('Back to shopping') }}</a>
        <form action="{{ route('newsletter.store') }}" method="POST">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button type="submit" class="px-5 py-2.5 rounded-lg border border-gray-300 font-semibold">{{ __('Subscribe again') }}</button>
        </form>
    </div>
</div>
@endsection
