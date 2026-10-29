{{-- A visitor without an account: who they are and how iruali answers them (email or mobile), and a field bots fill in. --}}
<div class="grid grid-cols-1 gap-2 sm:grid-cols-3" data-guest-fields>
    <div>
        <label for="guest-name" class="block text-xs font-medium text-gray-700">{{ __('Your name') }}</label>
        <input id="guest-name" name="name" required maxlength="100" autocomplete="name" value="{{ old('name') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
        @error('name')<p class="text-xs text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="guest-email" class="block text-xs font-medium text-gray-700">{{ __('Email') }}</label>
        <input id="guest-email" name="email" type="email" maxlength="190" autocomplete="email" dir="ltr" value="{{ old('email') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
        @error('email')<p class="text-xs text-danger">{{ $message }}</p>@enderror
    </div>
    <div>
        <label for="guest-phone" class="block text-xs font-medium text-gray-700">{{ __('or mobile number') }}</label>
        <input id="guest-phone" name="phone" type="tel" maxlength="20" autocomplete="tel" dir="ltr" placeholder="7771234" value="{{ old('phone') }}" class="mt-1 w-full rounded-lg border-gray-300 text-sm">
        @error('phone')<p class="text-xs text-danger">{{ $message }}</p>@enderror
    </div>
</div>
<p class="text-xs text-gray-500">{{ __('No account needed. We answer by email or SMS, with a link to this conversation.') }}</p>
<div class="hidden" aria-hidden="true"><label>Website <input name="website" tabindex="-1" autocomplete="off"></label></div>
