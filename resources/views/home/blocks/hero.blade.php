<!-- Hero + side promos -->
<section class="grid grid-cols-1 lg:grid-cols-3 gap-3 lg:gap-4">
    @if(($heroCampaign ?? null))
    <div class="lg:col-span-2">@include('campaigns._banner', ['campaign' => $heroCampaign, 'size' => 'hero'])</div>
    @else
    <div class="lg:col-span-2 relative overflow-hidden rounded-2xl bg-primary text-white p-6 sm:p-10 min-h-[15rem] lg:min-h-[20rem] flex flex-col justify-end">
        <svg class="absolute -top-6 -end-10 w-44 h-44 opacity-30 sm:opacity-90 sm:top-auto sm:-bottom-6 sm:end-0 sm:w-64 sm:h-64 lg:w-80 lg:h-80 text-sun" viewBox="0 0 200 200" aria-hidden="true"><path fill="currentColor" d="M20 110a80 80 0 01160 0z"/><rect x="10" y="122" width="180" height="12" rx="6" fill="#63C0B2"/><rect x="45" y="146" width="110" height="10" rx="5" fill="#9DD8CE"/></svg>
        <div class="relative max-w-md">
            <p class="inline-block px-3 py-1 rounded-full bg-white/15 text-xs font-semibold mb-3">{{ __('Shops from every island, in one place.') }}</p>
            <h1 class="font-display text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight">{{ __('Local shops. Island delivery.') }}</h1>
            <p class="mt-3 text-white/85 sm:text-lg">{{ __('Buy from local sellers across the Maldives and get it delivered to your island.') }}</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('shop') }}" class="inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-sun text-sun-on font-semibold hover:bg-accent-400">{{ __('Shop Now') }}<x-icon name="arrow-right" class="w-4 h-4 rtl:rotate-180" /></a>
                <a href="{{ route('categories.index') }}" class="inline-flex items-center px-6 py-3 rounded-lg bg-white/10 border border-white/30 font-semibold hover:bg-white/20">{{ __('Browse Categories') }}</a>
            </div>
        </div>
    </div>
    @endif
    @php $sellLinks = \App\Support\SellerApplications::linksShown(); @endphp
    <div class="grid {{ $sellLinks ? 'grid-cols-2' : 'grid-cols-1' }} lg:grid-cols-1 gap-3 lg:gap-4">
        <a href="{{ route('deals') }}" class="group rounded-2xl bg-coral text-white p-5 flex flex-col justify-between min-h-[8rem] hover:bg-coral-hover">
            <x-icon name="tag" class="w-7 h-7" />
            <span>
                <span class="block font-display text-xl lg:text-2xl font-bold">{{ __('Today\'s deals') }}</span>
                <span class="text-sm text-white/85 inline-flex items-center gap-1">{{ __('Marked-down prices') }}<x-icon name="chevron-right" class="w-4 h-4 rtl:rotate-180 group-hover:translate-x-0.5 transition" /></span>
            </span>
        </a>
        @if($sellLinks)
        <a href="{{ route('seller.apply') }}" class="group rounded-2xl bg-reef text-white p-5 flex flex-col justify-between min-h-[8rem] hover:bg-reef-night">
            <x-icon name="store" class="w-7 h-7 text-sun" />
            <span>
                <span class="block font-display text-xl lg:text-2xl font-bold">{{ __('Sell on iruali') }}</span>
                <span class="text-sm text-white/85 inline-flex items-center gap-1">{{ __('Open your shop') }}<x-icon name="chevron-right" class="w-4 h-4 rtl:rotate-180" /></span>
            </span>
        </a>
        @endif
    </div>
</section>

@include('partials.island-notice')
