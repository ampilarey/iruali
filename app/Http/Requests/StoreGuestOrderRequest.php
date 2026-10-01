<?php

namespace App\Http\Requests;

use App\Services\CartService;
use App\Support\GuestCheckout;

/**
 * Checkout without an account: the same address and payment fields, plus who the guest is.
 * The cart is the guest's token-keyed cart. Loyalty points and saved addresses do not apply.
 */
class StoreGuestOrderRequest extends StoreOrderRequest
{
    public function authorize(): bool
    {
        return GuestCheckout::enabled() && ! auth()->check();
    }

    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['address_id'], $rules['save_address'], $rules['address_label']);

        return $rules + [
            'guest_email' => 'required|email|max:255',
            'guest_name' => 'required|string|max:120',
        ];
    }

    public function messages(): array
    {
        return parent::messages() + [
            'guest_email.required' => __('Please enter your email so we can send your order confirmation.'),
            'guest_email.email' => __('Please enter a valid email address.'),
            'guest_name.required' => __('Please enter your name.'),
        ];
    }

    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        $this->merge([
            'guest_email' => mb_strtolower(trim((string) $this->input('guest_email'))),
            'guest_name' => trim((string) $this->input('guest_name')),
            'address_id' => null,
        ]);
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $cart = app(CartService::class)->currentCart();

            if (! $cart || $cart->items->count() === 0) {
                $validator->errors()->add('cart', __('Your cart is empty. Please add items before placing an order.'));

                return;
            }

            foreach ($cart->items as $item) {
                if (! $item->product || ! $item->product->is_active) {
                    $validator->errors()->add('cart', __('A product in your cart is no longer available.'));
                } elseif ($item->availableStock() < $item->quantity) {
                    $validator->errors()->add('cart', __('Not enough stock for :name.', ['name' => $item->product->name]));
                }
            }
        });
    }
}
