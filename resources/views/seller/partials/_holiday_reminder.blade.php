{{-- Every Seller Centre page while the shop is on holiday (Settings → Holiday mode): customers can't order, but orders already placed still need sending. --}}
@php $holidayShop = auth()->user(); @endphp
@if($holidayShop?->isOnHoliday() && ! request()->routeIs('seller.settings.holiday'))
    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900" data-holiday-reminder>
        <span class="flex items-center gap-2"><x-icon name="clock" class="w-5 h-5 shrink-0" />{{ $holidayShop->holiday_until ? __('Holiday mode is on until :date: customers can see your products but cannot order them. Keep sending the orders you already have.', ['date' => \App\Support\ShopHoliday::date($holidayShop)]) : __('Holiday mode is on: customers can see your products but cannot order them. Keep sending the orders you already have.') }}</span>
        <a href="{{ route('seller.settings.holiday') }}" class="font-semibold underline hover:no-underline">{{ __('Change') }}</a>
    </div>
@endif
