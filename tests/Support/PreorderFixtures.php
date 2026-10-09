<?php

namespace Tests\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;

/**
 * Shops, pre-order products, carts and orders for the pre-order tests. The clock is 20 Oct 2026 and
 * pre-orders are expected to ship on 3 Nov 2026 unless a test says otherwise.
 */
trait PreorderFixtures
{
    protected function seller(string $name = 'Reef Goods'): User
    {
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $shop;
    }

    protected function staffMember(string $role = 'admin'): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    /**
     * A product out of stock that takes pre-orders: up to 5 units, expected to ship on 3 Nov.
     */
    protected function preorderProduct(User $shop, array $attributes = []): Product
    {
        $product = Product::factory()->create(array_merge([
            'seller_id' => $shop->id, 'category_id' => Category::factory()->create(['status' => 'active'])->id,
            'price' => 100, 'compare_price' => null, 'stock_quantity' => 0, 'is_active' => true,
        ], array_diff_key($attributes, array_flip(['preorder_enabled', 'preorder_ship_date', 'preorder_limit', 'preorder_note']))));

        $product->forceFill(array_merge([
            'preorder_enabled' => true, 'preorder_ship_date' => '2026-11-03', 'preorder_limit' => 5, 'preorder_note' => 'Arrives on the next ship from Colombo',
        ], array_intersect_key($attributes, array_flip(['preorder_enabled', 'preorder_ship_date', 'preorder_limit', 'preorder_note']))))->save();

        return $product->fresh();
    }

    protected function stockedProduct(User $shop, float $price = 50, int $stock = 10): Product
    {
        return Product::factory()->create([
            'seller_id' => $shop->id, 'price' => $price, 'compare_price' => null, 'stock_quantity' => $stock, 'is_active' => true,
        ]);
    }

    /**
     * A product sold in sizes: S and M in stock (5 each), L sold out, taking pre-orders across the sizes.
     *
     * @return array{0: Product, 1: ProductVariant, 2: ProductVariant, 3: ProductVariant}
     */
    protected function preorderVariantProduct(User $shop, int $limit = 4): array
    {
        $product = $this->preorderProduct($shop, ['has_variants' => true, 'preorder_limit' => $limit]);
        $small = ProductVariant::factory()->attributes(['Size' => 'S'])->stock(5)->create(['product_id' => $product->id]);
        $medium = ProductVariant::factory()->attributes(['Size' => 'M'])->stock(5)->create(['product_id' => $product->id]);
        $large = ProductVariant::factory()->attributes(['Size' => 'L'])->outOfStock()->create(['product_id' => $product->id]);

        return [$product->fresh(), $small, $medium, $large];
    }

    /**
     * The customer's active cart with these lines: [product, quantity] or [product, quantity, variant].
     *
     * @param  list<array{0: Product, 1: int, 2?: ProductVariant}>  $lines
     */
    protected function cartWith(User $customer, array $lines): Cart
    {
        Cart::where('user_id', $customer->id)->where('status', 'active')->update(['status' => 'abandoned']);
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        foreach ($lines as $line) {
            CartItem::factory()->create([
                'cart_id' => $cart->id, 'product_id' => $line[0]->id, 'product_variant_id' => isset($line[2]) ? $line[2]->id : null,
                'quantity' => $line[1], 'price' => $line[0]->price,
            ]);
        }

        return $cart;
    }

    /**
     * @return array<string, mixed>
     */
    protected function shippingData(): array
    {
        return [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234',
            'delivery_zone' => 'islands', 'payment_method' => 'bml',
        ];
    }

    /**
     * Place the customer's order through OrderService with these lines.
     *
     * @param  list<array{0: Product, 1: int, 2?: ProductVariant}>  $lines
     * @return array{success: bool, message: string, order?: Order}
     */
    protected function order(User $customer, array $lines): array
    {
        $cart = $this->cartWith($customer, $lines);

        return app(OrderService::class)->createOrderFromCart($customer, $this->shippingData(), $cart);
    }

    /**
     * @param  list<array{0: Product, 1: int, 2?: ProductVariant}>  $lines
     */
    protected function paidOrder(User $customer, array $lines): Order
    {
        $result = $this->order($customer, $lines);
        $this->assertTrue($result['success'], $result['message']);
        app(PaymentService::class)->confirm($result['order']->fresh());

        return $result['order']->fresh();
    }

    /**
     * Fields for POST /orders (a typed address on Addu).
     *
     * @return array<string, mixed>
     */
    protected function checkoutFields(): array
    {
        return array_merge($this->shippingData(), ['shipping_phone' => '777 1234', 'agree_terms' => '1']);
    }
}
