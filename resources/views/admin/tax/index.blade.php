@extends('layouts.app')

@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $registered = (string) old('gst_registered', $settings['gst_registered'] ? '1' : '0') === '1';
    $prefix = old('invoice_prefix', $settings['invoice_prefix']);
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('admin.payouts._header', ['title' => __('Tax (GST)')])

    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
        <div class="rounded-lg bg-white p-5 shadow text-sm" data-tax-status>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wider text-primary-600">{{ __('GST-ready') }}</p>
                    @if($settings['gst_registered'])
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ __('iruali is GST-registered') }}</h2>
                        <p class="mt-1 text-gray-600">{{ __('TIN :tin at :rate%. iruali\'s receipts show the GST in delivery fees, and commission invoices show the GST in commission, for orders placed from the day it was switched on.', ['tin' => $settings['gst_tin'], 'rate' => \App\Support\InvoiceText::rate($settings['gst_rate'])]) }}</p>
                    @else
                        <h2 class="mt-1 text-lg font-semibold text-gray-900">{{ __('iruali is not GST-registered') }}</h2>
                        <p class="mt-1 text-gray-600">{{ __('Nothing about GST shows on iruali\'s receipts or commission invoices until you switch registration on below. Shops that say they are GST-registered (Seller Centre → Settings → Tax) get tax invoices for their own sales.') }}</p>
                    @endif
                </div>
                <a href="{{ route('admin.tax.report') }}" class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white hover:bg-primary-700">{{ __('Monthly GST report') }}</a>
            </div>
        </div>

        @if($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc ps-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('admin.tax.update') }}" class="space-y-5 rounded-lg bg-white p-6 shadow" data-tax-settings>
            @csrf
            @method('PUT')
            <h2 class="text-base font-semibold text-gray-900">{{ __('iruali\'s GST settings') }}</h2>

            <fieldset>
                <legend class="text-sm font-medium text-gray-700">{{ __('Is iruali GST-registered?') }}</legend>
                <div class="mt-2 flex flex-wrap gap-3">
                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                        <input type="radio" name="gst_registered" value="1" @checked($registered) class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                        {{ __('Yes, GST-registered') }}
                    </label>
                    <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                        <input type="radio" name="gst_registered" value="0" @checked(! $registered) class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                        {{ __('No (default)') }}
                    </label>
                </div>
            </fieldset>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="gst_tin" class="block text-sm font-medium text-gray-700">{{ __('iruali\'s GST TIN') }}</label>
                    <input id="gst_tin" name="gst_tin" maxlength="30" dir="ltr" autocomplete="off" placeholder="1012345GST501" class="{{ $field }}" value="{{ old('gst_tin', $settings['gst_tin']) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('As on the MIRA GST certificate: 7 digits, GST, 3 digits.') }}</p>
                </div>
                <div>
                    <label for="gst_rate" class="block text-sm font-medium text-gray-700">{{ __('GST rate (%)') }}</label>
                    <input id="gst_rate" name="gst_rate" type="number" step="0.01" min="0" max="100" required dir="ltr" class="{{ $field }}" value="{{ old('gst_rate', number_format($settings['gst_rate'], 2, '.', '')) }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Prices include GST: GST = price × rate ÷ (100 + rate).') }}</p>
                </div>
                <div>
                    <label for="invoice_prefix" class="block text-sm font-medium text-gray-700">{{ __('Invoice number prefix') }}</label>
                    <input id="invoice_prefix" name="invoice_prefix" maxlength="10" required dir="ltr" class="{{ $field }} uppercase" value="{{ $prefix }}">
                    <p class="mt-1 text-xs text-gray-500">{{ __('Letters, digits and dashes. Shop invoices look like :shop, iruali\'s commission invoices like :commission.', ['shop' => $prefix.'-12-000001', 'commission' => $prefix.'-C-000001']) }}</p>
                </div>
            </div>

            <p class="rounded-lg bg-amber-50 px-4 py-3 text-xs text-amber-900">{{ __('Before you switch registration on or change the rate, confirm the rate and what your invoices must show with your accountant or MIRA. Changes apply to orders placed after you save; earlier orders keep the GST they were placed with.') }}</p>

            <div class="flex justify-end border-t border-gray-100 pt-4">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save tax settings') }}</button>
            </div>
        </form>

        <div class="rounded-lg bg-white p-5 shadow text-sm" data-company-details>
            <h2 class="text-base font-semibold text-gray-900">{{ __('Business details on invoices') }}</h2>
            <p class="mt-1 text-gray-600">{{ __('iruali\'s receipts and commission invoices use the business details from Settings.') }}</p>
            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                <div><dt class="text-gray-500">{{ __('Registered business name') }}</dt><dd class="font-medium text-gray-900">{{ $company['legal_name'] ?? $company['trading_name'] }}</dd></div>
                <div><dt class="text-gray-500">{{ __('Registration no.') }}</dt><dd class="font-medium text-gray-900">{{ $company['registration_no'] ?? '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-gray-500">{{ __('Business address') }}</dt><dd class="font-medium text-gray-900">{{ $company['address'] ?? '—' }}</dd></div>
            </dl>
            @if(\App\Support\StaffAccess::can('admin.settings'))
                <a href="{{ route('admin.settings') }}" class="mt-3 inline-block text-sm font-medium text-primary-700 hover:underline">{{ __('Change in Settings') }}</a>
            @endif
        </div>

        <div class="overflow-hidden rounded-lg bg-white shadow" data-registered-shops>
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 class="text-base font-semibold text-gray-900">{{ __('GST-registered shops') }}</h2>
                <p class="text-xs text-gray-500">{{ __('As each shop entered it in Seller Centre → Settings → Tax.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wider text-gray-500">
                        <tr><th class="px-4 py-2 text-start">{{ __('Shop') }}</th><th class="px-4 py-2 text-start">{{ __('Registered business name') }}</th><th class="px-4 py-2 text-start">{{ __('TIN') }}</th><th class="px-4 py-2 text-start">{{ __('Business address') }}</th></tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($shops as $shopProfile)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900">{{ $shopProfile->user?->shopName() }}</td>
                                <td class="px-4 py-2">{{ $shopProfile->registered_name }}</td>
                                <td class="px-4 py-2 font-mono" dir="ltr">{{ $shopProfile->tin }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $shopProfile->business_address }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">{{ __('No shop has said it is GST-registered yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
