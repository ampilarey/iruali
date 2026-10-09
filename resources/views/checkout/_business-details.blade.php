{{-- Checkout: optional business details for the shops' invoices (App\Services\GstService::buyerFromRequest). Prefilled from My account → Business details; guests type them in. --}}
@php
    $businessProfile = auth()->check() ? \App\Models\BusinessProfile::forUser(auth()->id()) : null;
    $businessOn = session()->hasOldInput() ? (bool) old('business_invoice') : $businessProfile !== null;
    $businessField = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500';
@endphp
<section class="bg-white rounded-2xl border border-gray-200 p-6" data-business-details>
    <label class="flex items-start gap-3 cursor-pointer">
        <input type="checkbox" name="business_invoice" value="1" @checked($businessOn) data-business-toggle class="mt-1 h-4 w-4 rounded text-primary-600 focus:ring-primary-500">
        <span>
            <span class="block text-base font-semibold text-gray-900">{{ __('Buying for a business? Add details for the invoice') }}</span>
            <span class="block text-sm text-gray-600">{{ __('The company name, TIN and address go on the invoice from each shop.') }}</span>
        </span>
    </label>
    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-4 {{ $businessOn ? '' : 'hidden' }}" data-business-fields>
        <div class="sm:col-span-2">
            <label for="buyer_business_name" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Company name') }}</label>
            <input type="text" id="buyer_business_name" name="buyer_business_name" maxlength="150" autocomplete="organization" class="{{ $businessField }}" value="{{ old('buyer_business_name', $businessProfile?->company_name) }}" @error('buyer_business_name') aria-invalid="true" @enderror>
            @error('buyer_business_name')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="buyer_tin" class="block text-sm font-medium text-gray-700 mb-1">{{ __('TIN') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <input type="text" id="buyer_tin" name="buyer_tin" maxlength="30" dir="ltr" autocomplete="off" placeholder="1012345GST501" class="{{ $businessField }}" value="{{ old('buyer_tin', $businessProfile?->tin) }}" @error('buyer_tin') aria-invalid="true" @enderror>
            @error('buyer_tin')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        <div class="sm:col-span-2">
            <label for="buyer_business_address" class="block text-sm font-medium text-gray-700 mb-1">{{ __('Business address') }}</label>
            <input type="text" id="buyer_business_address" name="buyer_business_address" maxlength="300" autocomplete="street-address" class="{{ $businessField }}" value="{{ old('buyer_business_address', $businessProfile?->business_address) }}" @error('buyer_business_address') aria-invalid="true" @enderror>
            @error('buyer_business_address')<p class="mt-1 text-sm text-danger">{{ $message }}</p>@enderror
        </div>
        @auth
            @unless($businessProfile)
                <p class="sm:col-span-2 text-xs text-gray-500"><a href="{{ route('account.edit') }}#business" class="text-primary hover:underline">{{ __('Save business details to your profile') }}</a> {{ __('to have them filled in next time.') }}</p>
            @endunless
        @endauth
    </div>
</section>
@push('scripts')
<script>
    (function () {
        var toggle = document.querySelector('[data-business-toggle]'), fields = document.querySelector('[data-business-fields]');
        if (!toggle || !fields) return;
        toggle.addEventListener('change', function () { fields.classList.toggle('hidden', !toggle.checked); });
    })();
</script>
@endpush
