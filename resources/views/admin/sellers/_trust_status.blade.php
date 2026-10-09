{{-- Admin → Sellers, under a shop's status: holiday mode (customers can't order) and its business verification. --}}
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
