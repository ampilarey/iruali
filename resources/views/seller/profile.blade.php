@extends('layouts.app')

@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500'; @endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => 'Profile'])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('seller.profile.update') }}" enctype="multipart/form-data" class="space-y-4 rounded-lg bg-white p-6 shadow">
            @csrf
            @method('PUT')

            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-100 pb-4">
                <div>
                    <p class="text-sm text-gray-500">Email</p>
                    <p class="text-sm font-medium text-gray-900">{{ $user->email }}</p>
                </div>
                @if($user->seller_approved)
                    <span class="rounded-full bg-green-100 px-2 py-1 text-xs font-medium text-green-800">Approved seller</span>
                @else
                    <span class="rounded-full bg-yellow-100 px-2 py-1 text-xs font-medium text-yellow-800">Awaiting approval</span>
                @endif
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="business_name" class="block text-sm font-medium text-gray-700">Shop name *</label>
                    <input id="business_name" name="business_name" required class="{{ $field }}" value="{{ old('business_name', $user->business_name ?: $user->name) }}">
                </div>
                @unless(\App\Support\CurrentShop::isStaff())
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Your name *</label>
                    <input id="name" name="name" required class="{{ $field }}" value="{{ old('name', $user->name) }}">
                </div>
                @endunless
                <div class="sm:col-span-2">
                    <label for="business_description" class="block text-sm font-medium text-gray-700">About your shop</label>
                    <textarea id="business_description" name="business_description" rows="3" class="{{ $field }}">{{ old('business_description', $user->business_description) }}</textarea>
                </div>
                @unless(\App\Support\CurrentShop::isStaff())
                <div class="sm:col-span-2">
                    <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                    <input id="phone" name="phone" class="{{ $field }}" value="{{ old('phone', $user->phone) }}">
                </div>
                @endunless
                <div class="sm:col-span-2">
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <input id="address" name="address" class="{{ $field }}" value="{{ old('address', $user->address) }}">
                </div>
                <div>
                    <label for="city" class="block text-sm font-medium text-gray-700">Island / city</label>
                    <input id="city" name="city" class="{{ $field }}" value="{{ old('city', $user->city) }}">
                </div>
                <div>
                    <label for="state" class="block text-sm font-medium text-gray-700">Atoll</label>
                    <input id="state" name="state" class="{{ $field }}" value="{{ old('state', $user->state) }}">
                </div>
                <div>
                    <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal code</label>
                    <input id="postal_code" name="postal_code" class="{{ $field }}" value="{{ old('postal_code', $user->postal_code) }}">
                </div>
                <div>
                    <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                    <input id="country" name="country" class="{{ $field }}" value="{{ old('country', $user->country ?? 'Maldives') }}">
                </div>
            </div>

            <div id="branding" class="border-t border-gray-100 pt-4">
                <h2 class="text-base font-semibold text-gray-900">{{ __('Shop logo and banner') }}</h2>
                <p class="text-sm text-gray-500">{{ __('The logo is shown next to your shop name; the banner across the top of your shop page. JPEG, PNG or WebP.') }}</p>
                <div class="mt-3 grid gap-4 sm:grid-cols-2">
                    <div>
                        <label for="shop_logo" class="block text-sm font-medium text-gray-700">{{ __('Shop logo') }} <span class="font-normal text-gray-500">({{ __('square, up to 2 MB') }})</span></label>
                        @if($user->shop_logo)<img src="{{ \Illuminate\Support\Facades\Storage::url($user->shop_logo) }}" alt="" class="mt-1 h-16 w-16 rounded-lg object-cover border border-gray-200">@endif
                        <input id="shop_logo" name="shop_logo" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-gray-700">
                    </div>
                    <div>
                        <label for="shop_banner" class="block text-sm font-medium text-gray-700">{{ __('Shop banner') }} <span class="font-normal text-gray-500">({{ __('wide, up to 4 MB') }})</span></label>
                        @if($user->shop_banner)<img src="{{ \Illuminate\Support\Facades\Storage::url($user->shop_banner) }}" alt="" class="mt-1 h-16 w-full rounded-lg object-cover border border-gray-200">@endif
                        <input id="shop_banner" name="shop_banner" type="file" accept="image/jpeg,image/png,image/webp" class="mt-1 block w-full text-sm text-gray-700">
                    </div>
                </div>
            </div>

            <div id="delivery" class="border-t border-gray-100 pt-4">
                <h2 class="text-base font-semibold text-gray-900">{{ __('Delivery options') }}</h2>
                <p class="text-sm text-gray-500">{{ __('Tell customers how you send orders: boat or flight to other islands, courier in Malé, pick-up, and how long it usually takes.') }}</p>
                <div class="mt-3 space-y-3">
                    <div>
                        <label for="delivery_notes" class="block text-sm font-medium text-gray-700">{{ __('How you deliver') }}</label>
                        <textarea id="delivery_notes" name="delivery_notes" rows="3" maxlength="1000" class="{{ $field }}">{{ old('delivery_notes', $user->delivery_notes) }}</textarea>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="hidden" name="ships_to_islands" value="0">
                        <input type="checkbox" name="ships_to_islands" value="1" class="rounded border-gray-300" @checked(old('ships_to_islands', $user->ships_to_islands ?? true))>
                        {{ __('I ship to other islands (not only my own island)') }}
                    </label>
                </div>
            </div>

            @if(\App\Support\CurrentShop::can('seller.settings.bank'))
            <div class="border-t border-gray-100 pt-4">
                <h2 class="text-base font-semibold text-gray-900">{{ __('Payout bank account') }}</h2>
                <p class="text-sm text-gray-500">
                    @if($user->bankAccount)
                        {{ $user->bankAccount->account_name }} · {{ $user->bankAccount->bankName() }} · <span class="font-mono" dir="ltr">{{ $user->bankAccount->maskedNumber() }}</span>
                    @else
                        {{ __('Add your bank account so iruali can pay you. Without it your earnings stay on hold.') }}
                    @endif
                    <a href="{{ route('seller.settings.bank') }}" class="ms-1 font-medium text-primary-600 hover:underline">{{ __('Manage bank account') }}</a>
                </p>
            </div>
            @endif

            <div class="flex justify-end border-t border-gray-100 pt-4">
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">Save profile</button>
            </div>
        </form>
    </div>
</div>
@endsection
