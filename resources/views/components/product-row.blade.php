@props(['title', 'products', 'link' => null, 'compact' => false, 'linkLabel' => null])
<section {{ $attributes->merge(['class' => '']) }}>
    <div class="flex items-end justify-between gap-4 mb-3">
        <h2 class="font-display text-xl lg:text-2xl font-bold text-dark">{{ $title }}</h2>
        @if($link)
            <a href="{{ $link }}" class="shrink-0 inline-flex items-center gap-1 text-sm font-semibold text-primary hover:underline">{{ $linkLabel ?? __('See all') }}<x-icon name="chevron-right" class="w-4 h-4 rtl:rotate-180" /></a>
        @endif
    </div>
    <div class="-mx-4 px-4 lg:mx-0 lg:px-0 flex gap-3 lg:gap-4 overflow-x-auto snap-x snap-mandatory scrollbar-hide pb-2">
        @foreach($products as $product)
            <div class="snap-start shrink-0 w-[46%] sm:w-[30%] md:w-[23%] lg:w-[calc((100%-4rem)/5)]">
                <x-product-card :product="$product" :compact="$compact" />
            </div>
        @endforeach
    </div>
</section>
