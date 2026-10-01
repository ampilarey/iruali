@extends('layouts.app')

@section('title', __('Reset your password'))

@section('content')
<div class="flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-6">
        <div>
            <h1 class="text-center text-3xl font-extrabold text-gray-900">{{ __('Reset your password') }}</h1>
            <p class="mt-2 text-center text-sm text-gray-600">{{ __('Enter the email you signed up with and we will send you a link to choose a new password.') }}</p>
        </div>

        @if(session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800" role="status">{{ session('status') }}</div>
        @endif

        <form class="space-y-4 bg-white shadow rounded-lg p-6" action="{{ route('password.email') }}" method="POST">
            @csrf
            <div>
                <label for="email" class="block text-sm font-medium text-gray-700">{{ __('Email address') }}</label>
                <input id="email" name="email" type="email" autocomplete="email" required value="{{ old('email') }}"
                       class="mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-gray-900 focus:ring-2 focus:ring-primary-500 focus:border-primary-500 sm:text-sm @error('email') border-red-500 @enderror">
                @error('email')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="w-full flex justify-center py-2 px-4 rounded-md text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">{{ __('Email me a reset link') }}</button>
            <p class="text-center text-sm text-gray-600"><a href="{{ route('login') }}" class="font-medium text-primary-600 hover:text-primary-500">{{ __('Back to sign in') }}</a></p>
        </form>
    </div>
</div>
@endsection
