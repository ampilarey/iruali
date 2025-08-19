@extends('layouts.app')

@section('content')
<div class="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
    <div class="max-w-md w-full space-y-8">
        <div>
            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                {{ __('auth.verify_your_account') }}
            </h2>
            <p class="mt-2 text-center text-sm text-gray-600">
                {{ __('auth.verification_required_message') }}
            </p>
        </div>

        <div class="bg-white shadow rounded-lg p-6 space-y-6">
            <!-- Email Verification -->
            <div class="border-b border-gray-200 pb-4">
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    {{ __('auth.email_verification') }}
                </h3>
                
                @if($user->isEmailVerified())
                    <div class="flex items-center text-green-600">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        {{ __('auth.email_verified') }}
                    </div>
                @else
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">
                            {{ __('auth.email_verification_message', ['email' => $user->email]) }}
                        </p>
                        
                        <form action="{{ route('auth.send.email.otp') }}" method="POST" class="space-y-3">
                            @csrf
                            <button type="submit" 
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                {{ __('auth.resend_email_otp') }}
                            </button>
                        </form>

                        <form action="{{ route('auth.verify.email.otp') }}" method="POST" class="space-y-3">
                            @csrf
                            <input type="hidden" name="email" value="{{ $user->email }}">
                            <input type="text" name="code" placeholder="{{ __('auth.enter_email_code') }}" required
                                   class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                                   maxlength="6"
                                   pattern="[0-9]{6}">
                            <button type="submit" 
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                {{ __('auth.verify_email') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            <!-- Phone Verification -->
            <div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">
                    {{ __('auth.phone_verification') }}
                </h3>
                
                @if($user->isPhoneVerified())
                    <div class="flex items-center text-green-600">
                        <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                        {{ __('auth.phone_verified') }}
                    </div>
                @else
                    <div class="space-y-4">
                        <p class="text-sm text-gray-600">
                            {{ __('auth.phone_verification_message', ['phone' => $user->phone]) }}
                        </p>
                        
                        <form action="{{ route('auth.send.sms.otp') }}" method="POST" class="space-y-3">
                            @csrf
                            <button type="submit" 
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                                {{ __('auth.resend_sms_otp') }}
                            </button>
                        </form>

                        <form action="{{ route('auth.verify.phone.otp') }}" method="POST" class="space-y-3">
                            @csrf
                            <input type="hidden" name="phone" value="{{ $user->phone }}">
                            <input type="text" name="code" placeholder="{{ __('auth.enter_phone_code') }}" required
                                   class="appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm"
                                   maxlength="6"
                                   pattern="[0-9]{6}">
                            <button type="submit" 
                                    class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                                {{ __('auth.verify_phone') }}
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            @if($user->isEmailVerified() && $user->isPhoneVerified())
                <div class="pt-4">
                    <a href="{{ route('home') }}" 
                       class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        {{ __('auth.continue_to_dashboard') }}
                    </a>
                </div>
            @endif
        </div>

        @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-md p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-green-800">
                            {{ session('success') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection 