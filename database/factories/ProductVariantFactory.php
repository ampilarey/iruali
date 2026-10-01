<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    public function definition(): array
    {
        $size = $this->faker->randomElement(['S', 'M', 'L', 'XL']);

        return [
            'product_id' => Product::factory()->state(['has_variants' => true]),
            'name' => ['en' => $size],
            'type' => 'Size',
            'attributes' => ['Size' => $size],
            'sku' => $this->faker->unique()->bothify('VAR-#####'),
            'price' => null,
            'price_adjustment' => 0,
            'stock_quantity' => $this->faker->numberBetween(1, 50),
            'low_stock_threshold' => null,
            'image' => null,
            'is_active' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * A combination such as ['Size' => 'M', 'Colour' => 'Blue'].
     */
    public function attributes(array $attributes): static
    {
        return $this->state([
            'attributes' => $attributes,
            'type' => implode('/', array_keys($attributes)),
            'name' => ['en' => implode(' / ', array_values($attributes))],
        ]);
    }

    public function priced(float $price): static
    {
        return $this->state(['price' => $price]);
    }

    public function stock(int $quantity): static
    {
        return $this->state(['stock_quantity' => $quantity]);
    }

    public function outOfStock(): static
    {
        return $this->state(['stock_quantity' => 0]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
