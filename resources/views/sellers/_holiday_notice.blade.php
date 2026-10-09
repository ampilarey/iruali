{{-- A shop on holiday (Seller Centre → Settings → Holiday mode): its products stay listed but nobody can order until it is back.
     The product page buy box, and the shop page ($banner = true). --}}
@if($seller && $seller->isOnHoliday())
    @php $holidayBanner = $banner ?? false; @endphp
    <div class="{{ $holidayBanner ? 'mb-5 p-4 lg:px-6' : 'mt-4 p-3' }} rounded-xl border border-amber-200 bg-amber-50 text-sm text-amber-900" role="status" data-shop-holiday>
        <p class="font-semibold flex items-center gap-2"><x-icon name="clock" class="w-5 h-5 shrink-0" />{{ \App\Support\ShopHoliday::headline($seller) }}</p>
        @if($seller->holiday_message)
            <p class="mt-1 ps-7 whitespace-pre-line" dir="auto">{{ $seller->holiday_message }}</p>
        @endif
        <p class="mt-1 ps-7 text-xs text-amber-800">
            @if($holidayBanner)
                {{ $seller->holiday_until ? __('You can browse as usual; ordering opens again on :date.', ['date' => \App\Support\ShopHoliday::date($seller)]) : __('You can browse as usual; ordering opens again when the shop is back.') }}
            @else
                {{ $seller->holiday_until ? __('Ordering opens again on :date. Add it to your wishlist so it is easy to find.', ['date' => \App\Support\ShopHoliday::date($seller)]) : __('Ordering opens again when the shop is back. Add it to your wishlist so it is easy to find.') }}
            @endif
        </p>
    </div>
@endif
