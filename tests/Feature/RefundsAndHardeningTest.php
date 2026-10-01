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
use App\Notifications\RefundDue;
use App\Notifications\RefundRecorded;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Traits\SecureFileUpload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Money that must go back to a customer, and the hardening from the audit (API limits, headers, uploads, seeders).
 */
class RefundsAndHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected const API = 'https://bml.test/public';

    protected User $customer;

    protected string $bmlState = 'CONFIRMED';

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Setting::set(['contact_email' => 'hello@iruali.mv']);
        config(['services.bml.api_key' => 'test-key', 'services.bml.environment' => 'sandbox', 'services.bml.base_uri' => self::API]);

        Http::fake([
            self::API.'/v2/transactions' => fn () => Http::response(['id' => 'txn_'.uniqid(), 'url' => 'https://pay.bml.test/x', 'state' => 'INITIATED'], 201),
            self::API.'/v2/transactions/*' => function ($request) {
                $local = PaymentTransaction::where('transaction_id', basename(parse_url($request->url(), PHP_URL_PATH)))->first();

                return Http::response(['id' => $local?->transaction_id, 'state' => $this->bmlState, 'amount' => $local?->amount, 'currency' => 'MVR', 'localId' => $local?->local_id]);
            },
        ]);

        $this->customer = User::factory()->create();
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    protected function cardOrder(float $price = 250): Order
    {
        $product = Product::factory()->create(['price' => $price, 'stock_quantity' => 5]);
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $price]);

        $result = app(OrderService::class)->createOrderFromCart($this->customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'payment_method' => 'bml',
        ]);

        return $result['order'];
    }

    public function test_a_payment_that_lands_after_cancellation_is_not_confirmed_but_owed_back(): void
    {
        $order = $this->cardOrder();
        $payments = app(PaymentService::class);
        $payments->startBmlPayment($order);
        $transaction = $order->paymentTransactions()->first();

        $this->actingAs($this->customer)->post(route('orders.cancel', $order));
        $this->assertSame('cancelled', $order->fresh()->status);

        // The customer finishes paying in the tab they left open
        $payments->syncBml($transaction);

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotSame('paid', $order->payment_status, 'a cancelled order is never marked paid');
        $this->assertSame('due', $order->refund_status);
        $this->assertEquals($order->total_amount, $order->refund_amount);
        $this->assertSame('CONFIRMED', $transaction->fresh()->state);
        Notification::assertSentTo(new AnonymousNotifiable, RefundDue::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'hello@iruali.mv');

        // Syncing again changes nothing
        $payments->syncBml($transaction->fresh());
        $this->assertEquals($order->total_amount, $order->fresh()->refund_amount);
    }

    public function test_cancelling_a_paid_order_records_the_refund_and_admin_closes_it(): void
    {
        $order = $this->cardOrder();
        app(PaymentService::class)->confirm($order);

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.orders.status', $order), ['status' => 'cancelled']);

        $order->refresh();
        $this->assertSame(['cancelled', 'paid', 'due'], [$order->status, $order->payment_status, $order->refund_status]);
        $this->assertEquals($order->total_amount, $order->refund_amount);

        $this->get(route('admin.returns'))->assertOk()->assertSee('Refunds due')->assertSee($order->order_number);
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Refund due');
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()->assertSee('on its way to you');

        $this->actingAs($admin)->post(route('admin.orders.refunded', $order), ['refund_reference' => 'BML-RF-1'])->assertSessionHas('success');
        $this->assertSame('refunded', $order->fresh()->refund_status);
        Notification::assertSentTo($this->customer, RefundRecorded::class, fn ($n) => $n->order->refund_reference === 'BML-RF-1');
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('BML-RF-1');

        // Can't record it twice
        $this->actingAs($admin)->post(route('admin.orders.refunded', $order), ['refund_reference' => 'again'])->assertSessionHas('error');
    }

    public function test_a_second_confirmed_payment_is_flagged_as_a_duplicate(): void
    {
        $order = $this->cardOrder();
        $payments = app(PaymentService::class);
        $payments->startBmlPayment($order);
        $payments->startBmlPayment($order->fresh());
        [$first, $second] = $order->paymentTransactions()->orderBy('id')->get();

        $payments->syncBml($first);
        $this->assertSame('paid', $order->fresh()->payment_status);

        $payments->syncBml($second);
        $this->assertSame('DUPLICATE', $second->fresh()->state);
        $this->assertSame('due', $order->fresh()->refund_status);
        $this->assertEquals($order->total_amount, $order->fresh()->refund_amount);
    }

    public function test_admin_cannot_mark_a_card_or_cancelled_order_as_paid_by_hand(): void
    {
        $order = $this->cardOrder();
        $this->actingAs($this->admin())->post(route('admin.orders.payment', $order), ['action' => 'confirm'])->assertSessionHas('error');
        $this->assertNotSame('paid', $order->fresh()->payment_status);
    }

    public function test_api_sign_in_is_rate_limited_and_refuses_two_step_accounts(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/login', ['email' => 'x@example.com', 'password' => 'wrong-password-123'])->assertStatus(401);
        }
        $this->postJson('/api/v1/login', ['email' => 'x@example.com', 'password' => 'wrong-password-123'])->assertStatus(429);

        $user = User::factory()->create(['two_factor_enabled' => true, 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP')]);
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class)
            ->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password'])->assertStatus(403);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_every_response_carries_the_security_headers(): void
    {
        $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->getJson('/api/v1/products')->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_uploads_are_stored_with_the_extension_of_their_real_type(): void
    {
        Storage::fake('public');
        $uploader = new class
        {
            use SecureFileUpload;

            public function put(UploadedFile $file): ?string
            {
                return $this->storeFileSecurely($file, 'products');
            }
        };

        $gifBytes = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        // A real GIF uploaded under a .html name is stored as .gif, never as a page the browser would run
        $path = $uploader->put(UploadedFile::fake()->createWithContent('innocent.html', $gifBytes)->mimeType('image/gif'));

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.gif', $path);
        $this->assertNull($uploader->put(UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>')->mimeType('text/html')));
    }

    public function test_demo_accounts_are_not_seeded_on_production(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();
        $this->app['env'] = 'testing';

        $this->assertFalse(User::where('email', 'admin@example.com')->exists(), 'no default admin');
        $this->assertSame(0, User::where('is_seller', true)->count(), 'no demo shops');
        $this->assertTrue(Role::where('name', 'admin')->exists(), 'reference data still seeds');
    }
}
