<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Reference data is safe anywhere
        $this->call([PermissionSeeder::class, RoleSeeder::class, CategorySeeder::class, BannerSeeder::class, IslandSeeder::class]);

        // Demo shops, products and the default admin never go on a production database
        // (On production, create the admin with ADMIN_EMAIL and ADMIN_PASSWORD set: php artisan db:seed --class=UserSeeder)
        if (app()->isProduction()) {
            return;
        }

        $this->call([UserSeeder::class, MarketplaceDemoSeeder::class]);
    }
}
