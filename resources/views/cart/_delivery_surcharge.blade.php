{{-- Cart line: a bulky item's extra delivery charge (per unit; added when delivered, not waived by free delivery). Needs $product. --}}
@if((float) $product->delivery_surcharge > 0)
    <p class="text-xs text-gray-600 mt-0.5 flex items-center gap-1"><x-icon name="truck" class="w-3.5 h-3.5 text-primary" />{{ __('Bulky item: :amount extra delivery per item, not covered by free delivery.', ['amount' => \App\Support\Money::format($product->delivery_surcharge)]) }}</p>
@endif
