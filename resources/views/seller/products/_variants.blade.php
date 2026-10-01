@php
    $hasVariants = (bool) old('has_variants', $product->has_variants);
    $options = app(\App\Services\VariantService::class)->optionsOf($variants);
    // Rows come back from a failed submit as typed, else from the saved variants
    $rows = old('variants');
    if (! is_array($rows)) {
        $rows = $variants->map(fn ($v) => [
            'id' => $v->id,
            'attributes' => json_encode($v->attributes_list),
            'sku' => $v->sku,
            'price' => $v->price,
            'stock_quantity' => $v->stock_quantity,
            'low_stock_threshold' => $v->low_stock_threshold,
            'is_active' => $v->is_active,
            'image_url' => $v->image ? \Illuminate\Support\Facades\Storage::url($v->image) : null,
            'has_orders' => $v->hasOrderHistory(),
        ])->values()->all();
    }
    $small = 'block w-full rounded-md border border-gray-300 px-2 py-1.5 text-sm focus:border-primary-500 focus:ring-primary-500';
@endphp
<section data-variant-editor class="rounded-lg border border-gray-200 p-4 space-y-4">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-900">{{ __('Variants') }}</h2>
            <p class="text-xs text-gray-500">{{ __('Sizes, colours or other options, each with its own SKU, price and stock.') }}</p>
        </div>
        <label class="inline-flex items-center gap-2 text-sm font-medium text-gray-700">
            <input type="hidden" name="has_variants" value="0">
            <input type="checkbox" name="has_variants" value="1" data-has-variants class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" @checked($hasVariants)>
            {{ __('This product comes in variants') }}
        </label>
    </div>

    <div data-variants-section class="space-y-4 {{ $hasVariants ? '' : 'hidden' }}">
        <div class="rounded-md bg-gray-50 p-3">
            <p class="text-sm font-medium text-gray-700">{{ __('Option types (up to 3)') }}</p>
            <p class="text-xs text-gray-500 mb-2">{{ __('For example "Size" with values "S, M, L". Then generate the combinations and fill in each row.') }}</p>
            <div class="grid gap-2 sm:grid-cols-3" data-option-types>
                @for($i = 0; $i < \App\Http\Requests\Seller\ProductRequest::MAX_OPTION_TYPES; $i++)
                    @php $name = array_keys($options)[$i] ?? ''; $values = $name !== '' ? implode(', ', $options[$name]) : ''; @endphp
                    <div class="space-y-1">
                        <input type="text" name="options[{{ $i }}][name]" value="{{ old("options.$i.name", $name) }}" placeholder="{{ __('Option, e.g. Size') }}" class="{{ $small }}" data-option-name>
                        <input type="text" name="options[{{ $i }}][values]" value="{{ old("options.$i.values", $values) }}" placeholder="{{ __('Values, comma-separated') }}" class="{{ $small }}" data-option-values>
                    </div>
                @endfor
            </div>
            <button type="button" data-generate-variants class="mt-3 rounded-lg bg-gray-800 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-900">{{ __('Generate combinations') }}</button>
            <p class="mt-1 text-xs text-gray-500">{{ __('Existing rows are kept; new combinations are added below.') }}</p>
        </div>

        @error('variants')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50 text-xs font-medium uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-2 py-2 text-start">{{ __('Variant') }}</th>
                        <th class="px-2 py-2 text-start">{{ __('SKU') }}</th>
                        <th class="px-2 py-2 text-start">{{ __('Price') }}</th>
                        <th class="px-2 py-2 text-start">{{ __('Stock') }}</th>
                        <th class="px-2 py-2 text-start">{{ __('Low-stock alert at') }}</th>
                        <th class="px-2 py-2 text-start">{{ __('Image') }}</th>
                        <th class="px-2 py-2 text-start">{{ __('Active') }}</th>
                        <th class="px-2 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200" data-variant-rows data-product-sku-input="sku">
                    @foreach($rows as $i => $row)
                        @php $attrs = json_decode((string) ($row['attributes'] ?? '{}'), true) ?: []; @endphp
                        <tr data-variant-row data-attributes="{{ json_encode($attrs) }}" class="{{ ! empty($row['remove']) ? 'opacity-50' : '' }}">
                            <td class="px-2 py-2 align-top">
                                @if(! empty($row['id']))<input type="hidden" name="variants[{{ $i }}][id]" value="{{ $row['id'] }}">@endif
                                <input type="hidden" name="variants[{{ $i }}][attributes]" value="{{ json_encode($attrs) }}" data-variant-attributes>
                                <span class="font-medium text-gray-900" data-variant-label>{{ implode(' / ', array_map(fn ($k, $v) => __($k).': '.$v, array_keys($attrs), $attrs)) }}</span>
                                @if(! empty($row['has_orders']))<span class="block text-xs text-gray-500">{{ __('Has orders: removing deactivates it.') }}</span>@endif
                                @error("variants.$i.attributes")<span class="block text-xs text-red-600">{{ $message }}</span>@enderror
                                @error("variants.$i.id")<span class="block text-xs text-red-600">{{ $message }}</span>@enderror
                            </td>
                            <td class="px-2 py-2 align-top">
                                <input type="text" name="variants[{{ $i }}][sku]" value="{{ $row['sku'] ?? '' }}" required class="{{ $small }} min-w-[8rem]" dir="ltr">
                                @error("variants.$i.sku")<span class="block text-xs text-red-600">{{ $message }}</span>@enderror
                            </td>
                            <td class="px-2 py-2 align-top">
                                <input type="number" name="variants[{{ $i }}][price]" value="{{ $row['price'] ?? '' }}" step="0.01" min="0.01" placeholder="{{ __('Product price') }}" class="{{ $small }} min-w-[7rem]" dir="ltr">
                                @error("variants.$i.price")<span class="block text-xs text-red-600">{{ $message }}</span>@enderror
                            </td>
                            <td class="px-2 py-2 align-top">
                                <input type="number" name="variants[{{ $i }}][stock_quantity]" value="{{ $row['stock_quantity'] ?? 0 }}" min="0" required class="{{ $small }} w-20" dir="ltr">
                                @error("variants.$i.stock_quantity")<span class="block text-xs text-red-600">{{ $message }}</span>@enderror
                            </td>
                            <td class="px-2 py-2 align-top">
                                <input type="number" name="variants[{{ $i }}][low_stock_threshold]" value="{{ $row['low_stock_threshold'] ?? '' }}" min="0" placeholder="{{ old('reorder_point', $product->reorder_point ?? 5) }}" class="{{ $small }} w-20" dir="ltr">
                            </td>
                            <td class="px-2 py-2 align-top">
                                @if(! empty($row['image_url']))<img src="{{ $row['image_url'] }}" alt="" class="mb-1 h-10 w-10 rounded object-cover">@endif
                                <input type="file" name="variants[{{ $i }}][image]" accept="image/jpeg,image/png,image/gif" class="block w-36 text-xs text-gray-700">
                                @error("variants.$i.image")<span class="block text-xs text-red-600">{{ $message }}</span>@enderror
                            </td>
                            <td class="px-2 py-2 align-top">
                                <input type="hidden" name="variants[{{ $i }}][is_active]" value="0">
                                <input type="checkbox" name="variants[{{ $i }}][is_active]" value="1" class="rounded border-gray-300 text-primary-600 focus:ring-primary-500" @checked(! array_key_exists('is_active', $row) || $row['is_active'])>
                            </td>
                            <td class="px-2 py-2 align-top text-end whitespace-nowrap">
                                <label class="inline-flex items-center gap-1 text-xs text-red-600">
                                    <input type="checkbox" name="variants[{{ $i }}][remove]" value="1" data-variant-remove class="rounded border-gray-300 text-red-600 focus:ring-red-500" @checked(! empty($row['remove']))>
                                    {{ __('Remove') }}
                                </label>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p data-no-variants class="px-2 py-4 text-sm text-gray-500 {{ count($rows) ? 'hidden' : '' }}">{{ __('No variants yet. Add option types above and generate the combinations.') }}</p>
        </div>
    </div>

    <template data-variant-template>
        <tr data-variant-row data-attributes="">
            <td class="px-2 py-2 align-top">
                <input type="hidden" name="variants[__I__][attributes]" value="" data-variant-attributes>
                <span class="font-medium text-gray-900" data-variant-label></span>
            </td>
            <td class="px-2 py-2 align-top"><input type="text" name="variants[__I__][sku]" value="" required class="{{ $small }} min-w-[8rem]" dir="ltr"></td>
            <td class="px-2 py-2 align-top"><input type="number" name="variants[__I__][price]" value="" step="0.01" min="0.01" placeholder="{{ __('Product price') }}" class="{{ $small }} min-w-[7rem]" dir="ltr"></td>
            <td class="px-2 py-2 align-top"><input type="number" name="variants[__I__][stock_quantity]" value="0" min="0" required class="{{ $small }} w-20" dir="ltr"></td>
            <td class="px-2 py-2 align-top"><input type="number" name="variants[__I__][low_stock_threshold]" value="" min="0" class="{{ $small }} w-20" dir="ltr"></td>
            <td class="px-2 py-2 align-top"><input type="file" name="variants[__I__][image]" accept="image/jpeg,image/png,image/gif" class="block w-36 text-xs text-gray-700"></td>
            <td class="px-2 py-2 align-top">
                <input type="hidden" name="variants[__I__][is_active]" value="0">
                <input type="checkbox" name="variants[__I__][is_active]" value="1" checked class="rounded border-gray-300 text-primary-600 focus:ring-primary-500">
            </td>
            <td class="px-2 py-2 align-top text-end whitespace-nowrap">
                <button type="button" data-variant-delete class="text-xs font-medium text-red-600 hover:text-red-700">{{ __('Remove') }}</button>
            </td>
        </tr>
    </template>
</section>
