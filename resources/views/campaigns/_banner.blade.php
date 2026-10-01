{{-- A campaign banner: hero (big) or strip (one line). --}}
@props(['campaign', 'size' => 'hero'])
@php
    $url = $campaign->cta_url ?: route('campaigns.show', $campaign);
    $cta = $campaign->cta_text ?: __('Shop the campaign');
@endphp
@if($size === 'hero')
    <a href="{{ $url }}" class="relative overflow-hidden rounded-2xl text-white p-6 sm:p-10 min-h-[15rem] lg:min-h-[20rem] flex flex-col justify-end block" style="background-color: {{ $campaign->theme_colour }}">
        @if($campaign->banner_image)
            <img src="{{ $campaign->banner_image }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-60">
        @endif
        <div class="relative max-w-md">
            <p class="inline-block px-3 py-1 rounded-full bg-white/20 text-xs font-semibold mb-3">{{ $campaign->type === 'event' ? __('Event') : __('Sale') }}@if($campaign->discount_percent) · {{ __('Up to :percent% off', ['percent' => rtrim(rtrim(number_format((float) $campaign->discount_percent, 2, '.', ''), '0'), '.')]) }}@endif</p>
            <h2 class="font-display text-3xl sm:text-4xl lg:text-5xl font-bold leading-tight">{{ $campaign->headline }}</h2>
            @if($campaign->subheadline)<p class="mt-3 text-white/90 sm:text-lg">{{ $campaign->subheadline }}</p>@endif
            <span class="mt-6 inline-flex items-center gap-2 px-6 py-3 rounded-lg bg-white text-dark font-semibold">{{ $cta }}<x-icon name="arrow-right" class="w-4 h-4 rtl:rotate-180" /></span>
            <x-deal-countdown :ends="$campaign->ends_at" class="mt-4 bg-white/90" />
        </div>
    </a>
@else
    <a href="{{ $url }}" class="flex items-center justify-between gap-4 rounded-xl text-white px-4 py-3 hover:opacity-95" style="background-color: {{ $campaign->theme_colour }}">
        <span class="flex items-center gap-3 min-w-0">
            <x-icon name="tag" class="w-5 h-5 shrink-0" />
            <span class="min-w-0">
                <span class="block font-semibold truncate">{{ $campaign->headline }}</span>
                @if($campaign->subheadline)<span class="block text-sm text-white/85 truncate">{{ $campaign->subheadline }}</span>@endif
            </span>
        </span>
        <span class="shrink-0 inline-flex items-center gap-1 text-sm font-semibold">{{ $cta }}<x-icon name="chevron-right" class="w-4 h-4 rtl:rotate-180" /></span>
    </a>
@endif
