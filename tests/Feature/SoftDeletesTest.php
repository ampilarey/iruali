<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_soft_delete()
    {
        // Create a category first
        $category = Category::factory()->create();
        
        // Create a product
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => User::factory()->create()->id,
        ]);

        $productId = $product->id;

        // Verify product exists
        $this->assertDatabaseHas('products', ['id' => $productId]);

        // Soft delete the product
        $product->delete();

        // Verify product is soft deleted (not in normal queries)
        $this->assertNull(Product::find($productId));
        
        // Verify product still exists in database with deleted_at timestamp
        $this->assertDatabaseHas('products', [
            'id' => $productId,
            'deleted_at' => $product->fresh()->deleted_at
        ]);

        // Verify trashed() method works
        $this->assertTrue($product->fresh()->trashed());
        $this->assertTrue($product->fresh()->isTrashed());

        // Verify product is not found in normal queries
        $this->assertNull(Product::find($productId));
    }

    public function test_product_restore()
    {
        // Create a category first
        $category = Category::factory()->create();
        
        // Create a product
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => User::factory()->create()->id,
        ]);

        // Soft delete the product
        $product->delete();

        // Verify product is soft deleted
        $this->assertTrue($product->fresh()->trashed());

        // Restore the product
        $product->restoreProduct();

        // Verify product is restored
        $this->assertFalse($product->fresh()->trashed());
        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertNotNull(Product::find($product->id));
    }

    public function test_product_force_delete()
    {
        // Create a category first
        $category = Category::factory()->create();
        
        // Create a product
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => User::factory()->create()->id,
        ]);

        $productId = $product->id;

        // Force delete the product
        $product->forceDeleteProduct();

        // Verify product is permanently deleted
        $this->assertDatabaseMissing('products', ['id' => $productId]);
        $this->assertNull(Product::withTrashed()->find($productId));
    }

    public function test_order_soft_delete()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create an order
        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        $orderId = $order->id;

        // Verify order exists
        $this->assertDatabaseHas('orders', ['id' => $orderId]);

        // Soft delete the order
        $order->delete();

        // Verify order is soft deleted
        $this->assertNull(Order::find($orderId));
        $this->assertTrue($order->fresh()->trashed());
        $this->assertTrue($order->fresh()->isTrashed());
    }

    public function test_order_restore()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create an order
        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        // Soft delete the order
        $order->delete();

        // Restore the order
        $order->restoreOrder();

        // Verify order is restored
        $this->assertFalse($order->fresh()->trashed());
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
    }

    public function test_user_soft_delete()
    {
        // Create a user
        $user = User::factory()->create();
        $userId = $user->id;

        // Verify user exists
        $this->assertDatabaseHas('users', ['id' => $userId]);

        // Soft delete the user
        $user->delete();

        // Verify user is soft deleted
        $this->assertTrue($user->fresh()->trashed());
        $this->assertTrue($user->fresh()->isTrashed());
        $this->assertNull(User::find($userId));
    }

    public function test_user_restore()
    {
        // Create a user
        $user = User::factory()->create();

        // Soft delete the user
        $user->delete();

        // Restore the user
        $user->restoreUser();

        // Verify user is restored
        $this->assertFalse($user->fresh()->trashed());
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_scopes_with_trashed()
    {
        // Create a category first
        $category = Category::factory()->create();
        $user = User::factory()->create();
        
        // Create products
        $activeProduct = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $user->id,
        ]);
        
        $deletedProduct = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $user->id,
        ]);

        // Soft delete one product
        $deletedProduct->delete();

        // Test withTrashed scope
        $allProducts = Product::withTrashed()->get();
        $this->assertEquals(2, $allProducts->count());

        // Test onlyTrashed scope
        $trashedProducts = Product::onlyTrashed()->get();
        $this->assertEquals(1, $trashedProducts->count());
        $this->assertEquals($deletedProduct->id, $trashedProducts->first()->id);
    }

    public function test_soft_deletes_with_relationships()
    {
        // Create a user
        $user = User::factory()->create();
        
        // Create an order for the user
        $order = Order::factory()->create([
            'user_id' => $user->id,
        ]);

        // Soft delete the user
        $user->delete();

        // Verify user is soft deleted
        $this->assertTrue($user->fresh()->trashed());

        // Verify order still exists (soft deletes don't cascade by default)
        $this->assertDatabaseHas('orders', ['id' => $order->id]);
        $this->assertNotNull(Order::find($order->id));
    }
}
