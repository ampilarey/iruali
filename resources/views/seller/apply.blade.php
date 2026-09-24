@extends('layouts.app')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
            <p class="text-xs font-medium uppercase tracking-wider text-primary-600">Sell on iruali</p>
            <h1 class="mt-1 text-3xl font-bold text-gray-900">Open your shop</h1>
            <p class="mt-2 max-w-2xl text-sm text-gray-600">Reach customers on every inhabited island. Tell us about your business and we'll review your application, usually within two working days.</p>
        </div>
    </div>

    <div class="max-w-7xl mx-auto px-4 py-6 sm:px-6 lg:px-8 grid gap-6 lg:grid-cols-3">
        <aside class="space-y-4 lg:order-last">
            <div class="rounded-lg bg-white p-5 shadow">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-gray-500">How it works</h2>
                <ol class="mt-3 space-y-3 text-sm text-gray-700">
                    <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">1</span>Submit this form. You get Seller Centre access straight away.</li>
                    <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">2</span>Add your products while we review your shop.</li>
                    <li class="flex gap-3"><span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary-100 text-xs font-semibold text-primary-700">3</span>Once approved, your listings go live in the store.</li>
                </ol>
            </div>
            <div class="rounded-lg bg-white p-5 shadow text-sm text-gray-700">
                Applying as <span class="font-medium text-gray-900">{{ $user->email }}</span>.
                Customer features like your orders and wishlist keep working as normal.
            </div>
        </aside>

        <div class="lg:col-span-2">
            @if($errors->any())
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <ul class="list-disc pl-5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('seller.apply.store') }}" class="space-y-6 rounded-lg bg-white p-6 shadow">
                @csrf

                <section class="space-y-4">
                    <h2 class="text-lg font-semibold text-gray-900">Your business</h2>
                    <div>
                        <label for="business_name" class="block text-sm font-medium text-gray-700">Shop name *</label>
                        <input id="business_name" name="business_name" required maxlength="255" class="{{ $field }}" value="{{ old('business_name') }}" placeholder="e.g. Island Crafts">
                    </div>
                    <div>
                        <label for="business_description" class="block text-sm font-medium text-gray-700">What do you sell? *</label>
                        <textarea id="business_description" name="business_description" rows="4" required minlength="20" maxlength="2000" class="{{ $field }}" placeholder="Describe your products, where they come from and how you fulfil orders.">{{ old('business_description') }}</textarea>
                    </div>
                </section>

                <section class="space-y-4 border-t border-gray-100 pt-6">
                    <h2 class="text-lg font-semibold text-gray-900">Contact &amp; pickup location</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone *</label>
                            <input id="phone" name="phone" type="tel" required class="{{ $field }}" value="{{ old('phone', $user->phone) }}" placeholder="7XX XXXX">
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">Island / city *</label>
                            <input id="city" name="city" required class="{{ $field }}" value="{{ old('city', $user->city) }}" placeholder="e.g. Malé">
                        </div>
                        <div class="sm:col-span-2">
                            <label for="address" class="block text-sm font-medium text-gray-700">Address *</label>
                            <input id="address" name="address" required class="{{ $field }}" value="{{ old('address', $user->address) }}">
                        </div>
                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700">Atoll</label>
                            <input id="state" name="state" class="{{ $field }}" value="{{ old('state', $user->state) }}" placeholder="e.g. Kaafu">
                        </div>
                    </div>
                </section>

                <div class="border-t border-gray-100 pt-6">
                    <label class="flex items-start gap-3 text-sm text-gray-700">
                        <input type="checkbox" name="agree_seller_terms" value="1" required class="mt-0.5 h-4 w-4 rounded border-gray-300 text-primary-600" @checked(old('agree_seller_terms'))>
                        <span>I confirm the details above are accurate and agree to iruali's seller terms, including that listings are reviewed before going live.</span>
                    </label>
                </div>

                <div class="flex justify-end">
                    <button class="rounded-lg bg-primary-600 px-5 py-2.5 text-sm font-medium text-white hover:bg-primary-700">Submit application</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
