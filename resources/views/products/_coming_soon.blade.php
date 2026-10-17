{{-- Product page buy box: the product is listed but not on sale yet (ComingSoonService::offer()). Nothing can be
     ordered; the "Email me when it goes on sale" form follows. Needs $comingSoon (null when it is on sale). --}}
@if($comingSoon)
    <div class="mt-3 rounded-lg border border-sun-200 bg-sun-soft p-3 text-sm" data-coming-soon role="status">
        <p class="font-semibold text-dark flex items-center gap-2"><x-icon name="clock" class="w-4 h-4 shrink-0 text-sun-ink" />{{ $comingSoon['date'] ? __('Coming soon: on sale from :date', ['date' => $comingSoon['date']->translatedFormat('j M Y')]) : __('Coming soon') }}</p>
        <p class="mt-1 ps-6 text-gray-700">{{ __('This product is not on sale yet. Leave your email below and we will tell you when you can order it.') }}</p>
    </div>
@endif
