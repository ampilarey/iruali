<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PreorderAvailable;
use App\Notifications\ShopOrderPaid;
use App\Services\FulfilmentService;
use App\Services\ManualPaymentService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\PreorderConfirmService;
use App\Services\PreorderService;
use App\Support\AdminInbox;
use App\Support\PayLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * Pre-orders paid once the shop confirms it can get the item (App\Services\PreorderConfirmService):
 * the product form, ordering without paying, the shop (or an admin) confirming or declining, the
 * payment link, the reminder, paying by card or bank transfer, and the order cancelled when it is
 * not paid in time.
 *
 * The clock is 20 Oct 2026. The drone costs MVR 1,000, ships about 14 days after the order and gives
 * 2 days to pay once confirmed; delivery to the islands is MVR 75, so the order is MVR 1,075.
 */
class PreorderConfirmFirstTest extends TestCase
{
    use PreorderFixtures, RefreshDatabase;

    protected User $shop;

    protected ?Product $drone = null;

    protected int $bmlCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-10-20 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 75, 'contact_email' => 'ops@iruali.test', 'default_commission_rate' => 10]);
        $this->shop = $this->seller('Sky Gear');

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

    /** The drone: MVR 1,000, paid once the shop confirms (2 days to pay), ships about 14 days after the order. */
    protected function droneProduct(?User $shop = null, array $attributes = []): Product
    {
        $product = $this->preorderProduct($shop ?? $this->shop, ['price' => 1000, 'name' => 'Camera drone']);
        $product->forceFill(array_merge(['preorder_ship_date' => null, 'preorder_lead_days' => 14, 'preorder_confirm_first' => true, 'preorder_balance_days' => 2], $attributes))->save();

        return $product->fresh();
    }

    /** A drone ordered through the service, not confirmed yet. */
    protected function placedOrder(?User $customer = null, string $method = 'bml'): Order
    {
        $customer ??= User::factory()->create();
        session()->forget([PreorderService::SESSION_SHOWN, PreorderConfirmService::SESSION_SHOWN]); // another customer's checkout page
        $cart = $this->cartWith($customer, [[$this->drone ??= $this->droneProduct(), 1]]);
        $result = app(OrderService::class)->createOrderFromCart($customer, array_merge($this->shippingData(), ['payment_method' => $method]), $cart);
        $this->assertTrue($result['success'], $result['message']);

        return $result['order']->fresh();
    }

    public function test_the_product_form_takes_nothing_until_the_shop_confirms(): void
    {
        $category = Category::factory()->create(['status' => 'active']);
        $fields = ['name_en' => 'Camera drone', 'sku' => 'DRN-1', 'category_id' => $category->id, 'price' => 1000, 'stock_quantity' => 0, 'reorder_point' => 0,
            'preorder_form' => 1, 'preorder_enabled' => 1, 'preorder_limit' => 3, 'preorder_ships' => 'days', 'preorder_lead_days' => 14];

        $this->actingAs($this->shop)->get(route('seller.products.create'))->assertOk()
            ->assertSee('Nothing yet: the full price once I confirm I can get it')
            ->assertSee('Days to pay once you confirm');

        // The booking fee's amount is not needed with it, nor kept
        $this->post(route('seller.products.store'), $fields + ['preorder_deposit_type' => 'confirm', 'preorder_deposit_value' => 30, 'preorder_balance_days' => 2])
            ->assertSessionHasNoErrors()->assertRedirect(route('seller.products.index'));
        $product = Product::where('sku', 'DRN-1')->sole();
        $this->assertTrue($product->preorder_confirm_first);
        $this->assertTrue($product->confirmsFirst());
        $this->assertFalse($product->hasBookingFee());
        $this->assertNull($product->preorder_deposit_type);
        $this->assertNull($product->preorder_deposit_value);
        $this->assertSame(2, $product->preorderPayDays());
        $this->get(route('seller.products.edit', $product))->assertOk()->assertSee('value="confirm" selected', false);

        // Empty days: the default
        $this->put(route('seller.products.update', $product), $fields + ['preorder_deposit_type' => 'confirm', 'preorder_balance_days' => ''])->assertSessionHasNoErrors();
        $this->assertSame(PreorderConfirmService::PAY_DAYS, $product->fresh()->preorderPayDays());

        // A booking fee instead: no longer confirmed first
        $this->put(route('seller.products.update', $product), $fields + ['preorder_deposit_type' => 'percent', 'preorder_deposit_value' => 30])->assertSessionHasNoErrors();
        $product->refresh();
        $this->assertFalse($product->preorder_confirm_first);
        $this->assertTrue($product->hasBookingFee());
    }

    public function test_the_customer_orders_without_paying_and_pays_once_the_shop_confirms(): void
    {
        $drone = $this->drone = $this->droneProduct();
        $customer = User::factory()->create();
        $customer->forceFill(['wallet_balance' => 500])->save();

        $this->get(route('products.show', $drone))->assertOk()->assertSee('data-confirm-first', false)
            ->assertSee('Nothing to pay now: the shop first confirms it can get it, then you have 2 days to pay.');

        // Checkout: nothing to pay now, no wallet, no cash on delivery
        Setting::set(['payment_cod_enabled' => 1]);
        $this->cartWith($customer, [[$drone, 1]]);
        $this->actingAs($customer)->get(route('checkout'))->assertOk()
            ->assertSee('data-checkout-confirm-first', false)
            ->assertSee('Nothing to pay now. The shop first checks it can get your item; we then text and email you a link to pay, and you have 2 days to pay.')
            ->assertSee('data-confirm-first-now', false)
            ->assertSee('data-confirm-first-payment', false)
            ->assertDontSee('name="use_wallet"', false)
            ->assertDontSee('value="cod"', false);
        $this->assertSame(['pay_days' => 2], session(PreorderConfirmService::SESSION_SHOWN));

        // Placed by card: no card page now, and the wallet is left alone even if asked
        $this->post(route('orders.store'), $this->checkoutFields() + ['use_wallet' => '1']);
        $order = Order::sole();
        $this->assertTrue($order->confirm_first);
        $this->assertSame(2, $order->pay_days);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertEquals(1075, $order->total_amount);
        $this->assertEquals(0, (float) $order->wallet_amount);
        $this->assertEquals(500, (float) $customer->fresh()->wallet_balance);
        $this->assertTrue($order->awaitingConfirmation());
        $this->assertFalse($order->awaitingPayment());
        $this->assertFalse(PayLink::payable($order));
        $this->assertSame(0, PaymentTransaction::count());
        $this->assertTrue($order->items()->sole()->is_preorder);
        $this->assertNull(session(PreorderConfirmService::SESSION_SHOWN));
        Notification::assertSentTo($customer, OrderPlaced::class, fn ($n) => str_contains(implode(' ', $n->toMail($customer)->introLines), 'Nothing to pay yet: the shop first checks it can get your item. We then text and email you a link to pay MVR 1,075.00 within 2 days.'));
        Notification::assertSentTo($this->shop, NewSellerOrder::class, fn ($n) => str_contains(implode(' ', $n->toMail($this->shop)->introLines), 'The customer has not paid yet: they pay once you confirm you can get the item.'));

        $this->get(route('orders.show', $order))->assertOk()
            ->assertSee('data-confirm-first-box="waiting"', false)->assertSee('Waiting for the shop to confirm')
            ->assertSee('Rather not wait? Cancel the order (nothing is paid yet)')
            ->assertDontSee('Pay MVR 1,075.00 now');
        $this->post(route('payments.bml.pay', $order))->assertRedirect(route('orders.show', $order));
        $this->assertSame(0, PaymentTransaction::count());
        // It stays pending until it is paid (moved on, it could no longer be paid): only cancelling
        $this->assertSame([], app(FulfilmentService::class)->nextStatuses($order->sellerOrders()->sole()));
        $this->assertSame(['cancelled'], app(OrderService::class)->nextStatuses($order));

        // Days later it is still waiting for the shop: the unpaid-order job leaves it alone, the inbox counts it
        $this->travelTo(Carbon::parse('2026-10-24 10:00'));
        $this->artisan('orders:cancel-unpaid-card')->assertExitCode(0);
        $this->assertSame('pending', $order->fresh()->status);
        AdminInbox::forget('__refresh__');
        $this->assertSame(1, collect(AdminInbox::all())->firstWhere('key', 'preorders_to_confirm')['count']);

        // The shop confirms: the customer is texted and emailed a link to pay within 2 days
        $this->actingAs($this->shop)->get(route('seller.preorders'))->assertOk()
            ->assertSee('data-to-confirm', false)->assertSee('#'.$order->order_number)->assertSee('I can get it: ask the customer to pay');
        $this->get(route('seller.orders.show', $order))->assertOk()->assertSee('data-seller-confirm-first="waiting"', false);
        $this->post(route('seller.preorders.confirm', $order))
            ->assertSessionHas('success', 'Confirmed. We have sent the customer a link to pay MVR 1,075.00 by 26 Oct. We will tell you when it is paid: order the item then.');
        $order->refresh();
        $this->assertNotNull($order->availability_confirmed_at);
        $this->assertSame('2026-10-26', $order->pay_due_on->toDateString());
        $this->assertFalse($order->awaitingConfirmation());
        $this->assertTrue($order->awaitingPayment());
        $this->assertTrue(PayLink::payable($order));
        $this->assertTrue(AuditLog::where('action', 'preorder.confirmed')->where('subject_id', $order->id)->where('user_id', $this->shop->id)->exists());
        Notification::assertSentOnDemand(PreorderAvailable::class, function ($n, $channels, $notifiable) use ($order) {
            return $n->order->is($order) && ! $n->reminder && $channels === ['sms', 'mail']
                && $notifiable->routes['sms'] === '+9607771234' && $notifiable->routes['mail'] === $order->customerEmail()
                && str_contains($n->url, '/pay/'.$order->id);
        });
        $sms = (string) (new PreorderAvailable($order, 'https://iruali.test/pay/1'))->toSms(new AnonymousNotifiable);
        $this->assertSame('iruali: good news, the shop can get your pre-order (order '.$order->order_number.'). Please pay MVR 1,075.00 by 26 Oct: https://iruali.test/pay/1', $sms);
        $this->get(route('seller.preorders'))->assertOk()->assertSee('data-awaiting-pay', false)->assertSee('MVR 1,075.00 due 26 Oct');

        // Once only
        $this->post(route('seller.preorders.confirm', $order))->assertSessionHas('error');
        Notification::assertSentOnDemandTimes(PreorderAvailable::class, 1);
        AdminInbox::forget('__refresh__');
        $this->assertSame(0, collect(AdminInbox::all())->firstWhere('key', 'preorders_to_confirm')['count']);

        // The order page and the link's page offer the payment
        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('data-confirm-first-box="confirmed"', false)
            ->assertSee('Please pay MVR 1,075.00 by 26 Oct 2026.')
            ->assertSee('Pay MVR 1,075.00 now');
        $this->get(PayLink::url($order))->assertOk()->assertSee('MVR 1,075.00');

        // A reminder the day before it is due (not on the day it was confirmed), once
        $this->artisan('preorders:balance-reminders')->expectsOutputToContain('0 confirmed pre-order payment reminder(s)')->assertExitCode(0);
        $this->travelTo(Carbon::parse('2026-10-25 09:40'));
        $this->artisan('preorders:balance-reminders')->expectsOutputToContain('1 confirmed pre-order payment reminder(s)')->assertExitCode(0);
        $this->artisan('preorders:balance-reminders')->expectsOutputToContain('0 confirmed pre-order payment reminder(s)')->assertExitCode(0);
        Notification::assertSentOnDemand(PreorderAvailable::class, fn ($n) => $n->reminder);
        $this->artisan('orders:cancel-unpaid-card')->assertExitCode(0);
        $this->assertSame('pending', $order->fresh()->status, 'not cancelled before its payment is due');

        // Paid by card: the shop is told to order the item
        $this->actingAs($customer)->post(route('payments.bml.pay', $order))->assertRedirect('https://pay.bml.test/txn_1');
        $transaction = PaymentTransaction::sole();
        $this->assertSame(107500, $transaction->amount);
        app(PaymentService::class)->syncBml($transaction);
        $order->refresh();
        $this->assertSame('paid', $order->payment_status);
        Notification::assertSentTo($this->shop, ShopOrderPaid::class, fn ($n) => $n->part === 'confirmed'
            && $n->toMail($this->shop)->subject === 'Pre-order '.$order->order_number.' is paid: you can order the item');

        // From here it is a paid pre-order like any other
        $this->assertSame(['processing'], app(FulfilmentService::class)->nextStatuses($order->sellerOrders()->sole()));
        app(PreorderService::class)->receiveStock($drone, [0 => 1]);
        $this->assertNotNull($order->items()->sole()->preorder_allocated_at);
    }

    public function test_the_shop_cannot_get_it_and_the_order_is_cancelled(): void
    {
        $customer = User::factory()->create();
        $order = $this->placedOrder($customer);

        $this->actingAs($this->seller('Other Shop'))->post(route('seller.preorders.decline', $order))->assertForbidden();
        $this->actingAs($this->shop)->post(route('seller.preorders.decline', $order))
            ->assertSessionHas('success', 'Order '.$order->order_number.' is cancelled. We have told the customer you can\'t get the item.');
        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->availability_declined_at);
        $this->assertNull($order->refund_status, 'nothing was paid');
        $this->assertSame(0, app(PreorderService::class)->waitingUnits((int) $this->drone->id));
        $this->assertTrue(AuditLog::where('action', 'preorder.declined')->where('subject_id', $order->id)->exists());

        // The customer is told why
        Notification::assertSentTo($customer, OrderStatusChanged::class, function ($n) use ($customer, $order) {
            $mail = $n->toMail($customer);

            return $mail->subject === 'Order '.$order->order_number.': the shop can\'t get your pre-order'
                && str_contains($mail->introLines[0], 'You have not been charged');
        });
        $this->assertSame('iruali: sorry, the shop can\'t get your pre-order (order '.$order->order_number.'), so it is cancelled. You have not been charged.',
            (string) (new OrderStatusChanged($order))->toSms($customer));
        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()->assertSee('data-confirm-first-box="declined"', false);

        // Too late to confirm or decline it again
        $this->actingAs($this->shop)->post(route('seller.preorders.decline', $order))->assertSessionHas('error');
        $this->post(route('seller.preorders.confirm', $order))->assertSessionHas('error');
        Notification::assertSentOnDemandTimes(PreorderAvailable::class, 0);
    }

    public function test_unpaid_after_its_due_date_the_order_is_cancelled(): void
    {
        $order = $this->placedOrder();
        $this->assertTrue(app(PreorderConfirmService::class)->confirm($order, $this->staffMember('admin')));
        $this->assertSame('2026-10-22', $order->fresh()->pay_due_on->toDateString());

        $this->travelTo(Carbon::parse('2026-10-22 23:00'));
        $this->artisan('orders:cancel-unpaid-card')->assertExitCode(0);
        $this->assertSame('pending', $order->fresh()->status, 'due today: not yet');

        $this->travelTo(Carbon::parse('2026-10-23 11:00'));
        $this->artisan('orders:cancel-unpaid-card')->assertExitCode(0);
        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertFalse(PayLink::payable($order->fresh()));
    }

    public function test_by_bank_transfer_the_slip_waits_for_the_shop_to_confirm(): void
    {
        Setting::set(['payment_transfer_enabled' => 1, 'transfer_bank_name' => 'Bank of Maldives', 'transfer_account_name' => 'iruali', 'transfer_account_number' => '7730000012345']);
        $customer = User::factory()->create();
        $order = $this->placedOrder($customer, ManualPaymentService::TRANSFER);
        $this->assertFalse(ManualPaymentService::awaiting($order));

        // Nothing to transfer yet: no slip is taken, and an admin can't mark it paid
        $this->actingAs($customer)->post(route('orders.payment-slip', $order), ['slip' => UploadedFile::fake()->image('slip.jpg')]);
        $this->assertNull($order->fresh()->payment_slip_path);
        $this->actingAs($this->staffMember('admin'))->post(route('admin.orders.payment', $order), ['action' => 'confirm'])->assertSessionHas('error');
        $this->actingAs($this->staffMember('finance'))->post(route('admin.orders.confirm-payment', $order))->assertSessionHas('error');
        $this->assertSame('unpaid', $order->fresh()->payment_status);

        // Days pass: the transfer job leaves it alone
        $this->travelTo(Carbon::parse('2026-10-24 10:00'));
        $this->artisan('orders:cancel-unpaid-transfer')->assertExitCode(0);
        $this->assertSame('pending', $order->fresh()->status);

        // Confirmed: the slip is taken and an admin confirms the transfer; the shop is told once, to order the item
        app(PreorderConfirmService::class)->confirm($order, $this->shop);
        $this->actingAs($customer)->post(route('orders.payment-slip', $order), ['slip' => UploadedFile::fake()->image('slip.jpg')]);
        $this->assertNotNull($order->fresh()->payment_slip_path);
        $this->actingAs($this->staffMember('finance'))->post(route('admin.orders.confirm-payment', $order), ['payment_reference' => 'TRF-1'])->assertSessionHas('success');
        $this->assertSame('paid', $order->fresh()->payment_status);
        Notification::assertSentToTimes($this->shop, ShopOrderPaid::class, 1);
        Notification::assertSentTo($this->shop, ShopOrderPaid::class, fn ($n) => $n->part === 'confirmed');
    }

    public function test_ordered_on_their_own_by_card_or_transfer_as_the_checkout_page_said(): void
    {
        Setting::set(['payment_cod_enabled' => 1]);
        $drone = $this->droneProduct();
        $strap = $this->stockedProduct($this->shop, 50, 5);
        $customer = User::factory()->create();
        $alone = 'Pre-orders that the shop confirms first are ordered on their own. Please remove the other items from your cart and order them separately.';

        $this->cartWith($customer, [[$drone, 1], [$strap, 1]]);
        $this->actingAs($customer)->get(route('cart'))->assertOk()->assertSee('data-booking-fee-problem', false)->assertSee($alone);
        $this->get(route('checkout'))->assertRedirect(route('cart'))->assertSessionHas('error', $alone);
        $result = app(OrderService::class)->createOrderFromCart($customer, $this->shippingData());
        $this->assertFalse($result['success']);
        $this->assertSame($alone, $result['message']);

        // From two shops: one at a time
        $this->cartWith($customer, [[$drone, 1], [$this->droneProduct($this->seller('Drone Hub')), 1]]);
        $this->get(route('checkout'))->assertRedirect(route('cart'))
            ->assertSessionHas('error', 'Pre-orders that the shop confirms first are ordered from one shop at a time. Please order from each shop separately.');

        // On its own: not cash on delivery
        $this->cartWith($customer, [[$drone, 1]]);
        $this->get(route('checkout'))->assertOk()->assertDontSee('value="cod"', false);
        $this->post(route('orders.store'), array_merge($this->checkoutFields(), ['payment_method' => 'cod']))->assertSessionHasErrors('payment_method');

        // The shop switched to "pay now" after the checkout page: not ordered silently
        $drone->forceFill(['preorder_confirm_first' => false])->save();
        $this->post(route('orders.store'), $this->checkoutFields())
            ->assertSessionHas('notification', fn ($flash) => $flash['message'] === 'The shop has changed the terms of this pre-order. Please check your order and place it again.');
        // ...nor the other way round
        $this->get(route('checkout'))->assertOk()->assertDontSee('data-checkout-confirm-first', false);
        $drone->forceFill(['preorder_confirm_first' => true])->save();
        $this->post(route('orders.store'), $this->checkoutFields())
            ->assertSessionHas('notification', fn ($flash) => $flash['message'] === 'The shop has changed the terms of this pre-order. Please check your order and place it again.');
        $this->assertSame(0, Order::count());
    }

    public function test_an_admin_or_support_can_confirm_or_decline_for_the_shop(): void
    {
        $first = $this->placedOrder();
        $second = $this->placedOrder();

        $this->actingAs($this->staffMember('finance'))->post(route('admin.orders.preorder-confirm', $first))->assertForbidden();

        $support = $this->staffMember('support');
        $this->actingAs($support)->get(route('admin.preorders'))->assertOk()
            ->assertSee('data-to-confirm', false)->assertSee('#'.$first->order_number)->assertSee('#'.$second->order_number);
        $this->get(route('admin.orders.show', $first))->assertOk()
            ->assertSee('data-admin-confirm-first', false)->assertSee('Waiting for the shop to confirm it can get the item');
        $this->post(route('admin.orders.preorder-confirm', $first))->assertSessionHas('success');
        $this->post(route('admin.orders.preorder-decline', $second))->assertSessionHas('success');

        $this->assertNotNull($first->fresh()->availability_confirmed_at);
        $this->assertSame('cancelled', $second->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'preorder.confirmed')->where('subject_id', $first->id)->where('user_id', $support->id)->exists());
        $this->get(route('admin.preorders'))->assertOk()->assertSee('data-awaiting-pay', false)->assertDontSee('data-to-confirm', false);
        $this->get(route('admin.orders.show', $first))->assertOk()->assertSee('The customer was sent a link to pay');
        $this->post(route('admin.orders.preorder-confirm', $first))->assertSessionHas('error');
    }
}
