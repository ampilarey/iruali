{{-- A visitor without an account: who they are and how iruali answers them (an email or a mobile is enough), and a field
     bots fill in. $compact (narrow boxes such as the product page's) keeps the fields one under the other. --}}
@php
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-dark placeholder:text-gray-400 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $contactError = $errors->first('email') ?: $errors->first('phone');
@endphp
<fieldset class="space-y-3" data-guest-fields>
    <legend class="text-sm font-semibold text-dark">{{ __('Your details') }}</legend>
    <div>
        <label for="guest-name" class="block text-xs font-medium text-gray-700">{{ __('Your name') }}</label>
        <input id="guest-name" name="name" required maxlength="100" autocomplete="name" value="{{ old('name') }}" class="{{ $field }}">
        @error('name')<p class="mt-1 text-xs text-danger">{{ $message }}</p>@enderror
    </div>
    <div class="grid grid-cols-1 gap-3 {{ ($compact ?? false) ? '' : 'sm:grid-cols-2' }}">
        <div>
            <label for="guest-email" class="block text-xs font-medium text-gray-700">{{ __('Email') }}</label>
            <input id="guest-email" name="email" type="email" maxlength="190" autocomplete="email" dir="ltr" placeholder="name@example.com" value="{{ old('email') }}" class="{{ $field }} {{ $contactError ? 'border-danger' : '' }}">
        </div>
        <div>
            <label for="guest-phone" class="block text-xs font-medium text-gray-700">{{ __('Mobile number') }}</label>
            <input id="guest-phone" name="phone" type="tel" maxlength="20" autocomplete="tel" dir="ltr" placeholder="7771234" value="{{ old('phone') }}" class="{{ $field }} {{ $contactError ? 'border-danger' : '' }}">
        </div>
    </div>
    @if($contactError)
        <p class="text-xs text-danger" data-guest-contact-error>{{ $contactError }}</p>
    @else
        <p class="text-xs text-gray-500">{{ __('An email or a mobile number is enough. We answer there, with a link to this conversation. No account needed.') }}</p>
    @endif
</fieldset>
<div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
