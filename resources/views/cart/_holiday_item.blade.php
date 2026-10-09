{{-- A cart line whose shop is on holiday: it stays in the cart but can't be ordered until the back-on date. --}}
@if($seller?->isOnHoliday())
    <p class="mt-1 inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800" data-holiday-item><x-icon name="clock" class="w-3.5 h-3.5" />{{ $seller->holiday_until ? __('Shop on holiday until :date', ['date' => \App\Support\ShopHoliday::date($seller)]) : __('Shop on holiday') }}</p>
@endif
