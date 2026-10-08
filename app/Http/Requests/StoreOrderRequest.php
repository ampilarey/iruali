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
            'shipping_zip' => 'nullable|string|max:20',
            'shipping_country' => 'required|string|max:100',
            'shipping_phone' => ['required', 'string', 'max:20', 'regex:/^\+?[0-9 ]{7,15}$/'],
            'billing_address' => 'nullable|string|max:500',
            'billing_city' => 'nullable|string|max:100',
            'billing_state' => 'nullable|string|max:100',
            'billing_zip' => 'nullable|string|max:20',
            'billing_country' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000',
            'payment_method' => ['required', \Illuminate\Validation\Rule::in(array_keys(app(\App\Services\PaymentService::class)->methods()))],
            'delivery_zone' => 'nullable|in:greater_male,islands',
            'agree_terms' => 'required|accepted',
            // Saved addresses and the island picker (their values are copied into shipping_* before validation)
            'address_id' => 'nullable|integer',
            'island_id' => 'nullable|integer|exists:islands,id',
            'save_address' => 'nullable|boolean',
            'address_label' => 'nullable|string|max:50',
            'use_wallet' => 'nullable|boolean',
        ];
    }

    /**
     * The saved address the customer picked, when it is theirs.
     */
    public function savedAddress(): ?\App\Models\Address
    {
        if (! $this->filled('address_id') || ! auth()->check()) {
            return null;
        }

        return \App\Models\Address::where('user_id', auth()->id())->find((int) $this->input('address_id'));
    }

    /**
     * Fill the shipping_* fields from the chosen saved address, or the island name and atoll from
     * the island picked in the list, so OrderService sees the same fields as a typed address.
     */
    protected function applyAddressChoice(): void
    {
        if ($this->filled('address_id')) {
            if ($address = $this->savedAddress()) {
                $this->merge(array_merge($address->toShippingData(), ['shipping_phone' => $address->phone ?: auth()->user()?->phone]));
            }

            return;
        }

        if ($this->filled('island_id') && ($island = \App\Models\Island::find((int) $this->input('island_id')))) {
            $this->merge([
                'shipping_city' => $island->getTranslation('name', 'en', false) ?: $island->getTranslation('name', config('app.fallback_locale'), false),
                'shipping_state' => $island->atoll ?: (string) $this->input('shipping_state'),
                'delivery_zone' => app(\App\Services\DeliveryService::class)->zoneForIsland($island),
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'shipping_address.required' => __('Please enter your shipping address.'),
            'shipping_city.required' => __('Please enter your shipping city.'),
            'shipping_state.required' => __('Please enter your shipping state/province.'),
            'shipping_country.required' => __('Please select your shipping country.'),
            'shipping_phone.required' => __('Please enter a phone number we can reach you on for delivery.'),
            'shipping_phone.regex' => __('Please enter a valid phone number (digits only, e.g. 7771234).'),
            'payment_method.required' => __('Please select a payment method.'),
            'payment_method.in' => __('Please select a valid payment method.'),
            'agree_terms.required' => __('You must agree to the terms and conditions.'),
            'agree_terms.accepted' => __('You must agree to the terms and conditions.'),
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
            'shipping_phone' => 'delivery phone',
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
        $this->applyAddressChoice();

        // Trim whitespace from string inputs
        $this->merge([
            'shipping_address' => trim($this->input('shipping_address')),
            'shipping_city' => trim($this->input('shipping_city')),
            'shipping_state' => trim($this->input('shipping_state')),
            'shipping_zip' => $this->input('shipping_zip') ? trim($this->input('shipping_zip')) : null,
            'shipping_country' => trim($this->input('shipping_country')),
            'shipping_phone' => $this->input('shipping_phone') ? preg_replace('/\s+/', '', trim($this->input('shipping_phone'))) : null,
            'billing_address' => $this->input('billing_address') ? trim($this->input('billing_address')) : null,
            'billing_city' => $this->input('billing_city') ? trim($this->input('billing_city')) : null,
            'billing_state' => $this->input('billing_state') ? trim($this->input('billing_state')) : null,
            'billing_zip' => $this->input('billing_zip') ? trim($this->input('billing_zip')) : null,
            'billing_country' => $this->input('billing_country') ? trim($this->input('billing_country')) : null,
            'notes' => $this->input('notes') ? trim($this->input('notes')) : null,
        ]);

        // If billing address is not provided, use shipping address
        if (! $this->input('billing_address')) {
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
            // A saved address must be one of the customer's own
            if ($this->filled('address_id') && ! $this->savedAddress()) {
                $validator->errors()->add('address_id', __('That saved address could not be found. Please choose another or enter a new one.'));
            }

            // Check if user has items in cart
            $user = auth()->user();
            $cart = $user->carts()->where('status', 'active')->latest()->first();

            if (! $cart || $cart->items->count() === 0) {
                $validator->errors()->add('cart', __('Your cart is empty. Please add items before placing an order.'));
            }

            // Check that every item is still on sale and in stock (the chosen size/colour's own stock),
            // the same check as guest checkout
            if ($cart) {
                foreach ($cart->items as $item) {
                    if (! $item->product || ! $item->product->is_active) {
                        $validator->errors()->add('cart', __('A product in your cart is no longer available.'));
                    } elseif ($item->availableStock() < $item->quantity) {
                        $validator->errors()->add('cart', __('Not enough stock for :name.', ['name' => $item->product->name]));
                    }
                }
            }
        });
    }
}
