<?php

namespace Database\Factories;

use App\Models\Cart;
use Illuminate\Database\Eloquent\Factories\Factory;

class CartFactory extends Factory
{
    protected $model = Cart::class;

    public function definition()
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'session_id' => $this->faker->uuid(),
            'status' => 'active',
        ];
    }
} 