{{-- Seller product form: bulky items. The extra charge is added to the delivery fee for each unit
     delivered (not for pickups) and free delivery does not waive it. Needs $product and $field. --}}
<fieldset class="rounded-lg border border-gray-200 p-4">
    <legend class="px-1 text-sm font-medium text-gray-700">{{ __('Delivery') }}</legend>
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <label for="delivery_surcharge" class="block text-sm font-medium text-gray-700">{{ __('Extra delivery charge per item (MVR)') }}</label>
            <input id="delivery_surcharge" name="delivery_surcharge" type="number" step="0.01" min="0" max="99999.99" class="{{ $field }}" value="{{ old('delivery_surcharge', $product->delivery_surcharge ?? 0) }}" aria-describedby="delivery_surcharge-hint">
            @error('delivery_surcharge')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
        </div>
        <p id="delivery_surcharge-hint" class="sm:col-span-2 self-end text-xs text-gray-500">{{ __('For bulky or heavy items that cost more to send, such as furniture or a fridge. Customers pay it on top of the delivery fee for each one delivered, even with free delivery. Leave 0 for normal items.') }}</p>
    </div>
</fieldset>
