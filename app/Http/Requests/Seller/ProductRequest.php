<?php

namespace App\Http\Requests\Seller;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A shop's product form: the product's own fields plus its variants table.
 *
 * Variant rows arrive as variants[n][sku|price|stock_quantity|low_stock_threshold|is_active|image|id|remove]
 * with variants[n][attributes] a JSON object such as {"Size":"M","Colour":"Blue"}.
 */
class ProductRequest extends FormRequest
{
    public const MAX_OPTION_TYPES = 3;

    public const MAX_VARIANTS = 100;

    public function authorize(): bool
    {
        return $this->user() !== null; // ownership is checked by the controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['has_variants' => $this->boolean('has_variants')]);
    }

    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return [
            'name_en' => 'required|string|max:255',
            'name_dv' => 'nullable|string|max:255',
            'description_en' => 'nullable|string|max:5000',
            'description_dv' => 'nullable|string|max:5000',
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($product?->id)],
            'category_id' => 'required|exists:categories,id',
            'price' => 'required|numeric|min:0|max:999999.99',
            'compare_price' => 'nullable|numeric|min:0|max:999999.99|gt:price',
            'flash_sale_ends_at' => 'nullable|date|after:now',
            'has_variants' => 'boolean',
            // With variants the product's stock is the sum of theirs, so the field is not submitted
            'stock_quantity' => ['exclude_if:has_variants,true', 'required', 'integer', 'min:0', 'max:999999'],
            'reorder_point' => 'nullable|integer|min:0|max:999999',
            'brand' => 'nullable|string|max:120',
            'weight' => 'nullable|numeric|min:0|max:999.99',
            // Bulky items: added to the delivery fee per unit delivered, never waived by free delivery
            'delivery_surcharge' => 'nullable|numeric|min:0|max:99999.99',
            'main_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'video_url' => ['nullable', 'string', 'max:500', new \App\Rules\ProductVideoUrl],

