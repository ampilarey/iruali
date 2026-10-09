@extends('layouts.app')

@section('title', __('Request a bulk quote'))

@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
@endphp

@section('content')
<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <nav class="mb-4 text-sm text-gray-500" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ route('quotes.index') }}" class="hover:text-primary hover:underline">{{ __('My quote requests') }}</a>
    </nav>
    <h1 class="text-2xl font-bold text-dark">{{ __('Request a bulk quote') }}</h1>
    <p class="mt-1 text-sm text-gray-600">{{ __('For businesses buying in quantity: the shop replies with a price for the quantity it can supply and how long the price holds. Nothing is ordered until you accept the quote and check out.') }}</p>

    @if(! $product)
        {{-- From the shop page: pick one of the shop's products first --}}
        <form method="GET" action="{{ route('quotes.create') }}" class="mt-6 rounded-xl border border-gray-200 bg-white p-6 space-y-4" data-quote-product-picker>
            <p class="text-sm text-gray-700">{{ __('Which of :shop\'s products do you need?', ['shop' => $shop->shopName()]) }}</p>
            @if($products->isEmpty())
                <p class="text-sm text-gray-500">{{ __('This shop has nothing on sale right now.') }}</p>
            @else
                <div>
                    <label for="quote-product" class="block text-sm font-medium text-gray-700">{{ __('Product') }}</label>
                    <select id="quote-product" name="product" required class="{{ $field }}">
                        @foreach($products as $choice)
                            <option value="{{ $choice->id }}">{{ $choice->name }}@if($choice->sku) ({{ $choice->sku }})@endif – {{ \App\Support\Money::format($choice->final_price) }}</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Continue') }}</button>
            @endif
        </form>
    @else
        @php $business ??= null; $address ??= null; @endphp
        <div class="mt-6 flex gap-4 rounded-xl border border-gray-200 bg-white p-4" data-quote-product>
            <img src="{{ $product->mainImage?->url ?? '/images/product-placeholder.svg' }}" alt="" class="h-20 w-20 shrink-0 rounded-lg bg-primary-50 object-cover">
            <div class="min-w-0 text-sm">
                <a href="{{ route('products.show', $product) }}" class="font-semibold text-dark hover:text-primary hover:underline">{{ $product->name }}</a>
                <p class="text-gray-600">{{ __('Sold by') }} {{ $shop->shopName() }}</p>
                <p class="text-gray-600">{{ __('Listed at :price each', ['price' => \App\Support\Money::format($product->final_price)]) }} · {{ __('Bulk quotes from :count units', ['count' => $minimum]) }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('quotes.store') }}" class="mt-6 space-y-6" data-quote-form>
            @csrf
            <input type="hidden" name="product_id" value="{{ $product->id }}">

            <section class="rounded-xl border border-gray-200 bg-white p-6 space-y-4">
                <h2 class="text-lg font-semibold text-dark">{{ __('What you need') }}</h2>
                @if($product->has_variants)
                    <div>
                        <label for="quote-variant" class="block text-sm font-medium text-gray-700">{{ __('Option') }}</label>
                        <select id="quote-variant" name="product_variant_id" required class="{{ $field }}">
                            <option value="">{{ __('Choose an option') }}</option>
                            @foreach($product->variants as $variant)
                                <option value="{{ $variant->id }}" @selected((string) old('product_variant_id') === (string) $variant->id)>{{ $variant->displayNameWithKeys() }} – {{ \App\Support\Money::format($variant->effectivePrice()) }}</option>
                            @endforeach
                        </select>
                        @error('product_variant_id')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                @endif
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="quote-quantity" class="block text-sm font-medium text-gray-700">{{ __('Quantity') }}</label>
                        <input id="quote-quantity" name="quantity" type="number" inputmode="numeric" required min="{{ $minimum }}" max="{{ \App\Services\QuoteService::MAX_QUANTITY }}" step="1" dir="ltr" value="{{ old('quantity', $minimum) }}" class="{{ $field }}">
                        <p class="mt-1 text-xs text-gray-500">{{ __('At least :count.', ['count' => $minimum]) }}</p>
                        @error('quantity')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label for="quote-needed-by" class="block text-sm font-medium text-gray-700">{{ __('Needed by') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                        <input id="quote-needed-by" name="needed_by" type="date" min="{{ today()->toDateString() }}" dir="ltr" value="{{ old('needed_by') }}" class="{{ $field }}">
                        @error('needed_by')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-700">{{ __('Deliver to') }}</p>
                    <x-island-picker :islands-by-atoll="$islandsByAtoll" :selected-id="old('island_id', $address?->island_id)" :island="old('island', $address?->island)" :atoll="old('atoll', $address?->atoll)" prefix="quote" />
                </div>
                <div>
                    <label for="quote-notes" class="block text-sm font-medium text-gray-700">{{ __('Notes for the shop') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <textarea id="quote-notes" name="notes" rows="3" maxlength="2000" placeholder="{{ __('e.g. delivery in two batches, branding, packaging, regular orders') }}" class="{{ $field }}">{{ old('notes') }}</textarea>
                    @error('notes')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
            </section>

            <section class="rounded-xl border border-gray-200 bg-white p-6 space-y-4" data-quote-business>
                <div>
                    <h2 class="text-lg font-semibold text-dark">{{ __('Your business') }}</h2>
                    <p class="text-sm text-gray-600">{{ __('The shop sees who is asking, and the invoice for the order carries these details.') }}</p>
                </div>
                <div>
                    <label for="buyer_business_name" class="block text-sm font-medium text-gray-700">{{ __('Company name') }}</label>
                    <input id="buyer_business_name" name="buyer_business_name" required maxlength="150" autocomplete="organization" value="{{ old('buyer_business_name', $business?->company_name) }}" class="{{ $field }}">
                    @error('buyer_business_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="buyer_tin" class="block text-sm font-medium text-gray-700">{{ __('TIN') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
                    <input id="buyer_tin" name="buyer_tin" maxlength="30" dir="ltr" autocomplete="off" placeholder="1012345GST501" value="{{ old('buyer_tin', $business?->tin) }}" class="{{ $field }}">
                    @error('buyer_tin')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="buyer_business_address" class="block text-sm font-medium text-gray-700">{{ __('Business address') }}</label>
                    <input id="buyer_business_address" name="buyer_business_address" required maxlength="300" autocomplete="street-address" value="{{ old('buyer_business_address', $business?->business_address) }}" class="{{ $field }}">
                    @error('buyer_business_address')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
                </div>
                <label class="flex items-start gap-2 text-sm text-gray-700">
                    <input type="hidden" name="save_business" value="0">
                    <input type="checkbox" name="save_business" value="1" @checked(old('save_business', '1') === '1') class="mt-0.5 h-4 w-4 rounded text-primary-600 focus:ring-primary-500">
                    <span>{{ $business ? __('Update the business details on my account (used at checkout too)') : __('Save these business details to my account (used at checkout too)') }}</span>
                </label>
            </section>

            <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-xs text-gray-600 space-y-1">
                <p class="font-semibold text-dark">{{ __('How it works') }}</p>
                <p>{{ __('The shop sends a unit price and how long it holds, or asks you something in the messages. Accept the quote to put it in your cart at that price and quantity, then check out as usual and pay by card.') }}</p>
                <p>{{ __('Quoted prices are already the shop\'s best: shop codes, multi-buy offers and iruali vouchers do not apply to them. You can use your loyalty points and wallet. Stock is not held for a quote, so check out soon after accepting.') }}</p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('products.show', $product) }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">{{ __('Cancel') }}</a>
                <button type="submit" class="rounded-lg bg-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Send request') }}</button>
            </div>
        </form>
    @endif
</div>
@endsection
