@extends('layouts.app')

@section('title', __('Unsubscribed'))

@section('content')
<div class="max-w-xl mx-auto px-4 py-16 text-center">
    <h1 class="text-2xl font-bold text-dark">{{ __('You are unsubscribed from marketing emails') }}</h1>
    <p class="mt-2 text-gray-600">{{ __('We will not send offers or cart reminders to :email any more. Emails about your orders still arrive.', ['email' => $email]) }}</p>
    <p class="mt-1 text-sm text-gray-500">{{ __('You can switch them back on from My Account at any time.') }}</p>
    <a href="{{ route('home') }}" class="mt-6 inline-flex items-center px-5 py-2.5 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover">{{ __('Back to iruali') }}</a>
</div>
@endsection
