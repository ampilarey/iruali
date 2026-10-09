<?php

namespace Tests\Support;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
use App\Models\ShopTaxProfile;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PaymentService;

/**
 * Shops, staff and orders for the GST tests.
 */
trait GstFixtures
{
    protected function shop(string $name, ?string $tin = null, float $commission = 10): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name, 'address' => 'Majeedhee Magu', 'city' => 'Malé']);
        $user->forceFill(['commission_rate' => $commission])->save();
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $user->bankAccount()->create(['bank' => 'bml', 'account_name' => $name, 'account_number' => '7730000'.str_pad((string) $user->id, 6, '0', STR_PAD_LEFT)]);

        if ($tin) {
            ShopTaxProfile::create(['user_id' => $user->id, 'gst_registered' => true, 'tin' => $tin, 'registered_name' => $name.' Pvt Ltd', 'business_address' => 'H. Coral View, Malé']);
        }

        return $user;
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function registerIruali(float $rate = 8.0, string $tin = '1000001GST501'): void
    {
        Setting::set(['gst_registered' => 1, 'gst_tin' => $tin, 'gst_rate' => $rate]);
    }

    /**
     * Place a real order through OrderService: [[shop, price, quantity], ...]. Delivery to the islands
     * (MVR 75 unless the delivery settings say otherwise).
     *
     * @param  array<int, array{0: User|null, 1: float|int, 2?: int}>  $lines
     */
    protected function placeOrder(User $customer, array $lines, array $shipping = []): Order
    {
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        foreach ($lines as $line) {
            $product = Product::factory()->create(['seller_id' => $line[0]?->id, 'price' => $line[1], 'stock_quantity' => 50]);
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $line[2] ?? 1, 'price' => $line[1]]);
        }

        $result = app(OrderService::class)->createOrderFromCart($customer, $shipping + [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234',
            'delivery_zone' => 'islands', 'payment_method' => 'bml',
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');

        return $result['order']->fresh();
    }

    protected function pay(Order $order): Order
    {
        app(PaymentService::class)->confirm($order->fresh());

        return $order->fresh();
    }
}
