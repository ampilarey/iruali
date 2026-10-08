@extends('layouts.app')

@section('title', $campaign->headline)

@section('content')
<div class="bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 lg:px-6 py-4 lg:py-6 space-y-6">
        <nav class="text-xs sm:text-sm text-gray-500" aria-label="{{ __('Breadcrumb') }}">
            <ol class="flex items-center gap-1.5">
                <li><a href="{{ route('home') }}" class="hover:text-primary hover:underline">{{ __('Home') }}</a></li>
                <li class="flex items-center gap-1.5"><x-icon name="chevron-right" class="w-3 h-3 rtl:rotate-180" /><a href="{{ route('campaigns.index') }}" class="hover:text-primary hover:underline">{{ __('Campaigns') }}</a></li>
                <li class="flex items-center gap-1.5"><x-icon name="chevron-right" class="w-3 h-3 rtl:rotate-180" /><span class="text-dark font-medium">{{ $campaign->name }}</span></li>
            </ol>
        </nav>

        <section class="relative overflow-hidden rounded-2xl text-white p-6 sm:p-10 min-h-[14rem] flex flex-col justify-end" style="background-color: {{ $campaign->theme_colour }}">
            @if($campaign->banner_image)
                <img src="{{ $campaign->banner_image }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-60">
            @endif
            <div class="relative max-w-2xl">
                @if($campaign->brand)
                    {{-- A brand campaign: the brand's logo and name, leading to its page --}}
                    @php $brandPage = $campaign->brand->activeProductCount() > 0 ? route('brands.show', $campaign->brand) : null; @endphp
                    <div class="mb-3" data-campaign-brand>
                        @if($brandPage)
                            <a href="{{ $brandPage }}" class="inline-flex items-center gap-2 rounded-full bg-white ps-1 pe-3 py-1 text-sm font-semibold text-dark hover:bg-gray-50">
                                @include('brands._logo', ['brand' => $campaign->brand, 'class' => 'w-8 h-8 text-sm'])
                                {{ $campaign->brand->localizedName() }}<x-icon name="chevron-right" class="w-4 h-4 rtl:rotate-180" />
                            </a>
                        @else
                            <span class="inline-flex items-center gap-2 rounded-full bg-white ps-1 pe-3 py-1 text-sm font-semibold text-dark">
                                @include('brands._logo', ['brand' => $campaign->brand, 'class' => 'w-8 h-8 text-sm'])
                                {{ $campaign->brand->localizedName() }}
                            </span>
                        @endif
                    </div>
                @endif
                <p class="inline-block px-3 py-1 rounded-full bg-white/20 text-xs font-semibold mb-3">{{ $campaign->type === 'event' ? __('Event') : __('Sale') }}</p>
                <h1 class="font-display text-3xl sm:text-4xl font-bold leading-tight">{{ $campaign->headline }}</h1>
                @if($campaign->subheadline)<p class="mt-2 text-white/90 sm:text-lg">{{ $campaign->subheadline }}</p>@endif
                <div class="mt-4 flex flex-wrap items-center gap-3 text-sm">
                    @if($campaign->isLive())
                        <x-deal-countdown :ends="$campaign->ends_at" class="bg-white/90" />
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-white/90 text-dark px-2.5 py-1 font-semibold"><x-icon name="clock" class="w-4 h-4" />{{ __('Starts :when', ['when' => $campaign->starts_at->translatedFormat('j F, H:i')]) }}</span>
                    @endif
                    <span class="text-white/85">{{ __(':from to :to', ['from' => $campaign->starts_at->translatedFormat('j M'), 'to' => $campaign->ends_at->translatedFormat('j M Y')]) }}</span>
                </div>
            </div>
        </section>

        <section>
            <div class="flex items-end justify-between gap-4 mb-3">
                <h2 class="font-display text-xl lg:text-2xl font-bold text-dark">{{ __('Campaign products') }}</h2>
                <span class="text-sm text-gray-500">{{ trans_choice(':count product|:count products', $products->total(), ['count' => $products->total()]) }}</span>
            </div>
            @if($campaign->brand)
                <p class="-mt-1 mb-3 text-sm text-gray-600">
                    {{ __('Only :brand products are in this campaign.', ['brand' => $campaign->brand->localizedName()]) }}
                    @if($brandPage)<a href="{{ $brandPage }}" class="font-semibold text-primary hover:underline">{{ __('See everything from :brand', ['brand' => $campaign->brand->localizedName()]) }}</a>@endif
                </p>
            @endif
            @if($products->isEmpty())
                <p class="rounded-xl bg-white border border-gray-200 p-8 text-center text-gray-600">{{ $campaign->isLive() ? __('Shops are still adding products. Check back soon.') : __('Products will appear here when the campaign starts.') }}</p>
            @else
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-2.5 lg:gap-4">
                    @foreach($products as $product)
                        <x-product-card :product="$product" />
                    @endforeach
                </div>
                <div class="mt-6">{{ $products->links() }}</div>
            @endif
        </section>
    </div>
</div>
@endsection
