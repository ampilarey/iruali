<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentSlipUploaded;
use App\Notifications\ShopOrderPaid;
use App\Services\ManualPaymentService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * Bank transfer and cash on delivery, switched on by admins (Admin → Settings → Payment methods)
 * for when card payment is not available: checkout, the slip, confirming the money, the timeout and
 * the cash-on-delivery rules.
 */
class ManualPaymentTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        Setting::set([
            'delivery_fee_greater_male' => 25, 'delivery_fee_islands' => 75, 'free_delivery_over' => 0, 'contact_email' => 'hello@iruali.mv',
            'payment_card_enabled' => 0, 'payment_transfer_enabled' => 1, 'payment_cod_enabled' => 1,
            'transfer_bank_name' => 'Bank of Maldives', 'transfer_account_name' => 'Iruali Pvt Ltd', 'transfer_account_number' => '7730000123456',
        ]);
    }

    public function test_admins_choose_the_methods_offered_at_checkout(): void
    {
        $this->enableBml();
        Setting::set(['payment_card_enabled' => 1]);
        $this->assertSame(['bml', 'transfer', 'cod'], array_keys(app(PaymentService::class)->methods()));

        // Card off while the gateway has a problem; transfer needs an account number
        Setting::set(['payment_card_enabled' => 0, 'transfer_account_number' => '']);
        $this->assertSame(['cod'], array_keys(app(PaymentService::class)->methods()));

        $customer = User::factory()->create();
        $this->cartFor($customer, [[$this->product($this->shop('Reef Goods'), 100), 1]]);
        $this->actingAs($customer)->get('/checkout')->assertOk()->assertSee('Cash on delivery')->assertDontSee('value="bml"', false);
        $this->placeOrder($customer, ['payment_method' => 'bml'])->assertSessionHasErrors('payment_method');
    }

    public function test_bank_transfer_slip_and_confirmation(): void
    {
        $customer = User::factory()->create();
        $shop = $this->shop('Reef Goods');
        $this->cartFor($customer, [[$this->product($shop, 100), 1]]);

        $this->placeOrder($customer, ['payment_method' => 'transfer'])->assertRedirect();
        $order = Order::sole();
        $this->assertSame('transfer', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);

        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('data-transfer-box', false)->assertSee('7730000123456')->assertSee($order->order_number)->assertSee('MVR 125.00');
        // The shop is told to wait for the money
        $this->actingAs($shop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Please wait until the order shows as paid');

        // The slip: a photo, kept privately; the admins are emailed
        $this->actingAs($customer)->post(route('orders.payment-slip', $order), ['slip' => UploadedFile::fake()->create('notes.txt', 5, 'text/plain')])->assertSessionHasErrors('slip');
        $this->post(route('orders.payment-slip', $order), ['slip' => UploadedFile::fake()->image('slip.jpg')])->assertRedirect();
        $order->refresh();
        $this->assertNotNull($order->payment_slip_uploaded_at);
        Storage::disk('local')->assertExists($order->payment_slip_path);
        Notification::assertSentOnDemand(PaymentSlipUploaded::class);
        $this->get(route('orders.show', $order))->assertSee('data-slip-received', false);

        // Someone else's order: no
        $this->actingAs(User::factory()->create())->post(route('orders.payment-slip', $order), ['slip' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();

        // The admin sees the slip and confirms the money
        $admin = $this->admin();
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('View slip')->assertSee('Confirm payment received');
        $this->get(route('admin.orders.payment-slip', $order))->assertOk();
        $this->post(route('admin.orders.confirm-payment', $order), ['payment_reference' => 'BML-REF-991'])->assertSessionHas('success');

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('BML-REF-991', $order->payment_reference);
        $this->assertSame($admin->id, $order->payment_confirmed_by);
        Notification::assertSentTo($shop, ShopOrderPaid::class);
        $this->assertTrue(AuditLog::where('action', 'payment.confirmed_manually')->exists());

        // Only once
        $this->post(route('admin.orders.confirm-payment', $order))->assertSessionHas('error');
    }

    public function test_finance_staff_confirm_payments_and_support_staff_cannot(): void
    {
        $customer = User::factory()->create();
        $this->cartFor($customer, [[$this->product($this->shop('Reef Goods'), 100), 1]]);
        $this->placeOrder($customer, ['payment_method' => 'transfer']);
        $order = Order::sole();

        $this->actingAs($this->withRole('support'))->post(route('admin.orders.confirm-payment', $order))->assertForbidden();
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->actingAs($this->withRole('finance'))->post(route('admin.orders.confirm-payment', $order))->assertSessionHas('success');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_transfer_orders_with_no_slip_in_time_are_cancelled_and_the_stock_goes_back(): void
    {
        Setting::set(['transfer_pay_within_hours' => 12]);
        $customer = User::factory()->create();
        $product = $this->product($this->shop('Reef Goods'), 100);
        $this->cartFor($customer, [[$product, 2]]);
        $this->placeOrder($customer, ['payment_method' => 'transfer']);
        $waiting = Order::sole();
        $this->assertSame(18, $product->fresh()->stock_quantity);

        $this->cartFor($customer, [[$product, 1]]);
        $this->placeOrder($customer, ['payment_method' => 'transfer']);
        $withSlip = Order::latest('id')->first();
        $this->actingAs($customer)->post(route('orders.payment-slip', $withSlip), ['slip' => UploadedFile::fake()->image('slip.png')]);

        $this->travel(13)->hours();
        $this->artisan('orders:cancel-unpaid-transfer')->expectsOutputToContain('Cancelled 1')->assertSuccessful();

        $this->assertSame('cancelled', $waiting->fresh()->status);
        $this->assertSame('pending', $withSlip->fresh()->status, 'a slip waits for the admins');
        $this->assertSame(19, $product->fresh()->stock_quantity);
    }

    public function test_cash_on_delivery_rules_and_cash_received(): void
    {
        $customer = User::factory()->create();
        $reef = $this->shop('Reef Goods');
        $fish = $this->shop('Fish Corner');

        // Two shops: no
        $this->cartFor($customer, [[$this->product($reef, 100), 1], [$this->product($fish, 50), 1]]);
        $this->placeOrder($customer, ['payment_method' => 'cod'])->assertRedirect();
        $this->assertSame(0, Order::count());

        // Over the limit, or outside Greater Malé when it is limited to it: no
        Setting::set(['cod_max_amount' => 100, 'cod_greater_male_only' => 1]);
        $this->cartFor($customer, [[$this->product($reef, 100), 1]]);
        $this->placeOrder($customer, ['payment_method' => 'cod']);
        $this->assertSame(0, Order::count(), '125 with delivery is over 100');

        Setting::set(['cod_max_amount' => 0]);
        $this->cartFor($customer, [[$this->product($reef, 100), 1]]);
        $this->placeOrder($customer, ['payment_method' => 'cod', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Seenu', 'delivery_zone' => 'islands']);
        $this->assertSame(0, Order::count(), 'Greater Malé only');

        // One shop, in Malé: yes; the shop collects the cash
        $this->placeOrder($customer, ['payment_method' => 'cod'])->assertRedirect();
        $order = Order::sole();
        $this->assertSame('cod', $order->payment_method);
        $this->actingAs($customer)->get(route('orders.show', $order))->assertSee('data-cod-box', false)->assertSee('MVR 125.00');
        $this->actingAs($reef)->get(route('seller.orders.show', $order))->assertSee('collect MVR 125.00 from the customer');

        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertSee('Cash received');
        $this->post(route('admin.orders.confirm-payment', $order), ['payment_reference' => 'Cash from Reef Goods'])->assertSessionHas('success');
        $this->assertSame('paid', $order->fresh()->payment_status);
        Notification::assertNotSentTo($reef, ShopOrderPaid::class);
    }

    public function test_guests_upload_the_slip_through_their_order_link(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'guest@example.com', 'guest_token' => str_repeat('a', 40), 'payment_method' => 'transfer', 'payment_status' => 'unpaid', 'status' => 'pending', 'total_amount' => 80]);

        $this->get($order->guestUrl())->assertOk()->assertSee('data-transfer-box', false);
        $url = \Illuminate\Support\Facades\URL::signedRoute('guest.orders.payment-slip', ['order' => $order->id, 'token' => $order->guest_token]);
        $this->post($url, ['slip' => UploadedFile::fake()->create('slip.pdf', 50, 'application/pdf')])->assertRedirect();
        $this->assertNotNull($order->fresh()->payment_slip_uploaded_at);

        $this->post(route('guest.orders.payment-slip', ['order' => $order->id, 'token' => $order->guest_token]), ['slip' => UploadedFile::fake()->image('x.jpg')])->assertForbidden();
    }

    public function test_policies_describe_the_methods_on_offer(): void
    {
        $this->get(route('policies.terms'))->assertOk()->assertSee('Bank transfer:')->assertSee('Cash on delivery')->assertDontSee('Card payment is the only payment method');
        $this->get(route('policies.refunds'))->assertOk()->assertSee('Bank transfer and cash payments');

        Setting::set(['payment_transfer_enabled' => 0, 'payment_cod_enabled' => 0]);
        $this->get(route('policies.terms'))->assertOk()->assertSee('Card payment is the only payment method');
        $this->assertFalse(ManualPaymentService::transferEnabled());
    }
}
