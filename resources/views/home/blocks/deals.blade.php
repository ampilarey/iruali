@if($deals->isNotEmpty())
    <x-product-row :title="__('Today\'s deals')" :products="$deals" :link="route('deals')" />
@endif
