<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition()
    {
        return [
            'name' => $this->faker->word(),
            'name_dv' => $this->faker->word(),
            'description' => $this->faker->sentence(),
            'description_dv' => $this->faker->sentence(),
            'sku' => $this->faker->unique()->bothify('SKU-####'),
            'slug' => $this->faker->unique()->slug(),
            'category_id' => 1,
            'seller_id' => 1,
            'price' => $this->faker->randomFloat(2, 10, 1000),
            'compare_price' => null,
            'stock_quantity' => $this->faker->numberBetween(1, 100),
            'reorder_point' => 5,
            'is_active' => true,
            'is_featured' => false,
            'is_sponsored' => false,
            'sponsored_until' => null,
            'main_image' => null,
            'images' => [],
            'tags' => [],
            'brand' => $this->faker->word(),
            'model' => $this->faker->word(),
            'weight' => null,
            'dimensions' => null,
            'requires_shipping' => true,
            'is_digital' => false,
            'digital_file' => null,
            'wholesale_pricing' => [],
            'meta_title' => $this->faker->sentence(),
            'meta_description' => $this->faker->sentence(),
        ];
    }
} 