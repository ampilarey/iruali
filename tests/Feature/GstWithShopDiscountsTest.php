<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\MultiBuyOffer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\ShopDiscountCode;
use App\Models\User;
use App\Services\OrderService;
use App\Services\ShopDiscountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GstFixtures;
use Tests\TestCase;

/**
 * A GST-registered shop that gives a discount (its own code or a multi-buy offer) charges GST on
 * what the customer actually paid it: its goods total after its own discount.
 */
class GstWithShopDiscountsTest extends TestCase
{
    use GstFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 81, 'default_commission_rate' => 10, 'gst_rate' => 8]);
    }

    /**
     * @param  array<int, array{0: Product, 1: int}>  $lines
     */
    protected function orderFor(User $customer, array $lines): Order
    {
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        foreach ($lines as [$product, $quantity]) {
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity, 'price' => $product->price]);
        }

        $result = app(OrderService::class)->createOrderFromCart($customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234',
            'delivery_zone' => 'islands', 'payment_method' => 'bml',
        ], $cart);
        $this->assertTrue($result['success'], $result['message'] ?? '');

        return $result['order']->fresh();
    }

    public function test_gst_is_charged_on_the_goods_total_after_the_shops_own_code(): void
    {
        $this->enableBml();
        $customer = User::factory()->create();
        $shop = $this->shop('Reef Goods', '1012345GST501');
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 1080, 'compare_price' => null, 'stock_quantity' => 10]);
        $code = new ShopDiscountCode(['code' => 'TENOFF', 'type' => 'percent', 'value' => 10, 'applies_to' => 'all', 'is_active' => true]);
        $code->seller_id = $shop->id;
        $code->save();
        session([ShopDiscountService::SESSION_KEY => [$shop->id => 'TENOFF']]);

        $order = $this->pay($this->orderFor($customer, [[$product, 1]]));
        $part = $order->sellerOrders()->sole();

        $this->assertEquals(1080, $part->subtotal);
        $this->assertEquals(108, $part->shop_discount);
        $this->assertEquals(972, $part->gst_taxable, 'GST is on what the customer paid the shop');
        $this->assertEquals(72, $part->gst_amount, '972 x 8/108');
        $this->assertEquals(97.2, $part->commission_amount, 'commission is on the discounted total too');
        $this->assertNotNull($part->invoice_number);

        $this->actingAs($customer)->get(route('orders.invoice', [$order, $part]))->assertOk()
            ->assertSee('Shop discount')->assertSee('MVR 72.00');
    }

    public function test_gst_is_charged_on_the_goods_total_after_a_multi_buy_offer(): void
    {
        $this->enableBml();
        $customer = User::factory()->create();
        $shop = $this->shop('Island Crafts', '1012346GST501');
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 540, 'compare_price' => null, 'stock_quantity' => 10]);
        $offer = new MultiBuyOffer(['tiers' => [['min_qty' => 2, 'percent' => 5]]]);
        $offer->seller_id = $shop->id;
        $offer->save();
        $product->forceFill(['multibuy_offer_id' => $offer->id])->save();

        $order = $this->pay($this->orderFor($customer, [[$product->fresh(), 2]]));
        $part = $order->sellerOrders()->sole();

        $this->assertEquals(1080, $part->subtotal);
        $this->assertEquals(54, $part->shop_discount, '5% off two');
        $this->assertEquals(1026, $part->gst_taxable);
        $this->assertEquals(76, $part->gst_amount, '1026 x 8/108');
    }
}
