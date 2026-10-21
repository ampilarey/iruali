<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\SellerAdjustment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\BookingBalanceDue;
use App\Notifications\PaymentUpdated;
use App\Notifications\PreorderArrived;
use App\Notifications\ShopOrderPaid;
use App\Services\BookingFeeService;
use App\Services\FulfilmentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PreorderService;
use App\Services\ProductReviewService;
use App\Services\WalletService;
use App\Support\AdminInbox;
use App\Support\PayLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * Pre-orders with a booking fee (App\Services\BookingFeeService), for goods a shop buys abroad only
 * once a customer orders them: the product form, ordering them on their own, paying the fee then the
 * balance (card, bank transfer, wallet), the balance request when the stock arrives, reminders and
 * overdue balances, and who keeps the fee when the order is cancelled.
 *
 * The clock is 20 Oct 2026; the camera costs MVR 1,000 with a 30% booking fee and ships about 14 days
 * after the order (3 Nov). Delivery to the islands is MVR 75, so one camera is MVR 1,075: MVR 300
 * now and MVR 775 when it arrives.
 */
class BookingFeePreorderTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    protected User $shop;

    protected int $bmlCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 75, 'contact_email' => 'ops@iruali.test', 'default_commission_rate' => 10]);
        $this->shop = $this->seller('Lens House');

        // BML: each new payment is txn_1, txn_2...; asking about one says it is confirmed for its own amount
        config(['services.bml.api_key' => 'test-key', 'services.bml.environment' => 'sandbox', 'services.bml.base_uri' => 'https://bml.test/public']);
        Http::fake([
            'bml.test/public/v2/transactions' => function () {
                $this->bmlCalls++;

                return Http::response(['id' => 'txn_'.$this->bmlCalls, 'url' => 'https://pay.bml.test/txn_'.$this->bmlCalls, 'state' => 'INITIATED'], 201);
            },
            'bml.test/public/v2/transactions/*' => function (Request $request) {
                $id = basename((string) parse_url($request->url(), PHP_URL_PATH));
                $local = PaymentTransaction::where('transaction_id', $id)->first();

                return Http::response(['id' => $id, 'state' => 'CONFIRMED', 'amount' => $local?->amount, 'currency' => 'MVR', 'localId' => $local?->local_id]);
            },
        ]);
    }

    /** The camera: MVR 1,000, a 30% booking fee, ships about 14 days after the order. */
    protected function bookingProduct(?User $shop = null, array $attributes = []): Product
    {
        $product = $this->preorderProduct($shop ?? $this->shop, ['price' => 1000, 'name' => 'Mirrorless camera']);
        $product->forceFill(array_merge(['preorder_ship_date' => null, 'preorder_lead_days' => 14, 'preorder_deposit_type' => 'percent', 'preorder_deposit_value' => 30], $attributes))->save();

        return $product->fresh();
    }

    /**
     * A booking-fee order placed through the service by card, its booking fee paid unless $pay is false.
     */
    protected function bookedOrder(?User $customer = null, ?Product $product = null, bool $pay = true): Order
    {
        $customer ??= User::factory()->create();
        $cart = $this->cartWith($customer, [[$product ?? $this->bookingProduct(), 1]]);
        $result = app(OrderService::class)->createOrderFromCart($customer, $this->shippingData() + ['agree_booking_fee' => true], $cart);
        $this->assertTrue($result['success'], $result['message']);
        if ($pay) {
            app(PaymentService::class)->confirm($result['order']->fresh());
        }

        return $result['order']->fresh();
    }

    /** The balance is asked for: the camera's stock arrives. */
    protected function stockArrives(Order $order): Order
    {
        app(PreorderService::class)->receiveStock($order->items()->sole()->product, [0 => 1]);

        return $order->fresh();
    }

    protected function bookingFields(array $extra = []): array
    {
        return array_merge($this->checkoutFields(), ['agree_booking_fee' => '1'], $extra);
    }

    public function test_the_product_form_takes_a_booking_fee_and_a_number_of_days_after_the_order(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $fields = ['name_en' => 'Mirrorless camera', 'sku' => 'CAM-1', 'category_id' => $category->id, 'price' => 1000, 'stock_quantity' => 0, 'reorder_point' => 0,
            'preorder_form' => 1, 'preorder_enabled' => 1, 'preorder_limit' => 3];

        $this->actingAs($this->shop)->get(route('seller.products.create'))->assertOk()
            ->assertSee('data-preorder-deposit', false)
            ->assertSee('Some days after each order (you order it for each customer)')
            ->assertSee('A booking fee: a percentage of the price');

        // Some days after the order: the days are needed, not a date
        $this->post(route('seller.products.store'), $fields + ['preorder_ships' => 'days'])
            ->assertSessionHasErrors('preorder_lead_days')->assertSessionDoesntHaveErrors('preorder_ship_date');
        // A booking fee needs its amount, and a percentage is at most 90
        $days = $fields + ['preorder_ships' => 'days', 'preorder_lead_days' => 14];
        $this->post(route('seller.products.store'), $days + ['preorder_deposit_type' => 'percent'])->assertSessionHasErrors('preorder_deposit_value');
        $this->post(route('seller.products.store'), $days + ['preorder_deposit_type' => 'percent', 'preorder_deposit_value' => 95])->assertSessionHasErrors('preorder_deposit_value');
        $this->assertSame(0, Product::count());

        $this->post(route('seller.products.store'), $days + ['preorder_deposit_type' => 'percent', 'preorder_deposit_value' => 30, 'preorder_closes_on' => '2026-12-31'])
            ->assertRedirect(route('seller.products.index'));
        $product = Product::where('sku', 'CAM-1')->sole();
        $this->assertNull($product->preorder_ship_date);
        $this->assertSame(14, $product->preorder_lead_days);
        $this->assertSame('percent', $product->preorder_deposit_type);
        $this->assertEquals(30, $product->preorder_deposit_value);
        $this->assertSame('2026-12-31', $product->preorder_closes_on->toDateString(), 'pre-orders can stay open past "today plus the days"');
        $this->assertSame('2026-11-03', $product->preorderShipDate()->toDateString());
        $this->assertTrue($product->hasBookingFee());

        $this->get(route('seller.products.edit', $product))->assertOk()
            ->assertSee('value="14"', false)->assertSee('value="days" checked', false)->assertSee('value="percent" selected', false);

        // A date and a fixed amount per item
        $this->put(route('seller.products.update', $product), $fields + ['preorder_ships' => 'date', 'preorder_ship_date' => '2026-11-20', 'preorder_deposit_type' => 'fixed', 'preorder_deposit_value' => 250])
            ->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertSame('2026-11-20', $product->preorder_ship_date->toDateString());
        $this->assertNull($product->preorder_lead_days);
        $this->assertSame('fixed', $product->preorder_deposit_type);
        $this->assertSame(250.0, app(BookingFeeService::class)->unitFee($product, 1000));

        // The full price again: no booking fee
        $this->put(route('seller.products.update', $product), $fields + ['preorder_ship_date' => '2026-11-20', 'preorder_deposit_type' => 'none', 'preorder_deposit_value' => 250])
            ->assertSessionHasNoErrors();
        $this->assertFalse($product->fresh()->hasBookingFee());
    }

    public function test_the_product_page_and_the_cart_show_the_booking_fee_and_the_days_after_ordering(): void
    {
        $camera = $this->bookingProduct();

        $this->get(route('products.show', $camera))->assertOk()
            ->assertSee('Pre-order: ships about 14 days after you order')
            ->assertSee('data-booking-fee', false)
            ->assertSee('Booking fee now: MVR 300.00 per item (30% of the price). You pay the rest within 7 days of it arriving, before it is delivered.')
            ->assertSee('If you cancel, the booking fee is not refunded.');

        $customer = User::factory()->create();
        $this->cartWith($customer, [[$camera, 2]]);
        $this->actingAs($customer)->get(route('cart'))->assertOk()
            ->assertSee('Pre-order · ships around 3 Nov')
            ->assertSee('Booking fee now: MVR 600.00, the rest when it arrives')
            ->assertDontSee('data-booking-fee-problem', false);

        // Feeds and search engines get the date it would ship if ordered today
        $this->assertSame('2026-11-03', $camera->preorderShipDate()->toDateString());
        $this->assertTrue(app(PreorderService::class)->isPreorderable($camera));
    }

    public function test_booking_fee_items_are_ordered_on_their_own_and_from_one_shop(): void
    {
        $camera = $this->bookingProduct();
        $strap = $this->stockedProduct($this->shop, 50, 5);
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$camera, 1], [$strap, 1]]);
        $alone = 'Pre-orders with a booking fee are ordered on their own. Please remove the other items from your cart and order them separately.';

        $this->actingAs($customer)->get(route('cart'))->assertOk()->assertSee('data-booking-fee-problem', false)->assertSee($alone);
        $this->get(route('checkout'))->assertRedirect(route('cart'))->assertSessionHas('error', $alone);

        // Placing the order anyway is refused, and nothing is taken
        $result = app(OrderService::class)->createOrderFromCart($customer, $this->shippingData() + ['agree_booking_fee' => true]);
        $this->assertFalse($result['success']);
        $this->assertSame($alone, $result['message']);
        $this->assertSame(0, Order::count());

        // Booking-fee items from two shops: one shop at a time
        $lens = $this->bookingProduct($this->seller('Glass House'));
        $this->cartWith($customer, [[$camera, 1], [$lens, 1]]);
        $this->get(route('checkout'))->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'Pre-orders with a booking fee are ordered from one shop at a time. Please order from each shop separately.');

        // On its own it is fine
        $this->cartWith($customer, [[$camera, 1]]);
        $this->get(route('checkout'))->assertOk();
    }

    public function test_checkout_asks_only_for_the_booking_fee_once_the_customer_agrees_to_its_terms(): void
    {
        Setting::set(['payment_cod_enabled' => 1]);
        $camera = $this->bookingProduct();
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$camera, 1]]);

        $this->actingAs($customer)->get(route('checkout'))->assertOk()
            ->assertSee('data-checkout-booking-fee', false)
            ->assertSee('You pay a booking fee of MVR 300.00 now.')
            ->assertSee('Booking fee to pay now')
            ->assertSee('Balance when it arrives')
            ->assertSee('MVR 775.00')
            ->assertSee('name="agree_booking_fee"', false)
            ->assertDontSee('data-booking-fee-gst', false) // the shop is not GST-registered
            ->assertDontSee('value="cod"', false);
        $this->assertSame(['fee' => 300.0, 'late_days' => 7, 'balance_days' => 7], session(BookingFeeService::SESSION_SHOWN));
        $this->assertSame(['14d'], array_values(session(PreorderService::SESSION_SHOWN)), 'the checkout remembers "14 days after the order"');

        // The terms must be ticked, and cash on delivery is refused
        $this->post(route('orders.store'), $this->checkoutFields())->assertSessionHasErrors('agree_booking_fee');
        $this->post(route('orders.store'), $this->bookingFields(['payment_method' => 'cod']))->assertSessionHasErrors('payment_method');
        $this->assertSame(0, Order::count());

        $this->post(route('orders.store'), $this->bookingFields())->assertRedirect('https://pay.bml.test/txn_1');
        $order = Order::sole();
        $this->assertEquals(1075, $order->total_amount);
        $this->assertEquals(300, $order->deposit_amount);
        $this->assertSame(775.0, $order->balanceAmount());
        $this->assertSame('unpaid', $order->payment_status);
        $transaction = PaymentTransaction::sole();
        $this->assertSame(30000, $transaction->amount, 'BML is asked for the booking fee only');
        $this->assertSame('deposit', $transaction->purpose);
        $item = $order->items()->sole();
        $this->assertTrue($item->is_preorder);
        $this->assertSame('2026-11-03', $item->preorder_ship_date->toDateString(), '14 days after the order');
        $this->assertNull(session(BookingFeeService::SESSION_SHOWN));
    }

    public function test_the_shop_raising_the_fee_or_the_days_after_the_checkout_page_is_not_ordered_silently(): void
    {
        $camera = $this->bookingProduct();
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$camera, 1]]);
        $this->actingAs($customer)->get(route('checkout'))->assertOk();

        $camera->forceFill(['preorder_deposit_value' => 40])->save();
        $this->post(route('orders.store'), $this->bookingFields())->assertRedirect(route('cart'))
            ->assertSessionHas('notification', fn ($flash) => str_contains($flash['message'], 'The booking fee for your order is now MVR 400.00.'));

        $this->get(route('checkout'))->assertOk();
        $camera->forceFill(['preorder_lead_days' => 30])->save();
        $this->post(route('orders.store'), $this->bookingFields())->assertRedirect(route('cart'))
            ->assertSessionHas('notification', fn ($flash) => str_contains($flash['message'], 'is now a pre-order that ships around 19 Nov'));
        $this->assertSame(0, Order::count());
    }

    public function test_the_booking_fee_then_the_balance_by_card_when_the_stock_arrives(): void
    {
        $camera = $this->bookingProduct();
        $customer = User::factory()->create(['phone' => '+9607771234']);
        $this->cartWith($customer, [[$camera, 1]]);
        $this->actingAs($customer)->get(route('checkout'))->assertOk();
        $this->post(route('orders.store'), $this->bookingFields())->assertRedirect('https://pay.bml.test/txn_1');
        $order = Order::sole();

        // Two tabs: a second payment for the booking fee is started before the first is confirmed
        $this->post(route('payments.bml.pay', $order))->assertRedirect('https://pay.bml.test/txn_2');

        $this->get(route('payments.bml.return', $order).'?transactionId=txn_1')->assertRedirect(route('orders.show', $order));
        $order->refresh();
        $this->assertSame('deposit_paid', $order->payment_status);
        $this->assertNotNull($order->deposit_paid_at);
        $this->assertNull($order->paid_at);
        $this->assertNull($order->loyalty_points_awarded_at, 'points wait for the balance');
        Notification::assertSentTo($customer, PaymentUpdated::class, fn ($n) => $n->part === 'deposit');
        Notification::assertSentTo($this->shop, ShopOrderPaid::class, fn ($n) => $n->part === 'deposit');
        $this->assertTrue(AuditLog::where('action', 'booking_fee.paid')->exists());

        // The second booking-fee payment is a duplicate: refunded, not counted as the balance
        $this->get(route('payments.bml.return', $order).'?transactionId=txn_2');
        $order->refresh();
        $this->assertSame('deposit_paid', $order->payment_status);
        $this->assertSame('DUPLICATE', PaymentTransaction::where('transaction_id', 'txn_2')->sole()->state);
        $this->assertSame('due', $order->refund_status);
        $this->assertEquals(300, $order->refund_amount);
        $order->forceFill(['refund_status' => null, 'refund_amount' => null])->save(); // refunded by hand

        // Nothing more to pay until the camera arrives
        $this->assertFalse(app(PaymentService::class)->canPayOnline($order));
        $this->assertFalse(PayLink::payable($order));
        $this->get(route('orders.show', $order))->assertOk()
            ->assertSee('data-booking-fee-box', false)->assertSee('Due when your item arrives')->assertDontSee('Pay the balance of');
        $this->post(route('payments.bml.pay', $order))->assertRedirect(route('orders.show', $order));

        // The shop's Pre-orders page: the fee counts as paid
        $this->actingAs($this->shop)->get(route('seller.preorders'))->assertOk()
            ->assertSee('ships about 14 days after each order')->assertSee('Booking fee paid')->assertSee('Fee MVR 300.00, balance MVR 775.00');

        // The camera arrives: the customer is asked for the balance by SMS and email
        $this->post(route('seller.preorders.arrived', $camera->id), ['received' => [0 => 1]])->assertSessionHas('success');
        $order->refresh();
        $this->assertNotNull($order->balance_requested_at);
        $this->assertSame('2026-10-27', $order->balance_due_on->toDateString());
        Notification::assertSentOnDemand(BookingBalanceDue::class, function ($n, $channels, $notifiable) use ($order) {
            return $n->order->is($order) && ! $n->reminder && $channels === ['sms', 'mail']
                && $notifiable->routes['sms'] === '+9607771234' && $notifiable->routes['mail'] === $order->customerEmail()
                && str_contains($n->url, '/pay/'.$order->id);
        });
        Notification::assertNotSentTo($customer, PreorderArrived::class);
        $sms = (string) (new BookingBalanceDue($order, 'https://iruali.test/pay/1'))->toSms(new \Illuminate\Notifications\AnonymousNotifiable);
        $this->assertSame('iruali: your pre-order (order '.$order->order_number.') has arrived. Please pay the balance of MVR 775.00 by 27 Oct: https://iruali.test/pay/1', $sms);

        // The payment link page and the order page offer the balance
        $this->get(PayLink::url($order))->assertOk()->assertSee('data-pay-balance', false)->assertSee('Balance to pay')->assertSee('MVR 775.00');
        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('Pay the balance of MVR 775.00 now')->assertSee('Due by 27 Oct 2026');

        $this->post(route('payments.bml.pay', $order))->assertRedirect('https://pay.bml.test/txn_3');
        $balance = PaymentTransaction::where('transaction_id', 'txn_3')->sole();
        $this->assertSame(77500, $balance->amount);
        $this->assertSame('balance', $balance->purpose);

        $this->get(route('payments.bml.return', $order).'?transactionId=txn_3');
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->loyalty_points_awarded_at);
        Notification::assertSentTo($customer, PaymentUpdated::class, fn ($n) => $n->part === null && str_contains($n->toMail($customer)->introLines[0], 'We have received the balance of MVR 775.00.'));
        Notification::assertSentTo($this->shop, ShopOrderPaid::class, fn ($n) => $n->part === 'balance');
        $this->assertFalse(PayLink::payable($order));
    }

    public function test_the_order_cannot_be_sent_before_the_balance_is_paid(): void
    {
        $order = $this->stockArrives($this->bookedOrder());
        $part = $order->sellerOrders()->sole();
        $this->assertFalse($part->fresh()->awaiting_stock, 'the stock is in');

        $this->actingAs($this->shop)->post(route('seller.orders.status', $order), ['status' => 'processing'])->assertSessionHas('success');
        $this->assertNotContains('shipped', app(FulfilmentService::class)->nextStatuses($part->fresh()));
        $this->post(route('seller.orders.status', $order), ['status' => 'shipped'])->assertSessionHas('error');
        $this->assertNotContains('shipped', app(OrderService::class)->nextStatuses($order->fresh()));
        $this->get(route('seller.orders.show', $order))->assertOk()->assertSee('data-seller-booking-fee', false)
            ->assertSee('Waiting for the customer to pay the balance of MVR 775.00 (due 27 Oct).');

        app(PaymentService::class)->confirm($order->fresh()); // the balance
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->post(route('seller.orders.status', $order), ['status' => 'shipped'])->assertSessionHas('success');
        $this->assertSame('shipped', $order->fresh()->status);
    }

    public function test_a_customer_who_cancels_does_not_get_the_booking_fee_back_and_the_shop_gets_it(): void
    {
        $customer = User::factory()->create();
        $order = $this->bookedOrder($customer);

        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('data-booking-cancel', false)
            ->assertSee('If you cancel, the booking fee is not refunded.')
            ->assertSee('Rather not wait? Cancel (the booking fee is not refunded)');
        $this->get(route('orders.preorder.cancel', $order))->assertOk()
            ->assertSee('The booking fee of MVR 300.00 is not refunded, as agreed at checkout.')
            ->assertDontSee('We refund');

        $this->post(route('orders.preorder.cancel.store', $order))->assertRedirect(route('orders.show', $order))
            ->assertSessionHas('notification', fn ($flash) => $flash['message'] === 'Your order has been cancelled. The booking fee of MVR 300.00 is not refunded, as agreed at checkout.');
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertEquals(300, $order->deposit_kept_amount);
        $this->assertNull($order->refund_status, 'only the fee was paid: nothing to refund');
        $adjustment = SellerAdjustment::sole();
        $this->assertSame($this->shop->id, $adjustment->seller_id);
        $this->assertEquals(270, $adjustment->amount, 'the fee less the 10% commission');
        $this->assertTrue(AuditLog::where('action', 'booking_fee.kept')->exists());
        $this->assertSame(0, app(PreorderService::class)->waitingUnits($order->items()->sole()->product_id));

        $this->get(route('orders.show', $order))->assertOk()->assertSee('was not refunded when the order was cancelled');
        $this->actingAs($this->shop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('The booking fee of MVR 300.00 was kept for you');

        // Cancelling after paying the balance too (the plain "Cancel order"): the balance comes back, the fee does not
        $second = $this->stockArrives($this->bookedOrder($customer));
        app(PaymentService::class)->confirm($second);
        $this->actingAs($customer)->post(route('orders.cancel', $second))
            ->assertSessionHas('notification', fn ($flash) => str_contains($flash['message'], 'The booking fee of MVR 300.00 is not refunded'));
        $second->refresh();
        $this->assertSame('due', $second->refund_status);
        $this->assertEquals(775, $second->refund_amount);
        $this->assertSame('Order cancelled after payment (booking fee kept)', $second->refund_reason);
    }

    public function test_the_fee_is_refunded_when_the_shop_moves_the_date_later_or_is_late(): void
    {
        $camera = $this->bookingProduct();
        $customer = User::factory()->create();
        $moved = $this->bookedOrder($customer, $camera);
        $late = $this->bookedOrder(User::factory()->create(), $camera);

        // The shop moves the waiting orders later: their customers may cancel for everything back
        $this->actingAs($this->shop)->put(route('seller.preorders.date', $camera->id), ['preorder_ship_date' => '2026-11-10'])->assertSessionHas('success');
        $this->assertNull($camera->fresh()->preorder_ship_date, 'the product still ships some days after each order');
        $this->assertSame(14, $camera->fresh()->preorder_lead_days);
        $this->assertNotNull($moved->fresh()->deposit_refundable_at);
        $this->assertSame('2026-11-10', $moved->items()->sole()->preorder_ship_date->toDateString());

        $this->actingAs($customer)->get(route('orders.preorder.cancel', $moved))->assertOk()
            ->assertSee('so your booking fee is refunded too')->assertSee('We refund MVR 300.00');
        $this->post(route('orders.preorder.cancel.store', $moved));
        $moved->refresh();
        $this->assertSame('cancelled', $moved->status);
        $this->assertNull($moved->deposit_kept_amount);
        $this->assertSame('due', $moved->refund_status);
        $this->assertEquals(300, $moved->refund_amount);
        $this->assertSame(0, SellerAdjustment::count());

        // More than a week late: refundable too
        $late->refresh();
        $late->forceFill(['deposit_refundable_at' => null])->save();
        $this->travelTo(Carbon::parse('2026-11-18 07:00'));
        $this->artisan('preorders:flag-late')->assertExitCode(0);
        $this->assertNotNull($late->fresh()->deposit_refundable_at);
        $this->assertFalse(app(BookingFeeService::class)->keepable($late->fresh()));
    }

    public function test_an_admin_cancelling_refunds_everything_unless_they_keep_the_fee(): void
    {
        $admin = $this->staffMember('admin');
        $first = $this->bookedOrder();

        $this->actingAs($admin)->get(route('admin.orders.show', $first))->assertOk()
            ->assertSee('data-admin-booking-fee', false)
            ->assertSee('data-keep-booking-fee', false)
            ->assertSee('Keep the booking fee (MVR 300.00)');
        $this->post(route('admin.orders.status', $first), ['status' => 'cancelled'])->assertSessionHas('success');
        $first->refresh();
        $this->assertNull($first->deposit_kept_amount);
        $this->assertSame('due', $first->refund_status);
        $this->assertEquals(300, $first->refund_amount);

        // The balance was never paid: the admin keeps the fee for the shop
        $second = $this->stockArrives($this->bookedOrder());
        $this->post(route('admin.orders.status', $second), ['status' => 'cancelled', 'keep_booking_fee' => 1])
            ->assertSessionHas('success', 'Order marked as cancelled. The booking fee of MVR 300.00 is kept and credited to the shop (less commission).');
        $second->refresh();
        $this->assertEquals(300, $second->deposit_kept_amount);
        $this->assertNull($second->refund_status);
        $this->assertEquals(270, SellerAdjustment::sole()->amount);
    }

    public function test_the_wallet_pays_towards_the_booking_fee_and_a_kept_fee_is_not_returned_to_it(): void
    {
        $camera = $this->bookingProduct();
        $partly = User::factory()->create();
        app(WalletService::class)->credit($partly, 100, 'gift_card');
        $this->cartWith($partly, [[$camera, 1]]);
        $this->actingAs($partly)->get(route('checkout'))->assertOk()->assertSee('Your store credit pays the booking fee first');
        $this->post(route('orders.store'), $this->bookingFields(['use_wallet' => '1']))->assertRedirect('https://pay.bml.test/txn_1');
        $order = Order::sole();
        $this->assertEquals(100, $order->wallet_amount);
        $this->assertSame(20000, PaymentTransaction::where('gateway', 'bml')->sole()->amount, 'the card pays the rest of the fee');
        $this->assertSame(0.0, app(WalletService::class)->balance($partly));

        // A wallet that covers the whole fee: the fee is paid at once, the balance comes later
        $covered = User::factory()->create();
        app(WalletService::class)->credit($covered, 500, 'gift_card');
        $this->cartWith($covered, [[$camera, 1]]);
        $this->actingAs($covered)->get(route('checkout'))->assertOk();
        $response = $this->post(route('orders.store'), $this->bookingFields(['use_wallet' => '1']));
        $order = Order::where('user_id', $covered->id)->sole();
        $response->assertRedirect(route('orders.show', $order));
        $this->assertSame('deposit_paid', $order->payment_status);
        $this->assertSame('bml', $order->payment_method, 'the balance is paid by card');
        $this->assertEquals(300, $order->wallet_amount);
        $this->assertSame(200.0, app(WalletService::class)->balance($covered));
        $this->assertSame(775.0, $this->stockArrives($order)->cardAmount());

        // The customer cancels: the fee (paid from the wallet) is kept
        app(PreorderService::class)->cancel($order->fresh());
        $order->refresh();
        $this->assertEquals(300, $order->deposit_kept_amount);
        $this->assertNull($order->refund_status);
        $this->assertNull($order->wallet_refunded_at);
        $this->assertSame(200.0, app(WalletService::class)->balance($covered));
    }

    public function test_the_booking_fee_and_the_balance_paid_by_bank_transfer(): void
    {
        Setting::set([
            'payment_card_enabled' => 0, 'payment_transfer_enabled' => 1,
            'transfer_bank_name' => 'Bank of Maldives', 'transfer_account_name' => 'Iruali Pvt Ltd', 'transfer_account_number' => '7730000123456',
        ]);
        $camera = $this->bookingProduct();
        $customer = User::factory()->create();
        $this->cartWith($customer, [[$camera, 1]]);
        $this->actingAs($customer)->get(route('checkout'))->assertOk();
        $this->post(route('orders.store'), $this->bookingFields(['payment_method' => 'transfer']))->assertRedirect();
        $order = Order::sole();

        $this->get(route('orders.show', $order))->assertOk()->assertSee('Pay the booking fee by bank transfer')->assertSee('MVR 300.00');
        $this->post(route('orders.payment-slip', $order), ['slip' => UploadedFile::fake()->image('fee.jpg')])->assertRedirect();
        $depositSlip = $order->fresh()->payment_slip_path;

        // The page showed the fee: confirming it as the balance is refused
        $finance = $this->staffMember('finance');
        $this->actingAs($finance)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Confirm booking fee received (MVR 300.00)');
        $this->post(route('admin.orders.confirm-payment', $order), ['part' => 'balance'])->assertSessionHas('error');
        $this->post(route('admin.orders.confirm-payment', $order), ['part' => 'deposit', 'payment_reference' => 'FEE-1'])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('deposit_paid', $order->payment_status);
        $this->assertSame($depositSlip, $order->deposit_slip_path, 'the fee\'s slip is kept for the balance\'s own');
        $this->assertNull($order->payment_slip_path);
        $this->get(route('admin.orders.payment-slip', ['order' => $order, 'part' => 'deposit']))->assertOk();
        Notification::assertSentTo($this->shop, ShopOrderPaid::class, fn ($n) => $n->part === 'deposit');

        // Days pass without a slip: the unpaid-transfer sweep leaves a paid booking fee alone
        $this->travelTo(Carbon::parse('2026-10-23 10:00'));
        $this->artisan('orders:cancel-unpaid-transfer')->assertExitCode(0);
        $this->assertSame('pending', $order->fresh()->status);
        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()->assertDontSee('data-transfer-box', false);

        $order = $this->stockArrives($order);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Pay the balance by bank transfer')->assertSee('MVR 775.00')->assertSee('by 30 Oct 2026');
        $this->post(route('orders.payment-slip', $order), ['slip' => UploadedFile::fake()->image('balance.jpg')])->assertRedirect();
        $this->actingAs($finance)->post(route('admin.orders.confirm-payment', $order), ['part' => 'balance'])->assertSessionHas('success');
        $this->assertSame('paid', $order->fresh()->payment_status);
        Notification::assertSentTo($this->shop, ShopOrderPaid::class, fn ($n) => $n->part === 'balance');
        Notification::assertSentToTimes($this->shop, ShopOrderPaid::class, 2);
    }

    public function test_balance_reminders_overdue_balances_and_more_time(): void
    {
        $order = $this->stockArrives($this->bookedOrder());
        $this->assertSame('2026-10-27', $order->balance_due_on->toDateString());

        // Three days before: not yet
        $this->travelTo(Carbon::parse('2026-10-24 09:40'));
        $this->artisan('preorders:balance-reminders')->expectsOutputToContain('Sent 0 balance reminder(s); 0 balance(s) newly overdue.')->assertExitCode(0);
        // Two days before: one reminder, once
        $this->travelTo(Carbon::parse('2026-10-25 09:40'));
        $this->artisan('preorders:balance-reminders')->expectsOutputToContain('Sent 1 balance reminder(s)')->assertExitCode(0);
        $this->artisan('preorders:balance-reminders')->expectsOutputToContain('Sent 0 balance reminder(s)')->assertExitCode(0);
        Notification::assertSentOnDemandTimes(BookingBalanceDue::class, 2); // the request and the reminder
        Notification::assertSentOnDemand(BookingBalanceDue::class, fn ($n) => $n->reminder);

        // Past the due date: the team is told once and the inbox lists it
        $this->travelTo(Carbon::parse('2026-10-28 09:40'));
        $this->artisan('preorders:balance-reminders')->expectsOutputToContain('1 balance(s) newly overdue')->assertExitCode(0);
        $this->artisan('preorders:balance-reminders')->expectsOutputToContain('0 balance(s) newly overdue')->assertExitCode(0);
        $this->assertNotNull($order->fresh()->balance_overdue_at);
        $this->assertSame(1, AuditLog::where('action', 'booking_fee.balance_overdue')->count());
        AdminInbox::forget('__refresh__');
        $this->assertSame(1, collect(AdminInbox::all())->firstWhere('key', 'booking_balances_overdue')['count']);

        $admin = $this->staffMember('admin');
        $this->actingAs($admin)->get(route('admin.preorders'))->assertOk()->assertSee('data-balances-due', false)->assertSee('#'.$order->order_number)->assertSee('Overdue');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('data-balance-due-form', false)->assertSee('(overdue)');

        // The unpaid card sweep never cancels a paid booking fee
        $this->artisan('orders:cancel-unpaid-card')->assertExitCode(0);
        $this->assertSame('pending', $order->fresh()->status);

        // More time: the reminder and the alert start again, with a new link
        $this->post(route('admin.orders.balance-due', $order), ['balance_due_on' => '2026-11-04'])->assertSessionHas('success');
        $order->refresh();
        $this->assertSame('2026-11-04', $order->balance_due_on->toDateString());
        $this->assertNull($order->balance_overdue_at);
        $this->assertNull($order->balance_reminded_at);
        Notification::assertSentOnDemandTimes(BookingBalanceDue::class, 3);
        AdminInbox::forget('__refresh__');
        $this->assertSame(0, collect(AdminInbox::all())->firstWhere('key', 'booking_balances_overdue')['count']);

        $this->artisan('schedule:list')->expectsOutputToContain('preorders:balance-reminders');
    }

    public function test_a_customer_with_only_a_mobile_number_is_told_by_sms(): void
    {
        $customer = User::factory()->create(['email' => null, 'phone' => '+9607771234', 'phone_verified_at' => now()]);
        $this->bookedOrder($customer);

        Notification::assertSentTo($customer, PaymentUpdated::class, fn ($n, $channels) => $n->part === 'deposit' && $channels === ['sms']);
    }

    public function test_a_booking_fee_on_a_live_product_of_a_shop_that_is_reviewed_waits_for_approval(): void
    {
        $camera = Product::factory()->create(['seller_id' => $this->shop->id, 'category_id' => Category::factory()->create(['status' => 'active'])->id, 'price' => 1000, 'stock_quantity' => 0, 'is_active' => true, 'sku' => 'CAM-9']);
        $camera->forceFill(['approved_at' => now()])->save();
        $fields = ['name_en' => $camera->getTranslation('name', 'en'), 'sku' => 'CAM-9', 'category_id' => $camera->category_id, 'price' => 1000, 'stock_quantity' => 0, 'reorder_point' => 0,
            'preorder_form' => 1, 'preorder_enabled' => 1, 'preorder_limit' => 3, 'preorder_ships' => 'days', 'preorder_lead_days' => 14, 'preorder_deposit_type' => 'fixed', 'preorder_deposit_value' => 250];

        $this->actingAs($this->shop)->put(route('seller.products.update', $camera), $fields)->assertSessionHasNoErrors();
        $this->assertFalse($camera->fresh()->hasBookingFee(), 'waits for review');
        $change = ProductChangeRequest::sole();
        $this->assertContains('On: ships about 14 days after the order, up to 3 units, booking fee 250.00 per item', array_column(app(ProductReviewService::class)->changes($change)['fields'], 'after'));

        app(ProductReviewService::class)->approve($change, $this->staffMember('admin'));
        $camera->refresh();
        $this->assertTrue($camera->hasBookingFee());
        $this->assertSame(14, $camera->preorder_lead_days);
        $this->assertSame(250.0, app(BookingFeeService::class)->unitFee($camera, 1000));
    }
}
