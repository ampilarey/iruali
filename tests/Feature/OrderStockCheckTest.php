<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStockCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_fails_if_stock_is_insufficient()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 2,
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 3, // More than available
        ]);

        $orderService = app(OrderService::class);
        $shippingData = [
            'shipping_address' => '123 Main St',
            'shipping_city' => 'City',
            'shipping_state' => 'State',
            'shipping_zip' => '12345',
            'shipping_country' => 'Country',
        ];

        $result = $orderService->createOrderFromCart($user, $shippingData);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not enough stock', $result['message']);
    }

    public function test_order_succeeds_and_decrements_stock()
    {
        $user = User::factory()->create();
        $category = Category::factory()->create();
        $seller = User::factory()->create();
        $product = Product::factory()->create([
            'stock_quantity' => 5,
            'category_id' => $category->id,
            'seller_id' => $seller->id,
        ]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create([
            'cart_id' => $cart->id,
            'product_id' => $product->id,
            'quantity' => 2,
        ]);

        $orderService = app(OrderService::class);
        $shippingData = [
            'shipping_address' => '123 Main St',
            'shipping_city' => 'City',
            'shipping_state' => 'State',
            'shipping_zip' => '12345',
            'shipping_country' => 'Country',
        ];

        $result = $orderService->createOrderFromCart($user, $shippingData);
        $this->assertTrue($result['success']);
        $product->refresh();
        $this->assertEquals(3, $product->stock_quantity); // 5 - 2 = 3
    }
}
