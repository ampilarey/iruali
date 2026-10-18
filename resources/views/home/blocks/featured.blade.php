@if($featured->isNotEmpty())
    <x-product-row :title="__('Featured Products')" :products="$featured" :link="route('products.index')" />
@endif
