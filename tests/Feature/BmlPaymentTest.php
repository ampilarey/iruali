<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PaymentUpdated;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BmlPaymentTest extends TestCase
{
    use RefreshDatabase;

    protected const API = 'https://api.uat.merchants.bankofmaldives.com.mv/public';

    protected User $customer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.bml.api_key' => 'test-key',
            'services.bml.environment' => 'sandbox',
            'services.bml.base_uri' => self::API,
        ]);
        Notification::fake();

        $this->customer = User::factory()->create();
        $this->product = Product::factory()->create(['price' => 200, 'stock_quantity' => 10, 'is_active' => true]);
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'quantity' => 1, 'price' => 200]);
    }

    protected function checkout(string $method = 'bml')
    {
        return $this->actingAs($this->customer)->post('/orders', [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'delivery_zone' => 'islands',
            'payment_method' => $method, 'agree_terms' => '1',
        ]);
    }

    protected string $bmlState = 'CONFIRMED';

    protected ?int $bmlAmount = null;

    protected string $bmlId = 'txn_123';

    protected bool $bmlFaked = false;

    /**
     * Fake BML once; later calls just change what the fake answers.
     */
    protected function fakeBml(string $state = 'CONFIRMED', ?int $amount = null, string $id = 'txn_123'): void
    {
        [$this->bmlState, $this->bmlAmount, $this->bmlId] = [$state, $amount, $id];

        if ($this->bmlFaked) {
            return;
        }
        $this->bmlFaked = true;

        Http::fake([
            self::API.'/v2/transactions' => fn () => Http::response(['id' => $this->bmlId, 'url' => 'https://pay.bml.test/'.$this->bmlId, 'state' => 'INITIATED'], 201),
            self::API.'/v2/transactions/*' => function (Request $request) {
                $id = basename(parse_url($request->url(), PHP_URL_PATH));
                $local = PaymentTransaction::where('transaction_id', $id)->first();

                return Http::response([
                    'id' => $id,
                    'state' => $this->bmlState,
                    'amount' => $this->bmlAmount ?? $local?->amount,
                    'currency' => 'MVR',
                    'localId' => $local?->local_id,
                ]);
            },
        ]);
    }

    public function test_card_payment_is_offered_only_when_bml_is_configured(): void
    {
        $this->actingAs($this->customer)->get('/checkout')->assertSee('Card payment (BML)')->assertSee('value="bml" checked', false);

        config(['services.bml.api_key' => null]);
        $this->get('/checkout')->assertDontSee('Card payment (BML)');
        $this->checkout('bml')->assertSessionHasErrors('payment_method');
    }

    public function test_checkout_sends_the_customer_to_bml_with_the_order_total_in_laari(): void
    {
        $this->fakeBml();

        $this->checkout()->assertRedirect('https://pay.bml.test/txn_123');

        $order = Order::sole();
        $this->assertSame('bml', $order->payment_method);
        $this->assertSame('unpaid', $order->payment_status);

        Http::assertSent(function (Request $request) use ($order) {
            return $request->method() === 'POST'
                && $request->url() === self::API.'/v2/transactions'
                && $request->header('Authorization')[0] === 'test-key'
                && $request['amount'] === 27500 // MVR 200 + MVR 75 island delivery
                && $request['currency'] === 'MVR'
                && $request['localId'] === preg_replace('/[^A-Za-z0-9]/', '', $order->order_number).'P1'
                && ctype_alnum($request['localId'])
                && $request['paymentPortalExperience'] === ['externalWebsiteTermsAccepted' => true, 'externalWebsiteTermsUrl' => route('policies.terms')]
                && $request['redirectUrl'] === route('payments.bml.return', $order)
                && $request['webhook'] === route('payments.bml.webhook');
        });
    }

    public function test_bml_uat_is_paid_in_usd_and_live_payments_always_in_mvr(): void
    {
        $bml = app(\App\Services\BmlConnect::class);

        // BML's UAT only takes USD: the sandbox sends the same figure in USD unless told otherwise
        config(['services.bml.currency' => null]);
        $this->assertSame('USD', $bml->currency());
        config(['services.bml.currency' => 'mvr']);
        $this->assertSame('MVR', $bml->currency());

        // Live payments are MVR whatever BML_CURRENCY says
        config(['services.bml.environment' => 'production', 'services.bml.currency' => 'USD']);
        $this->assertSame('MVR', $bml->currency());

        // A UAT payment is created, stored and confirmed in USD
        config(['services.bml.environment' => 'sandbox', 'services.bml.currency' => 'USD']);
        Http::fake([
            self::API.'/v2/transactions' => Http::response(['id' => 'txn_usd', 'url' => 'https://pay.bml.test/txn_usd', 'state' => 'INITIATED'], 201),
            self::API.'/v2/transactions/*' => fn () => Http::response(['id' => 'txn_usd', 'state' => 'CONFIRMED', 'amount' => 27500, 'currency' => 'USD', 'localId' => PaymentTransaction::sole()->local_id]),
        ]);

        $this->checkout()->assertRedirect('https://pay.bml.test/txn_usd');
        $order = Order::sole();
        Http::assertSent(fn (Request $request) => $request->method() === 'POST' && $request['currency'] === 'USD' && $request['amount'] === 27500);
        $this->assertSame('USD', PaymentTransaction::sole()->currency);

        $this->get(route('payments.bml.return', $order).'?transactionId=txn_usd');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_confirmed_payment_marks_the_order_paid_once(): void
    {
        $this->fakeBml('CONFIRMED');
        $this->checkout();
        $order = Order::sole();

        // The redirect's "state" is ignored; BML's API is asked
        $this->get(route('payments.bml.return', $order).'?transactionId=txn_123&state=CANCELLED')
            ->assertRedirect(route('orders.show', $order));

        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertSame('CONFIRMED', PaymentTransaction::sole()->state);

        // Returning again (or the webhook) doesn't confirm twice
        $this->get(route('payments.bml.return', $order).'?transactionId=txn_123');
        // The card order is announced once, now that it is paid ("we have your order")
        Notification::assertSentToTimes($this->customer, \App\Notifications\OrderPlaced::class, 1);
        Notification::assertNotSentTo($this->customer, PaymentUpdated::class);

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Paid by card')->assertDontSee('Pay MVR');
        // The customer sees "Paid", not the order's internal "pending" (waiting for the shop to start)
        $this->assertSame('Paid', $order->fresh()->customerStatusLabel());
        $order->forceFill(['payment_status' => 'unpaid'])->save();
        $this->assertSame('Waiting for payment', $order->fresh()->customerStatusLabel());
    }

    public function test_a_redirect_claiming_success_is_not_trusted(): void
    {
        $this->fakeBml('CANCELLED');
        $this->checkout();
        $order = Order::sole();

        $this->get(route('payments.bml.return', $order).'?transactionId=txn_123&state=CONFIRMED');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->actingAs($this->customer)->get(route('orders.show', $order))
            ->assertSee('did not go through')
            ->assertSee(route('payments.bml.pay', $order));
    }

    public function test_confirmed_transaction_with_the_wrong_amount_is_not_accepted(): void
    {
        $this->fakeBml('CONFIRMED', amount: 100);
        $this->checkout();
        $order = Order::sole();

        $this->get(route('payments.bml.return', $order).'?transactionId=txn_123');

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('MISMATCH', PaymentTransaction::sole()->state);
    }

    public function test_customer_can_try_again_with_a_new_transaction(): void
    {
        $this->fakeBml('FAILED', id: 'txn_1');
        $this->checkout();
        $order = Order::sole();
        $this->get(route('payments.bml.return', $order).'?transactionId=txn_1');

        $this->fakeBml('CONFIRMED', id: 'txn_2');
        $this->actingAs($this->customer)->post(route('payments.bml.pay', $order))->assertRedirect('https://pay.bml.test/txn_2');
        $this->assertSame(preg_replace('/[^A-Za-z0-9]/', '', $order->order_number).'P2', PaymentTransaction::where('transaction_id', 'txn_2')->value('local_id'));

        $this->actingAs(User::factory()->create())->post(route('payments.bml.pay', $order))->assertForbidden();
    }

    public function test_webhook_needs_a_valid_signature_and_then_checks_the_api(): void
    {
        $this->fakeBml('CONFIRMED');
        $this->checkout();
        $order = Order::sole();

        $this->postJson(route('payments.bml.webhook'), ['transactionId' => 'txn_123', 'state' => 'CONFIRMED'], [
            'X-Signature-Nonce' => 'n1', 'X-Signature-Timestamp' => (string) time(), 'X-Signature' => 'forged',
        ])->assertForbidden();
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Only the signature counts, as in Akuru's live BML setup: BML does not document the
        // timestamp's format, and a late retry must still get through (the API is asked anyway)
        $timestamp = now()->subHour()->toIso8601String();
        $this->postJson(route('payments.bml.webhook'), ['transactionId' => 'txn_123', 'state' => 'CONFIRMED'], [
            'X-Signature-Nonce' => 'n1', 'X-Signature-Timestamp' => $timestamp,
            'X-Signature' => hash('sha256', 'n1'.$timestamp.'test-key'),
        ])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_a_stranger_return_syncs_payment_without_showing_the_order(): void
    {
        $this->fakeBml('CONFIRMED');
        $this->checkout();
        $order = Order::sole();

        // The checkout request left the payer on the guard and the return flag in the
        // shared test session. A stranger has neither.
        $this->actingAsGuest();
        $this->app['session']->driver()->flush();

        $this->get(route('payments.bml.return', $order).'?transactionId=txn_123')
            ->assertRedirect(route('login'))
            ->assertSessionHas('info');

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertGuest();
    }

    public function test_webhook_signed_with_the_portal_secret_is_accepted(): void
    {
        config(['services.bml.webhook_secret' => 'whsec']);
        $this->fakeBml('CONFIRMED');
        $this->checkout();
        $order = Order::sole();
        $body = json_encode(['transactionId' => 'txn_123', 'state' => 'CONFIRMED']);

        $this->call('POST', route('payments.bml.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BML_SIGNATURE' => hash_hmac('sha256', $body, 'wrong')], $body)
            ->assertForbidden();
        $this->call('POST', route('payments.bml.webhook'), [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_BML_SIGNATURE' => hash_hmac('sha256', $body, 'whsec')], $body)
            ->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_auth_header_modes(): void
    {
        $this->fakeBml();
        config(['services.bml.auth_mode' => 'bearer_basic', 'services.bml.app_id' => 'app1']);
        $this->checkout();

        Http::assertSent(fn (Request $r) => $r->method() === 'POST' && $r->header('Authorization')[0] === 'Bearer '.base64_encode('test-key:app1'));
    }

    public function test_auto_sends_the_plain_key_to_the_uat_and_bearer_for_a_live_jwt_key(): void
    {
        $header = fn () => (fn () => $this->authorizationHeader())->call(app(\App\Services\BmlConnect::class));
        config(['services.bml.auth_mode' => 'auto', 'services.bml.api_key' => 'eyJhbGciOiJIUzI1NiJ9.x.y']);

        // BML's UAT refuses "Bearer" with 401 PP-C-004 (as Bake & Grill found): the key as it is
        $this->assertSame('eyJhbGciOiJIUzI1NiJ9.x.y', $header());

        config(['services.bml.environment' => 'production']);
        $this->assertSame('Bearer eyJhbGciOiJIUzI1NiJ9.x.y', $header());
        config(['services.bml.api_key' => 'plain-live-key']);
        $this->assertSame('plain-live-key', $header());
    }

    public function test_bml_being_down_keeps_the_order_and_offers_pay_now(): void
    {
        Http::fake([self::API.'/*' => Http::response(['message' => 'down'], 503)]);

        $this->checkout();
        $order = Order::sole();

        $this->assertSame('pending', $order->status);
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee(route('payments.bml.pay', $order));
    }

    public function test_unpaid_card_orders_are_cancelled_after_a_day_and_stock_returns(): void
    {
        $this->fakeBml('EXPIRED');
        $this->checkout();
        $order = Order::sole();
        $this->assertSame(9, $this->product->fresh()->stock_quantity);

        $this->artisan('orders:cancel-unpaid-card')->assertSuccessful();
        $this->assertSame('pending', $order->fresh()->status); // too new

        $this->travel(25)->hours();
        $this->artisan('orders:cancel-unpaid-card')->assertSuccessful();

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(10, $this->product->fresh()->stock_quantity);
    }

    public function test_card_payment_is_the_only_method_and_checkout_closes_without_bml(): void
    {
        $this->actingAs($this->customer)->get('/checkout')->assertSee('value="bml"', false)
            ->assertDontSee('value="cod"', false)->assertDontSee('value="bank_transfer"', false);
        $this->checkout('cod')->assertSessionHasErrors('payment_method');
        $this->checkout('bank_transfer')->assertSessionHasErrors('payment_method');

        config(['services.bml.api_key' => null]);
        $this->get('/checkout')->assertSee('Payment is not available right now')->assertDontSee('name="payment_method"', false);
        $this->checkout('bml')->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());
    }

    public function test_admin_sees_attempts_and_can_recheck_with_bml(): void
    {
        $this->fakeBml('QR_CODE_GENERATED');
        $this->checkout();
        $order = Order::sole();

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertSee('txn_123')->assertSee('Check with BML');

        $this->fakeBml('CONFIRMED');
        $this->post(route('admin.orders.bml-sync', $order))->assertSessionHas('success', 'BML confirms this order is paid.');
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    public function test_the_reconcile_job_records_a_payment_whose_return_and_webhook_were_lost(): void
    {
        $this->fakeBml('INITIATED');
        $this->checkout();
        $order = Order::sole();
        $this->assertSame('unpaid', $order->payment_status);

        // Still open at BML, and too new to check
        $this->artisan('payments:reconcile-bml')->expectsOutputToContain('Checked 0')->assertSuccessful();
        $this->travel(5)->minutes();
        $this->artisan('payments:reconcile-bml')->expectsOutputToContain('Checked 1 open card payment(s); 0 went through.')->assertSuccessful();

        // The customer paid, but neither the return nor the webhook reached iruali
        $this->fakeBml('CONFIRMED');
        $this->artisan('payments:reconcile-bml')->expectsOutputToContain('1 went through')->assertSuccessful();
        $this->assertSame('paid', $order->fresh()->payment_status);

        // Nothing left to check
        $this->artisan('payments:reconcile-bml')->expectsOutputToContain('Checked 0')->assertSuccessful();
    }

    public function test_money_arriving_for_a_cancelled_order_is_flagged_for_a_refund(): void
    {
        $this->fakeBml('INITIATED');
        $this->checkout();
        $order = Order::sole();
        app(\App\Services\OrderService::class)->updateOrderStatus($order, 'cancelled');

        $this->travel(5)->minutes();
        $this->fakeBml('CONFIRMED');
        $this->artisan('payments:reconcile-bml')->assertSuccessful();

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('due', $order->refund_status);
        $this->assertSame('Paid after the order was cancelled', $order->refund_reason);
    }
}
