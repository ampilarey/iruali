<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\SellerAdjustment;
use App\Models\Setting;
use App\Models\User;
use App\Services\BookingFeeService;
use App\Services\GstReportService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PayoutService;
use App\Services\PreorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GstFixtures;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * Pre-orders with a booking fee, second round: each product's own days (to pay the balance, and how
 * late before a full refund), and GST on the booking fee: its own tax invoice when it is paid, the
 * final invoice less the fee, the monthly GST report, and a fee refunded or kept.
 *
 * The clock is 20 Oct 2026. Lens House is GST-registered (8%); the camera costs MVR 1,000 with a
 * 30% booking fee, so the fee of MVR 300 has MVR 22.22 GST in it, the sale MVR 74.07 and the rest
 * (MVR 700) MVR 51.85.
 */
class BookingFeeGstAndDaysTest extends TestCase
{
    use GstFixtures, PreorderFixtures, RefreshDatabase;

    protected User $lens;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 75, 'contact_email' => 'ops@iruali.test', 'default_commission_rate' => 10]);
        $this->lens = $this->shop('Lens House', '1012345GST501');
        $this->lens->forceFill(['preorders_allowed' => true])->save(); // an admin allowed it pre-orders
        $this->enableBml();
    }

    protected function camera(array $attributes = []): Product
    {
        $product = $this->preorderProduct($this->lens, ['price' => 1000, 'name' => 'Mirrorless camera']);
        $product->forceFill(array_merge(['preorder_ship_date' => null, 'preorder_lead_days' => 14, 'preorder_deposit_type' => 'percent', 'preorder_deposit_value' => 30], $attributes))->save();

        return $product->fresh();
    }

    protected function bookedOrder(?User $customer = null, ?Product $product = null, bool $pay = true): Order
    {
        $customer ??= User::factory()->create();
        session()->forget([PreorderService::SESSION_SHOWN, BookingFeeService::SESSION_SHOWN]); // another customer's checkout page
        $cart = $this->cartWith($customer, [[$product ?? $this->camera(), 1]]);
        $result = app(OrderService::class)->createOrderFromCart($customer, $this->shippingData() + ['agree_booking_fee' => true], $cart);
        $this->assertTrue($result['success'], $result['message']);
        if ($pay) {
            app(PaymentService::class)->confirm($result['order']->fresh());
        }

        return $result['order']->fresh();
    }

    protected function stockArrives(Order $order): Order
    {
        app(PreorderService::class)->receiveStock($order->items()->sole()->product, [0 => 1]);

        return $order->fresh();
    }

    /**
     * @return array<string, mixed> the shop's line of a month's GST report
     */
    protected function shopLine(string $month): array
    {
        return app(GstReportService::class)->build($month)['shops']->firstWhere('seller_id', $this->lens->id) ?? [];
    }

    public function test_each_product_sets_its_own_days_to_pay_the_balance_and_to_be_late(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $fields = ['name_en' => 'Mirrorless camera', 'sku' => 'CAM-1', 'category_id' => $category->id, 'price' => 1000, 'stock_quantity' => 0, 'reorder_point' => 0,
            'preorder_form' => 1, 'preorder_enabled' => 1, 'preorder_limit' => 3, 'preorder_ships' => 'days', 'preorder_lead_days' => 14,
            'preorder_deposit_type' => 'percent', 'preorder_deposit_value' => 30];

        $this->actingAs($this->lens)->get(route('seller.products.create'))->assertOk()
            ->assertSee('Days to pay the rest once it arrives')->assertSee('Late after how many days?');
        $this->post(route('seller.products.store'), $fields + ['preorder_balance_days' => 0, 'preorder_late_days' => 91])
            ->assertSessionHasErrors(['preorder_balance_days', 'preorder_late_days']);
        $this->post(route('seller.products.store'), $fields + ['preorder_balance_days' => 10, 'preorder_late_days' => 21])->assertRedirect(route('seller.products.index'));
        $camera = Product::where('sku', 'CAM-1')->sole();
        $this->assertSame(10, $camera->preorder_balance_days);
        $this->assertSame(21, $camera->preorder_late_days);
        $this->assertSame(10, $camera->preorderBalanceDays());

        // Empty: the defaults (7 days each); no booking fee, no days to pay
        $this->put(route('seller.products.update', $camera), $fields)->assertSessionHasNoErrors();
        $this->assertNull($camera->fresh()->preorder_balance_days);
        $this->assertSame(7, $camera->fresh()->preorderLateDays());
        $camera->refresh()->forceFill(['preorder_balance_days' => 10, 'preorder_late_days' => 21, 'is_active' => true])->save();

        $this->get(route('products.show', $camera))->assertOk()
            ->assertSee('You pay the rest within 10 days of it arriving, before it is delivered.')
            ->assertSee('is more than 21 days late, you get everything back');

        // Checkout shows the product's days, and the order keeps them
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$camera, 1]]);
        $this->actingAs($customer)->get(route('checkout'))->assertOk()
            ->assertSee('the balance within 10 days of my order arriving')->assertSee('more than 21 days late, I get everything back')
            ->assertSee('data-booking-fee-gst', false)->assertSee('GST included. You get a tax invoice for it.');
        $this->assertSame(['fee' => 300.0, 'late_days' => 21, 'balance_days' => 10], session(BookingFeeService::SESSION_SHOWN));
        $this->post(route('orders.store'), array_merge($this->checkoutFields(), ['agree_booking_fee' => '1']))->assertRedirect();
        $order = Order::sole();
        $this->assertSame(10, $order->balance_days);
        $this->assertSame(21, $order->items()->sole()->preorder_late_days);
        $this->assertSame(21, $order->preorderLateDays());

        // The shop changes the product afterwards: the order keeps what it was placed with
        $camera->forceFill(['preorder_balance_days' => 3, 'preorder_late_days' => 30])->save();
        app(PaymentService::class)->confirm($order->fresh());
        $late = $this->bookedOrder(User::factory()->create(), $camera->fresh()); // placed now: 30 days late
        $this->assertSame('2026-10-30', $this->stockArrives($order)->balance_due_on->toDateString(), '10 days to pay, not 3');

        // 22 days past 3 Nov: late for an order allowing 21 days, not yet for one allowing 30
        $camera->forceFill(['preorder_late_days' => 21])->save();
        $waiting = $this->bookedOrder(User::factory()->create(), $camera->fresh());
        $this->travelTo(Carbon::parse('2026-11-25 07:00'));
        $this->artisan('preorders:flag-late')->assertExitCode(0);
        $this->assertNotNull($waiting->items()->sole()->preorder_late_at);
        $this->assertNotNull($waiting->fresh()->deposit_refundable_at, 'late: the fee is refunded in full if the customer cancels');
        $this->assertNull($late->items()->sole()->preorder_late_at);
        $this->assertNull($late->fresh()->deposit_refundable_at);
    }

    public function test_fewer_days_to_pay_or_more_days_before_a_refund_after_the_checkout_page_are_not_ordered_silently(): void
    {
        $camera = $this->camera(['preorder_balance_days' => 10, 'preorder_late_days' => 14]);
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$camera, 1]]);

        $this->actingAs($customer)->get(route('checkout'))->assertOk();
        $camera->forceFill(['preorder_balance_days' => 5])->save();
        $this->post(route('orders.store'), array_merge($this->checkoutFields(), ['agree_booking_fee' => '1']))->assertRedirect(route('cart'))
            ->assertSessionHas('notification', fn ($flash) => $flash['message'] === 'The shop has changed the terms of this pre-order. Please check your order and place it again.');

        $this->get(route('checkout'))->assertOk();
        $camera->forceFill(['preorder_late_days' => 30])->save();
        $this->post(route('orders.store'), array_merge($this->checkoutFields(), ['agree_booking_fee' => '1']))->assertRedirect(route('cart'));
        $this->assertSame(0, Order::count());

        // Kinder terms are fine: more days to pay
        $this->get(route('checkout'))->assertOk();
        $camera->forceFill(['preorder_balance_days' => 20])->save();
        $this->post(route('orders.store'), array_merge($this->checkoutFields(), ['agree_booking_fee' => '1']))->assertRedirect();
        $this->assertSame(20, Order::sole()->balance_days);
    }

    public function test_the_booking_fee_gets_its_own_tax_invoice_and_the_final_invoice_takes_it_off(): void
    {
        $customer = User::factory()->create(['name' => 'Aishath Shifa']);
        $order = $this->bookedOrder($customer);
        $part = $order->sellerOrders()->sole();

        $this->assertSame('INV-'.$this->lens->id.'-000001', $part->deposit_invoice_number);
        $this->assertEquals(300, $part->deposit_amount);
        $this->assertEquals(22.22, $part->deposit_gst);
        $this->assertNotNull($part->deposit_invoiced_at);
        $this->assertNull($part->invoice_number, 'the rest is invoiced when the balance is paid');

        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('data-booking-fee-invoice-link', false)->assertSee('Tax invoice for the booking fee · Lens House')->assertSee($part->deposit_invoice_number);
        $this->get(route('orders.invoice', [$order, $part, 'booking' => 1]))->assertOk()
            ->assertSee('data-booking-fee-invoice', false)->assertSee('Booking fee (advance payment)')->assertSee($part->deposit_invoice_number)
            ->assertSee('1012345GST501')->assertSee('Aishath Shifa')->assertSee('MVR 300.00')->assertSee('MVR 277.78')->assertSee('MVR 22.22');
        $this->get(route('orders.invoice', [$order, $part]))->assertNotFound(); // no invoice for the rest yet
        $this->actingAs(User::factory()->create())->get(route('orders.invoice', [$order, $part, 'booking' => 1]))->assertForbidden();

        $this->actingAs($this->lens)->get(route('seller.orders.show', $order))->assertOk()->assertSee('data-shop-booking-fee-invoice', false)->assertSee('GST included: MVR 22.22 at 8%.');
        $this->get(route('seller.orders.invoice', [$order, 'booking' => 1]))->assertOk()->assertSee($part->deposit_invoice_number);
        $this->actingAs($this->staff('admin'))->get(route('admin.orders.show', $order))->assertOk()->assertSee('data-admin-booking-fee-invoice', false);

        // The balance is paid: the invoice for the whole sale, less the fee already invoiced
        $order = $this->stockArrives($order);
        app(PaymentService::class)->confirm($order);
        $part->refresh();
        $this->assertSame('INV-'.$this->lens->id.'-000002', $part->invoice_number, 'the next number in the shop\'s own series');
        $this->actingAs($customer)->get(route('orders.invoice', [$order, $part]))->assertOk()
            ->assertSee('data-less-booking-fee', false)->assertSee('Less booking fee, invoice '.$part->deposit_invoice_number)
            ->assertSee('data-gst-this-invoice', false)->assertSee('MVR 1,000.00')->assertSee('MVR 74.07')->assertSee('MVR 700.00')->assertSee('MVR 51.85')->assertSee('MVR 648.15');
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Tax invoice · Lens House')->assertSee('Tax invoice for the booking fee · Lens House');
    }

    public function test_the_gst_report_counts_the_fee_when_it_is_paid_and_the_rest_with_the_balance(): void
    {
        $order = $this->bookedOrder();
        $october = $this->shopLine('2026-10');
        $this->assertSame([1, 300.0, 22.22], [$october['invoices'], $october['sales'], $october['gst']]);

        $this->travelTo(Carbon::parse('2026-11-02 10:00'));
        $order = $this->stockArrives($order);
        app(PaymentService::class)->confirm($order);
        $november = $this->shopLine('2026-11');
        $this->assertSame([1, 700.0, 51.85], [$november['invoices'], $november['sales'], $november['gst']]);
        $this->assertSame([1, 300.0, 22.22], [$this->shopLine('2026-10')['invoices'], $this->shopLine('2026-10')['sales'], $this->shopLine('2026-10')['gst']], 'October is unchanged');

        // The ledger has both invoices, each in its month
        $admin = $this->staff('admin');
        $ledger = $this->actingAs($admin)->get(route('admin.tax.report', ['month' => '2026-10', 'export' => 'ledger']))->streamedContent();
        $this->assertStringContainsString('"shop sale (booking fee)",'.$order->order_number.','.$order->sellerOrders()->sole()->deposit_invoice_number.',"Lens House",1012345GST501,300.00,22.22', $ledger);
        $ledger = $this->get(route('admin.tax.report', ['month' => '2026-11', 'export' => 'ledger']))->streamedContent();
        $this->assertStringContainsString('"shop sale",'.$order->order_number.','.$order->sellerOrders()->sole()->invoice_number.',"Lens House",1012345GST501,700.00,51.85', $ledger);
    }

    public function test_a_refunded_fee_is_reversed_and_a_kept_fee_stays_a_sale_with_irualis_commission(): void
    {
        $this->registerIruali();
        $refunded = $this->bookedOrder();
        $kept = $this->bookedOrder();

        // The shop can't get the camera: an admin cancels and everything is refunded
        $admin = $this->staff('admin');
        $this->actingAs($admin)->post(route('admin.orders.status', $refunded), ['status' => 'cancelled'])->assertSessionHas('success');
        $refundedPart = $refunded->sellerOrders()->sole();
        $this->assertNotNull($refundedPart->deposit_reversed_at);
        $this->assertNull($refundedPart->deposit_kept_at);

        // The customer cancels the other: the fee is kept, iruali's commission on it is counted
        app(PreorderService::class)->cancel($kept->fresh());
        $keptPart = $kept->sellerOrders()->sole();
        $this->assertNotNull($keptPart->deposit_kept_at);
        $this->assertNull($keptPart->deposit_reversed_at);
        $this->assertEquals(30, $keptPart->deposit_commission);
        $this->assertEquals(2.22, $keptPart->deposit_commission_gst);
        $this->assertEquals(270, SellerAdjustment::sole()->amount);
        $this->assertSame($keptPart->id, SellerAdjustment::sole()->seller_order_id);

        $report = app(GstReportService::class)->build('2026-10');
        $shop = $report['shops']->firstWhere('seller_id', $this->lens->id);
        $this->assertSame([2, 600.0, 44.44, 300.0, 22.22, 300.0, 22.22], [$shop['invoices'], $shop['sales'], $shop['gst'], $shop['refunds'], $shop['refund_gst'], $shop['net_sales'], $shop['net_gst']]);
        $this->assertSame(['booking_fee'], $report['refunds']->pluck('kind')->all());
        $this->assertSame([30.0, 2.22], [$report['iruali']['commission']['amount'], $report['iruali']['commission']['gst']]);
        $this->actingAs($admin)->get(route('admin.tax.report', ['month' => '2026-10']))->assertOk()->assertSee('Booking fee refunded');

        $this->get(route('admin.orders.invoice', [$refunded, $refundedPart, 'booking' => 1]))->assertOk()->assertSee('the booking fee was refunded');
        $this->get(route('admin.orders.invoice', [$kept, $keptPart, 'booking' => 1]))->assertOk()->assertSee('As agreed at checkout, the booking fee was not refunded.');

        // The shop's next payout (with a camera it delivered): the kept fee is a commission line of
        // its own on iruali's invoice, and the settlement adds up
        $sold = $this->stockArrives($this->bookedOrder());
        app(PaymentService::class)->confirm($sold);
        $part = $sold->sellerOrders()->sole();
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->assertTrue(app(\App\Services\FulfilmentService::class)->advance($part->fresh(), $status));
        }
        $payout = app(PayoutService::class)->createPayout($this->lens, null, 'TRF-1', null, $admin);
        $this->assertEquals(900 + 270, $payout->amount);
        $this->actingAs($this->lens)->get(route('seller.payouts.invoice', $payout))->assertOk()
            ->assertSee('data-commission-invoice="tax"', false)->assertSee('data-kept-booking-fee', false)->assertSee($kept->order_number)
            ->assertSee('MVR 300.00')->assertSee('MVR 30.00')->assertSee('MVR 2.22')
            ->assertSee('MVR 130.00')->assertSee('MVR 9.63')->assertSee('MVR 1,300.00')->assertSee('MVR 1,170.00')
            ->assertDontSee('Returns and other adjustments');
        $this->assertTrue(AuditLog::where('action', 'booking_fee.kept')->exists());
    }
}
