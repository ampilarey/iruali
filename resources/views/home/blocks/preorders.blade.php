@if($preorders->isNotEmpty())
    <x-product-row :title="__('Pre-order now')" :products="$preorders" :link="route('shop', ['preorder' => 1])" />
@endif
