@extends('layouts.app')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">{{ __('products.edit_product') }}</h1>

            <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                
                <!-- Language Tabs -->
                <div class="mb-6">
                    <div class="border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8">
                            <button type="button" class="language-tab active" data-language="en">
                                <span class="text-sm font-medium text-primary-600 border-b-2 border-primary-600 py-2 px-1">
                                    English
                                </span>
                            </button>
                            <button type="button" class="language-tab" data-language="dv">
                                <span class="text-sm font-medium text-gray-500 border-b-2 border-transparent py-2 px-1">
                                    ދިވެހިންނަށް
                                </span>
                            </button>
                        </nav>
                    </div>
                </div>

                <!-- Basic Information -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="sku" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.sku') }} *
                        </label>
                        <input type="text" name="sku" id="sku" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('sku', $product->sku) }}">
                        @error('sku')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="category_id" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.category') }} *
                        </label>
                        <select name="category_id" id="category_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                            <option value="">{{ __('products.select_category') }}</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ old('category_id', $product->category_id) == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('category_id')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Multilingual Name Fields -->
                <div class="mb-6">
                    <div class="language-content" id="content-en">
                        <label for="name_en" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.name') }} (English) *
                        </label>
                        <input type="text" name="name[en]" id="name_en" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('name.en', $product->getTranslation('name', 'en')) }}">
                        @error('name.en')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="language-content hidden" id="content-dv">
                        <label for="name_dv" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.name') }} (ދިވެހިންނަށް) *
                        </label>
                        <input type="text" name="name[dv]" id="name_dv" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('name.dv', $product->getTranslation('name', 'dv')) }}">
                        @error('name.dv')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Multilingual Description Fields -->
                <div class="mb-6">
                    <div class="language-content" id="content-en">
                        <label for="description_en" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.description') }} (English)
                        </label>
                        <textarea name="description[en]" id="description_en" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">{{ old('description.en', $product->getTranslation('description', 'en')) }}</textarea>
                        @error('description.en')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="language-content hidden" id="content-dv">
                        <label for="description_dv" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.description') }} (ދިވެހިންނަށް)
                        </label>
                        <textarea name="description[dv]" id="description_dv" rows="4"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">{{ old('description.dv', $product->getTranslation('description', 'dv')) }}</textarea>
                        @error('description.dv')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Pricing -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="price" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.price') }} *
                        </label>
                        <input type="number" name="price" id="price" step="0.01" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('price', $product->price) }}">
                        @error('price')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="compare_price" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.compare_price') }}
                        </label>
                        <input type="number" name="compare_price" id="compare_price" step="0.01"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('compare_price', $product->compare_price) }}">
                        @error('compare_price')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Stock -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="stock_quantity" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.stock_quantity') }}
                        </label>
                        <input type="number" name="stock_quantity" id="stock_quantity" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('stock_quantity', $product->stock_quantity) }}">
                        @error('stock_quantity')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="reorder_point" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.reorder_point') }}
                        </label>
                        <input type="number" name="reorder_point" id="reorder_point" min="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('reorder_point', $product->reorder_point) }}">
                        @error('reorder_point')
                            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Current Image -->
                @if($product->main_image)
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('products.current_image') }}
                    </label>
                    <img src="{{ asset('storage/' . $product->main_image) }}" alt="{{ $product->name }}" 
                         class="w-32 h-32 object-cover rounded-lg border">
                </div>
                @endif

                <!-- New Image -->
                <div class="mb-6">
                    <label for="main_image" class="block text-sm font-medium text-gray-700 mb-2">
                        {{ __('products.new_image') }}
                    </label>
                    <input type="file" name="main_image" id="main_image" accept="image/*"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500">
                    @error('main_image')
                        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Additional Fields -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label for="brand" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.brand') }}
                        </label>
                        <input type="text" name="brand" id="brand"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('brand', $product->brand) }}">
                    </div>

                    <div>
                        <label for="model" class="block text-sm font-medium text-gray-700 mb-2">
                            {{ __('products.model') }}
                        </label>
                        <input type="text" name="model" id="model"
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary-500 focus:border-primary-500"
                               value="{{ old('model', $product->model) }}">
                    </div>
                </div>

                <!-- Checkboxes -->
                <div class="mb-6">
                    <div class="flex items-center space-x-6">
                        <label class="flex items-center">
                            <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', $product->is_featured) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">{{ __('products.featured') }}</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="requires_shipping" value="1" {{ old('requires_shipping', $product->requires_shipping) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">{{ __('products.requires_shipping') }}</span>
                        </label>

                        <label class="flex items-center">
                            <input type="checkbox" name="is_digital" value="1" {{ old('is_digital', $product->is_digital) ? 'checked' : '' }}
                                   class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">{{ __('products.digital_product') }}</span>
                        </label>
                    </div>
                </div>

                <!-- Product Variants -->
                <div class="mb-8">
                    <h2 class="text-lg font-semibold mb-2">{{ __('products.variants') }}</h2>
                    <table class="w-full text-sm border mb-2" id="variants-table">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="p-2">{{ __('products.variant_name_en') }}</th>
                                <th class="p-2">{{ __('products.variant_name_dv') }}</th>
                                <th class="p-2">{{ __('products.variant_type') }}</th>
                                <th class="p-2">{{ __('products.sku') }}</th>
                                <th class="p-2">{{ __('products.price_adjustment') }}</th>
                                <th class="p-2">{{ __('products.stock_quantity') }}</th>
                                <th class="p-2">{{ __('products.image') }}</th>
                                <th class="p-2">{{ __('products.active') }}</th>
                                <th class="p-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($product->variants as $i => $variant)
                                <tr>
                                    <td><input type="text" name="variants[{{ $i }}][name][en]" class="w-32 border rounded p-1" value="{{ $variant->getTranslation('name', 'en') }}"></td>
                                    <td><input type="text" name="variants[{{ $i }}][name][dv]" class="w-32 border rounded p-1" value="{{ $variant->getTranslation('name', 'dv') }}"></td>
                                    <td><input type="text" name="variants[{{ $i }}][type]" class="w-24 border rounded p-1" value="{{ $variant->type }}"></td>
                                    <td><input type="text" name="variants[{{ $i }}][sku]" class="w-24 border rounded p-1" value="{{ $variant->sku }}"></td>
                                    <td><input type="number" step="0.01" name="variants[{{ $i }}][price_adjustment]" class="w-20 border rounded p-1" value="{{ $variant->price_adjustment }}"></td>
                                    <td><input type="number" name="variants[{{ $i }}][stock_quantity]" class="w-16 border rounded p-1" value="{{ $variant->stock_quantity }}"></td>
                                    <td>
                                        @if($variant->image)
                                            <img src="{{ asset('storage/' . $variant->image) }}" alt="" class="w-10 h-10 object-cover mb-1">
                                        @endif
                                        <input type="file" name="variants[{{ $i }}][image]" class="w-32">
                                    </td>
                                    <td class="text-center"><input type="checkbox" name="variants[{{ $i }}][is_active]" value="1" {{ $variant->is_active ? 'checked' : '' }}></td>
                                    <td><button type="button" class="remove-variant-btn text-red-600">&times;</button></td>
                                    <input type="hidden" name="variants[{{ $i }}][id]" value="{{ $variant->id }}">
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" id="add-variant-btn" class="px-3 py-1 bg-primary-600 text-white rounded hover:bg-primary-700">+ {{ __('products.add_variant') }}</button>
                </div>

                <!-- Island Inventory -->
                <div class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Island Availability & Inventory</h3>
                    <table class="w-full text-sm border mb-2">
                        <thead>
                            <tr class="bg-gray-100">
                                <th class="p-2">Island</th>
                                <th class="p-2">Stock Quantity</th>
                                <th class="p-2">Reorder Point</th>
                                <th class="p-2">Available</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($islands as $island)
                            @php
                                $pivot = $product->islands->firstWhere('id', $island->id)?->pivot;
                            @endphp
                            <tr>
                                <td>{{ $island->name[app()->getLocale()] ?? $island->name['en'] }}</td>
                                <td><input type="number" name="island_stock[{{ $island->id }}][stock_quantity]" min="0" class="w-24 border rounded p-1" value="{{ $pivot->stock_quantity ?? 0 }}"></td>
                                <td><input type="number" name="island_stock[{{ $island->id }}][reorder_point]" min="0" class="w-24 border rounded p-1" value="{{ $pivot->reorder_point ?? 10 }}"></td>
                                <td class="text-center"><input type="checkbox" name="island_stock[{{ $island->id }}][is_active]" value="1" {{ ($pivot && $pivot->is_active) ? 'checked' : '' }}></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Submit Button -->
                <div class="flex justify-end space-x-4">
                    <a href="{{ route('products.index') }}" 
                       class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        {{ __('common.cancel') }}
                    </a>
                    <button type="submit" 
                            class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        {{ __('products.update_product') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const languageTabs = document.querySelectorAll('.language-tab');
    const languageContents = document.querySelectorAll('.language-content');

    languageTabs.forEach(tab => {
        tab.addEventListener('click', function() {
            const language = this.getAttribute('data-language');
            
            // Update tab styles
            languageTabs.forEach(t => {
                const span = t.querySelector('span');
                span.classList.remove('text-primary-600', 'border-primary-600');
                span.classList.add('text-gray-500', 'border-transparent');
            });
            
            const activeSpan = this.querySelector('span');
            activeSpan.classList.remove('text-gray-500', 'border-transparent');
            activeSpan.classList.add('text-primary-600', 'border-primary-600');
            
            // Show/hide content
            languageContents.forEach(content => {
                content.classList.add('hidden');
            });
            
            document.getElementById(`content-${language}`).classList.remove('hidden');
        });
    });

    const variantsTable = document.getElementById('variants-table').getElementsByTagName('tbody')[0];
    const addVariantBtn = document.getElementById('add-variant-btn');

    function createVariantRow(index = null, data = {}) {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><input type="text" name="variants[${index}][name][en]" class="w-32 border rounded p-1" value="${data.name?.en ?? ''}"></td>
            <td><input type="text" name="variants[${index}][name][dv]" class="w-32 border rounded p-1" value="${data.name?.dv ?? ''}"></td>
            <td><input type="text" name="variants[${index}][type]" class="w-24 border rounded p-1" value="${data.type ?? ''}"></td>
            <td><input type="text" name="variants[${index}][sku]" class="w-24 border rounded p-1" value="${data.sku ?? ''}"></td>
            <td><input type="number" step="0.01" name="variants[${index}][price_adjustment]" class="w-20 border rounded p-1" value="${data.price_adjustment ?? 0}"></td>
            <td><input type="number" name="variants[${index}][stock_quantity]" class="w-16 border rounded p-1" value="${data.stock_quantity ?? 0}"></td>
            <td><input type="file" name="variants[${index}][image]" class="w-32"></td>
            <td class="text-center"><input type="checkbox" name="variants[${index}][is_active]" value="1" ${data.is_active ? 'checked' : ''}></td>
            <td><button type="button" class="remove-variant-btn text-red-600">&times;</button></td>
        `;
        return row;
    }

    let variantIndex = {{ $product->variants->count() }};
    addVariantBtn.addEventListener('click', function() {
        const row = createVariantRow(variantIndex++);
        variantsTable.appendChild(row);
    });

    variantsTable.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-variant-btn')) {
            e.target.closest('tr').remove();
        }
    });
});
</script>
@endsection 