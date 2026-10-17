{{-- Listings: the chosen island has no shops of its own. In "Only shops on <island>" this is the
     empty state, with one click back to everything that delivers there; never a blank page. --}}
@php
    $shopping = app(\App\Services\ShoppingIsland::class);
    $noticeIsland = $shopping->island();
    $noticeView = $shopping->view();
    $noticeNoLocal = $noticeIsland && ! app(\App\Services\ShopCoverage::class)->hasLocalShops($noticeIsland);
@endphp
@if($noticeNoLocal && $noticeView !== \App\Services\ShoppingIsland::VIEW_ALL)
    <div class="rounded-xl border border-primary-100 bg-primary-50 px-4 py-3 text-sm text-dark flex flex-wrap items-center justify-between gap-3" data-island-notice="{{ $noticeView === \App\Services\ShoppingIsland::VIEW_LOCAL ? 'local-empty' : 'no-local' }}">
        <p class="flex items-start gap-2">
            <x-icon name="store" class="w-5 h-5 shrink-0 text-primary" />
            @if($noticeView === \App\Services\ShoppingIsland::VIEW_LOCAL)
                <span><strong>{{ __('No shops on :island yet.', ['island' => $noticeIsland->localized_name]) }}</strong> {{ __('Shops on other islands deliver to you.') }}</span>
            @else
                <span>{{ __('No shops on :island yet. These shops deliver to you.', ['island' => $noticeIsland->localized_name]) }}</span>
            @endif
        </p>
        @if($noticeView === \App\Services\ShoppingIsland::VIEW_LOCAL)
            <form method="POST" action="{{ route('delivery.island.view') }}">
                @csrf
                <input type="hidden" name="view" value="{{ \App\Services\ShoppingIsland::VIEW_DELIVERS }}">
                <button class="rounded-lg bg-primary px-4 py-2 font-semibold text-white hover:bg-primary-hover">{{ __('Show what delivers to :island', ['island' => $noticeIsland->localized_name]) }}</button>
            </form>
        @endif
    </div>
@endif
