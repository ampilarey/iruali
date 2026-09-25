<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Role;
use App\Models\Setting;
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
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'delivery_zone' => 'islands',
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
        Notification::assertSentToTimes($this->customer, PaymentUpdated::class, 1);

        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Paid by card')->assertDontSee('Pay MVR');
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
            'X-Signature-Nonce' => 'n1', 'X-Signature-Timestamp' => '1700000000', 'X-Signature' => 'forged',
        ])->assertForbidden();
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        $this->postJson(route('payments.bml.webhook'), ['transactionId' => 'txn_123', 'state' => 'CONFIRMED'], [
            'X-Signature-Nonce' => 'n1', 'X-Signature-Timestamp' => '1700000000',
            'X-Signature' => hash('sha256', 'n1'.'1700000000'.'test-key'),
        ])->assertOk();

        $this->assertSame('paid', $order->fresh()->payment_status);
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

    public function test_cash_on_delivery_can_be_turned_off_but_not_while_cards_are_off(): void
    {
        Setting::set(['payment_cod_enabled' => '0']);
        $this->actingAs($this->customer)->get('/checkout')->assertDontSee('value="cod"', false);
        $this->checkout('cod')->assertSessionHasErrors('payment_method');

        config(['services.bml.api_key' => null]);
        $this->get('/checkout')->assertSee('value="cod"', false);
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
}
