@extends('layouts.app')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    <div class="bg-white shadow">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
                </div>
                <div class="flex items-center">
                    <a href="{{ route('admin.dashboard') }}" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm font-medium">Back to Dashboard</a>
                </div>
            </div>
        </div>
    </div>

    <div class="max-w-3xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Storefront</h2>
                <div class="mt-4 space-y-4">
                    <div>
                        <label for="announcement_text" class="block text-sm font-medium text-gray-700">Announcement bar</label>
                        <input id="announcement_text" name="announcement_text" class="{{ $field }}" value="{{ old('announcement_text', $settings['announcement_text']) }}">
                        <p class="mt-1 text-xs text-gray-500">Shown at the top of every page. Leave empty to hide the bar.</p>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="contact_email" class="block text-sm font-medium text-gray-700">Contact email</label>
                            <input id="contact_email" name="contact_email" type="email" class="{{ $field }}" value="{{ old('contact_email', $settings['contact_email']) }}">
                        </div>
                        <div>
                            <label for="contact_phone" class="block text-sm font-medium text-gray-700">Contact phone</label>
                            <input id="contact_phone" name="contact_phone" class="{{ $field }}" value="{{ old('contact_phone', $settings['contact_phone']) }}">
                        </div>
                        <div>
                            <label for="whatsapp_number" class="block text-sm font-medium text-gray-700">WhatsApp number</label>
                            <input id="whatsapp_number" name="whatsapp_number" class="{{ $field }}" placeholder="+960 7xx xxxx" value="{{ old('whatsapp_number', $settings['whatsapp_number'] ?? '') }}">
                            @error('whatsapp_number')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                    <p class="text-xs text-gray-500">Used by the “Contact Us” links. The WhatsApp number adds “Chat on WhatsApp” buttons to the help centre and product pages.</p>
                </div>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Loyalty &amp; referrals</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="loyalty_spend_per_point" class="block text-sm font-medium text-gray-700">MVR spent per point</label>
                        <input id="loyalty_spend_per_point" name="loyalty_spend_per_point" type="number" min="1" step="0.01" required class="{{ $field }}" value="{{ old('loyalty_spend_per_point', $settings['loyalty_spend_per_point']) }}">
                    </div>
                    <div>
                        <label for="referral_referrer_points" class="block text-sm font-medium text-gray-700">Referrer reward (points)</label>
                        <input id="referral_referrer_points" name="referral_referrer_points" type="number" min="0" required class="{{ $field }}" value="{{ old('referral_referrer_points', $settings['referral_referrer_points']) }}">
                    </div>
                    <div>
                        <label for="referral_referee_points" class="block text-sm font-medium text-gray-700">New customer reward (points)</label>
                        <input id="referral_referee_points" name="referral_referee_points" type="number" min="0" required class="{{ $field }}" value="{{ old('referral_referee_points', $settings['referral_referee_points']) }}">
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Referral rewards are paid once, when a referred customer places their first order.</p>
            </section>

            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Delivery fees (MVR)</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="delivery_fee_greater_male" class="block text-sm font-medium text-gray-700">Greater Malé</label>
                        <input id="delivery_fee_greater_male" name="delivery_fee_greater_male" type="number" min="0" step="0.01" required class="{{ $field }}" value="{{ old('delivery_fee_greater_male', $settings['delivery_fee_greater_male']) }}">
                    </div>
                    <div>
                        <label for="delivery_fee_islands" class="block text-sm font-medium text-gray-700">Other islands</label>
                        <input id="delivery_fee_islands" name="delivery_fee_islands" type="number" min="0" step="0.01" required class="{{ $field }}" value="{{ old('delivery_fee_islands', $settings['delivery_fee_islands']) }}">
                    </div>
                    <div>
                        <label for="free_delivery_over" class="block text-sm font-medium text-gray-700">Free delivery over</label>
                        <input id="free_delivery_over" name="free_delivery_over" type="number" min="0" step="0.01" required class="{{ $field }}" value="{{ old('free_delivery_over', $settings['free_delivery_over']) }}">
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Greater Malé is Malé, Hulhumalé and Villimalé. Set "Free delivery over" to 0 to always charge delivery. Checked against the order total after discounts.</p>
            </section>

            @php $bml = app(\App\Services\BmlConnect::class); @endphp
            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Payments</h2>
                <div class="mt-3 rounded-lg border px-4 py-3 text-sm {{ $bml->enabled() ? 'border-green-200 bg-green-50 text-green-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
                    @if($bml->enabled())
                        <p class="font-semibold">BML card payments are on{{ $bml->isSandbox() ? ' (sandbox: test cards only, no real money)' : '' }}.</p>
                        <p class="mt-1">Customers pay on Bank of Maldives' secure page. Refunds are made in the BML merchant portal.</p>
                    @else
                        <p class="font-semibold">BML card payments are off.</p>
                        <p class="mt-1">Add <code>BML_API_KEY</code> (and <code>BML_ENVIRONMENT=production</code> when BML approves you) to the server's <code>.env</code>. See docs/PAYMENTS_BML.md.</p>
                    @endif
                </div>
                <label class="mt-4 flex items-start gap-3">
                    <input type="hidden" name="payment_cod_enabled" value="0">
                    <input type="checkbox" name="payment_cod_enabled" value="1" class="mt-0.5 h-4 w-4 rounded text-primary-600" @checked(old('payment_cod_enabled', $settings['payment_cod_enabled'] ?? '1') !== '0')>
                    <span class="text-sm"><span class="font-medium text-gray-900">Offer cash on delivery</span><span class="block text-gray-500">Turn off to take card payments only. Cash on delivery stays on while card payments are off.</span></span>
                </label>
            </section>
            <section class="rounded-lg bg-white p-6 shadow">
                <h2 class="text-lg font-semibold text-gray-900">Bank transfer</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="bank_name" class="block text-sm font-medium text-gray-700">Bank</label>
                        <input id="bank_name" name="bank_name" class="{{ $field }}" value="{{ old('bank_name', $settings['bank_name']) }}">
                    </div>
                    <div>
                        <label for="bank_account_name" class="block text-sm font-medium text-gray-700">Account name</label>
                        <input id="bank_account_name" name="bank_account_name" class="{{ $field }}" value="{{ old('bank_account_name', $settings['bank_account_name']) }}">
                    </div>
                    <div>
                        <label for="bank_account_number" class="block text-sm font-medium text-gray-700">Account number</label>
                        <input id="bank_account_number" name="bank_account_number" class="{{ $field }}" value="{{ old('bank_account_number', $settings['bank_account_number']) }}">
                    </div>
                </div>
                <p class="mt-2 text-xs text-gray-500">Bank transfer is offered at checkout once an account number is set. Customers upload their slip on the order page; you confirm it on the order.</p>
            </section>

            <div class="flex justify-end">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Save settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
