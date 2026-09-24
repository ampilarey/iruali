<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use Database\Seeders\MarketplaceDemoSeeder;
use Database\Seeders\RemoveGenericDemoProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketplace_demo_seeder_is_idempotent(): void
    {
        $this->seed(MarketplaceDemoSeeder::class);
        $this->seed(MarketplaceDemoSeeder::class);

        $this->assertSame(6, User::where('is_seller', true)->where('seller_approved', true)->count());
        $this->assertSame(18, Product::count());
        $this->assertSame('Island Crafts', Product::where('sku', 'IC-MAT-SM')->first()->seller->business_name);
    }

    public function test_cleanup_only_removes_old_demo_products_owned_by_admin(): void
    {
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $seller = User::factory()->create();

        $oldDemo = Product::factory()->create(['seller_id' => $admin->id, 'sku' => 'IPHONE15PRO']);
        $oldSample = Product::factory()->create(['seller_id' => $admin->id, 'name' => ['en' => 'Modern Coffee Table']]);
        $adminReal = Product::factory()->create(['seller_id' => $admin->id, 'sku' => 'REAL-1']);
        $sellerSameName = Product::factory()->create(['seller_id' => $seller->id, 'name' => ['en' => 'Modern Coffee Table']]);

        $this->seed(RemoveGenericDemoProductsSeeder::class);

        $this->assertSoftDeleted($oldDemo);
        $this->assertSoftDeleted($oldSample);
        $this->assertNotSoftDeleted($adminReal);
        $this->assertNotSoftDeleted($sellerSameName);
    }
}
