@if($comingSoon->isNotEmpty())
    <x-product-row :title="__('Coming soon')" :products="$comingSoon" :link="route('shop', ['coming_soon' => 1])" />
@endif
