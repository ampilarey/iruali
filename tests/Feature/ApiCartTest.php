<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ApiCartTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_add_update_remove_and_clear(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 10, 'is_active' => true]);

        $response = $this->postJson('/api/v1/cart/add', ['product_id' => $product->id, 'quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.total_items', 2)
            ->assertJsonPath('data.total', 200);

        // Adding the same product again merges into one line
        $this->postJson('/api/v1/cart/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertJsonPath('data.total_items', 3)
            ->assertJsonCount(1, 'data.items');

        $itemId = $response->json('data.items.0.id');

        $this->putJson("/api/v1/cart/update/{$itemId}", ['quantity' => 5])
            ->assertOk()
            ->assertJsonPath('data.total', 500);

        $this->deleteJson("/api/v1/cart/remove/{$itemId}")
            ->assertOk()
            ->assertJsonPath('data.total_items', 0);

        $this->postJson('/api/v1/cart/add', ['product_id' => $product->id, 'quantity' => 1]);
        $this->postJson('/api/v1/cart/clear')->assertOk()->assertJsonCount(0, 'data.items');

        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.user_id', $this->user->id);
    }

    public function test_stock_is_enforced_including_quantity_already_in_cart(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 3, 'is_active' => true]);

        $this->postJson('/api/v1/cart/add', ['product_id' => $product->id, 'quantity' => 2])->assertCreated();
        $this->postJson('/api/v1/cart/add', ['product_id' => $product->id, 'quantity' => 2])
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_inactive_products_and_foreign_variants_are_rejected(): void
    {
        $inactive = Product::factory()->create(['is_active' => false]);
        $this->postJson('/api/v1/cart/add', ['product_id' => $inactive->id, 'quantity' => 1])->assertStatus(400);

        $product = Product::factory()->create(['is_active' => true]);
        $otherVariant = ProductVariant::create([
            'product_id' => Product::factory()->create()->id,
            'name' => 'Large', 'type' => 'size', 'sku' => 'V-1', 'price_adjustment' => 0, 'stock_quantity' => 5, 'is_active' => true,
        ]);

        $this->postJson('/api/v1/cart/add', [
            'product_id' => $product->id, 'quantity' => 1, 'product_variant_id' => $otherVariant->id,
        ])->assertStatus(422);
    }

    public function test_cannot_modify_another_users_cart_item(): void
    {
        $otherCart = Cart::factory()->create(['status' => 'active']);
        $item = CartItem::factory()->create(['cart_id' => $otherCart->id]);

        $this->putJson("/api/v1/cart/update/{$item->id}", ['quantity' => 1])->assertForbidden();
        $this->deleteJson("/api/v1/cart/remove/{$item->id}")->assertForbidden();
        $this->assertModelExists($item);
    }

    public function test_voucher_is_stored_on_cart_and_used_at_checkout(): void
    {
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 5, 'is_active' => true]);
        Voucher::factory()->create(['code' => 'SAVE10', 'type' => 'percent', 'amount' => 10]);

        $this->postJson('/api/v1/cart/apply-voucher', ['voucher_code' => 'SAVE10'])->assertStatus(400); // empty cart

        $this->postJson('/api/v1/cart/add', ['product_id' => $product->id, 'quantity' => 1]);

        $this->postJson('/api/v1/cart/apply-voucher', ['voucher_code' => 'NOPE'])->assertStatus(400);

        $this->postJson('/api/v1/cart/apply-voucher', ['voucher_code' => 'SAVE10'])
            ->assertOk()
            ->assertJsonPath('data.voucher_code', 'SAVE10')
            ->assertJsonPath('data.voucher_discount', 20)
            ->assertJsonPath('data.total', 180);

        // Persists across stateless API requests
        $this->getJson('/api/v1/cart')->assertJsonPath('data.voucher_code', 'SAVE10');

        $result = app(OrderService::class)->createOrderFromCart($this->user, [
            'shipping_address' => 'Somewhere', 'shipping_city' => 'Male', 'shipping_state' => 'Kaafu',
            'shipping_zip' => '20001', 'shipping_country' => 'Maldives',
        ]);

        $this->assertTrue($result['success']);
        // Goods after the 10% voucher, plus the Greater Malé delivery fee
        $this->assertEquals(25, $result['order']->shipping_amount);
        $this->assertEquals(180 + 25, $result['order']->total_amount);
        $this->assertSame('SAVE10', $result['order']->voucher_code);

        $this->postJson('/api/v1/cart/remove-voucher')->assertOk()->assertJsonPath('data.voucher_code', null);
    }
}
