@extends('layouts.app')

@section('title', __('Track Your Order'))

@section('content')
<div class="max-w-md mx-auto py-12">
    <div class="bg-white rounded-lg shadow-lg p-8">
        <h1 class="text-2xl font-bold mb-6 text-center">{{ __('Track Your Order') }}</h1>
        <form action="{{ route('order.track.submit') }}" method="POST">
            @csrf
            <div class="mb-4">
                <label for="order_code" class="block text-sm font-medium text-gray-700 mb-2">{{ __('Order Code') }}</label>
                <input type="text" name="order_code" id="order_code" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g. ORD-123456">
                @error('order_code')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <div class="my-4 text-center text-gray-500">{{ __('or') }}</div>
            <div class="mb-4">
                <label for="mobile" class="block text-sm font-medium text-gray-700 mb-2">{{ __('Mobile Number') }}</label>
                <input type="text" name="mobile" id="mobile" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="e.g. 7xxxxxx">
            </div>
            <div class="mb-6">
                <label for="otp" class="block text-sm font-medium text-gray-700 mb-2">{{ __('OTP') }}</label>
                <input type="text" name="otp" id="otp" class="w-full px-3 py-2 border border-gray-300 rounded-md" placeholder="6-digit code">
                @error('otp')
                    <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="w-full bg-primary-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-primary-700 transition duration-300">{{ __('Track Order') }}</button>
        </form>
    </div>
</div>
@endsection 