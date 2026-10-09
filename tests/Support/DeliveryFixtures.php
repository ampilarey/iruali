<?php

namespace Tests\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Island;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerDeliverySetting;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * Shops, islands, carts and checkouts for the delivery and checkout option tests.
 */
trait DeliveryFixtures
{
    protected function withRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function admin(): User
    {
        return $this->withRole('admin');
    }

    /**
     * An approved shop; with $pickup it offers pickup from that island.
     */
    protected function shop(string $name, ?Island $pickup = null, int $shipsWithinDays = 1): User
    {
        $seller = $this->withRole('seller', ['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        SellerDeliverySetting::create([
            'seller_id' => $seller->id,
            'ships_within_days' => $shipsWithinDays,
            'pickup_enabled' => $pickup !== null,
            'pickup_address' => $pickup ? 'M. '.$name.' Store, Majeedhee Magu' : null,
            'pickup_island_id' => $pickup?->id,
            'pickup_hours' => $pickup ? 'Sat to Thu, 9:00 to 21:00' : null,
        ]);

        return $seller;
    }

    protected function island(string $name, string $atoll): Island
    {
        return Island::create(['name' => ['en' => $name, 'dv' => $name], 'atoll' => $atoll, 'is_active' => true]);
    }

    protected function product(User $seller, float $price, float $surcharge = 0, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'seller_id' => $seller->id, 'price' => $price, 'stock_quantity' => 20, 'delivery_surcharge' => $surcharge,
        ], $attributes));
    }

    /**
     * @param  list<array{0: Product, 1: int}>  $lines
     */
    protected function cartFor(User $customer, array $lines): Cart
    {
        Cart::where('user_id', $customer->id)->where('status', 'active')->update(['status' => 'abandoned']);
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        foreach ($lines as [$product, $quantity]) {
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity, 'price' => $product->price]);
        }

        return $cart;
    }

    /**
     * Fields for POST /orders: a typed Greater Malé address unless overridden.
     */
    protected function orderFields(array $overrides = []): array
    {
        return array_merge([
            'shipping_address' => 'H. Blue Villa', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu',
            'shipping_zip' => '', 'shipping_country' => 'Maldives', 'shipping_phone' => '777 1234',
            'payment_method' => 'bml', 'agree_terms' => '1',
        ], $overrides);
    }

    protected function placeOrder(User $customer, array $overrides = []): TestResponse
    {
        return $this->actingAs($customer)->post('/orders', $this->orderFields($overrides));
    }
}
