@extends('layouts.app')

@php
    $editing = $code->exists;
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $type = old('type', $code->type ?? 'percent');
    $appliesTo = old('applies_to', $code->applies_to ?? 'all');
    $chosen = array_map('intval', (array) old('product_ids', $selected));
    $number = fn ($value) => $value === null || $value === '' ? '' : rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @component('seller.partials.header', ['title' => $editing ? __('Edit code :code', ['code' => $code->code]) : __('New discount code')])
        @slot('action')
            <a href="{{ route('seller.discounts') }}" class="text-sm font-medium text-primary-600 hover:text-primary-700">{{ __('All discount codes') }}</a>
        @endslot
    @endcomponent

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $editing ? route('seller.discounts.update', $code) : route('seller.discounts.store') }}" class="space-y-6 rounded-lg bg-white p-6 shadow">
            @csrf
            @if($editing) @method('PUT') @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">{{ __('Code') }} *</label>
                    <input id="code" name="code" required minlength="3" maxlength="30" pattern="[A-Za-z0-9_\-]+" autocomplete="off" dir="ltr" class="{{ $field }} font-mono uppercase" value="{{ old('code', $code->code) }}" placeholder="SAVE10">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Letters, numbers, - and _. Customers can type it in small or capital letters.') }}</p>
                </div>
                <fieldset>
                    <legend class="block text-sm font-medium text-gray-700">{{ __('Discount') }} *</legend>
                    <div class="mt-1 flex gap-2">
                        <select name="type" aria-label="{{ __('Type of discount') }}" class="rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                            <option value="percent" @selected($type === 'percent')>{{ __('Percent (%)') }}</option>
                            <option value="fixed" @selected($type === 'fixed')>{{ __('Amount (MVR)') }}</option>
                        </select>
                        <input name="value" type="number" step="0.01" min="0.01" required dir="ltr" aria-label="{{ __('Discount') }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500" value="{{ old('value', $number($code->value)) }}">
                    </div>
                    <p class="mt-1 text-xs text-gray-500">{{ __('A percent comes off each eligible item; an amount is shared over them and never more than they cost.') }}</p>
                </fieldset>
                <div>
                    <label for="min_spend" class="block text-sm font-medium text-gray-700">{{ __('Minimum spend (MVR)') }}</label>
                    <input id="min_spend" name="min_spend" type="number" step="0.01" min="0" dir="ltr" class="{{ $field }}" value="{{ old('min_spend', $number($code->min_spend)) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('On the eligible items from your shop in the cart, after multi-buy savings. Leave empty for none.') }}</p>
                </div>
                <div></div>
                <div>
                    <label for="starts_at" class="block text-sm font-medium text-gray-700">{{ __('Starts') }}</label>
                    <input id="starts_at" name="starts_at" type="datetime-local" class="{{ $field }}" value="{{ old('starts_at', $code->starts_at?->format('Y-m-d\TH:i')) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Leave empty to start now.') }}</p>
                </div>
                <div>
                    <label for="ends_at" class="block text-sm font-medium text-gray-700">{{ __('Ends') }}</label>
                    <input id="ends_at" name="ends_at" type="datetime-local" class="{{ $field }}" value="{{ old('ends_at', $code->ends_at?->format('Y-m-d\TH:i')) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Leave empty for no end date.') }}</p>
                </div>
                <div>
                    <label for="max_uses" class="block text-sm font-medium text-gray-700">{{ __('Total uses') }}</label>
                    <input id="max_uses" name="max_uses" type="number" min="1" step="1" dir="ltr" class="{{ $field }}" value="{{ old('max_uses', $code->max_uses) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('How many orders can use it in all. Leave empty for no limit.') }}</p>
                </div>
                <div>
                    <label for="max_uses_per_customer" class="block text-sm font-medium text-gray-700">{{ __('Uses per customer') }}</label>
                    <input id="max_uses_per_customer" name="max_uses_per_customer" type="number" min="1" step="1" dir="ltr" class="{{ $field }}" value="{{ old('max_uses_per_customer', $code->max_uses_per_customer) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Counted by account, or by email for guests. Leave empty for no limit.') }}</p>
                </div>
            </div>

            <fieldset class="space-y-2">
                <legend class="text-sm font-medium text-gray-700">{{ __('Products') }}</legend>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="applies_to" value="all" @checked($appliesTo !== 'selected') class="text-primary-600 focus:ring-primary-500"> {{ __('All your products') }}</label>
                <label class="flex items-center gap-2 text-sm"><input type="radio" name="applies_to" value="selected" @checked($appliesTo === 'selected') class="text-primary-600 focus:ring-primary-500"> {{ __('Only the products ticked below') }}</label>
                @if($products->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('You have no products yet.') }}</p>
                @else
                    <div class="grid max-h-72 gap-2 overflow-y-auto rounded-lg border border-gray-200 p-3 sm:grid-cols-2">
                        @foreach($products as $product)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="product_ids[]" value="{{ $product->id }}" @checked(in_array($product->id, $chosen, true)) class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                                <span class="min-w-0 truncate">{{ $product->name }} <span class="text-xs text-gray-500" dir="ltr">{{ $product->sku }}</span>@unless($product->is_active) <span class="text-xs text-gray-500">({{ __('not live') }})</span>@endunless</span>
                            </label>
                        @endforeach
                    </div>
                @endif
            </fieldset>

            <label class="flex items-start gap-2 text-sm">
                <input type="hidden" name="is_active" value="0">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $code->is_active ?? true)) class="mt-0.5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
                <span><span class="font-medium text-gray-900">{{ __('Active') }}</span> <span class="block text-xs text-gray-500">{{ __('Untick to pause the code: customers can\'t use it until you switch it back on.') }}</span></span>
            </label>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('seller.discounts') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">{{ __('Cancel') }}</a>
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ $editing ? __('Save code') : __('Create code') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
