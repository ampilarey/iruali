@if($newArrivals->isNotEmpty())
    <x-product-row :title="__('New arrivals')" :products="$newArrivals" :link="route('shop', ['sort' => 'newest'])" />
@endif
