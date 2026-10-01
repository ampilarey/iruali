<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Services\PaymentService;
use App\Services\PayoutService;
use App\Support\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
    }

    protected function actions(): array
    {
        return AuditLog::orderBy('id')->pluck('action')->all();
    }

    public function test_record_captures_who_what_and_where(): void
    {
        $order = Order::factory()->create();

        $this->actingAs($this->admin);
        $log = Audit::record('order.status', $order, ['from' => 'pending', 'to' => 'shipped']);

        $this->assertSame($this->admin->id, $log->user_id);
        $this->assertSame(Order::class, $log->subject_type);
        $this->assertSame($order->id, $log->subject_id);
        $this->assertSame(['from' => 'pending', 'to' => 'shipped'], $log->changes);
        $this->assertNotNull($log->created_at);
        $this->assertSame(route('admin.orders.show', $order), $log->subjectUrl());
        $this->assertNull(Audit::record('x', null, [])->subject_type);
    }

    public function test_order_status_changes_cancellations_and_refunds_are_audited(): void
    {
        $order = Order::factory()->create(['status' => 'pending', 'payment_status' => 'paid', 'payment_method' => 'bml', 'total_amount' => 150]);

        $this->actingAs($this->admin)->post('/admin/orders/'.$order->id.'/status', ['status' => 'processing'])->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/orders/'.$order->id.'/status', ['status' => 'cancelled'])->assertRedirect();

        $this->assertSame(['order.status', 'refund.flagged', 'order.cancelled'], $this->actions());
        $status = AuditLog::where('action', 'order.status')->first();
        $this->assertSame(['from' => 'pending', 'to' => 'processing', 'order_number' => $order->order_number], $status->changes);
        $this->assertSame($this->admin->id, $status->user_id);
        $this->assertSame(150.0, (float) AuditLog::where('action', 'refund.flagged')->first()->changes['amount']);

        $this->assertTrue(app(PaymentService::class)->markRefunded($order->fresh(), 'BML-RF-77'));
        $this->assertSame('BML-RF-77', AuditLog::where('action', 'refund.recorded')->first()->changes['reference']);
    }

    public function test_payouts_are_audited(): void
    {
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Goods']);
        $shop->bankAccount()->create(['bank' => 'bml', 'account_name' => 'Reef Goods', 'account_number' => '7730000000001']);
        $order = Order::factory()->create(['status' => 'delivered', 'payment_status' => 'paid']);
        SellerOrder::create(['order_id' => $order->id, 'seller_id' => $shop->id, 'status' => 'delivered', 'subtotal' => 100, 'commission_rate' => 10, 'commission_amount' => 10, 'seller_earnings' => 90]);

        $payout = app(PayoutService::class)->createPayout($shop, null, 'TRX-1', null, $this->admin);

        $this->assertNotNull($payout);
        $log = AuditLog::where('action', 'payout.created')->first();
        $this->assertSame($payout->id, $log->subject_id);
        $this->assertSame('Reef Goods', $log->changes['shop']);
        $this->assertSame(90.0, (float) $log->changes['amount']);
        $this->assertSame(route('admin.payouts.show', $payout), $log->subjectUrl());
    }

    public function test_seller_product_settings_legal_and_voucher_actions_are_audited(): void
    {
        $seller = User::factory()->create(['is_seller' => true, 'seller_approved' => false, 'business_name' => 'Hulhumale Fresh']);
        $seller->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $product = Product::factory()->create(['is_active' => false]);

        $this->actingAs($this->admin)->post('/admin/sellers/'.$seller->id.'/approve')->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/sellers/'.$seller->id.'/suspend')->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/sellers/'.$seller->id.'/reject')->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/products/'.$product->id.'/approve')->assertRedirect();
        $this->actingAs($this->admin)->put('/admin/settings', ['loyalty_spend_per_point' => 10, 'referral_referrer_points' => 5, 'referral_referee_points' => 5, 'announcement_text' => 'Hi'])->assertRedirect();
        $this->actingAs($this->admin)->put('/admin/legal', ['legal_last_updated_date' => '1 Oct 2026'])->assertRedirect();
        $this->actingAs($this->admin)->post('/admin/vouchers', ['code' => 'EID10', 'type' => 'percent', 'amount' => 10, 'is_active' => 1])->assertRedirect();
        $voucher = Voucher::where('code', 'EID10')->firstOrFail();
        $this->actingAs($this->admin)->put('/admin/vouchers/'.$voucher->id, ['code' => 'EID10', 'type' => 'percent', 'amount' => 15, 'is_active' => 1])->assertRedirect();
        $this->actingAs($this->admin)->delete('/admin/vouchers/'.$voucher->id)->assertRedirect();

        $this->assertSame([
            'seller.approved', 'seller.suspended', 'seller.rejected', 'product.approved',
            'settings.saved', 'legal.saved', 'voucher.created', 'voucher.updated', 'voucher.deleted',
        ], $this->actions());
        $this->assertSame('Hulhumale Fresh', AuditLog::where('action', 'seller.approved')->first()->changes['business_name']);
        $this->assertContains('announcement_text', AuditLog::where('action', 'settings.saved')->first()->changes['keys']);
        $this->assertContains('amount', AuditLog::where('action', 'voucher.updated')->first()->changes['changed']);
        $this->assertTrue(AuditLog::get()->every(fn ($l) => $l->user_id === $this->admin->id));
    }

    public function test_audit_page_filters_by_action_user_and_date_and_links_subjects(): void
    {
        $order = Order::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($this->admin);
        Audit::record('order.status', $order, ['from' => 'pending', 'to' => 'shipped']);
        $this->actingAs($other);
        Audit::record('settings.saved', null, ['keys' => ['announcement_text']]);
        AuditLog::first()->forceFill(['created_at' => now()->subDays(10)])->save();

        $this->actingAs($this->admin)->get('/admin/audit')->assertOk()
            ->assertSee('Order status changed')->assertSee('Settings saved')
            ->assertSee('Order #'.$order->id)->assertSee(route('admin.orders.show', $order))
            ->assertSee($other->name);

        // The action dropdown always lists every label, so rows are told apart by their subject / changes
        $orderRow = 'Order #'.$order->id;
        $settingsRow = 'keys:';
        $this->actingAs($this->admin)->get('/admin/audit?action=settings.saved')->assertOk()->assertSee($settingsRow)->assertDontSee($orderRow);
        $this->actingAs($this->admin)->get('/admin/audit?user='.$this->admin->id)->assertOk()->assertSee($orderRow)->assertDontSee($settingsRow);
        $this->actingAs($this->admin)->get('/admin/audit?from='.now()->subDay()->toDateString())->assertOk()->assertSee($settingsRow)->assertDontSee($orderRow);
        $this->actingAs($this->admin)->get('/admin/audit?to='.now()->subDays(5)->toDateString())->assertOk()->assertSee($orderRow)->assertDontSee($settingsRow);
    }
}
