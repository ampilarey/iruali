{{-- A product row added under Admin → Home page --}}
@if(($block['items'] ?? collect())->isNotEmpty())
    <x-product-row :title="\App\Support\HomeLayout::title($block)" :products="$block['items']" :link="($block['link'] ?? null) ?: null" />
@endif
