@extends('layouts.app')

{{-- Seller Centre → Products → "Import from a link" (App\Services\ProductImportService): the product form is filled from
     a product's page on an online shop abroad. For shops allowed pre-orders. --}}
@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => __('Import from a link')])

    <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
        <form method="POST" action="{{ route('seller.products.from-link.store') }}" class="space-y-4 rounded-lg bg-white p-6 shadow" data-from-link-form>
            @csrf
            <p class="text-sm text-gray-700">{{ __('Paste the address of a product on an online shop. We read its name, description, brand, options, price and photos from the page and fill in a new pre-order product for you to check.') }}</p>
            <div>
                <label for="url" class="block text-sm font-medium text-gray-700">{{ __('Link to the product') }}</label>
                <input id="url" name="url" type="url" required maxlength="500" dir="ltr" value="{{ old('url') }}" placeholder="https://" class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500">
                @error('url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
            </div>
            <ul class="list-disc space-y-1 ps-5 text-xs text-gray-600">
                <li>{{ __('Some big shops block this (Amazon, Shein, Temu, for example). Then fill in the product yourself.') }}</li>
                <li>{{ __('The price is converted to MVR with iruali\'s rates as a suggestion; you set the final price.') }}</li>
                <li>{{ __('Use photos you may use. Nothing is listed until you save the product and iruali approves it.') }}</li>
            </ul>
            <div class="flex items-center gap-3">
                <button type="submit" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Fill in the product') }}</button>
                <a href="{{ route('seller.products.index') }}" class="text-sm text-gray-600 hover:underline">{{ __('Cancel') }}</a>
            </div>
        </form>
    </div>
</div>
@endsection
