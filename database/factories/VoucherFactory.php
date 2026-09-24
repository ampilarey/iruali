<?php

namespace Database\Factories;

use App\Models\Voucher;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoucherFactory extends Factory
{
    protected $model = Voucher::class;

    public function definition()
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('VOUCHER-####')),
            'type' => 'fixed',
            'amount' => 10,
            'min_order' => null,
            'max_uses' => null,
            'used_count' => 0,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addMonth(),
            'is_active' => true,
        ];
    }
}
