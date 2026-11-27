@extends('layouts.app')

@section('title', $title)

@section('content')
{{-- A link in an email does nothing until the button is pressed: mail scanners open links to check them --}}
<div class="max-w-md mx-auto px-4 lg:px-6 py-16 text-center" data-email-link-action>
    <span class="mx-auto w-14 h-14 rounded-full bg-primary-50 text-primary flex items-center justify-center mb-4"><x-icon name="mail" class="w-7 h-7" /></span>
    <h1 class="font-display text-2xl font-bold text-dark mb-2">{{ $title }}</h1>
    @if(filled($email ?? null))<p class="text-gray-600" dir="ltr">{{ $email }}</p>@endif
    <form action="{{ request()->fullUrl() }}" method="POST" class="mt-6">
        <button type="submit" class="px-5 py-2.5 rounded-lg bg-primary text-white font-semibold">{{ $button }}</button>
    </form>
</div>
@endsection
