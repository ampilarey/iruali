{{-- Seller Centre → Settings → Tax: the shop's GST details (App\Http\Controllers\Seller\TaxSettingsController) --}}
@php
    $taxField = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $registered = (string) old('gst_registered', $profile?->gst_registered ? '1' : '0') === '1';
@endphp
<form method="POST" action="{{ route('seller.settings.tax.update') }}" class="space-y-5 rounded-lg bg-white p-6 shadow" data-shop-tax-form>
    @csrf
    @method('PUT')
    <div>
        <h2 class="text-base font-semibold text-gray-900">{{ __('GST') }}</h2>
        <p class="text-sm text-gray-500">{{ __('If your shop is registered for GST with MIRA, each paid order from your shop gets a tax invoice showing the GST in your prices (prices on iruali include GST). Otherwise customers get a receipt.') }}</p>
    </div>

    <fieldset>
        <legend class="text-sm font-medium text-gray-700">{{ __('Is your shop GST-registered?') }}</legend>
        <div class="mt-2 flex flex-wrap gap-3">
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                <input type="radio" name="gst_registered" value="1" @checked($registered) data-gst-registered class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                {{ __('Yes, GST-registered') }}
            </label>
            <label class="flex items-center gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm has-[:checked]:border-primary-500 has-[:checked]:bg-primary-50">
                <input type="radio" name="gst_registered" value="0" @checked(! $registered) data-gst-registered class="h-4 w-4 text-primary-600 focus:ring-primary-500">
                {{ __('No') }}
            </label>
        </div>
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label for="tin" class="block text-sm font-medium text-gray-700">{{ __('GST TIN') }}</label>
            <input id="tin" name="tin" maxlength="30" dir="ltr" autocomplete="off" placeholder="1012345GST501" class="{{ $taxField }}" value="{{ old('tin', $profile?->tin) }}">
            <p class="mt-1 text-xs text-gray-500">{{ __('As on the MIRA GST certificate: 7 digits, GST, 3 digits.') }}</p>
        </div>
        <div>
            <label for="registered_name" class="block text-sm font-medium text-gray-700">{{ __('Registered business name') }}</label>
            <input id="registered_name" name="registered_name" maxlength="150" autocomplete="organization" class="{{ $taxField }}" value="{{ old('registered_name', $profile?->registered_name ?? $user->shopName()) }}">
        </div>
        <div class="sm:col-span-2">
            <label for="business_address" class="block text-sm font-medium text-gray-700">{{ __('Business address') }}</label>
            <textarea id="business_address" name="business_address" rows="2" maxlength="300" class="{{ $taxField }}">{{ old('business_address', $profile?->business_address ?? collect([$user->address, $user->city, $user->state])->filter()->join(', ')) }}</textarea>
            <p class="mt-1 text-xs text-gray-500">{{ __('Printed on your invoices. Needed when you are GST-registered.') }}</p>
        </div>
    </div>

    <p class="rounded-lg bg-gray-50 px-4 py-3 text-xs text-gray-600">{{ __('Changes apply to orders placed from now on; invoices for earlier orders keep the details they were placed with. The GST rate is set by iruali (now :rate%). Check what applies to your shop with MIRA or your accountant.', ['rate' => \App\Support\InvoiceText::rate($rate)]) }}</p>

    <div class="flex justify-end border-t border-gray-100 pt-4">
        <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">{{ __('Save tax details') }}</button>
    </div>
</form>
