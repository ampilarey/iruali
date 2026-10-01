<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\SellerPerformanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SellerPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $shop;

    protected Product $box;

    protected Product $rope;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-01 10:00:00');

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $this->shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        $this->box = Product::factory()->create(['seller_id' => $this->shop->id, 'name' => ['en' => 'Lacquer Box'], 'price' => 100]);
        $this->rope = Product::factory()->create(['seller_id' => $this->shop->id, 'name' => ['en' => 'Coir Rope'], 'price' => 20]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * An order for this shop: paid on $paidAt, part shipped on $shippedAt (null = not yet).
     */
    protected function order(User $customer, array $lines, string $paidAt, ?string $shippedAt, string $status = 'delivered'): SellerOrder
    {
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => $status, 'payment_status' => 'paid', 'paid_at' => $paidAt, 'created_at' => $paidAt]);
        foreach ($lines as [$product, $qty]) {
            $order->items()->create(['product_id' => $product->id, 'quantity' => $qty, 'price' => $product->price]);
        }
        $part = SellerOrder::where('order_id', $order->id)->where('seller_id', $this->shop->id)->firstOrFail();
        $part->update(['status' => $status, 'shipped_at' => $shippedAt, 'delivered_at' => $status === 'delivered' ? ($shippedAt ?? $paidAt) : null]);

        return $part;
    }

    public function test_report_numbers(): void
    {
        Setting::set(['late_shipment_days' => 3]);
        [$a, $b, $c] = User::factory()->count(3)->create()->all();

        // Customer A orders twice (repeat); B once; C once but cancelled (ignored)
        $onTime = $this->order($a, [[$this->box, 2]], '2026-09-20 09:00:00', '2026-09-22 09:00:00');
        $late = $this->order($a, [[$this->rope, 5]], '2026-09-10 09:00:00', '2026-09-15 09:00:00');
        $unshipped = $this->order($b, [[$this->box, 1]], '2026-09-26 09:00:00', null, 'processing');
        $this->order($c, [[$this->box, 1]], '2026-09-01 09:00:00', null, 'cancelled');
        $old = $this->order($b, [[$this->rope, 1]], '2026-05-01 09:00:00', '2026-05-10 09:00:00'); // outside the 90-day window

        // Returns: 1 of the 2 boxes on the first order, approved
        $return = ReturnRequest::create(['order_id' => $onTime->order_id, 'seller_order_id' => $onTime->id, 'user_id' => $a->id, 'status' => 'approved', 'reason' => 'faulty', 'items_value' => 100, 'refund_amount' => 100]);
        $return->items()->create(['order_item_id' => $onTime->order->items()->first()->id, 'quantity' => 1]);
        $rejected = ReturnRequest::create(['order_id' => $late->order_id, 'seller_order_id' => $late->id, 'user_id' => $a->id, 'status' => 'rejected', 'reason' => 'change_of_mind', 'items_value' => 20]);
        $rejected->items()->create(['order_item_id' => $late->order->items()->first()->id, 'quantity' => 5]);

        // Reviews: two approved this month, one hidden, one old
        ProductReview::forceCreate(['product_id' => $this->box->id, 'user_id' => $a->id, 'reviewer_name' => 'Customer', 'reviewer_email' => 'c@example.com', 'comment' => 'Nice', 'rating' => 5, 'is_approved' => true, 'created_at' => '2026-09-28']);
        ProductReview::forceCreate(['product_id' => $this->rope->id, 'user_id' => $b->id, 'reviewer_name' => 'Customer', 'reviewer_email' => 'c@example.com', 'comment' => 'Nice', 'rating' => 3, 'is_approved' => true, 'created_at' => '2026-09-29']);
        ProductReview::forceCreate(['product_id' => $this->box->id, 'user_id' => $c->id, 'reviewer_name' => 'Customer', 'reviewer_email' => 'c@example.com', 'comment' => 'Nice', 'rating' => 1, 'is_approved' => false, 'created_at' => '2026-09-29']);
        ProductReview::forceCreate(['product_id' => $this->box->id, 'user_id' => $c->id, 'reviewer_name' => 'Customer', 'reviewer_email' => 'c@example.com', 'comment' => 'Nice', 'rating' => 4, 'is_approved' => true, 'created_at' => '2026-07-15']);

        $report = app(SellerPerformanceService::class)->report($this->shop);

        // Late: 3 paid parts in the window; the 5-day one and the unshipped one (paid 5 days ago) are late
        $this->assertSame(['count' => 2, 'total' => 3, 'rate' => 66.7], array_intersect_key($report['late'], ['count' => 1, 'total' => 1, 'rate' => 1]));
        $this->assertEqualsCanonicalizing([$late->id, $unshipped->id], $report['late']['parts']->pluck('id')->all());

        // Returns: 1 returned of 8 items sold in 90 days (2 + 5 + 1; cancelled and old orders excluded)
        $this->assertSame(['returned' => 1, 'sold' => 8, 'rate' => 12.5], $report['return_rate']);

        // Ratings: September average 4.0 from two approved reviews; overall (5+3+4)/3 = 4.0 from three
        $sep = collect($report['ratings']['months'])->firstWhere('month', '2026-09');
        $this->assertSame(['avg' => 4.0, 'count' => 2], array_intersect_key($sep, ['avg' => 1, 'count' => 1]));
        $this->assertSame(4.0, collect($report['ratings']['months'])->firstWhere('month', '2026-07')['avg']);
        $this->assertSame(4.0, $report['ratings']['overall']);
        $this->assertSame(3, $report['ratings']['count']);
        $this->assertCount(12, $report['ratings']['months']);

        // Repeat customers: A (2 orders) and B (2 orders) of 2 customers with non-cancelled orders... C only cancelled
        $this->assertSame(['customers' => 2, 'repeat' => 2, 'rate' => 100.0], $report['repeat']);

        // Best sellers by revenue: boxes 300 (3 units), rope 120 (6 units)
        $this->assertSame([['Lacquer Box', 3, 300.0], ['Coir Rope', 6, 120.0]], $report['best_sellers']->map(fn ($r) => [$r['name'], $r['units'], $r['revenue']])->all());

        // Weekly: 12 weeks, the week of 20 Sep has the 200 box order
        $this->assertCount(12, $report['weekly']);
        $week = collect($report['weekly'])->firstWhere('label', '14 Sep');
        $this->assertSame(200.0, $week['revenue']);
        $this->assertSame(1, $week['orders']);
        $this->assertSame(400.0, round(collect($report['weekly'])->sum('revenue'), 2), 'The May order is outside the 12 weeks');
    }

    public function test_seller_and_admin_pages_show_the_numbers(): void
    {
        Setting::set(['late_shipment_days' => 2]);
        $customer = User::factory()->create();
        $this->order($customer, [[$this->box, 1]], '2026-09-20 09:00:00', '2026-09-25 09:00:00');
        $this->order($customer, [[$this->rope, 2]], '2026-09-21 09:00:00', '2026-09-21 12:00:00');

        $this->actingAs($this->shop)->get('/seller/performance')->assertOk()
            ->assertSee('Performance')->assertSee('50%')->assertSee('1 of 2 paid orders')->assertSee('within 2 days of payment')
            ->assertSee('Lacquer Box')->assertSee('Sales by week')->assertSee('data-chart="ratings"', false)->assertSee('Late shipments');

        $this->actingAs($this->admin)->get(route('admin.sellers.performance', $this->shop))->assertOk()
            ->assertSee('Island Crafts performance')->assertSee('50%')->assertSee('Days late')->assertSee('25 Sep 2026');

        $this->get('/admin/sellers')->assertOk()->assertSee('50%')->assertSee('1/2')->assertSee(route('admin.sellers.performance', $this->shop), false);
    }

    public function test_summary_for_the_sellers_list_and_authorisation(): void
    {
        $other = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reefline']);
        $customer = User::factory()->create();
        $this->order($customer, [[$this->box, 1]], '2026-09-20 09:00:00', '2026-09-20 12:00:00');
        ProductReview::forceCreate(['product_id' => $this->box->id, 'user_id' => $customer->id, 'reviewer_name' => 'Customer', 'reviewer_email' => 'c@example.com', 'comment' => 'Nice', 'rating' => 5, 'is_approved' => true]);

        $summary = app(SellerPerformanceService::class)->summaryFor([$this->shop->id, $other->id]);
        $this->assertSame(['late_count' => 0, 'shipped_total' => 1, 'late_rate' => 0.0, 'rating' => 5.0, 'reviews' => 1], $summary[$this->shop->id]);
        $this->assertSame(['late_count' => 0, 'shipped_total' => 0, 'late_rate' => null, 'rating' => null, 'reviews' => 0], $summary[$other->id]);

        $this->actingAs($this->shop)->get(route('admin.sellers.performance', $this->shop))->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/seller/performance')->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.sellers.performance', $customer))->assertNotFound();
    }
}
