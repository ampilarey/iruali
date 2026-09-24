@props(['product', 'small' => false])
@if($product->stock_quantity > 0)
    <form action="{{ route('cart.add') }}" method="POST" {{ $attributes }}>
        @csrf
        <input type="hidden" name="product_id" value="{{ $product->id }}">
        <input type="hidden" name="quantity" value="1">
        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-primary hover:bg-primary-hover text-white font-semibold whitespace-nowrap {{ $small ? 'text-[13px] sm:text-sm py-2 px-2 sm:px-3' : 'py-2.5 px-4' }}">
            <x-icon name="cart" class="w-4 h-4 shrink-0 {{ $small ? 'hidden min-[380px]:block' : '' }}" />{{ __('Add to cart') }}
        </button>
    </form>
@else
    <a href="{{ route('products.show', $product) }}" {{ $attributes->merge(['class' => 'inline-flex items-center justify-center rounded-lg border border-gray-300 text-gray-600 font-semibold hover:bg-gray-50 '.($small ? 'text-sm py-2 px-3' : 'py-2.5 px-4')]) }}>{{ __('View details') }}</a>
@endif
