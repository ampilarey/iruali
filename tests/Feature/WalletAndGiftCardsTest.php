<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Notifications\GiftCardIssued;
use App\Notifications\PaymentUpdated;
use App\Notifications\RefundRecorded;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Services\WalletService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Store credit: paying whole or part of an order from the wallet, refunds to the wallet, gift
 * cards from purchase to redemption, and the balance never going below zero.
 */
class WalletAndGiftCardsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableBml();

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
    }

    protected function fillCart(User $user, float $price = 500, int $qty = 1): Cart
    {
        $product = Product::factory()->create(['price' => $price, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $qty, 'price' => $price]);

        return $cart;
    }

    protected function checkout(User $user, bool $useWallet = true)
    {
        return $this->actingAs($user)->post(route('orders.store'), [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Male', 'shipping_state' => 'Kaafu', 'shipping_zip' => '20026',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'delivery_zone' => 'greater_male', 'payment_method' => 'bml',
            'agree_terms' => '1', 'use_wallet' => $useWallet ? '1' : '0',
        ]);
    }

    protected function assertWalletConsistent(User $user): void
    {
        $this->assertEquals((float) $user->fresh()->wallet_balance, (float) WalletTransaction::where('user_id', $user->id)->sum('amount'));
    }

    // ---- Paying from the wallet -------------------------------------------------------------

    public function test_the_wallet_pays_the_whole_order_without_bml(): void
    {
        $user = User::factory()->create(['loyalty_points' => 0]);
        app(WalletService::class)->credit($user, 1000, 'adjustment');
        $this->fillCart($user, 500);

        $this->actingAs($user)->get(route('checkout'))->assertOk()->assertSee('Use wallet balance');

        $response = $this->checkout($user);
        $order = Order::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('wallet', $order->payment_method);
        $this->assertEquals((float) $order->total_amount, (float) $order->wallet_amount);
        $this->assertEquals(0, $order->cardAmount());
        $this->assertEquals(1000 - (float) $order->total_amount, (float) $user->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $user->id, 'type' => 'purchase', 'order_id' => $order->id, 'amount' => -(float) $order->total_amount]);
        $this->assertDatabaseHas('payment_transactions', ['order_id' => $order->id, 'gateway' => 'wallet', 'state' => 'CONFIRMED']);
        Http::assertNothingSent();

        // PaymentService::confirm ran: rewards given and the "paid" email sent
        $this->assertNotNull($order->loyalty_points_awarded_at);
        $this->assertSame($order->loyalty_points_earned, $user->fresh()->loyalty_points);
        Notification::assertSentTo($user, PaymentUpdated::class);
        $this->assertWalletConsistent($user);

        $this->actingAs($user)->get(route('orders.show', $order))->assertOk()->assertSee('Paid from your wallet');
    }

    public function test_a_partial_wallet_payment_sends_only_the_remainder_to_bml(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 120, 'gift_card');
        $this->fillCart($user, 500);

        $response = $this->checkout($user);
        $order = Order::where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect('https://pay.bml.test/txn_test');

        $this->assertNotSame('paid', $order->payment_status);
        $this->assertSame('bml', $order->payment_method);
        $this->assertEquals(120, (float) $order->wallet_amount);
        $this->assertEquals((float) $order->total_amount - 120, $order->cardAmount());
        $this->assertEquals(0, (float) $user->fresh()->wallet_balance);

        $bml = PaymentTransaction::where('order_id', $order->id)->where('gateway', 'bml')->firstOrFail();
        $this->assertSame((int) round($order->cardAmount() * 100), $bml->amount);
        Http::assertSent(fn ($request) => $request['amount'] === (int) round($order->cardAmount() * 100));

        $this->actingAs($user)->get(route('orders.show', $order))->assertOk()->assertSee('From your wallet');
        $this->assertWalletConsistent($user);
    }

    public function test_cancelling_an_order_the_wallet_paid_credits_the_wallet_back_and_only_flags_the_card_part(): void
    {
        $user = User::factory()->create();
        app(WalletService::class)->credit($user, 100, 'adjustment');
        $this->fillCart($user, 500);
        $this->checkout($user);
        $order = Order::where('user_id', $user->id)->firstOrFail();
        app(PaymentService::class)->confirm($order); // the card part paid

        app(OrderService::class)->updateOrderStatus($order->fresh(), 'cancelled');
        $order = $order->fresh();
        $this->assertEquals(100, (float) $user->fresh()->wallet_balance, 'wallet share back');
        $this->assertNotNull($order->wallet_refunded_at);
        $this->assertSame('due', $order->refund_status);
        $this->assertEquals((float) $order->total_amount - 100, (float) $order->refund_amount, 'only the card part needs a manual refund');
        $this->assertWalletConsistent($user);

        // A fully wallet-paid order needs no manual refund at all
        $other = User::factory()->create();
        app(WalletService::class)->credit($other, 5000, 'adjustment');
        $this->fillCart($other, 300);
        $this->checkout($other);
        $paid = Order::where('user_id', $other->id)->firstOrFail();
        $this->assertSame('paid', $paid->payment_status);
        $this->actingAs($other)->post(route('orders.cancel', $paid))->assertRedirect();
        $this->assertEquals(5000, (float) $other->fresh()->wallet_balance);
        $this->assertNull($paid->fresh()->refund_status);
        $this->assertWalletConsistent($other);
    }

    public function test_the_balance_can_never_go_negative(): void
    {
        $user = User::factory()->create();
        $wallet = app(WalletService::class);
        $wallet->credit($user, 50, 'adjustment');

        $this->expectException(\RuntimeException::class);
        try {
            $wallet->debit($user, 50.01, 'purchase');
        } finally {
            $this->assertEquals(50, (float) $user->fresh()->wallet_balance);
            $this->assertSame(1, WalletTransaction::where('user_id', $user->id)->count());
        }
    }

    // ---- Refund to wallet -----------------------------------------------------------------------

    public function test_admin_can_refund_a_due_refund_to_the_wallet_instead_of_the_card(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'cancelled', 'payment_status' => 'paid', 'payment_method' => 'bml', 'total_amount' => 250, 'wallet_amount' => 0]);
        app(PaymentService::class)->flagRefund($order, 250, 'Order cancelled after payment');

        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Refund to wallet');
        $this->actingAs($this->admin)->post(route('admin.orders.refund-wallet', $order))->assertRedirect();

        $order = $order->fresh();
        $this->assertSame('refunded', $order->refund_status);
        $this->assertStringStartsWith('WALLET-', $order->refund_reference);
        $this->assertEquals(250, (float) $user->fresh()->wallet_balance);
        $this->assertDatabaseHas('wallet_transactions', ['user_id' => $user->id, 'type' => 'refund', 'order_id' => $order->id, 'amount' => 250]);
        Notification::assertSentTo($user, RefundRecorded::class);
        $this->assertWalletConsistent($user);

        // Not twice
        $this->actingAs($this->admin)->post(route('admin.orders.refund-wallet', $order))->assertSessionHas('error');
        $this->assertEquals(250, (float) $user->fresh()->wallet_balance);

        $this->actingAs($user)->get(route('account.wallet'))->assertOk()->assertSee('250.00')->assertSee($order->order_number);
        $this->actingAs($user)->get(route('account'))->assertOk()->assertSee('Wallet balance');
    }

    // ---- Gift cards ------------------------------------------------------------------------------

    public function test_a_gift_card_is_bought_issued_on_payment_and_redeemed_into_the_recipients_wallet(): void
    {
        $buyer = User::factory()->create();
        $this->get(route('gift-cards'))->assertOk()->assertSee('Sign in to buy a gift card');

        $response = $this->actingAs($buyer)->post(route('gift-cards.store'), [
            'amount' => 250, 'recipient_email' => 'Friend@Example.com', 'recipient_name' => 'Ahmed', 'message' => 'Happy birthday!',
        ]);
        $response->assertRedirect('https://pay.bml.test/txn_test');

        $card = GiftCard::firstOrFail();
        $order = $card->order;
        $this->assertSame('pending', $card->status);
        $this->assertNull($card->code);
        $this->assertSame('friend@example.com', $card->recipient_email);
        $this->assertEquals(250, (float) $order->total_amount);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame('Gift card', (string) $order->items()->first()->product->name);
        $this->assertSame(0, (int) $order->loyalty_points_earned);
        Notification::assertNotSentTo(new AnonymousNotifiable, GiftCardIssued::class);

        // Paid: the card is issued and emailed to the recipient
        app(PaymentService::class)->confirm($order);
        $card = $card->fresh();
        $this->assertSame('active', $card->status);
        $this->assertMatchesRegularExpression('/^IRU-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $card->code);
        $this->assertTrue($card->expires_at->between(now()->addMonths(12)->subMinute(), now()->addMonths(12)->addMinute()));
        $this->assertNotNull($card->delivered_at);
        Notification::assertSentOnDemand(GiftCardIssued::class, fn ($n, $channels, $notifiable) => array_key_exists('friend@example.com', $notifiable->routes['mail']) && $n->card->is($card));
        $this->assertStringContainsString($card->code, (new GiftCardIssued($card))->toMail(new AnonymousNotifiable)->render());

        // Confirming again does not issue a second time
        app(PaymentService::class)->confirm($order->fresh());
        Notification::assertSentOnDemandTimes(GiftCardIssued::class, 1);

        // The recipient redeems it (code typed loosely), once
        $recipient = User::factory()->create(['email' => 'friend@example.com']);
        $this->actingAs($recipient)->post(route('account.wallet.redeem'), ['code' => strtolower(str_replace('-', ' ', $card->code))])->assertRedirect(route('account.wallet'));
        $this->assertEquals(250, (float) $recipient->fresh()->wallet_balance);
        $card = $card->fresh();
        $this->assertSame('redeemed', $card->status);
        $this->assertEquals(0, (float) $card->balance);
        $this->assertSame($recipient->id, $card->redeemed_by);
        $this->assertWalletConsistent($recipient);

        $this->actingAs($recipient)->post(route('account.wallet.redeem'), ['code' => $card->code])->assertSessionHasErrors('code');
        $this->actingAs($recipient)->post(route('account.wallet.redeem'), ['code' => 'IRU-NOPE-NOPE-NOPE'])->assertSessionHasErrors('code');
        $this->assertEquals(250, (float) $recipient->fresh()->wallet_balance);

        // And the credit pays for an order
        $this->fillCart($recipient, 200);
        $this->checkout($recipient);
        $this->assertSame('paid', Order::where('user_id', $recipient->id)->firstOrFail()->payment_status);
        $this->assertEquals(250 - (float) Order::where('user_id', $recipient->id)->firstOrFail()->total_amount, (float) $recipient->fresh()->wallet_balance);
    }

    public function test_gift_card_amounts_are_validated_and_custom_amounts_allowed(): void
    {
        $buyer = User::factory()->create();

        $this->actingAs($buyer)->post(route('gift-cards.store'), ['amount' => 'custom', 'custom_amount' => 20, 'recipient_email' => 'a@b.mv'])->assertSessionHasErrors('custom_amount');
        $this->actingAs($buyer)->post(route('gift-cards.store'), ['amount' => 'custom', 'custom_amount' => 6000, 'recipient_email' => 'a@b.mv'])->assertSessionHasErrors('custom_amount');
        $this->actingAs($buyer)->post(route('gift-cards.store'), ['amount' => 777, 'recipient_email' => 'a@b.mv'])->assertSessionHasErrors('amount');
        $this->actingAs($buyer)->post(route('gift-cards.store'), ['amount' => 'custom', 'custom_amount' => 300, 'recipient_email' => 'not-an-email'])->assertSessionHasErrors('recipient_email');
        $this->assertSame(0, GiftCard::count());

        $this->actingAs($buyer)->post(route('gift-cards.store'), ['amount' => 'custom', 'custom_amount' => 300, 'recipient_email' => 'a@b.mv'])->assertRedirect('https://pay.bml.test/txn_test');
        $this->assertEquals(300, (float) GiftCard::firstOrFail()->amount);

        $this->post(route('gift-cards.store'), ['amount' => 100, 'recipient_email' => 'a@b.mv']);
    }

    public function test_expired_and_cancelled_cards_cannot_be_redeemed_and_unpaid_orders_cancel_the_card(): void
    {
        $buyer = User::factory()->create();
        $this->actingAs($buyer)->post(route('gift-cards.store'), ['amount' => 100, 'recipient_email' => 'x@y.mv']);
        $card = GiftCard::firstOrFail();

        // The buyer never pays and cancels: the card goes with the order
        $this->actingAs($buyer)->post(route('orders.cancel', $card->order))->assertRedirect();
        $this->assertSame('cancelled', $card->fresh()->status);

        $expired = GiftCard::create(['code' => 'IRU-AAAA-BBBB-CCCC', 'amount' => 100, 'balance' => 100, 'recipient_email' => 'x@y.mv', 'status' => 'active', 'expires_at' => now()->subDay()]);
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('account.wallet.redeem'), ['code' => 'IRU-AAAA-BBBB-CCCC'])->assertSessionHasErrors('code');
        $this->assertSame('expired', $expired->fresh()->status);
        $this->assertEquals(0, (float) $user->fresh()->wallet_balance);

        // Admin cancels an active card
        $active = GiftCard::create(['code' => 'IRU-DDDD-EEEE-FFFF', 'amount' => 100, 'balance' => 100, 'recipient_email' => 'x@y.mv', 'status' => 'active', 'expires_at' => now()->addYear()]);
        $this->actingAs($this->admin)->get(route('admin.gift-cards'))->assertOk()->assertSee('IRU-DDDD-EEEE-FFFF');
        $this->actingAs($this->admin)->post(route('admin.gift-cards.cancel', $active))->assertRedirect();
        $this->assertSame('cancelled', $active->fresh()->status);
        $this->actingAs($user)->post(route('account.wallet.redeem'), ['code' => 'IRU-DDDD-EEEE-FFFF'])->assertSessionHasErrors('code');
    }

    public function test_only_admins_reach_admin_wallet_pages_and_users_only_see_their_own_wallet(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $user->id, 'status' => 'cancelled', 'payment_status' => 'paid', 'refund_status' => 'due', 'refund_amount' => 50, 'refund_reason' => 'x']);

        $this->get(route('account.wallet'))->assertRedirect(route('login'));
        $this->get(route('admin.gift-cards'))->assertRedirect(route('login'));

        $this->actingAs($user)->get(route('admin.gift-cards'))->assertForbidden();
        $this->actingAs($user)->post(route('admin.orders.refund-wallet', $order))->assertForbidden();
        $this->assertEquals(0, (float) $user->fresh()->wallet_balance);

        $other = User::factory()->create();
        app(WalletService::class)->credit($other, 999, 'adjustment');
        $this->actingAs($user)->get(route('account.wallet'))->assertOk()->assertDontSee('999.00');
    }
}
