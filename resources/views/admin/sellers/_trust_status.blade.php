{{-- Admin → Sellers, under a shop's status: iruali's own shop on an admin account, or a team account that cannot
     sell (support, finance, catalogue; from before team accounts were kept from selling), trusted (listings skip
     review), pre-orders and "coming soon" allowed, holiday mode (customers can't order) and its business verification. --}}
@if($seller->isStaff() && $seller->maySell())
    <span class="mt-1 flex w-fit items-center gap-1 rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800" data-seller-admin-shop>{{ __('iruali\'s own shop (admin account)') }}</span>
@elseif($seller->isStaff())
    <span class="mt-1 flex w-fit items-center gap-1 rounded-full bg-red-100 px-2.5 py-0.5 text-xs font-medium text-red-800" data-seller-team title="{{ __('Team accounts run the website and do not sell. The shop needs an account of its own.') }}">{{ __('iruali team account: cannot sell') }}</span>
@endif
@if($seller->auto_approve_listings)
    <span class="mt-1 flex w-fit items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-800" data-seller-trusted>{{ __('Trusted: listings skip review') }}</span>
@endif
@if($seller->preorders_allowed)
    <span class="mt-1 flex w-fit items-center gap-1 rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800" data-seller-preorders>{{ __('Pre-orders allowed') }}</span>
@endif
@if($seller->coming_soon_allowed)
    <span class="mt-1 flex w-fit items-center gap-1 rounded-full bg-primary-100 px-2.5 py-0.5 text-xs font-medium text-primary-800" data-seller-coming-soon>{{ __('"Coming soon" allowed') }}</span>
@endif
@if($seller->isOnHoliday())
    <span class="mt-1 flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800" @if($seller->holiday_message) title="{{ $seller->holiday_message }}" @endif data-seller-holiday>
        <x-icon name="clock" class="w-3.5 h-3.5" />{{ $seller->holiday_until ? __('On holiday until :date', ['date' => \App\Support\ShopHoliday::date($seller)]) : __('On holiday') }}
    </span>
@endif
@php $sellerVerification = $seller->businessVerification; @endphp
@if($sellerVerification)
    @php
        $verificationLabel = ['approved' => __('Verified business'), 'rejected' => __('Verification rejected'), 'pending' => __('Verification to check')][$sellerVerification->status] ?? $sellerVerification->status;
        $verificationStyle = ['approved' => 'bg-blue-100 text-blue-800', 'rejected' => 'bg-red-100 text-red-800'][$sellerVerification->status] ?? 'bg-yellow-100 text-yellow-800';
    @endphp
    <a href="{{ route('admin.verifications', $sellerVerification->isPending() ? [] : ['status' => $sellerVerification->status]) }}" class="mt-1 flex w-fit items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium hover:underline {{ $verificationStyle }}" data-seller-verification="{{ $sellerVerification->status }}">
        <x-icon name="shield" class="w-3.5 h-3.5" />{{ $verificationLabel }}
    </a>
@endif
