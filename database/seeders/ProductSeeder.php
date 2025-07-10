<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = Category::all();
        $admin = User::where('email', 'admin@example.com')->first();

        $products = [
            [
                'name' => [
                    'en' => 'iPhone 15 Pro',
                    'dv' => 'އައިފޯން 15 ޕްރޯ'
                ],
                'description' => [
                    'en' => 'The latest iPhone with advanced features and stunning camera capabilities.',
                    'dv' => 'އައިފޯން 15 ޕްރޯ އަދި އަދިކަމަށް ހުރި ފީޗަރުތައް އަދި ކެމަރާގެ ހަމައެކުން.'
                ],
                'price' => 999.99,
                'compare_price' => 1099.99,
                'sku' => 'IPHONE15PRO',
                'stock_quantity' => 50,
                'is_featured' => true,
                'is_active' => true,
                'slug' => 'iphone-15-pro'
            ],
            [
                'name' => [
                    'en' => 'MacBook Air M2',
                    'dv' => 'މެކްބޫކް އެއަރ M2'
                ],
                'description' => [
                    'en' => 'Ultra-thin laptop with powerful M2 chip for productivity and creativity.',
                    'dv' => 'M2 ޗިޕް އަދި ބައްދަލު ފަރާތްތައް އަދި ކްރިއެޓިވިޓީއަށް ހުރި އަދިކަމަށް.'
                ],
                'price' => 1199.99,
                'compare_price' => 1299.99,
                'sku' => 'MACBOOKAIRM2',
                'stock_quantity' => 30,
                'is_featured' => true,
                'is_active' => true,
                'slug' => 'macbook-air-m2'
            ],
            [
                'name' => [
                    'en' => 'Nike Air Max 270',
                    'dv' => 'އައިފޯން އެއަރ 270'
                ],
                'description' => [
                    'en' => 'Comfortable running shoes with excellent cushioning and breathable design.',
                    'dv' => 'އައިފޯން އެއަރ 270 އަދި އަދި ކަމަށް ހުރި ފީޗަރުތައް އަދި ކެމަރާގެ ހަމައެކުން.'
                ],
                'price' => 129.99,
                'sale_price' => 99.99,
                'sku' => 'NIKEAIRMAX270',
                'stock_quantity' => 100,
                'is_featured' => false,
                'is_active' => true,
                'slug' => 'nike-air-max-270'
            ],
            [
                'name' => [
                    'en' => 'Samsung 4K Smart TV',
                    'dv' => 'އައިފޯން 4K އެއަރ ގޮތް'
                ],
                'description' => [
                    'en' => '55-inch 4K Ultra HD Smart TV with Crystal Display and Alexa Built-in.',
                    'dv' => '55-inch 4K Ultra HD Smart TV with Crystal Display and Alexa Built-in.'
                ],
                'price' => 699.99,
                'sku' => 'SAMSUNG4KTV',
                'stock_quantity' => 25,
                'is_featured' => true,
                'is_active' => true,
                'slug' => 'samsung-4k-smart-tv'
            ],
            [
                'name' => [
                    'en' => 'Wireless Bluetooth Headphones',
                    'dv' => 'އައިފޯން އެއަރ ގޮތް އެއަރ ގޮތް'
                ],
                'description' => [
                    'en' => 'Premium wireless headphones with noise cancellation and 30-hour battery life.',
                    'dv' => 'Premium wireless headphones with noise cancellation and 30-hour battery life.'
                ],
                'price' => 199.99,
                'sale_price' => 149.99,
                'sku' => 'WIRELESSHP',
                'stock_quantity' => 75,
                'is_featured' => false,
                'is_active' => true,
                'slug' => 'wireless-bluetooth-headphones'
            ],
            [
                'name' => [
                    'en' => 'Coffee Maker',
                    'dv' => 'އައިފޯން އެއަރ ގޮތް'
                ],
                'description' => [
                    'en' => 'Programmable coffee maker with 12-cup capacity and auto-shutoff feature.',
                    'dv' => 'Programmable coffee maker with 12-cup capacity and auto-shutoff feature.'
                ],
                'price' => 89.99,
                'sku' => 'COFFEEMAKER',
                'stock_quantity' => 40,
                'is_featured' => false,
                'is_active' => true,
                'slug' => 'coffee-maker'
            ],
            [
                'name' => [
                    'en' => 'Yoga Mat',
                    'dv' => 'އައިފޯން އެއަރ ގޮތް'
                ],
                'description' => [
                    'en' => 'Non-slip yoga mat made from eco-friendly materials, perfect for home workouts.',
                    'dv' => 'Non-slip yoga mat made from eco-friendly materials, perfect for home workouts.'
                ],
                'price' => 29.99,
                'sku' => 'YOGAMAT',
                'stock_quantity' => 200,
                'is_featured' => false,
                'is_active' => true,
                'slug' => 'yoga-mat'
            ],
            [
                'name' => [
                    'en' => 'Wireless Charger',
                    'dv' => 'އައިފޯން އެއަރ ގޮތް'
                ],
                'description' => [
                    'en' => 'Fast wireless charging pad compatible with all Qi-enabled devices.',
                    'dv' => 'Fast wireless charging pad compatible with all Qi-enabled devices.'
                ],
                'price' => 49.99,
                'sale_price' => 39.99,
                'sku' => 'WIRELESSCHARGER',
                'stock_quantity' => 60,
                'is_featured' => false,
                'is_active' => true,
                'slug' => 'wireless-charger'
            ]
        ];

        foreach ($products as $productData) {
            // Assign random category
            $category = $categories->random();
            
            Product::create(array_merge($productData, [
                'category_id' => $category->id,
                'seller_id' => $admin->id,
            ]));
        }
    }
} 