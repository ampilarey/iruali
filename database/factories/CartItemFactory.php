<?php

namespace Database\Factories;

use App\Models\CartItem;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartItemFactory extends Factory
{
    protected $model = CartItem::class;

    public function definition()
    {
        return [
            'cart_id' => \App\Models\Cart::factory(),
            'product_id' => \App\Models\Product::factory(),
            'product_variant_id' => null,
            'quantity' => $this->faker->numberBetween(1, 5),
            'price' => $this->faker->randomFloat(2, 10, 1000),
        ];
    }
} 