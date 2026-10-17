<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentLink;
use App\Support\PayLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Admin → order → Send payment link: a signed page where the customer pays an unpaid order by card or
 * bank transfer without signing in.
 */
class PaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Setting::set([
            'payment_card_enabled' => 1, 'payment_transfer_enabled' => 1,
            'transfer_bank_name' => 'Bank of Maldives', 'transfer_account_name' => 'Iruali Pvt Ltd', 'transfer_account_number' => '7730000123456',
        ]);
    }

    protected function order(array $attributes = []): Order
    {
        return Order::factory()->create(array_merge(['status' => 'pending', 'payment_status' => 'unpaid', 'payment_method' => 'cod', 'total_amount' => 450, 'shipping_phone' => '7771234'], $attributes));
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_an_admin_sends_the_link_by_sms_and_email(): void
    {
        Notification::fake();
        $order = $this->order();

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertOk()->assertSee('data-payment-link', false)->assertSee('Send payment link');
        $this->post(route('admin.orders.payment-link', $order), ['by' => ['sms', 'mail']])->assertSessionHas('success')->assertSessionHas('payment_link');

        Notification::assertSentOnDemand(PaymentLink::class, function ($n, $channels, AnonymousNotifiable $to) use ($order) {
            return $to->routes === ['sms' => '+9607771234', 'mail' => $order->customerEmail()]
                && str_contains((string) $n->toSms($to), 'MVR 450.00') && str_contains($n->url, '/pay/'.$order->id);
        });
        $this->assertNotNull($order->fresh()->payment_link_sent_at);
        $this->assertTrue(AuditLog::where('action', 'payment.link_sent')->exists());

        // A paid order: no link
        $order->forceFill(['payment_status' => 'paid'])->save();
        $this->post(route('admin.orders.payment-link', $order), ['by' => ['mail']])->assertSessionHas('error');
    }

    public function test_the_page_works_without_signing_in_and_only_with_the_signature(): void
    {
        $order = $this->order();
        $url = PayLink::url($order);

        $this->get($url)->assertOk()->assertSee($order->order_number)->assertSee('MVR 450.00')
            ->assertSee('data-pay-transfer', false)->assertSee('7730000123456');
        $this->get(route('pay.show', $order))->assertForbidden();

        // Expired after 48 hours
        $this->travel(49)->hours();
        $this->get($url)->assertForbidden();
    }

    public function test_paying_by_card_switches_the_order_to_card_and_opens_bml(): void
    {
        $this->enableBml();
        $order = $this->order();
        $this->get(PayLink::url($order))->assertOk()->assertSee('data-pay-card', false);

        $cardAction = \Illuminate\Support\Facades\URL::temporarySignedRoute('pay.card', now()->addHour(), ['order' => $order->id]);
        $this->post($cardAction)->assertRedirect('https://pay.bml.test/txn_test');
        $this->assertSame('bml', $order->fresh()->payment_method);
        $this->assertTrue(AuditLog::where('action', 'payment.method_switched')->exists());
    }

    public function test_a_transfer_slip_switches_to_transfer_and_waits_for_the_admin(): void
    {
        Notification::fake();
        $order = $this->order(['payment_method' => 'bml']);
        $slip = \Illuminate\Support\Facades\URL::temporarySignedRoute('pay.slip', now()->addHour(), ['order' => $order->id]);

        $this->post($slip, ['slip' => UploadedFile::fake()->image('slip.jpg')])->assertRedirect();
        $order->refresh();
        $this->assertSame('transfer', $order->payment_method);
        $this->assertNotNull($order->payment_slip_uploaded_at);
        $this->get(PayLink::url($order))->assertSee('data-slip-received', false);
    }

    public function test_an_unpaid_order_with_a_live_link_is_not_cancelled(): void
    {
        $order = $this->order(['payment_method' => 'bml', 'created_at' => now()->subDays(2)]);
        $order->forceFill(['payment_link_sent_at' => now()->subHours(3)])->save();
        $old = $this->order(['payment_method' => 'bml', 'created_at' => now()->subDays(2)]);

        $this->artisan('orders:cancel-unpaid-card')->assertSuccessful();
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame('cancelled', $old->fresh()->status);
    }
}
