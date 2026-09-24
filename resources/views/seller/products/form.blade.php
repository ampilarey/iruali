@extends('layouts.app')

@php
    $editing = $product->exists;
    $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-primary-500 focus:ring-primary-500';
@endphp

@section('content')
<div class="min-h-screen bg-gray-100 pb-12">
    @include('seller.partials.header', ['title' => $editing ? 'Edit product' : 'Add product'])

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        @if($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc pl-5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" enctype="multipart/form-data"
              action="{{ $editing ? route('seller.products.update', $product) : route('seller.products.store') }}"
              class="space-y-6 rounded-lg bg-white p-6 shadow">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="name_en" class="block text-sm font-medium text-gray-700">Name (English) *</label>
                    <input id="name_en" name="name_en" required class="{{ $field }}"
                           value="{{ old('name_en', $product->getTranslation('name', 'en', false)) }}">
                </div>
                <div>
                    <label for="name_dv" class="block text-sm font-medium text-gray-700">Name (Dhivehi)</label>
                    <input id="name_dv" name="name_dv" dir="rtl" class="{{ $field }}"
                           value="{{ old('name_dv', $product->getTranslation('name', 'dv', false)) }}">
                </div>
                <div>
                    <label for="description_en" class="block text-sm font-medium text-gray-700">Description (English)</label>
                    <textarea id="description_en" name="description_en" rows="4" class="{{ $field }}">{{ old('description_en', $product->getTranslation('description', 'en', false)) }}</textarea>
                </div>
                <div>
                    <label for="description_dv" class="block text-sm font-medium text-gray-700">Description (Dhivehi)</label>
                    <textarea id="description_dv" name="description_dv" rows="4" dir="rtl" class="{{ $field }}">{{ old('description_dv', $product->getTranslation('description', 'dv', false)) }}</textarea>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="sku" class="block text-sm font-medium text-gray-700">SKU *</label>
                    <input id="sku" name="sku" required class="{{ $field }}" value="{{ old('sku', $product->sku) }}">
                </div>
                <div class="sm:col-span-2">
                    <label for="category_id" class="block text-sm font-medium text-gray-700">Category *</label>
                    <select id="category_id" name="category_id" required class="{{ $field }}">
                        <option value="">Choose a category</option>
                        @foreach($categories as $category)
                            <option value="{{ $category->id }}" @selected(old('category_id', $product->category_id) == $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="price" class="block text-sm font-medium text-gray-700">Price (MVR) *</label>
                    <input id="price" name="price" type="number" step="0.01" min="0" required class="{{ $field }}" value="{{ old('price', $product->price) }}">
                </div>
                <div>
                    <label for="compare_price" class="block text-sm font-medium text-gray-700">Compare-at price</label>
                    <input id="compare_price" name="compare_price" type="number" step="0.01" min="0" class="{{ $field }}" value="{{ old('compare_price', $product->compare_price) }}">
                </div>
                <div>
                    <label for="brand" class="block text-sm font-medium text-gray-700">Brand</label>
                    <input id="brand" name="brand" class="{{ $field }}" value="{{ old('brand', $product->brand) }}">
                </div>
                <div>
                    <label for="stock_quantity" class="block text-sm font-medium text-gray-700">Stock *</label>
                    <input id="stock_quantity" name="stock_quantity" type="number" min="0" required class="{{ $field }}" value="{{ old('stock_quantity', $product->stock_quantity) }}">
                </div>
                <div>
                    <label for="reorder_point" class="block text-sm font-medium text-gray-700">Low-stock alert at</label>
                    <input id="reorder_point" name="reorder_point" type="number" min="0" class="{{ $field }}" value="{{ old('reorder_point', $product->reorder_point) }}">
                </div>
                <div>
                    <label for="weight" class="block text-sm font-medium text-gray-700">Weight (kg)</label>
                    <input id="weight" name="weight" type="number" step="0.01" min="0" class="{{ $field }}" value="{{ old('weight', $product->weight) }}">
                </div>
            </div>

            <div>
                <label for="main_image" class="block text-sm font-medium text-gray-700">Main image</label>
                @if($product->mainImage)
                    <img src="{{ $product->mainImage->url }}" alt="" class="mt-2 h-24 w-24 rounded-lg object-cover">
                @endif
                <input id="main_image" name="main_image" type="file" accept="image/jpeg,image/png,image/gif" class="mt-2 block w-full text-sm text-gray-700">
                <p class="mt-1 text-xs text-gray-500">JPEG, PNG or GIF, up to 2 MB.</p>
            </div>

            <div class="flex items-center justify-end gap-3 border-t border-gray-100 pt-4">
                <a href="{{ route('seller.products.index') }}" class="rounded-lg px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100">Cancel</a>
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-medium text-white hover:bg-primary-700">
                    {{ $editing ? 'Save changes' : 'Submit for approval' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
