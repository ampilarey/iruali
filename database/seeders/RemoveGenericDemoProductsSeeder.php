<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * One-off cleanup for the TEST site: soft-deletes the old generic demo products
 * (iPhone, MacBook, Nike…) that the previous seeders created under the admin
 * account. Only matches those exact SKUs/names AND the admin@example.com seller,
 * so real listings are never touched.
 *
 *   php artisan db:seed --class=RemoveGenericDemoProductsSeeder --force
 */
class RemoveGenericDemoProductsSeeder extends Seeder
{
    public const SKUS = [
        'IPHONE15PRO', 'MACBOOKAIRM2', 'NIKEAIRMAX270', 'SAMSUNG4KTV',
        'WIRELESSHP', 'COFFEEMAKER', 'YOGAMAT', 'WIRELESSCHARGER',
    ];

    public const NAMES = [
        'Wireless Bluetooth Headphones', 'Smart LED TV 55"', 'Portable Bluetooth Speaker', 'Classic Denim Jacket',
        'Premium Cotton T-Shirt', 'Modern Coffee Table', 'Indoor Plant Set', 'Professional Yoga Mat',
        'Hiking Backpack 30L', 'Bestselling Novel Collection', 'Wireless Gaming Mouse', 'Organic Face Cream',
        'Electric Toothbrush Set', 'Latest Smartphone Pro', 'Wireless Charging Pad', 'Ultrabook Laptop 14"',
        'Laptop Stand & Cooling Pad', 'Formal Business Suit', 'Casual Polo Shirt', 'Elegant Evening Dress',
        'Comfortable Leggings', 'Sample Product',
    ];

    public function run(): void
    {
        $adminId = User::where('email', 'admin@example.com')->value('id');
        if (! $adminId) {
            $this->command?->info('No admin@example.com user, nothing to remove.');

            return;
        }

        $removed = 0;
        Product::where('seller_id', $adminId)->get()->each(function (Product $product) use (&$removed) {
            $name = $product->getTranslation('name', 'en', false);
            if (in_array($product->sku, self::SKUS, true) || in_array($name, self::NAMES, true)) {
                $product->delete();
                $removed++;
            }
        });

        $this->command?->info("✅ Removed {$removed} generic demo products");
    }
}
