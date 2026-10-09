{{-- Catalog cards: a small "On holiday" label on products of a shop on holiday (they stay listed). $class places it. --}}
@if($seller?->isOnHoliday())
    <span class="{{ $class ?? '' }} inline-flex items-center gap-1 bg-reef text-white text-[11px] font-bold px-1.5 py-0.5 rounded" data-holiday-label><x-icon name="clock" class="w-3 h-3" />{{ __('On holiday') }}</span>
@endif
