<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\ShopTaxProfile;
use App\Models\User;
use App\Services\FulfilmentService;
use App\Services\GstService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GstFixtures;
use Tests\TestCase;

/**
 * GST is frozen on each order when it is placed: later changes to a shop's details, iruali's
 * registration or the rate never alter an order already placed. With nobody registered, nothing
 * a shopper sees changes.
 */
class GstSnapshotTest extends TestCase
{
    use GstFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 81, 'default_commission_rate' => 10]);
    }

    public function test_nothing_changes_for_shoppers_when_nobody_is_registered(): void
    {
        $this->enableBml();
        $customer = User::factory()->create();
        $shop = $this->shop('Island Crafts');

        // Checkout looks as it always did: no GST anywhere, the same totals
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        $product = Product::factory()->create(['seller_id' => $shop->id, 'price' => 225, 'stock_quantity' => 10]);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 2, 'price' => 225]);
        $this->actingAs($customer)->get(route('checkout'))->assertOk()->assertSee('MVR 531.00')
            ->assertDontSee('incl. GST')->assertDontSee('GST included')->assertDontSee('Tax invoice');

        $this->actingAs($customer)->post(route('orders.store'), [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu', 'shipping_zip' => '',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'delivery_zone' => 'islands', 'payment_method' => 'bml', 'agree_terms' => '1',
        ])->assertRedirect('https://pay.bml.test/txn_test');

        $order = $customer->orders()->sole();
        $this->assertEquals(531, $order->total_amount);
        $this->assertEquals(81, $order->shipping_amount);
        $this->assertFalse($order->gst_platform_registered);
        $this->assertEquals(0, $order->delivery_gst);
        $this->assertNull($order->gst_rate);
        $this->assertNotNull($order->gst_captured_at);
        $this->assertNull($order->buyer_business_name);

        $part = $order->sellerOrders()->sole();
        $this->assertFalse($part->gst_registered);
        $this->assertNull($part->gst_tin);
        $this->assertNull($part->gst_rate);
        $this->assertEquals(450, $part->gst_taxable);
        $this->assertEquals(0, $part->gst_amount);
        $this->assertEquals(0, $part->commission_gst);
        $this->assertEquals(45, $part->commission_amount);
        $this->assertEquals(405, $part->seller_earnings);
        $this->assertNull($part->invoice_number);

        $order = $this->pay($order);
        $part->refresh();
        $this->assertNotNull($part->invoice_number);

        // iruali's receipt shows no GST; the shop's document is a plain receipt
        $this->actingAs($customer)->get(route('orders.receipt', $order))->assertOk()->assertDontSee('GST')->assertSee('MVR 531.00');
        $this->actingAs($customer)->get(route('orders.invoice', [$order, $part]))->assertOk()
            ->assertSee('data-invoice="receipt"', false)->assertSee('Receipt no.')->assertDontSee('GST')->assertSee('MVR 450.00');
        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()->assertDontSee('GST')->assertSee('Receipt · Island Crafts');
    }

    public function test_the_gst_is_frozen_when_the_order_is_placed(): void
    {
        $this->registerIruali(8);
        $customer = User::factory()->create();
        $crafts = $this->shop('Island Crafts', '1012345GST501');
        $reef = $this->shop('Reefline Marine');

        $order = $this->placeOrder($customer, [[$crafts, 216], [$reef, 100]]);

        $this->assertTrue($order->gst_platform_registered);
        $this->assertSame('1000001GST501', $order->gst_platform_tin);
        $this->assertEquals(8, $order->gst_rate);
        $this->assertEquals(6.00, $order->delivery_gst);    // 81 × 8 ÷ 108
        $this->assertEquals(397, $order->total_amount);      // prices include GST: totals unchanged

        $a = $order->sellerOrders()->where('seller_id', $crafts->id)->sole();
        $this->assertTrue($a->gst_registered);
        $this->assertSame('1012345GST501', $a->gst_tin);
        $this->assertSame('Island Crafts Pvt Ltd', $a->gst_business_name);
        $this->assertSame('H. Coral View, Malé', $a->gst_business_address);
        $this->assertEquals(8, $a->gst_rate);
        $this->assertEquals(216, $a->gst_taxable);
        $this->assertEquals(16.00, $a->gst_amount);
        $this->assertEquals(21.60, $a->commission_amount);
        $this->assertEquals(1.60, $a->commission_gst);       // iruali's commission includes GST
        $this->assertEquals(194.40, $a->seller_earnings);    // earnings unchanged

        $b = $order->sellerOrders()->where('seller_id', $reef->id)->sole();
        $this->assertFalse($b->gst_registered);
        $this->assertNull($b->gst_tin);
        $this->assertSame('Reefline Marine', $b->gst_business_name);
        $this->assertEquals(0, $b->gst_amount);
        $this->assertEquals(0.74, $b->commission_gst);

        // Everything changes afterwards...
        ShopTaxProfile::where('user_id', $crafts->id)->update(['gst_registered' => false, 'tin' => '7654321GST999', 'registered_name' => 'Renamed Ltd', 'business_address' => 'Elsewhere']);
        ShopTaxProfile::create(['user_id' => $reef->id, 'gst_registered' => true, 'tin' => '2000002GST502', 'registered_name' => 'Reefline Pvt Ltd', 'business_address' => 'Hulhumalé']);
        Setting::set(['gst_registered' => 0, 'gst_tin' => '', 'gst_rate' => 10]);

        // ...and the parts are refreshed (as FulfilmentService does when items change): the snapshot holds
        app(FulfilmentService::class)->createParts($order);
        $this->assertSnapshotUnchanged($a, $b, $order->fresh());

        // Paying freezes it for good, and the invoice prints what was true when the order was placed
        $order = $this->pay($order);
        $this->assertSnapshotUnchanged($a, $b, $order);
        $this->actingAs($customer)->get(route('orders.invoice', [$order, $a->fresh()]))->assertOk()
            ->assertSee('Island Crafts Pvt Ltd')->assertSee('1012345GST501')->assertDontSee('7654321GST999')
            ->assertSee('8%')->assertSee('MVR 16.00')->assertSee('MVR 200.00'); // value before GST
        $this->actingAs($customer)->get(route('orders.invoice', [$order, $b->fresh()]))->assertOk()
            ->assertSee('data-invoice="receipt"', false)->assertDontSee('2000002GST502');

        // A new order uses the new details
        $next = $this->placeOrder($customer, [[$crafts, 216], [$reef, 110]]);
        $this->assertFalse($next->gst_platform_registered);
        $this->assertEquals(0, $next->delivery_gst);
        $this->assertFalse($next->sellerOrders()->where('seller_id', $crafts->id)->sole()->gst_registered);
        $newReef = $next->sellerOrders()->where('seller_id', $reef->id)->sole();
        $this->assertTrue($newReef->gst_registered);
        $this->assertEquals(10, $newReef->gst_rate);
        $this->assertEquals(10.00, $newReef->gst_amount); // 110 × 10 ÷ 110
        $this->assertEquals(0, $newReef->commission_gst);
    }

    public function test_amounts_follow_the_goods_until_the_order_is_paid_then_never_change(): void
    {
        $customer = User::factory()->create();
        $crafts = $this->shop('Island Crafts', '1012345GST501');
        $order = $this->placeOrder($customer, [[$crafts, 108]]);
        $part = $order->sellerOrders()->sole();
        $this->assertEquals(8.00, $part->gst_amount);

        // The rate changes, then another item joins the part: the amount follows, at the frozen rate
        Setting::set(['gst_rate' => 12]);
        $order->items()->create(['product_id' => Product::factory()->create(['seller_id' => $crafts->id, 'price' => 54])->id, 'quantity' => 1, 'price' => 54]);
        $part->refresh();
        $this->assertEquals(162, $part->gst_taxable);
        $this->assertEquals(8, $part->gst_rate);
        $this->assertEquals(12.00, $part->gst_amount);

        $this->pay($order);
        $part->refresh();
        $invoice = $part->invoice_number;
        $this->assertNotNull($invoice);

        app(GstService::class)->capturePart($part->fresh());
        app(FulfilmentService::class)->createParts($order->fresh());
        $this->assertEquals(12.00, $part->fresh()->gst_amount);
        $this->assertSame($invoice, $part->fresh()->invoice_number);
    }

    public function test_a_part_without_a_shop_is_iruali_own_sale_with_iruali_registration(): void
    {
        $this->registerIruali(8);
        Setting::set(['company_legal_name' => 'Iruali Pvt Ltd', 'company_address' => 'H. Example, Malé']);
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'unpaid', 'shipping_amount' => 0, 'total_amount' => 54]);
        $part = SellerOrder::create(['order_id' => $order->id, 'seller_id' => null, 'status' => 'pending', 'subtotal' => 54, 'commission_rate' => 0, 'commission_amount' => 0, 'seller_earnings' => 54]);

        app(GstService::class)->capturePart($part, $order);

        $part->refresh();
        $this->assertTrue($part->gst_registered);
        $this->assertSame('1000001GST501', $part->gst_tin);
        $this->assertSame('Iruali Pvt Ltd', $part->gst_business_name);
        $this->assertSame('H. Example, Malé', $part->gst_business_address);
        $this->assertEquals(4.00, $part->gst_amount);

        $this->pay($order);
        $this->assertSame('INV-IR-000001', $part->fresh()->invoice_number);
    }

    protected function assertSnapshotUnchanged(SellerOrder $a, SellerOrder $b, $order): void
    {
        $fields = ['gst_registered', 'gst_tin', 'gst_business_name', 'gst_business_address', 'gst_rate', 'gst_taxable', 'gst_amount', 'commission_gst'];
        $this->assertSame($a->only($fields), $a->fresh()->only($fields));
        $this->assertSame($b->only($fields), $b->fresh()->only($fields));
        $this->assertTrue($order->gst_platform_registered);
        $this->assertSame('1000001GST501', $order->gst_platform_tin);
        $this->assertEquals(8, $order->gst_rate);
        $this->assertEquals(6.00, $order->delivery_gst);
    }
}
