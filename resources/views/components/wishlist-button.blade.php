@props(['product', 'floating' => false])
<form action="{{ route('wishlist.add', $product) }}" method="POST" class="{{ $floating ? 'absolute top-2 end-2 z-10' : '' }}">
    @csrf
    <input type="hidden" name="product_id" value="{{ $product->id }}">
    <button type="submit" title="{{ __('Add to Wishlist') }}" aria-label="{{ __('Add to Wishlist') }}"
            class="flex items-center justify-center rounded-full text-gray-600 hover:text-coral {{ $floating ? 'w-9 h-9 bg-white/95 shadow-sm border border-gray-200' : 'w-11 h-11 border border-gray-300 rounded-lg' }}">
        <x-icon name="heart" class="w-5 h-5" />
    </button>
</form>
