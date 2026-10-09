<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\GstReportService;
use App\Services\OrderService;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GstFixtures;
use Tests\TestCase;

/**
 * The monthly GST report: paid orders in the month, less refunds made in it, from the GST frozen
 * on each order.
 */
class GstReportTest extends TestCase
{
    use GstFixtures, RefreshDatabase;

    protected User $customer;

    protected User $crafts;

    protected User $reef;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 81, 'default_commission_rate' => 10]);
        $this->registerIruali(8);
        $this->customer = User::factory()->create();
        $this->crafts = $this->shop('Island Crafts', '1012345GST501');
        $this->reef = $this->shop('Reefline Marine');
    }

    protected function deliver(Order $order): void
    {
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            app(OrderService::class)->updateOrderStatus($order->fresh(), $status);
        }
    }

    public function test_report_totals_with_a_refund(): void
    {
        // Paid in September: not in October's report
        $this->travelTo(Carbon::parse('2026-09-30 22:00'));
        $this->pay($this->placeOrder($this->customer, [[$this->crafts, 999]]));

        // October: two of shop A's items (GST-registered) and one of shop B's (not registered)
        $this->travelTo(Carbon::parse('2026-10-05 10:00'));
        $craftsOrder = $this->pay($this->placeOrder($this->customer, [[$this->crafts, 540, 2]]));
        $reefOrder = $this->pay($this->placeOrder($this->customer, [[$this->reef, 100]]));
        $this->placeOrder($this->customer, [[$this->crafts, 777]]); // never paid

        // One item comes back and is refunded on 20 October
        $this->deliver($craftsOrder);
        $this->travelTo(Carbon::parse('2026-10-20 12:00'));
        $part = $craftsOrder->sellerOrders()->sole();
        $returns = app(ReturnService::class);
        $return = $returns->request($part->fresh(), $this->customer, [$craftsOrder->items()->sole()->id => 1], 'change_of_mind', null, null);
        $this->assertTrue($returns->approve($return, 540, true, null, $this->staff('admin')));

        $report = app(GstReportService::class)->build('2026-10');

        $this->assertCount(1, $report['shops']);
        $shop = $report['shops'][0];
        $this->assertSame('Island Crafts Pvt Ltd', $shop['name']);
        $this->assertSame('1012345GST501', $shop['tin']);
        $this->assertSame(1, $shop['invoices']);
        $this->assertSame(1080.0, $shop['sales']);
        $this->assertSame(80.0, $shop['gst']);
        $this->assertSame(540.0, $shop['refunds']);
        $this->assertSame(40.0, $shop['refund_gst']);
        $this->assertSame(540.0, $shop['net_sales']);
        $this->assertSame(40.0, $shop['net_gst']);

        // iruali: commission 108 + 10 (GST 8.00 + 0.74), 54 of it given back on the return (GST 4.00);
        // delivery 81 + 81 (GST 6.00 each)
        $this->assertSame(['amount' => 118.0, 'gst' => 8.74, 'refunded' => 54.0, 'refunded_gst' => 4.0, 'net' => 64.0, 'net_gst' => 4.74], $report['iruali']['commission']);
        $this->assertSame(['amount' => 162.0, 'gst' => 12.0, 'refunded' => 0.0, 'refunded_gst' => 0.0, 'net' => 162.0, 'net_gst' => 12.0], $report['iruali']['delivery']);
        $this->assertSame(16.74, $report['iruali_totals']['net_gst']);
        $this->assertSame(2, $report['registered_orders']);

        $admin = $this->staff('admin');
        $this->actingAs($admin)->get(route('admin.tax.report', ['month' => '2026-10']))->assertOk()
            ->assertSee('Island Crafts Pvt Ltd')->assertSee('1012345GST501')->assertSee('MVR 1,080.00')->assertSee('MVR 80.00')
            ->assertSee('−MVR 540.00')->assertSee('−MVR 40.00')->assertSee('MVR 118.00')->assertSee('MVR 8.74')->assertSee('MVR 162.00')
            ->assertSee($craftsOrder->order_number)->assertDontSee('Reefline Marine Pvt Ltd');

        $csv = $this->actingAs($admin)->get(route('admin.tax.report', ['month' => '2026-10', 'export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('attachment; filename=gst-report-2026-10.csv', $csv->headers->get('Content-Disposition'));
        $lines = array_map('str_getcsv', array_filter(explode("\n", $csv->streamedContent())));
        $this->assertSame(['month', 'section', 'name', 'tin', 'amount_incl_gst', 'gst', 'refunds_incl_gst', 'gst_on_refunds', 'net_incl_gst', 'net_gst'], $lines[0]);
        $this->assertSame(['2026-10', 'shop', 'Island Crafts Pvt Ltd', '1012345GST501', '1080.00', '80.00', '540.00', '40.00', '540.00', '40.00'], $lines[1]);
        $this->assertSame(['2026-10', 'iruali', 'iruali commission', '1000001GST501', '118.00', '8.74', '54.00', '4.00', '64.00', '4.74'], $lines[2]);
        $this->assertSame(['2026-10', 'iruali', 'iruali delivery fees', '1000001GST501', '162.00', '12.00', '0.00', '0.00', '162.00', '12.00'], $lines[3]);

        // September has only the late-night order of 30 September
        $september = app(GstReportService::class)->build('2026-09');
        $this->assertSame(999.0, $september['shops'][0]['sales']);
        $this->assertSame(74.0, $september['shops'][0]['gst']);
        $this->assertSame(0.0, $september['shops'][0]['refunds']);
    }

    public function test_a_paid_order_cancelled_next_month_is_a_refund_in_that_month(): void
    {
        $this->travelTo(Carbon::parse('2026-10-28 10:00'));
        $order = $this->pay($this->placeOrder($this->customer, [[$this->crafts, 216], [$this->reef, 100]]));

        $this->travelTo(Carbon::parse('2026-11-02 09:00'));
        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled'));
        $this->assertNotNull($order->sellerOrders()->where('seller_id', $this->crafts->id)->sole()->gst_reversed_at);

        $october = app(GstReportService::class)->build('2026-10');
        $this->assertSame(216.0, $october['shops'][0]['sales']);
        $this->assertSame(0.0, $october['shops'][0]['refunds']);
        $this->assertSame(81.0, $october['iruali']['delivery']['amount']);

        $november = app(GstReportService::class)->build('2026-11');
        $this->assertSame(0.0, $november['shops'][0]['sales']);
        $this->assertSame(216.0, $november['shops'][0]['refunds']);
        $this->assertSame(16.0, $november['shops'][0]['refund_gst']);
        $this->assertSame(-16.0, $november['shops'][0]['net_gst']);
        $this->assertSame(['amount' => 0.0, 'gst' => 0.0, 'refunded' => 81.0, 'refunded_gst' => 6.0, 'net' => -81.0, 'net_gst' => -6.0], $november['iruali']['delivery']);
        $this->assertSame(31.6, $november['iruali']['commission']['refunded']); // 21.60 + 10.00
        $this->assertCount(2, $november['refunds']);

        // The invoice says the sale was reversed
        $part = $order->sellerOrders()->where('seller_id', $this->crafts->id)->sole();
        $this->actingAs($this->customer)->get(route('orders.invoice', [$order, $part]))->assertOk()->assertSee('data-reversed', false)->assertSee('2 Nov 2026');
    }

    public function test_a_dispute_refund_counts_like_a_return(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:00'));
        $order = $this->pay($this->placeOrder($this->customer, [[$this->crafts, 540]]));
        $part = $order->sellerOrders()->sole();

        // Decided on 12 October: MVR 621 back, the item (540) and the delivery fee (81)
        $this->travelTo(Carbon::parse('2026-10-12 10:00'));
        \App\Models\Dispute::create([
            'order_id' => $order->id, 'seller_order_id' => $part->id, 'customer_id' => $this->customer->id, 'seller_id' => $this->crafts->id,
            'type' => 'non_delivery', 'status' => 'resolved_refund', 'details' => 'Never arrived', 'amount_claimed' => 621, 'amount_resolved' => 621,
            'opened_at' => now()->subDay(), 'resolved_at' => now(),
        ]);

        $report = app(GstReportService::class)->build('2026-10');
        $this->assertSame(540.0, $report['shops'][0]['refunds']);
        $this->assertSame(40.0, $report['shops'][0]['refund_gst']);
        $this->assertSame(0.0, $report['shops'][0]['net_sales']);
        $this->assertSame(['amount' => 54.0, 'gst' => 4.0, 'refunded' => 54.0, 'refunded_gst' => 4.0, 'net' => 0.0, 'net_gst' => 0.0], $report['iruali']['commission']);
        $this->assertSame(81.0, $report['iruali']['delivery']['refunded']);
        $this->assertSame(6.0, $report['iruali']['delivery']['refunded_gst']);
        $this->assertSame('dispute', $report['refunds'][0]['kind']);
    }

    public function test_the_csv_never_starts_a_cell_with_a_formula(): void
    {
        $this->travelTo(Carbon::parse('2026-10-05 10:00'));
        \App\Models\ShopTaxProfile::where('user_id', $this->crafts->id)->update(['registered_name' => '=HYPERLINK("http://evil.test")']);
        $this->pay($this->placeOrder($this->customer, [[$this->crafts, 108]]));

        $reports = app(GstReportService::class);
        $rows = $reports->csvRows($reports->build('2026-10'));
        $this->assertSame('\'=HYPERLINK("http://evil.test")', $rows[1][2]);
    }

    public function test_the_month_defaults_to_this_month_and_bad_input_is_ignored(): void
    {
        $this->travelTo(Carbon::parse('2026-10-09 12:00'));
        $this->assertSame('2026-10', app(GstReportService::class)->build(null)['month']);
        $this->assertSame('2026-10', app(GstReportService::class)->build('2026-13')['month']);
        $this->assertSame('2026-10', app(GstReportService::class)->build(['x'])['month']);

        $this->actingAs($this->staff('admin'))->get(route('admin.tax.report', ['month' => "2026-10'; drop"]))->assertOk()
            ->assertSee('value="2026-10"', false)->assertSee('No sales by GST-registered shops in this month.');
    }
}
