@extends('layouts.admin')

@section('title', 'Create Voucher')

@section('content')
<div class="max-w-lg mx-auto py-8">
    <h1 class="text-xl font-bold lg:text-2xl mb-6">Create Voucher</h1>
    <form action="{{ route('admin.vouchers.store') }}" method="POST">
        @csrf
        <div class="mb-4">
            <label for="code" class="block text-sm font-medium mb-1">Code *</label>
            <input type="text" name="code" id="code" class="w-full border rounded px-3 py-2" required value="{{ old('code') }}">
            @error('code')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label for="type" class="block text-sm font-medium mb-1">Type *</label>
            <select name="type" id="type" class="w-full border rounded px-3 py-2" required>
                <option value="fixed" {{ old('type') == 'fixed' ? 'selected' : '' }}>Fixed Amount</option>
                <option value="percent" {{ old('type') == 'percent' ? 'selected' : '' }}>Percentage</option>
            </select>
            @error('type')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label for="amount" class="block text-sm font-medium mb-1">Amount *</label>
            <input type="number" name="amount" id="amount" class="w-full border rounded px-3 py-2" step="0.01" required value="{{ old('amount') }}">
            @error('amount')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label for="min_order" class="block text-sm font-medium mb-1">Minimum Order</label>
            <input type="number" name="min_order" id="min_order" class="w-full border rounded px-3 py-2" step="0.01" value="{{ old('min_order') }}">
            @error('min_order')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label for="max_uses" class="block text-sm font-medium mb-1">Max Uses</label>
            <input type="number" name="max_uses" id="max_uses" class="w-full border rounded px-3 py-2" value="{{ old('max_uses') }}">
            @error('max_uses')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label for="valid_from" class="block text-sm font-medium mb-1">Valid From</label>
            <input type="date" name="valid_from" id="valid_from" class="w-full border rounded px-3 py-2" value="{{ old('valid_from') }}">
            @error('valid_from')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-4">
            <label for="valid_until" class="block text-sm font-medium mb-1">Valid Until</label>
            <input type="date" name="valid_until" id="valid_until" class="w-full border rounded px-3 py-2" value="{{ old('valid_until') }}">
            @error('valid_until')<p class="text-red-600 text-sm mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="mb-6">
            <label class="inline-flex items-center">
                <input type="checkbox" name="is_active" value="1" class="rounded border-gray-300" {{ old('is_active', true) ? 'checked' : '' }}>
                <span class="ml-2">Active</span>
            </label>
        </div>
        <div class="mb-6 space-y-2 rounded border border-gray-200 bg-gray-50 p-3" data-automatic-offer>
            <label class="flex items-start gap-2">
                <input type="hidden" name="is_automatic" value="0">
                <input type="checkbox" name="is_automatic" value="1" class="mt-1 rounded border-gray-300" {{ old('is_automatic', false) ? 'checked' : '' }}>
                <span><span class="font-medium">Apply automatically</span><br><span class="text-xs text-gray-500">No code needed: when a shopper has not typed a code, the best automatic offer their cart reaches is applied (the minimum order and dates above count).</span></span>
            </label>
            <label class="flex items-start gap-2">
                <input type="hidden" name="free_delivery" value="0">
                <input type="checkbox" name="free_delivery" value="1" class="mt-1 rounded border-gray-300" {{ old('free_delivery', false) ? 'checked' : '' }}>
                <span><span class="font-medium">Free delivery</span><br><span class="text-xs text-gray-500">With "Apply automatically": delivery is free on orders over the minimum while it runs. Set the amount to 0 for free delivery only.</span></span>
            </label>
        </div>
        <div class="flex justify-end space-x-4">
            <a href="{{ route('admin.vouchers.index') }}" class="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded hover:bg-primary-700">Create</button>
        </div>
    </form>
</div>
@endsection 