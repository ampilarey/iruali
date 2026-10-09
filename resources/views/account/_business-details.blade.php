{{-- My account → Edit profile: business details offered at checkout for the shops' invoices --}}
@php
    $businessProfile = \App\Models\BusinessProfile::forUser($user->id);
    $bf = 'mt-1 block w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:ring-2 focus:ring-primary-500 focus:border-primary-500';
@endphp
<section id="business" class="mt-8 bg-white rounded-lg shadow-sm border border-gray-100 p-6" data-business-profile>
    <h2 class="text-xl font-semibold text-dark">{{ __('Business details') }}</h2>
    <p class="mt-1 text-sm text-gray-600">{{ __('Buying for a company? Its name, TIN and address go on the invoices from the shops. At checkout you choose whether to use them for each order.') }}</p>
    <form method="POST" action="{{ route('account.business.update') }}" class="mt-4 space-y-4">
        @csrf @method('PUT')
        <div>
            <label for="company_name" class="block text-sm font-medium text-gray-700">{{ __('Company name') }}</label>
            <input id="company_name" name="company_name" required maxlength="150" autocomplete="organization" value="{{ old('company_name', $businessProfile?->company_name) }}" class="{{ $bf }} @error('company_name', 'business') border-red-500 @enderror">
            @error('company_name', 'business')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="tin" class="block text-sm font-medium text-gray-700">{{ __('TIN') }} <span class="font-normal text-gray-500">({{ __('optional') }})</span></label>
            <input id="tin" name="tin" maxlength="30" dir="ltr" autocomplete="off" placeholder="1012345GST501" value="{{ old('tin', $businessProfile?->tin) }}" class="{{ $bf }} @error('tin', 'business') border-red-500 @enderror">
            <p class="mt-1 text-xs text-gray-500">{{ __('As on the MIRA GST certificate: 7 digits, GST, 3 digits.') }}</p>
            @error('tin', 'business')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div>
            <label for="business_address" class="block text-sm font-medium text-gray-700">{{ __('Business address') }}</label>
            <input id="business_address" name="business_address" required maxlength="300" autocomplete="street-address" value="{{ old('business_address', $businessProfile?->business_address) }}" class="{{ $bf }} @error('business_address', 'business') border-red-500 @enderror">
            @error('business_address', 'business')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <div class="flex flex-wrap gap-3">
            <button type="submit" class="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-hover">{{ __('Save business details') }}</button>
        </div>
    </form>
    @if($businessProfile)
        <form method="POST" action="{{ route('account.business.destroy') }}" class="mt-3" onsubmit="return confirm('{{ __('Remove your business details?') }}')">
            @csrf @method('DELETE')
            <button type="submit" class="text-sm text-gray-600 underline hover:text-gray-900">{{ __('Remove business details') }}</button>
        </form>
    @endif
</section>
