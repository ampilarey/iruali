{{-- Admin → Sellers, under a shop's status: holiday mode (customers can't order). --}}
@if($seller->isOnHoliday())
    <span class="mt-1 flex w-fit items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-medium text-amber-800" @if($seller->holiday_message) title="{{ $seller->holiday_message }}" @endif data-seller-holiday>
        <x-icon name="clock" class="w-3.5 h-3.5" />{{ $seller->holiday_until ? __('On holiday until :date', ['date' => \App\Support\ShopHoliday::date($seller)]) : __('On holiday') }}
    </span>
@endif
