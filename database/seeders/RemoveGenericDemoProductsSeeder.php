<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Support\DemoData;
use Illuminate\Database\Seeder;

/**
 * One-off cleanup for the TEST site: soft-deletes the old generic demo products
 * (iPhone, MacBook, Nike…) that the previous seeders created under the admin
 * account. Only matches those exact SKUs/names AND that admin account
 * (admin@example.com, or the same account renamed: DemoData::genericProductsOwner()),
 * so real listings are never touched. The lists live in App\Support\DemoData
 * (shared with Admin → Sample data, which can also remove and restore them).
 *
 *   php artisan db:seed --class=RemoveGenericDemoProductsSeeder --force
 */
class RemoveGenericDemoProductsSeeder extends Seeder
{
    public const SKUS = DemoData::GENERIC_SKUS;

    public const NAMES = DemoData::GENERIC_NAMES;

    public function run(): void
    {
        $admin = DemoData::genericProductsOwner();
        if (! $admin) {
            $this->command?->info('No demo admin account (admin@example.com), nothing to remove.');

            return;
        }

        $removed = 0;
        Product::where('seller_id', $admin->id)->get()->each(function (Product $product) use (&$removed) {
            $name = $product->getTranslation('name', 'en', false);
            if (in_array($product->sku, self::SKUS, true) || in_array($name, self::NAMES, true)) {
                $product->delete();
                $removed++;
            }
        });

        $this->command?->info("✅ Removed {$removed} generic demo products");
    }
}
