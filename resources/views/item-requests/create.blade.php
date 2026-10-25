@extends('layouts.app')

@section('title', __('Request an item'))

@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
@endphp

{{-- "Can't find it? We'll bring it for you" (ItemRequestService): the customer asks, an admin sends a price. --}}
@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-dark">{{ __('Can\'t find it? We\'ll bring it for you') }}</h1>
        <p class="mt-1 text-gray-600">{{ __('Tell us what you are looking for, from any shop in the world. We check it and send you a price in MVR, delivered to your island.') }}</p>
    </div>

    @if(session('error'))
        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
    @endif

    <form method="POST" action="{{ route('item-requests.store') }}" enctype="multipart/form-data" class="space-y-6" data-item-request-form>
        @csrf
        <section class="rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <div>
                <label for="request-link" class="block text-sm font-medium text-gray-700">{{ __('Link to the item') }} <span class="font-normal text-gray-500">({{ __('optional, but it helps') }})</span></label>
                <input id="request-link" name="link" type="url" maxlength="500" dir="ltr" placeholder="https://" value="{{ old('link', $prefillLink) }}" class="{{ $field }}">
                @error('link')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="request-title" class="block text-sm font-medium text-gray-700">{{ __('What is it?') }}</label>
                <input id="request-title" name="title" required minlength="3" maxlength="200" placeholder="{{ __('e.g. Nike Air Zoom Pegasus running shoes') }}" value="{{ old('title', $prefill) }}" class="{{ $field }}">
                @error('title')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="request-options" class="block text-sm font-medium text-gray-700">{{ __('Size, colour or model') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="request-options" name="options" maxlength="255" placeholder="{{ __('e.g. size 42, black') }}" value="{{ old('options') }}" class="{{ $field }}">
                    @error('options')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="request-quantity" class="block text-sm font-medium text-gray-700">{{ __('Quantity') }}</label>
                    <input id="request-quantity" name="quantity" type="number" inputmode="numeric" required min="1" max="50" step="1" dir="ltr" value="{{ old('quantity', 1) }}" class="{{ $field }}">
                    @error('quantity')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
            <div>
                <label for="request-details" class="block text-sm font-medium text-gray-700">{{ __('Anything else we should know') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                <textarea id="request-details" name="details" rows="3" maxlength="2000" class="{{ $field }}">{{ old('details') }}</textarea>
                @error('details')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div>
                <label for="request-photo" class="block text-sm font-medium text-gray-700">{{ __('A photo or screenshot') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                <input id="request-photo" name="photo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm">
                @error('photo')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-6 space-y-4">
            <div>
                <p class="text-sm font-medium text-gray-700">{{ __('Deliver to') }}</p>
                <x-island-picker :islands-by-atoll="$islandsByAtoll" :selected-id="old('island_id', $address?->island_id)" :island="old('island', $address?->island)" :atoll="old('atoll', $address?->atoll)" prefix="request" />
                @error('island_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="request-budget" class="block text-sm font-medium text-gray-700">{{ __('Your budget in MVR') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="request-budget" name="budget" type="number" inputmode="decimal" min="1" step="1" dir="ltr" value="{{ old('budget') }}" class="{{ $field }}">
                    @error('budget')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="request-needed-by" class="block text-sm font-medium text-gray-700">{{ __('Needed by') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="request-needed-by" name="needed_by" type="date" min="{{ today()->toDateString() }}" dir="ltr" value="{{ old('needed_by') }}" class="{{ $field }}">
                    @error('needed_by')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </div>
        </section>

        <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-xs text-gray-600 space-y-1">
            <p class="font-semibold text-dark">{{ __('How it works') }}</p>
            <p>{{ __('We check the item and send you a price, usually within 2 working days, by email or SMS. The price includes shipping to your island and any customs duty.') }}</p>
            <p>{{ __('Accept it to put it in your cart, then check out as usual: usually a booking fee now and the rest when it arrives. Nothing is charged until you check out.') }}</p>
            <p>{{ __('Some items can\'t be brought in.') }} <a href="{{ route('policies.prohibited') }}" target="_blank" rel="noopener" class="font-medium text-primary hover:underline">{{ __('See the Prohibited & Restricted Items list') }}</a></p>
        </div>

        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route('item-requests.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">{{ __('My requests') }}</a>
            <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Send request') }}</button>
        </div>
    </form>
</div>
@endsection
