<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartReminder;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\CartReminder as CartReminderNotification;
use App\Services\CartReminderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Abandoned-cart emails: a nudge 3 hours after the customer last touched the cart, a second one after
 * 48 hours (with a single-use voucher when Settings say so), never twice, never after an order.
 */
class AbandonedCartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Carbon::setTestNow('2026-10-02 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function abandonedCart(?User $user = null): Cart
    {
        $user ??= User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => Product::factory()->create(['price' => 200])->id, 'quantity' => 2, 'price' => 200]);

        return $cart->fresh();
    }

    public function test_first_reminder_goes_out_three_hours_after_the_last_change_and_only_once(): void
    {
        $cart = $this->abandonedCart();

        $this->travel(2)->hours();
        $this->artisan('marketing:cart-reminders')->assertSuccessful();
        Notification::assertNothingSent();

        $this->travel(2)->hours();
        $this->artisan('marketing:cart-reminders')->assertSuccessful();
        Notification::assertSentTo($cart->user, CartReminderNotification::class, fn ($n) => $n->stage === 1 && $n->voucher === null && $n->cart->is($cart));
        $this->assertDatabaseHas('cart_reminders', ['cart_id' => $cart->id, 'stage' => 1]);

        // Running again an hour later sends nothing new
        $this->travel(1)->hours();
        $this->artisan('marketing:cart-reminders');
        Notification::assertSentTimes(CartReminderNotification::class, 1);
    }

    public function test_the_email_lists_the_items_with_images_and_links_to_the_cart_and_an_unsubscribe_link(): void
    {
        $cart = $this->abandonedCart();
        $product = $cart->items->first()->product;

        $mail = (new CartReminderNotification($cart, 1))->toMail($cart->user);
        $html = (string) $mail->render();

        $this->assertStringContainsString((string) $product->name, $html);
        $this->assertStringContainsString('<img', $html);
        $this->assertStringContainsString(route('cart'), $html);
        $this->assertStringContainsString('/marketing/unsubscribe/'.$cart->user_id, $html);
        $this->assertStringContainsString('signature=', $html);
    }

    public function test_second_reminder_after_48_hours_carries_a_single_use_voucher_for_that_customer_only(): void
    {
        Setting::set(['abandoned_cart_voucher_percent' => 10]);
        $cart = $this->abandonedCart();
        $stranger = User::factory()->create();

        $this->travel(4)->hours();
        $this->artisan('marketing:cart-reminders');
        $this->travel(45)->hours(); // 49 h after the cart was last touched
        $this->artisan('marketing:cart-reminders');

        Notification::assertSentTo($cart->user, CartReminderNotification::class, fn ($n) => $n->stage === 2 && $n->voucher !== null);
        $reminder = CartReminder::where('cart_id', $cart->id)->where('stage', 2)->firstOrFail();
        $voucher = Voucher::findOrFail($reminder->voucher_id);
        $this->assertSame('percent', $voucher->type);
        $this->assertEquals(10, (float) $voucher->amount);
        $this->assertSame(1, $voucher->max_uses);
        $this->assertSame($cart->user_id, $voucher->user_id);
        $this->assertTrue($voucher->valid_until->equalTo(now()->addDays(7)));

        // Only the customer it was issued to can apply it
        $this->actingAs($stranger)->post(route('cart.add'), ['product_id' => Product::factory()->create(['price' => 50])->id, 'quantity' => 1]);
        $this->actingAs($stranger)->post(route('cart.applyVoucher'), ['voucher_code' => $voucher->code])->assertSessionHasErrors('voucher_code');

        $this->actingAs($cart->user)->post(route('cart.applyVoucher'), ['voucher_code' => $voucher->code])->assertSessionHasNoErrors();
        $this->assertSame($voucher->code, session('voucher_code'));

        // A third run sends nothing more to this customer (the stranger's new cart gets its own first nudge)
        $this->travel(1)->days();
        $this->artisan('marketing:cart-reminders');
        Notification::assertSentToTimes($cart->user, CartReminderNotification::class, 2);
    }

    public function test_no_voucher_when_the_percent_is_zero(): void
    {
        Setting::set(['abandoned_cart_voucher_percent' => 0]);
        $cart = $this->abandonedCart();

        $this->travel(4)->hours();
        $this->artisan('marketing:cart-reminders');
        $this->travel(2)->days();
        $this->artisan('marketing:cart-reminders');

        Notification::assertSentTo($cart->user, CartReminderNotification::class, fn ($n) => $n->stage === 2 && $n->voucher === null);
        $this->assertSame(0, Voucher::count());
    }

    public function test_guests_opted_out_customers_ordered_customers_and_empty_carts_are_skipped(): void
    {
        // Guest cart
        $guest = Cart::factory()->create(['user_id' => null, 'session_id' => 'tok', 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $guest->id, 'product_id' => Product::factory()->create()->id, 'price' => 10]);

        // Opted out
        $optedOut = $this->abandonedCart(User::factory()->create(['marketing_opt_out_at' => now()]));

        // Ordered since the cart was last touched
        $ordered = $this->abandonedCart();
        $this->travel(1)->hours();
        Order::factory()->create(['user_id' => $ordered->user_id, 'status' => 'pending']);

        // Empty cart
        Cart::factory()->create(['status' => 'active']);

        $this->travel(5)->hours();
        $this->artisan('marketing:cart-reminders');

        Notification::assertNothingSent();
        $this->assertSame(0, CartReminder::count());
    }

    public function test_changing_the_cart_restarts_the_clock(): void
    {
        $cart = $this->abandonedCart();
        $this->travel(2)->hours();
        $cart->items()->create(['product_id' => Product::factory()->create()->id, 'quantity' => 1, 'price' => 10]);

        $this->travel(2)->hours(); // 4 h since the cart row, 2 h since the new line
        $this->artisan('marketing:cart-reminders');
        Notification::assertNothingSent();

        $this->travel(2)->hours();
        $this->artisan('marketing:cart-reminders');
        Notification::assertSentTimes(CartReminderNotification::class, 1);
    }

    public function test_setting_switches_the_emails_off(): void
    {
        Setting::set(['abandoned_cart_emails_enabled' => 0]);
        $this->abandonedCart();

        $this->travel(1)->days();
        $this->artisan('marketing:cart-reminders')->expectsOutputToContain('switched off');
        Notification::assertNothingSent();

        $this->assertFalse(app(CartReminderService::class)->enabled());
    }

    public function test_signed_unsubscribe_link_opts_the_customer_out_and_the_account_page_can_turn_it_back_on(): void
    {
        $user = User::factory()->create();
        $url = URL::signedRoute('marketing.unsubscribe', ['user' => $user->id]);

        $this->get(route('marketing.unsubscribe', ['user' => $user->id]))->assertForbidden();
        $this->get($url)->assertOk()->assertSee('You are unsubscribed from marketing emails')->assertSee($user->email);
        $this->assertNotNull($user->fresh()->marketing_opt_out_at);

        $this->actingAs($user)->get(route('account'))->assertOk()->assertSee('Marketing emails')->assertSee('Turn on');
        $this->actingAs($user)->put(route('account.marketing'), ['marketing_emails' => 1])->assertRedirect();
        $this->assertNull($user->fresh()->marketing_opt_out_at);
    }

    public function test_admin_can_change_the_settings(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->actingAs($admin)->get(route('admin.settings'))->assertOk()->assertSee('Abandoned cart emails');

        $this->actingAs($admin)->put(route('admin.settings.update'), [
            'loyalty_spend_per_point' => 100, 'referral_referrer_points' => 100, 'referral_referee_points' => 50,
            'abandoned_cart_emails_enabled' => 0, 'abandoned_cart_voucher_percent' => 15,
        ])->assertRedirect(route('admin.settings'));

        $this->assertEquals(0, Setting::get('abandoned_cart_emails_enabled'));
        $this->assertEquals(15, Setting::get('abandoned_cart_voucher_percent'));
    }
}
