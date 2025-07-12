<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && (auth()->user()->isSeller() || auth()->user()->isAdmin());
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name.en' => 'required|string|max:255',
            'name.dv' => 'required|string|max:255',
            'description.en' => 'nullable|string|max:5000',
            'description.dv' => 'nullable|string|max:5000',
            'sku' => [
                'required',
                'string',
                'max:100',
                'unique:products,sku'
            ],
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0|max:999999.99',
            'compare_price' => 'nullable|numeric|min:0|max:999999.99|gt:price',
            'stock_quantity' => 'nullable|integer|min:0|max:999999',
            'reorder_point' => 'nullable|integer|min:0|max:999999',
            'brand' => 'nullable|string|max:255',
            'model' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0|max:999.99',
            'dimensions' => 'nullable|string|max:255',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_featured' => 'boolean',
            'requires_shipping' => 'boolean',
            'is_digital' => 'boolean',
            'island_stock' => 'nullable|array',
            'island_stock.*.stock_quantity' => 'nullable|integer|min:0|max:999999',
            'island_stock.*.reorder_point' => 'nullable|integer|min:0|max:999999',
            'island_stock.*.is_active' => 'boolean',
            'variants' => 'nullable|array',
            'variants.*.name.en' => 'required_with:variants|string|max:255',
            'variants.*.name.dv' => 'required_with:variants|string|max:255',
            'variants.*.type' => 'required_with:variants|string|max:100',
            'variants.*.sku' => 'required_with:variants|string|max:100',
            'variants.*.price_adjustment' => 'nullable|numeric|min:-999999.99|max:999999.99',
            'variants.*.stock_quantity' => 'nullable|integer|min:0|max:999999',
            'variants.*.is_active' => 'boolean',
            'variants.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.en.required' => 'Product name in English is required.',
            'name.dv.required' => 'Product name in Dhivehi is required.',
            'sku.unique' => 'This SKU is already in use. Please choose a different one.',
            'price.min' => 'Price must be greater than or equal to 0.',
            'compare_price.gt' => 'Compare price must be greater than the regular price.',
            'main_image.max' => 'Product image must not exceed 2MB.',
            'main_image.mimes' => 'Product image must be a JPEG, PNG, JPG, or GIF file.',
            'variants.*.image.max' => 'Variant image must not exceed 2MB.',
            'variants.*.image.mimes' => 'Variant image must be a JPEG, PNG, JPG, or GIF file.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name.en' => 'product name (English)',
            'name.dv' => 'product name (Dhivehi)',
            'description.en' => 'description (English)',
            'description.dv' => 'description (Dhivehi)',
            'category_id' => 'category',
            'main_image' => 'product image',
            'island_stock.*.stock_quantity' => 'island stock quantity',
            'island_stock.*.reorder_point' => 'island reorder point',
            'variants.*.name.en' => 'variant name (English)',
            'variants.*.name.dv' => 'variant name (Dhivehi)',
            'variants.*.image' => 'variant image',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure boolean fields are properly cast
        $this->merge([
            'is_featured' => $this->boolean('is_featured'),
            'requires_shipping' => $this->boolean('requires_shipping'),
            'is_digital' => $this->boolean('is_digital'),
        ]);

        // Handle island stock boolean fields
        if ($this->has('island_stock')) {
            $islandStock = $this->input('island_stock');
            foreach ($islandStock as $islandId => $data) {
                if (isset($data['is_active'])) {
                    $islandStock[$islandId]['is_active'] = $this->boolean("island_stock.{$islandId}.is_active");
                }
            }
            $this->merge(['island_stock' => $islandStock]);
        }

        // Handle variant boolean fields
        if ($this->has('variants')) {
            $variants = $this->input('variants');
            foreach ($variants as $index => $variant) {
                if (isset($variant['is_active'])) {
                    $variants[$index]['is_active'] = $this->boolean("variants.{$index}.is_active");
                }
            }
            $this->merge(['variants' => $variants]);
        }
    }
}
