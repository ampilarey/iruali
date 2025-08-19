<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_state' => 'required|string|max:100',
            'shipping_zip' => 'required|string|max:20',
            'shipping_country' => 'required|string|max:100',
            'shipping_phone' => 'nullable|string|max:20',
            'billing_address' => 'nullable|string|max:500',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:100',
            'billing_zip' => 'nullable|string|max:20',
            'billing_country' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => 'required|in:cod,card,bank_transfer',
            'agree_terms' => 'required|accepted',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'shipping_address.required' => 'Please enter your shipping address.',
            'shipping_city.required' => 'Please enter your shipping city.',
            'shipping_state.required' => 'Please enter your shipping state/province.',
            'shipping_zip.required' => 'Please enter your shipping postal code.',
            'shipping_country.required' => 'Please select your shipping country.',
            'payment_method.required' => 'Please select a payment method.',
            'payment_method.in' => 'Please select a valid payment method.',
            'agree_terms.required' => 'You must agree to the terms and conditions.',
            'agree_terms.accepted' => 'You must agree to the terms and conditions.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'shipping_address' => 'shipping address',
            'shipping_city' => 'shipping city',
            'shipping_state' => 'shipping state/province',
            'shipping_zip' => 'shipping postal code',
            'shipping_country' => 'shipping country',
            'shipping_phone' => 'shipping phone',
            'billing_address' => 'billing address',
            'billing_city' => 'billing city',
            'billing_state' => 'billing state/province',
            'billing_zip' => 'billing postal code',
            'billing_country' => 'billing country',
            'notes' => 'order notes',
            'payment_method' => 'payment method',
            'agree_terms' => 'terms and conditions',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Trim whitespace from string inputs
        $this->merge([
            'shipping_address' => trim($this->input('shipping_address')),
            'shipping_city' => trim($this->input('shipping_city')),
            'shipping_state' => trim($this->input('shipping_state')),
            'shipping_zip' => trim($this->input('shipping_zip')),
            'shipping_country' => trim($this->input('shipping_country')),
            'shipping_phone' => $this->input('shipping_phone') ? trim($this->input('shipping_phone')) : null,
            'billing_address' => $this->input('billing_address') ? trim($this->input('billing_address')) : null,
            'billing_city' => $this->input('billing_city') ? trim($this->input('billing_city')) : null,
            'billing_state' => $this->input('billing_state') ? trim($this->input('billing_state')) : null,
            'billing_zip' => $this->input('billing_zip') ? trim($this->input('billing_zip')) : null,
            'billing_country' => $this->input('billing_country') ? trim($this->input('billing_country')) : null,
            'notes' => $this->input('notes') ? trim($this->input('notes')) : null,
        ]);

        // If billing address is not provided, use shipping address
        if (!$this->input('billing_address')) {
            $this->merge([
                'billing_address' => $this->input('shipping_address'),
                'billing_city' => $this->input('shipping_city'),
                'billing_state' => $this->input('shipping_state'),
                'billing_zip' => $this->input('shipping_zip'),
                'billing_country' => $this->input('shipping_country'),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Check if user has items in cart
            $user = auth()->user();
            $cart = $user->carts()->where('status', 'active')->latest()->first();

            if (!$cart || $cart->items->count() === 0) {
                $validator->errors()->add('cart', 'Your cart is empty. Please add items before placing an order.');
            }

            // Check if all cart items are still available
            if ($cart) {
                foreach ($cart->items as $item) {
                    if (!$item->product->is_active) {
                        $validator->errors()->add('cart', "Product '{$item->product->name['en']}' is no longer available.");
                    }

                    if ($item->product->stock_quantity < $item->quantity) {
                        $validator->errors()->add('cart', "Insufficient stock for '{$item->product->name['en']}'. Available: {$item->product->stock_quantity}");
                    }
                }
            }
        });
    }
}
