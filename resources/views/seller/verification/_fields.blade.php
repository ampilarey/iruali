{{-- Business verification fields: the seller application (optional there) and Seller Centre → Settings → Business verification.
     $verification: what the shop already sent; its files and ID number may then be left empty to keep them. The form needs enctype="multipart/form-data". --}}
@php
    $verification = $verification ?? null;
    $verificationInput = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
    $verificationFile = 'mt-1 block w-full text-sm text-gray-700 file:me-3 file:rounded-lg file:border-0 file:bg-primary-50 file:px-3 file:py-2 file:text-sm file:font-medium file:text-primary-700 hover:file:bg-primary-100';
@endphp
<div class="grid gap-4 sm:grid-cols-2" data-verification-fields>
    <div>
        <label for="business_registration_number" class="block text-sm font-medium text-gray-700">{{ __('Business registration number') }}</label>
        <input id="business_registration_number" name="business_registration_number" maxlength="50" dir="ltr" autocomplete="off" class="{{ $verificationInput }}" value="{{ old('business_registration_number', $verification?->business_registration_number) }}" placeholder="{{ __('e.g. C-0123/2020') }}">
        @error('business_registration_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="registration_certificate" class="block text-sm font-medium text-gray-700">{{ __('Business registration certificate') }}</label>
        <input id="registration_certificate" name="registration_certificate" type="file" accept="application/pdf,image/jpeg,image/png" class="{{ $verificationFile }}">
        <p class="mt-1 text-xs text-gray-500">{{ $verification ? __('PDF, JPG or PNG, up to 5 MB. Leave empty to keep the one we have.') : __('PDF, JPG or PNG, up to 5 MB.') }}</p>
        @error('registration_certificate')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="national_id_number" class="block text-sm font-medium text-gray-700">{{ __('Owner\'s national ID card number') }}</label>
        <input id="national_id_number" name="national_id_number" maxlength="30" dir="ltr" autocomplete="off" class="{{ $verificationInput }}" value="{{ old('national_id_number') }}" placeholder="{{ $verification ? $verification->maskedNationalId() : __('e.g. A123456') }}">
        @if($verification)<p class="mt-1 text-xs text-gray-500">{{ __('Leave empty to keep the number we have.') }}</p>@endif
        @error('national_id_number')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="id_card_front" class="block text-sm font-medium text-gray-700">{{ __('Photo of the ID card (front)') }}</label>
        <input id="id_card_front" name="id_card_front" type="file" accept="image/jpeg,image/png" class="{{ $verificationFile }}">
        <p class="mt-1 text-xs text-gray-500">{{ $verification ? __('JPG or PNG, up to 5 MB. Leave empty to keep the one we have.') : __('JPG or PNG, up to 5 MB. The name, photo and number must be easy to read.') }}</p>
        @error('id_card_front')<p class="mt-1 text-xs text-red-700">{{ $message }}</p>@enderror
    </div>
</div>
