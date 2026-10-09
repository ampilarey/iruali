<?php

namespace App\Http\Requests\Seller;

use App\Models\Product;
use App\Models\ShopDiscountCode;
use App\Support\CurrentShop;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Seller Centre → Discount codes: create or edit one of the shop's codes.
 */
class ShopDiscountCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // ownership of an existing code is checked by the controller
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => ShopDiscountCode::normalize((string) $this->input('code', '')),
            'applies_to' => $this->input('applies_to') === 'selected' ? 'selected' : 'all',
        ]);
        // The form always sends it (a hidden 0 under the checkbox); left out, a code stays as it is
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
    }

    public function rules(): array
    {
        /** @var ShopDiscountCode|null $code */
        $code = $this->route('discount');

        return [
            // Unique per shop; codes are kept in capitals, so "save10" and "SAVE10" are the same code
            'code' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('shop_discount_codes', 'code')->where('seller_id', CurrentShop::id())->ignore($code?->id)],
            'type' => ['required', Rule::in(ShopDiscountCode::TYPES)],
            'value' => ['required', 'numeric', 'min:0.01', $this->input('type') === 'percent' ? 'max:90' : 'max:999999.99'],
            'min_spend' => 'nullable|numeric|min:0|max:999999.99',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date',
            'max_uses' => 'nullable|integer|min:1|max:1000000',
            'max_uses_per_customer' => 'nullable|integer|min:1|max:1000',
            'applies_to' => 'required|in:all,selected',
            'product_ids' => 'nullable|array|max:500',
            'product_ids.*' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'code.regex' => __('Use only letters, numbers, - and _ in the code (no spaces).'),
            'code.unique' => __('Your shop already has a code with that name.'),
            'value.max' => $this->input('type') === 'percent' ? __('A percent discount can be at most 90%.') : __('That amount is too large.'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $starts = $this->date('starts_at');
            $ends = $this->date('ends_at');
            if ($starts && $ends && $ends->lte($starts)) {
                $validator->errors()->add('ends_at', __('The end must be after the start.'));
            }

            if ($this->input('applies_to') === 'selected') {
                $ids = array_unique(array_map('intval', (array) $this->input('product_ids', [])));
                if ($ids === []) {
                    $validator->errors()->add('product_ids', __('Choose at least one product, or let the code cover all your products.'));
                } elseif (Product::where('seller_id', CurrentShop::id())->whereIn('id', $ids)->count() !== count($ids)) {
                    $validator->errors()->add('product_ids', __('Choose products from your own shop.'));
                }
            }
        });
    }

    /**
     * The code's columns from the validated input.
     *
     * @return array<string, mixed>
     */
    public function codeAttributes(): array
    {
        $data = $this->validated();

        return [
            'code' => $data['code'],
            'type' => $data['type'],
            'value' => round((float) $data['value'], 2),
            'min_spend' => filled($data['min_spend'] ?? null) ? round((float) $data['min_spend'], 2) : null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'max_uses' => $data['max_uses'] ?? null,
            'max_uses_per_customer' => $data['max_uses_per_customer'] ?? null,
            'applies_to' => $data['applies_to'],
        ] + (array_key_exists('is_active', $data) ? ['is_active' => (bool) $data['is_active']] : []);
    }

    /**
     * @return list<int>
     */
    public function productIds(): array
    {
        return $this->input('applies_to') === 'selected' ? array_values(array_unique(array_map('intval', (array) $this->input('product_ids', [])))) : [];
    }
}
