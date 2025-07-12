<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Electronics',
                'description' => 'Latest electronic devices and gadgets',
                'slug' => 'electronics',
                'status' => 'active'
            ],
            [
                'name' => 'Clothing',
                'description' => 'Fashion and apparel for all ages',
                'slug' => 'clothing',
                'status' => 'active'
            ],
            [
                'name' => 'Home & Garden',
                'description' => 'Everything for your home and garden',
                'slug' => 'home-garden',
                'status' => 'active'
            ],
            [
                'name' => 'Sports & Outdoors',
                'description' => 'Sports equipment and outdoor gear',
                'slug' => 'sports-outdoors',
                'status' => 'active'
            ],
            [
                'name' => 'Books & Media',
                'description' => 'Books, movies, and digital media',
                'slug' => 'books-media',
                'status' => 'active'
            ],
            [
                'name' => 'Health & Beauty',
                'description' => 'Health products and beauty supplies',
                'slug' => 'health-beauty',
                'status' => 'active'
            ]
        ];

        foreach ($categories as $categoryData) {
            $existingCategory = Category::where('slug', $categoryData['slug'])->first();
            if (!$existingCategory) {
                Category::create($categoryData);
                $this->command->info("✅ Created category: {$categoryData['name']}");
            } else {
                $this->command->info("✅ Category already exists: {$categoryData['name']}");
            }
        }

        // Create subcategories
        $electronics = Category::where('slug', 'electronics')->first();
        if ($electronics) {
            $smartphones = Category::where('slug', 'smartphones')->first();
            if (!$smartphones) {
                Category::create([
                    'name' => 'Smartphones',
                    'description' => 'Latest smartphones and mobile devices',
                    'slug' => 'smartphones',
                    'parent_id' => $electronics->id,
                    'status' => 'active'
                ]);
                $this->command->info("✅ Created subcategory: Smartphones");
            }

            $laptops = Category::where('slug', 'laptops')->first();
            if (!$laptops) {
                Category::create([
                    'name' => 'Laptops',
                    'description' => 'Portable computers and accessories',
                    'slug' => 'laptops',
                    'parent_id' => $electronics->id,
                    'status' => 'active'
                ]);
                $this->command->info("✅ Created subcategory: Laptops");
            }
        }

        $clothing = Category::where('slug', 'clothing')->first();
        if ($clothing) {
            $mensClothing = Category::where('slug', 'mens-clothing')->first();
            if (!$mensClothing) {
                Category::create([
                    'name' => 'Men\'s Clothing',
                    'description' => 'Fashion for men',
                    'slug' => 'mens-clothing',
                    'parent_id' => $clothing->id,
                    'status' => 'active'
                ]);
                $this->command->info("✅ Created subcategory: Men's Clothing");
            }

            $womensClothing = Category::where('slug', 'womens-clothing')->first();
            if (!$womensClothing) {
                Category::create([
                    'name' => 'Women\'s Clothing',
                    'description' => 'Fashion for women',
                    'slug' => 'womens-clothing',
                    'parent_id' => $clothing->id,
                    'status' => 'active'
                ]);
                $this->command->info("✅ Created subcategory: Women's Clothing");
            }
        }
    }
}
