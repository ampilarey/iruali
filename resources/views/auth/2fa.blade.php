@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                {{ __('auth.two_factor_authentication') }}
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                {{ __('auth.enter_2fa_code') }}
            </p>
        </div>
        
        <form class="mt-8 space-y-6" action="{{ route('2fa.verify') }}" method="POST">
            @csrf
            
            <div>
                <label for="code" class="sr-only">{{ __('auth.verification_code') }}</label>
                <input id="code" name="code" type="text" required 
                       class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                       placeholder="{{ __('auth.verification_code') }}"
                       maxlength="6"
                       pattern="[0-9]{6}"
                       autocomplete="off">
            </div>

            @error('code')
                <div class="text-red-600 text-sm text-center">
                    {{ $message }}
                </div>
            @enderror

            <div>
                <button type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                    {{ __('auth.verify') }}
                </button>
            </div>

            <div class="text-center">
                <p class="text-sm text-gray-600">
                    {{ __('auth.or_use_recovery_code') }}
                </p>
            </div>
        </form>
    </div>
</div>
@endsection 