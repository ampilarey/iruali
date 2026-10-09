{{-- Admin → Payouts: "Require verified business before payouts" (off by default; only admins may change it) and the shops it holds back. --}}
@php
    $verifiedRule = \App\Models\SellerVerification::requiredForPayouts();
    $heldShops = $verifiedRule ? $sellers->filter(fn ($shop) => $shop->getAttribute('balances')['available'] > 0 && $shop->bankAccount && ! $shop->hasVerifiedBusiness()) : collect();
@endphp
<div class="rounded-lg bg-white p-5 shadow" data-payout-rule="{{ $verifiedRule ? 'on' : 'off' }}">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="max-w-2xl">
            <h2 class="flex items-center gap-1.5 text-base font-semibold text-gray-900"><x-icon name="shield" class="w-5 h-5 text-primary-600" />{{ __('Require verified business before payouts') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ __('When this is on, a shop is paid only once iruali has verified its business registration (Admin → Verifications). Payouts and payout batches skip shops that are not verified; their earnings wait until they are.') }}</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $verifiedRule ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' }}">{{ $verifiedRule ? __('On') : __('Off') }}</span>
            @if(\App\Support\StaffAccess::can('admin.verifications.payout-rule'))
                <form method="POST" action="{{ route('admin.verifications.payout-rule') }}">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="required" value="{{ $verifiedRule ? 0 : 1 }}">
                    <button class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50">{{ $verifiedRule ? __('Turn off') : __('Turn on') }}</button>
                </form>
            @endif
        </div>
    </div>
    @if($heldShops->isNotEmpty())
        <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" data-payout-held>
            {{ trans_choice(':count shop with earnings ready is held because its business is not verified: :shops.|:count shops with earnings ready are held because their business is not verified: :shops.', $heldShops->count(), ['count' => $heldShops->count(), 'shops' => $heldShops->map(fn ($shop) => $shop->shopName())->join(', ')]) }}
            @if(\Illuminate\Support\Facades\Route::has('admin.verifications') && \App\Support\StaffAccess::can('admin.verifications'))<a href="{{ route('admin.verifications') }}" class="font-semibold underline">{{ __('Business verifications') }}</a>@endif
        </p>
    @endif
</div>
