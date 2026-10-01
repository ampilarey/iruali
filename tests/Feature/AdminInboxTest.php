<?php

namespace Tests\Feature;

use App\Models\ErrorEvent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\User;
use App\Support\AdminInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInboxTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        AdminInbox::reset();
    }

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function seedWork(): array
    {
        $pendingShop = User::factory()->create(['is_seller' => true, 'seller_approved' => false]);
        $pendingShop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        Product::factory()->create(['is_active' => false]);
        Product::factory()->create(['is_active' => false]);
        Order::factory()->create(['refund_status' => 'due', 'refund_amount' => 50]);

        $order = Order::factory()->create(['status' => 'delivered', 'payment_status' => 'paid']);
        $part = SellerOrder::create(['order_id' => $order->id, 'seller_id' => $pendingShop->id, 'status' => 'delivered', 'subtotal' => 10, 'commission_rate' => 10, 'commission_amount' => 1, 'seller_earnings' => 9]);
        ReturnRequest::create(['order_id' => $order->id, 'seller_order_id' => $part->id, 'user_id' => $order->user_id, 'status' => 'requested', 'reason' => 'damaged', 'items_value' => 10]);

        PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'bml', 'transaction_id' => 't1', 'local_id' => 'l1', 'amount' => 1000, 'currency' => 'MVR', 'state' => 'FAILED']);
        $old = PaymentTransaction::create(['order_id' => $order->id, 'gateway' => 'bml', 'transaction_id' => 't2', 'local_id' => 'l2', 'amount' => 1000, 'currency' => 'MVR', 'state' => 'CANCELLED']);
        PaymentTransaction::whereKey($old->id)->update(['updated_at' => now()->subDays(3)]);

        // Best seller of the month that is almost gone, and one with plenty
        $hot = Product::factory()->create(['stock_quantity' => 2]);
        $plenty = Product::factory()->create(['stock_quantity' => 50]);
        $recent = Order::factory()->create(['status' => 'delivered']);
        OrderItem::create(['order_id' => $recent->id, 'product_id' => $hot->id, 'quantity' => 9, 'price' => 10]);
        OrderItem::create(['order_id' => $recent->id, 'product_id' => $plenty->id, 'quantity' => 5, 'price' => 10]);

        ErrorEvent::create(['fingerprint' => sha1('e1'), 'exception_class' => 'RuntimeException', 'first_seen_at' => now(), 'last_seen_at' => now()]);
        ErrorEvent::create(['fingerprint' => sha1('e2'), 'exception_class' => 'RuntimeException', 'first_seen_at' => now(), 'last_seen_at' => now(), 'resolved_at' => now()]);

        return compact('hot', 'plenty');
    }

    public function test_built_in_rows_count_what_is_waiting(): void
    {
        $this->seedWork();
        $counts = collect(AdminInbox::all())->pluck('count', 'key')->all();

        $this->assertSame([
            'sellers_pending' => 1,
            'products_pending' => 2,
            'refunds_due' => 1,
            'returns_open' => 1,
            'bml_failed' => 1,
            'low_stock_best_sellers' => 1,
            'errors_unresolved' => 1,
        ], $counts);
        $this->assertSame(8, AdminInbox::total($this->staff('admin')));
        $this->assertSame(route('admin.errors'), collect(AdminInbox::all())->firstWhere('key', 'errors_unresolved')['url']);
    }

    public function test_other_features_can_register_rows(): void
    {
        AdminInbox::register('disputes', fn () => ['label' => 'Open disputes', 'count' => 4, 'route' => 'admin.orders', 'severity' => 'danger']);
        AdminInbox::register('broken', fn () => throw new \RuntimeException('provider down'));

        $items = collect(AdminInbox::items($this->staff('admin')));
        $this->assertSame(4, $items->firstWhere('key', 'disputes')['count']);
        $this->assertSame(route('admin.orders'), $items->firstWhere('key', 'disputes')['url']);
        $this->assertNull($items->firstWhere('key', 'broken'), 'a failing provider is skipped, not fatal');
    }

    public function test_inbox_page_and_nav_badge_for_admin(): void
    {
        $this->seedWork();

        $this->actingAs($this->staff('admin'))->get('/admin/inbox')
            ->assertOk()
            ->assertSee('Shops awaiting approval')
            ->assertSee('Best sellers almost out of stock')
            ->assertSee('data-inbox="errors_unresolved" data-count="1"', false)
            ->assertSee('data-inbox-badge>8<', false);

        $this->actingAs($this->staff('admin'))->get('/admin/dashboard')
            ->assertOk()->assertSee('Needs attention')->assertSee('data-inbox="refunds_due"', false);
    }

    public function test_support_and_finance_only_see_rows_they_can_open(): void
    {
        $this->seedWork();

        $this->actingAs($this->staff('support'))->get('/admin/inbox')
            ->assertOk()
            ->assertSee('Open return requests')
            ->assertSee('BML payments failed')
            ->assertDontSee('Shops awaiting approval')
            ->assertDontSee('Unresolved errors')
            ->assertSee('data-inbox-badge>3<', false); // refunds due + open returns + BML failed

        $this->actingAs($this->staff('finance'))->get('/admin/inbox')
            ->assertOk()
            ->assertSee('Refunds due')
            ->assertSee('Unresolved errors')
            ->assertDontSee('Products pending review');

        $this->actingAs(User::factory()->create())->get('/admin/inbox')->assertForbidden();
    }
}
