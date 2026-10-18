{{-- A text block with an optional button, added under Admin → Home page --}}
<section class="rounded-2xl bg-white border border-gray-200 p-6 lg:p-8" data-home-text>
    @if(\App\Support\HomeLayout::title($block) !== '')<h2 class="font-display text-xl lg:text-2xl font-bold text-dark">{{ \App\Support\HomeLayout::title($block) }}</h2>@endif
    @if(\App\Support\HomeLayout::text($block) !== '')<p class="mt-2 text-gray-600 whitespace-pre-line">{{ \App\Support\HomeLayout::text($block) }}</p>@endif
    @if(! empty($block['link']) && ! empty($block['button_en']))
        <a href="{{ $block['link'] }}" class="mt-4 inline-flex items-center px-5 py-2.5 rounded-lg bg-primary text-white font-semibold hover:bg-primary-hover">{{ app()->getLocale() === 'dv' && ! empty($block['button_dv']) ? $block['button_dv'] : $block['button_en'] }}</a>
    @endif
</section>
