<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVoucherRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => 'required|string|max:50|unique:vouchers,code',
            'type' => 'required|in:fixed,percent',
            'amount' => 'required|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'valid_from' => 'nullable|date|before_or_equal:valid_until',
            'valid_until' => 'nullable|date|after_or_equal:valid_from',
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:500',
            'applicable_categories' => 'nullable|array',
            'applicable_categories.*' => 'exists:categories,id',
            'excluded_products' => 'nullable|array',
            'excluded_products.*' => 'exists:products,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Voucher code is required.',
            'code.unique' => 'This voucher code is already in use.',
            'type.required' => 'Please select a voucher type.',
            'type.in' => 'Please select a valid voucher type.',
            'amount.required' => 'Voucher amount is required.',
            'amount.min' => 'Voucher amount must be greater than 0.',
            'min_order.min' => 'Minimum order amount must be greater than or equal to 0.',
            'max_uses.min' => 'Maximum uses must be at least 1.',
            'valid_from.before_or_equal' => 'Valid from date must be before or equal to valid until date.',
            'valid_until.after_or_equal' => 'Valid until date must be after or equal to valid from date.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'code' => 'voucher code',
            'type' => 'voucher type',
            'amount' => 'voucher amount',
            'min_order' => 'minimum order amount',
            'max_uses' => 'maximum uses',
            'valid_from' => 'valid from date',
            'valid_until' => 'valid until date',
            'description' => 'description',
            'applicable_categories' => 'applicable categories',
            'excluded_products' => 'excluded products',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Ensure voucher code is uppercase and trimmed
        $this->merge([
            'code' => strtoupper(trim($this->input('code'))),
            'description' => $this->input('description') ? trim($this->input('description')) : null,
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate percent voucher amount
            if ($this->input('type') === 'percent' && $this->input('amount') > 100) {
                $validator->errors()->add('amount', 'Percentage discount cannot exceed 100%.');
            }

            // Validate fixed voucher amount
            if ($this->input('type') === 'fixed' && $this->input('min_order') && $this->input('amount') >= $this->input('min_order')) {
                $validator->errors()->add('amount', 'Fixed discount amount must be less than the minimum order amount.');
            }
        });
    }
}
