<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 10, 1000);
        $taxAmount = $subtotal * 0.1; // 10% tax
        $shippingAmount = $this->faker->randomFloat(2, 5, 50);
        $discountAmount = $this->faker->randomFloat(2, 0, $subtotal * 0.2); // Up to 20% discount
        $totalAmount = $subtotal + $taxAmount + $shippingAmount - $discountAmount;

        return [
            'user_id' => User::factory(),
            'order_number' => 'ORD-' . str_pad($this->faker->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'status' => $this->faker->randomElement(['pending', 'processing', 'shipped', 'delivered', 'cancelled']),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'shipping_amount' => $shippingAmount,
            'discount_amount' => $discountAmount,
            'total_amount' => $totalAmount,
            'voucher_code' => $this->faker->optional()->regexify('[A-Z]{3}[0-9]{3}'),
            'voucher_discount' => $this->faker->optional()->randomFloat(2, 5, 50),
            'loyalty_points_earned' => $this->faker->numberBetween(10, 100),
            'points_redeemed' => $this->faker->optional()->numberBetween(0, 500),
            'points_redeemed_discount' => $this->faker->optional()->randomFloat(2, 0, 25),
            'shipping_address' => [
                'street' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => $this->faker->state(),
                'zip' => $this->faker->postcode(),
                'country' => $this->faker->country(),
            ],
            'shipping_city' => $this->faker->city(),
            'shipping_state' => $this->faker->state(),
            'shipping_zip' => $this->faker->postcode(),
            'shipping_country' => $this->faker->country(),
            'billing_address' => [
                'street' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => $this->faker->state(),
                'zip' => $this->faker->postcode(),
                'country' => $this->faker->country(),
            ],
            'payment_method' => $this->faker->randomElement(['credit_card', 'paypal', 'bank_transfer', 'cash_on_delivery']),
            'payment_status' => $this->faker->randomElement(['pending', 'paid', 'failed', 'refunded']),
            'notes' => $this->faker->optional()->sentence(),
            'tracking_number' => $this->faker->optional()->regexify('[A-Z]{2}[0-9]{9}[A-Z]{2}'),
        ];
    }

    /**
     * Indicate that the order is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the order is delivered.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'delivered',
        ]);
    }

    /**
     * Indicate that the order is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the payment is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_status' => 'paid',
        ]);
    }
}