            'variants' => ['exclude_if:has_variants,false', 'required', 'array', 'min:1', 'max:'.self::MAX_VARIANTS],
            'variants.*.id' => 'nullable|integer',
            'variants.*.remove' => 'nullable|boolean',
            'variants.*.attributes' => ['required', 'string', 'max:1000', 'json'],
            'variants.*.sku' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'variants.*.price' => 'nullable|numeric|min:0.01|max:999999.99',
            'variants.*.stock_quantity' => 'required|integer|min:0|max:999999',
            'variants.*.low_stock_threshold' => 'nullable|integer|min:0|max:999999',
            'variants.*.is_active' => 'nullable|boolean',
            'variants.*.image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ] + \App\Services\MultiBuyService::formRules()
          + \App\Services\PreorderService::formRules();
    }

    public function attributes(): array
    {
        return [
            'variants.*.sku' => __('variant SKU'),
            'variants.*.price' => __('variant price'),
            'variants.*.stock_quantity' => __('variant stock'),
            'variants.*.attributes' => __('variant options'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => app(\App\Services\MultiBuyService::class)->validateForm($validator, $this->user(), (array) $this->input('multibuy', [])));
        $validator->after(function (Validator $validator) {
            if (! $this->boolean('has_variants')) {
                return;
            }

            /** @var Product|null $product */
            $product = $this->route('product');
            $rows = collect($this->input('variants', []))->filter(fn ($row) => empty($row['remove']));
            $seen = [];

            foreach ($rows as $index => $row) {
                $line = $index + 1;
                $attributes = json_decode((string) ($row['attributes'] ?? ''), true);
                if (! is_array($attributes) || $attributes === [] || count($attributes) > self::MAX_OPTION_TYPES) {
                    $validator->errors()->add("variants.$index.attributes", __('Variant :n needs between 1 and 3 options.', ['n' => $line]));

                    continue;
                }
                foreach ($attributes as $key => $value) {
                    if (! is_string($key) || trim($key) === '' || mb_strlen($key) > 50 || ! is_scalar($value) || trim((string) $value) === '' || mb_strlen((string) $value) > 100) {
                        $validator->errors()->add("variants.$index.attributes", __('Variant :n has an invalid option.', ['n' => $line]));

                        continue 2;
                    }
                }
                $combo = json_encode(array_map(fn ($v) => mb_strtolower(trim((string) $v)), $attributes));
                if (isset($seen[$combo])) {
                    $validator->errors()->add("variants.$index.attributes", __('Variant :n repeats the combination of variant :other.', ['n' => $line, 'other' => $seen[$combo]]));
                }
                $seen[$combo] = $line;

                // SKUs are unique across the whole marketplace, like product SKUs
                $sku = (string) ($row['sku'] ?? '');
                $id = (int) ($row['id'] ?? 0);
                $taken = ProductVariant::where('sku', $sku)->when($id, fn ($q) => $q->whereKeyNot($id))->exists()
                    || Product::withTrashed()->where('sku', $sku)->exists();
                if ($taken) {
                    $validator->errors()->add("variants.$index.sku", __('The SKU ":sku" is already in use.', ['sku' => $sku]));
                }
                if ($id && $product && ! $product->variants()->whereKey($id)->exists()) {
                    $validator->errors()->add("variants.$index.id", __('Variant :n does not belong to this product.', ['n' => $line]));
                }
            }

            if ($rows->isEmpty()) {
                $validator->errors()->add('variants', __('Add at least one variant, or turn variants off.'));
            }
        });
    }

    /**
     * The product's own columns from the validated input.
     */
    public function productAttributes(): array
    {
        $data = $this->validated();

        $attributes = [
            'name' => array_filter(['en' => $data['name_en'], 'dv' => $data['name_dv'] ?? null]),
            'description' => array_filter(['en' => $data['description_en'] ?? null, 'dv' => $data['description_dv'] ?? null]),
            'sku' => $data['sku'],
            'category_id' => $data['category_id'],
            'price' => $data['price'],
            'compare_price' => $data['compare_price'] ?? null,
            'flash_sale_ends_at' => ! empty($data['compare_price']) ? ($data['flash_sale_ends_at'] ?? null) : null,
            'has_variants' => (bool) ($data['has_variants'] ?? false),
            'reorder_point' => $data['reorder_point'] ?? 5,
            'brand' => $data['brand'] ?? null,
            'weight' => $data['weight'] ?? null,
        ];

        if (array_key_exists('stock_quantity', $data)) {
            $attributes['stock_quantity'] = $data['stock_quantity'];
        }
        // Only when the form sent it, so a form without the field leaves the charge as it was
        if (array_key_exists('delivery_surcharge', $data)) {
            $attributes['delivery_surcharge'] = round((float) ($data['delivery_surcharge'] ?? 0), 2);
        }

        return $attributes;
    }

    /**
     * The variant rows to sync (empty when variants are off).
     */
    public function variantRows(): array
    {
        return $this->boolean('has_variants') ? array_values($this->validated()['variants'] ?? []) : [];
    }

    /**
     * The video columns from the form's video link (both null when it was emptied), or nothing
     * when the form did not send the field, so the video stays as it was.
     *
     * @return array{video_provider?: string|null, video_id?: string|null}
     */
    public function videoAttributes(): array
    {
        $data = $this->validated();
        if (! array_key_exists('video_url', $data)) {
            return [];
        }

        $video = \App\Support\ProductVideo::parse($data['video_url']);

        return ['video_provider' => $video?->provider, 'video_id' => $video?->id];
    }

    /**
     * Messages for the "Pre-order" fieldset.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return \App\Services\PreorderService::formMessages();
    }

    /**
     * The pre-order columns from the form's "Pre-order" fieldset, or nothing when the form did not
     * have it (PreorderService::attributesFromForm()).
     *
     * @return array<string, mixed>
     */
    public function preorderAttributes(): array
    {
        return app(\App\Services\PreorderService::class)->attributesFromForm($this->validated());
    }
}
