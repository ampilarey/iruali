<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WishlistDuplicateTest extends TestCase
{
    use RefreshDatabase;

    public function test_cannot_add_duplicate_product_to_wishlist()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        // Add product to wishlist first time
        $result1 = Wishlist::addToWishlist($user->id, $product->id);
        $this->assertTrue($result1['success']);

        // Try to add the same product again
        $result2 = Wishlist::addToWishlist($user->id, $product->id);
        $this->assertFalse($result2['success']);
        $this->assertStringContainsString('already in your wishlist', $result2['message']);

        // Verify only one record exists
        $this->assertEquals(1, Wishlist::where('user_id', $user->id)->count());
    }

    public function test_database_unique_constraint_prevents_duplicates()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        // Create first wishlist item
        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);

        // Try to create duplicate directly (should fail)
        $this->expectException(\Illuminate\Database\QueryException::class);

        Wishlist::create([
            'user_id' => $user->id,
            'product_id' => $product->id,
        ]);
    }

    public function test_is_in_wishlist_method_works_correctly()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        // Initially not in wishlist
        $this->assertFalse(Wishlist::isInWishlist($user->id, $product->id));

        // Add to wishlist
        Wishlist::addToWishlist($user->id, $product->id);

        // Now should be in wishlist
        $this->assertTrue(Wishlist::isInWishlist($user->id, $product->id));
    }

    public function test_different_users_can_add_same_product()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        // Both users can add the same product
        $result1 = Wishlist::addToWishlist($user1->id, $product->id);
        $result2 = Wishlist::addToWishlist($user2->id, $product->id);

        $this->assertTrue($result1['success']);
        $this->assertTrue($result2['success']);

        // Verify both records exist
        $this->assertEquals(2, Wishlist::count());
        $this->assertTrue(Wishlist::isInWishlist($user1->id, $product->id));
        $this->assertTrue(Wishlist::isInWishlist($user2->id, $product->id));
    }

    public function test_remove_from_wishlist_method_works()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);

        // Add to wishlist
        Wishlist::addToWishlist($user->id, $product->id);
        $this->assertTrue(Wishlist::isInWishlist($user->id, $product->id));

        // Remove from wishlist
        $removed = Wishlist::removeFromWishlist($user->id, $product->id);
        $this->assertTrue($removed);
        $this->assertFalse(Wishlist::isInWishlist($user->id, $product->id));
    }

    public function test_only_products_on_sale_can_be_saved_or_shown(): void
    {
        $customer = User::factory()->create();
        // Another customer's private item request, or a listing waiting for approval: inactive
        $hidden = Product::factory()->create(['name' => ['en' => 'Private request: gold ring for Aminath'], 'is_active' => false, 'price' => 4321]);

        $this->actingAs($customer)->post('/wishlist/add/'.$hidden->id, ['product_id' => $hidden->id]);
        $this->get('/wishlist')->assertOk()->assertDontSee('gold ring for Aminath')->assertDontSee('4,321');
        $this->assertSame(0, Wishlist::count());

        \Laravel\Sanctum\Sanctum::actingAs($customer, ['*']);
        $this->postJson('/api/v1/wishlist/add', ['product_id' => $hidden->id])->assertStatus(400);
        $this->assertSame(0, Wishlist::count());

        // Saved while on sale, then taken off sale: no longer shown
        $live = Product::factory()->create(['name' => ['en' => 'Reed mat'], 'is_active' => true]);
        $this->postJson('/api/v1/wishlist/add', ['product_id' => $live->id])->assertOk();
        $live->update(['is_active' => false]);
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('data.total_items', 0);
    }
}
