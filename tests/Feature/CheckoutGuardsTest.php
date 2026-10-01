<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerAdjustment;
use App\Models\User;
use App\Models\Voucher;
use App\Services\CartService;
use App\Services\OrderService;
use App\Services\PayoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Checkout re-checks what the session claims (vouchers, points, stock), deleted products leave carts,
 * closed shops stop selling, and uploads are served by the app.
 */
class CheckoutGuardsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableBml();
        $this->customer = User::factory()->create(['loyalty_points' => 0]);
    }

    protected function cartWith(Product $product, int $qty = 1): Cart
    {
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qty, 'price' => $product->price]);

        return $cart;
    }

    protected function checkout(): array
    {
        return app(OrderService::class)->createOrderFromCart($this->customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'payment_method' => 'bml',
        ]);
    }

    public function test_an_expired_or_used_up_voucher_is_dropped_at_checkout(): void
    {
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 5]);
        $this->cartWith($product);
        Voucher::factory()->create(['code' => 'SAVE50', 'type' => 'fixed', 'amount' => 50, 'valid_until' => now()->addDay(), 'max_uses' => 1, 'used_count' => 0]);
        Session::put('voucher_code', 'SAVE50');

        $this->travel(2)->days(); // it expired while the customer was thinking

        $result = $this->checkout();
        $this->assertTrue($result['success'], $result['message']);
        $this->assertNull($result['order']->voucher_code);
        $this->assertEquals(200, $result['order']->total_amount - $result['order']->shipping_amount);
        $this->assertSame(0, Voucher::where('code', 'SAVE50')->value('used_count'));
    }

    public function test_the_last_use_of_a_voucher_cannot_be_taken_twice(): void
    {
        $product = Product::factory()->create(['price' => 200, 'stock_quantity' => 5]);
        Voucher::factory()->create(['code' => 'LAST1', 'type' => 'fixed', 'amount' => 10, 'max_uses' => 1, 'used_count' => 0]);

        $this->cartWith($product);
        Session::put('voucher_code', 'LAST1');
        $this->assertTrue($this->checkout()['success']);
        $this->assertSame(1, Voucher::where('code', 'LAST1')->value('used_count'));

        $this->cartWith($product);
        Session::put('voucher_code', 'LAST1');
        $second = $this->checkout();
        $this->assertTrue($second['success']);
        $this->assertNull($second['order']->voucher_code, 'the used-up voucher is dropped, the order still goes through');
        $this->assertSame(1, Voucher::where('code', 'LAST1')->value('used_count'));
    }

    public function test_redeemed_points_are_capped_by_the_balance_and_the_cart_at_checkout(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
        $this->cartWith($product);
        $this->customer->forceFill(['loyalty_points' => 500])->save();
        Session::put('points_redeemed', 500); // redeemed against a bigger cart earlier

        $result = $this->checkout();
        $this->assertTrue($result['success'], $result['message']);
        $this->assertSame(100, (int) $result['order']->points_redeemed, 'only as many points as the goods are worth');
        $this->assertSame(400, $this->customer->fresh()->loyalty_points);
    }

    public function test_stock_is_taken_atomically_so_the_last_unit_sells_once(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 1]);
        $this->cartWith($product);

        $this->assertTrue($this->checkout()['success']);
        $this->assertSame(0, $product->fresh()->stock_quantity);

        // A second cart for the same product: the conditional decrement refuses and nothing goes negative
        $cart = $this->cartWith($product);
        $product->forceFill(['stock_quantity' => 1])->save();
        CartItem::where('cart_id', $cart->id)->update(['quantity' => 2]);
        $product->forceFill(['stock_quantity' => 2])->save();
        $product2 = Product::find($product->id);
        $product2->forceFill(['stock_quantity' => 1])->saveQuietly(); // the pre-check saw 2, the database has 1
        $result = $this->checkout();
        $this->assertFalse($result['success']);
        $this->assertGreaterThanOrEqual(0, $product->fresh()->stock_quantity);
    }

    public function test_a_deleted_product_leaves_carts_and_the_cart_page_still_works(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 5]);
        $cart = $this->cartWith($product);

        $product->delete();

        $this->assertSame(0, CartItem::where('cart_id', $cart->id)->count());
        $this->actingAs($this->customer)->get(route('cart'))->assertOk();
    }

    public function test_guest_cart_merge_is_capped_by_stock(): void
    {
        $product = Product::factory()->create(['price' => 100, 'stock_quantity' => 6]);
        $mine = $this->cartWith($product, 5);
        $guest = Cart::factory()->create(['user_id' => null, 'session_id' => 'guest-token', 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $guest->id, 'product_id' => $product->id, 'quantity' => 5, 'price' => 100]);
        Session::put('cart_token', 'guest-token');

        app(CartService::class)->mergeGuestCart($this->customer);

        $this->assertSame(6, CartItem::where('cart_id', $mine->id)->value('quantity'));
    }

    public function test_a_suspended_shop_cannot_sell_or_use_the_seller_area(): void
    {
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Closed Shop']);
        $shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 100, 'stock_quantity' => 5]);
        $this->actingAs($shop)->get(route('seller.dashboard'))->assertOk();

        $shop->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($shop->fresh())->get(route('seller.dashboard'))->assertForbidden();
        $this->cartWith($product);
        $result = $this->checkout();
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no longer available', $result['message']);
    }

    public function test_return_adjustment_follows_the_refund_actually_approved(): void
    {
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $shop->forceFill(['commission_rate' => 10])->save();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 200, 'stock_quantity' => 5]);
        $this->cartWith($product);
        $order = $this->checkout()['order'];
        $this->actingAs($admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }
        app(\App\Services\PaymentService::class)->confirm($order->fresh());
        $part = $order->sellerOrders()->firstOrFail();
        $this->actingAs($this->customer)->post(route('orders.returns.store', [$order, $part]), ['quantities' => [$part->items()->first()->id => 1], 'reason' => 'change_of_mind']);

        // Only half refunded (the customer keeps part of the value): the shop gives back its share of that half
        $this->actingAs($admin)->post(route('admin.returns.approve', ReturnRequest::sole()), ['refund_amount' => 100, 'restock' => 1]);
        $this->assertEquals(-90, SellerAdjustment::sole()->amount);
        $this->assertEquals(180 - 90, app(PayoutService::class)->balances($shop)['available']);
        $this->assertEquals(app(PayoutService::class)->balances($shop), app(PayoutService::class)->balancesForAll()[$shop->id]);
    }

    public function test_uploads_are_served_by_the_app_with_caching(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('products/photo.jpg', 'jpegbytes');

        $this->get('/storage/products/photo.jpg')->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=604800, public');
        $this->get('/storage/products/missing.jpg')->assertNotFound();
        $this->get('/storage/../.env')->assertNotFound();
    }
}
