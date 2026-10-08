<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo marketplace: six approved local sellers and their products.
 * Idempotent (keyed on seller email and product SKU), so it is safe to re-run.
 *
 *   php artisan db:seed --class=MarketplaceDemoSeeder --force
 */
class MarketplaceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $sellerRole = Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller']);
        $categories = Category::whereIn('slug', [
            'food-groceries', 'handmade-crafts', 'fashion', 'home-living',
            'electronics', 'beauty-wellness', 'fishing-marine', 'kids-baby',
        ])->pluck('id', 'slug');

        if ($categories->count() < 8) {
            $this->call(CategorySeeder::class);
            $categories = Category::pluck('id', 'slug');
        }

        foreach ($this->sellers() as $sellerData) {
            $seller = User::updateOrCreate(
                ['email' => $sellerData['email']],
                [
                    'name' => $sellerData['owner'],
                    'business_name' => $sellerData['shop'],
                    'business_description' => $sellerData['about'],
                    'city' => $sellerData['island'],
                    'state' => $sellerData['atoll'],
                    'country' => 'Maldives',
                    'phone' => $sellerData['phone'],
                    'password' => Hash::make('password'),
                    'status' => 'active',
                    'is_active' => true,
                    'email_verified' => true,
                    'phone_verified' => true,
                    'is_seller' => true,
                    'seller_approved' => true,
                    'seller_approved_at' => now(),
                    'seller_applied_at' => now()->subWeeks(3),
                    'onboarding_completed_at' => now(),
                ]
            );
            $seller->roles()->syncWithoutDetaching([$sellerRole->id]);

            foreach ($sellerData['products'] as $p) {
                Product::updateOrCreate(
                    ['sku' => $p['sku']],
                    [
                        'name' => ['en' => $p['en'], 'dv' => $p['dv']],
                        'description' => ['en' => $p['desc']],
                        'category_id' => $categories[$p['cat']],
                        'seller_id' => $seller->id,
                        'price' => $p['price'],
                        'compare_price' => $p['was'] ?? null,
                        'stock_quantity' => $p['stock'] ?? 25,
                        'reorder_point' => 5,
                        'is_active' => true,
                        'is_featured' => $p['featured'] ?? false,
                        // Each demo shop sells its own label, so the brand pages have something to show
                        'brand' => $p['brand'] ?? $sellerData['brand'] ?? null,
                    ]
                );
            }
        }

        $this->command?->info('✅ Demo marketplace: '.count($this->sellers()).' sellers and their products');
    }

    /**
     * The demo shops and their products, from App\Support\DemoData (shared with Admin → Sample data).
     */
    protected function sellers(): array
    {
        return DemoData::SHOPS;
    }
}
