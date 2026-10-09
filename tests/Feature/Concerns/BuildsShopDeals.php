<?php

namespace Tests\Feature\Concerns;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MultiBuyOffer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\ShopDiscountCode;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShopDiscountService;
use Illuminate\Support\Str;

/**
 * Shops, products, codes, offers and carts for the shop deals tests (discount codes, multi-buy).
 */
trait BuildsShopDeals
{
    protected function shop(string $name, ?float $commissionRate = null): User
    {
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $shop->forceFill(['commission_rate' => $commissionRate])->save();
        $shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $shop->bankAccount()->create(['bank' => 'bml', 'account_name' => $name, 'account_number' => '7730000'.str_pad((string) $shop->id, 6, '0', STR_PAD_LEFT)]);

        return $shop;
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    protected function productOf(User $shop, float $price, array $attributes = []): Product
    {
        return Product::factory()->create(array_merge(['seller_id' => $shop->id, 'price' => $price, 'compare_price' => null, 'stock_quantity' => 50], $attributes));
    }

    protected function codeFor(User $shop, string $code, array $attributes = []): ShopDiscountCode
    {
        $model = new ShopDiscountCode(array_merge(['code' => $code, 'type' => 'percent', 'value' => 10, 'applies_to' => 'all', 'is_active' => true], $attributes));
        $model->seller_id = $shop->id;
        $model->save();

        return $model;
    }

    /**
     * @param  array<int, array{min_qty: int, percent: float|int}>  $tiers
     */
    protected function offerFor(User $shop, array $tiers, array $products, ?string $group = null): MultiBuyOffer
    {
        $offer = new MultiBuyOffer(['name' => $group, 'tiers' => $tiers]);
        $offer->seller_id = $shop->id;
        $offer->save();
        foreach ($products as $product) {
            $product->forceFill(['multibuy_offer_id' => $offer->id])->saveQuietly();
        }

        return $offer;
    }

    /**
     * An active cart for the customer (or a guest cart when null) holding [product, quantity, variant?] lines.
     *
     * @param  array<int, array{0: Product, 1: int, 2?: int|null}>  $lines
     */
    protected function cartFor(?User $customer, array $lines): Cart
    {
        $cart = Cart::factory()->create(['user_id' => $customer?->id, 'session_id' => Str::random(40), 'status' => 'active']);
        foreach ($lines as $line) {
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $line[0]->id, 'product_variant_id' => $line[2] ?? null, 'quantity' => $line[1], 'price' => $line[0]->price]);
        }

        return $cart;
    }

    protected function shippingFields(): array
    {
        return [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml',
        ];
    }

    /**
     * Check out the customer's cart through OrderService (as checkout does) and return the result.
     */
    protected function checkout(?User $customer, ?Cart $cart = null, array $guest = []): array
    {
        return app(OrderService::class)->createOrderFromCart($customer, $this->shippingFields(), $cart, $guest);
    }

    protected function placeOrder(?User $customer, ?Cart $cart = null, array $guest = []): Order
    {
        $result = $this->checkout($customer, $cart, $guest);
        $this->assertTrue($result['success'], $result['message'] ?? '');

        return $result['order']->fresh();
    }

    /**
     * Use the shop codes as the cart's "Shop code" box keeps them: [seller_id => code].
     *
     * @param  array<int, string>  $codes
     */
    protected function useShopCodes(array $codes): void
    {
        session([ShopDiscountService::SESSION_KEY => $codes]);
    }

    protected function laari(mixed $amount): int
    {
        return (int) round(((float) $amount) * 100);
    }

    /**
     * Every amount is to the laari and the parts add up: the lines after the shops' discounts, less
     * the voucher and points, plus delivery, are the order total; each shop part's subtotal less its
     * shop_discount is its lines; the order's shop_discount is the parts'.
     */
    protected function assertOrderAddsUp(Order $order): void
    {
        $order = $order->fresh(['items.product', 'sellerOrders']);

        $lines = 0;
        $byShop = [];
        foreach ($order->items as $item) {
            $net = $this->laari($item->price) * $item->quantity - $this->laari($item->multibuy_discount) - $this->laari($item->shop_code_discount);
            $this->assertGreaterThanOrEqual(0, $net);
            $lines += $net;
            $byShop[(int) $item->product->seller_id] = ($byShop[(int) $item->product->seller_id] ?? 0) + $net;
        }

        $this->assertSame(
            $this->laari($order->total_amount),
            $lines - $this->laari($order->voucher_discount) - $this->laari($order->points_redeemed_discount) + $this->laari($order->shipping_amount),
            'lines - voucher - points + delivery = total'
        );

        $shopDiscounts = 0;
        foreach ($order->sellerOrders as $part) {
            $this->assertSame($byShop[(int) $part->seller_id] ?? 0, $this->laari($part->subtotal) - $this->laari($part->shop_discount), 'part subtotal - shop_discount = its lines');
            $this->assertSame($this->laari($part->subtotal) - $this->laari($part->shop_discount), $this->laari($part->commission_amount) + $this->laari($part->seller_earnings), 'commission + earnings = the discounted goods');
            $shopDiscounts += $this->laari($part->shop_discount);
        }
        $this->assertSame($this->laari($order->shop_discount), $shopDiscounts);
    }
}
