{{-- "Delivering to: <island> (change)" with the view switch, or, before an island is chosen, a
     small invitation to choose one. Never a gate: with no island everything is shown. The dialog
     loads the island list as JSON when opened (resources/js/shopping-island.js); it opens by
     itself on a first visit, never for search engines. Storefront pages only. --}}
@php
    $shopping = app(\App\Services\ShoppingIsland::class);
    $shopIsland = $shopping->island();
    $shopView = $shopping->view();
    $isBot = (bool) preg_match('/bot|crawl|spider|slurp|facebookexternalhit|embedly|preview|lighthouse/i', (string) request()->userAgent());
    $autoPrompt = ! $isBot && $shopping->shouldPrompt();
@endphp
<div class="border-t border-gray-100 bg-primary-50/60 text-sm" data-shopping-island>
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-1.5 flex flex-wrap items-center gap-x-3 gap-y-1">
        <x-icon name="map-pin" class="w-4 h-4 shrink-0 text-primary" />
        @if($shopIsland)
            <span class="text-gray-700">{{ __('Delivering to') }} <strong class="text-dark" data-current-island="{{ $shopIsland->id }}">{{ $shopIsland->localized_name }}</strong></span>
            <button type="button" data-island-dialog-open class="font-semibold text-primary hover:underline">({{ __('change') }})</button>
            <form method="POST" action="{{ route('delivery.island.view') }}" class="ms-auto flex items-center gap-1.5">
                @csrf
                <label for="shopping-view" class="sr-only">{{ __('Show') }}</label>
                <select id="shopping-view" name="view" onchange="this.form.submit()" class="rounded-lg border border-gray-300 bg-white ps-2 pe-8 py-0.5 text-xs focus:border-primary focus:ring-primary" data-shopping-view>
                    @foreach(\App\Services\ShoppingIsland::viewLabels($shopIsland) as $value => $label)
                        <option value="{{ $value }}" @selected($shopView === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="text-xs font-semibold text-primary">{{ __('Change') }}</button></noscript>
            </form>
        @else
            <span class="text-gray-700" data-island-banner>{{ __('Choose your island to see what delivers to you.') }}</span>
            <button type="button" data-island-dialog-open class="font-semibold text-primary hover:underline">{{ __('Choose island') }}</button>
        @endif
    </div>
</div>

<dialog data-island-dialog @if($autoPrompt) data-auto-open @endif
        data-list-url="{{ route('delivery.island.show') }}" data-store-url="{{ route('delivery.island') }}"
        data-locate-url="{{ route('delivery.island.locate') }}" data-dismiss-url="{{ route('delivery.island.dismiss') }}"
        data-text-locating="{{ __('Finding your island…') }}" data-text-located-failed="{{ __('We could not get your location. Please choose your island from the list.') }}"
        data-text-greater-male="{{ __('Greater Malé') }}" data-text-no-match="{{ __('No island matches.') }}"
        aria-labelledby="island-dialog-title"
        class="w-[min(32rem,calc(100%-2rem))] max-h-[85vh] rounded-2xl p-0 shadow-2xl backdrop:bg-reef/60">
    <div class="flex max-h-[85vh] flex-col">
        <div class="flex items-start justify-between gap-3 border-b border-gray-100 p-4">
            <div>
                <h2 id="island-dialog-title" class="font-display text-lg sm:text-xl font-bold text-dark">{{ __('Where should we deliver?') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ __('Choose your island to see the shops that deliver there. You can change it any time, for example to send a gift to family.') }}</p>
            </div>
            <button type="button" data-island-dialog-close class="p-1 text-gray-500 hover:text-dark" aria-label="{{ __('Close') }}"><x-icon name="x" class="w-5 h-5" /></button>
        </div>
        <div class="space-y-3 p-4">
            <button type="button" data-island-locate class="inline-flex w-full items-center justify-center gap-2 rounded-lg border-2 border-primary px-4 py-2 font-semibold text-primary hover:bg-primary-50">
                <x-icon name="map-pin" class="w-4 h-4" />{{ __('Use my location') }}
            </button>
            <p data-island-status class="hidden text-sm text-gray-600" role="status"></p>
            <label for="island-dialog-search" class="sr-only">{{ __('Search islands or atolls…') }}</label>
            <input id="island-dialog-search" type="search" data-island-dialog-search autocomplete="off" placeholder="{{ __('Search islands or atolls…') }}" class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary focus:ring-primary">
        </div>
        <div data-island-dialog-list class="min-h-[8rem] flex-1 overflow-y-auto px-4 pb-4 text-sm" aria-live="polite">
            <p class="text-gray-500">{{ __('Loading islands…') }}</p>
        </div>
        <div class="border-t border-gray-100 p-3 text-center">
            <button type="button" data-island-dialog-skip class="text-sm font-medium text-gray-600 hover:underline">{{ __('Not now, show me everything') }}</button>
        </div>
    </div>
</dialog>
