<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\ItemRequest;
use App\Models\Message;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductQuestion;
use App\Models\ProductReview;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\SmsMessage;
use App\Models\User;
use App\Services\CustomerAccountService;
use App\Services\NewsletterService;
use App\Support\ErrorTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Audit 2, batch 4: accounts (phone changes, merging, erasing, two-step codes), privacy (masking,
 * exports, error log, emails) and staff roles.
 */
class AccountsPrivacyTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function seller(string $name = 'Island Crafts'): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    public function test_a_phone_only_customers_new_number_from_the_team_counts_as_verified(): void
    {
        $admin = $this->staff('admin');
        $phoneOnly = User::factory()->create(['email' => null, 'phone' => '7771234', 'phone_verified_at' => now()]);
        $phoneOnly->forceFill(['has_password' => false])->save();
        $withEmail = User::factory()->create(['phone' => '7771235', 'phone_verified_at' => now()]);

        $this->actingAs($admin)->post(route('admin.customers.phone', $phoneOnly), ['phone' => '7779999', '_method' => 'PUT'])->assertSessionHas('success', fn ($m) => str_contains($m, 'counts as verified'));
        $this->assertNotNull($phoneOnly->fresh()->phone_verified_at, 'they can sign in with a code to it');

        $this->post(route('admin.customers.phone', $withEmail), ['phone' => '7779998', '_method' => 'PUT'])->assertSessionHas('success', fn ($m) => str_contains($m, 'needs to verify'));
        $this->assertNull($withEmail->fresh()->phone_verified_at);
    }

    public function test_accounts_merge_by_phone_or_number_and_bring_their_item_requests_and_referrals(): void
    {
        $admin = $this->staff('admin');
        $keep = User::factory()->create(['email' => null, 'phone' => '7771234', 'phone_verified_at' => now()]);
        $duplicate = User::factory()->create();
        $request = new ItemRequest(['title' => 'Shoes', 'quantity' => 1]);
        $request->forceFill(['user_id' => $duplicate->id, 'status' => 'new'])->save();
        $referred = User::factory()->create(['referred_by' => $duplicate->id]);

        $this->actingAs($admin)->post(route('admin.customers.merge', $duplicate), ['into' => 'nobody', 'confirm' => 1])->assertSessionHasErrors('into');
        $this->post(route('admin.customers.merge', $duplicate), ['into' => '777 1234', 'confirm' => 1])->assertRedirect(route('admin.customers.show', $keep));
        $this->assertSame($keep->id, (int) $request->fresh()->user_id);
        $this->assertSame($keep->id, (int) $referred->fresh()->referred_by);

        $other = User::factory()->create();
        $this->post(route('admin.customers.merge', $other), ['into' => '#'.$keep->id, 'confirm' => 1])->assertRedirect(route('admin.customers.show', $keep));
    }

    public function test_an_account_with_open_invoices_or_cases_cannot_be_erased_and_erasing_removes_what_they_wrote(): void
    {
        Storage::fake('public');
        $customer = User::factory()->create(['email' => 'aisha@example.com', 'phone' => '7771234', 'phone_verified_at' => now()]);
        $accounts = app(CustomerAccountService::class);

        // A pay-later invoice still to pay
        $credit = Order::factory()->create(['user_id' => $customer->id, 'status' => 'delivered', 'payment_method' => \App\Services\CreditService::METHOD, 'payment_status' => 'unpaid']);
        $this->assertStringContainsString('pay-later', (string) $accounts->eraseProblem($customer));
        $credit->forceFill(['payment_status' => 'paid'])->save();

        // A return still being decided
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'delivered', 'payment_status' => 'paid', 'shipping_phone' => '7775555']);
        $part = (new SellerOrder)->forceFill(['order_id' => $order->id, 'seller_id' => $this->seller()->id, 'status' => 'delivered', 'subtotal' => 100])->save();
        $return = ReturnRequest::create(['order_id' => $order->id, 'seller_order_id' => SellerOrder::where('order_id', $order->id)->value('id'), 'user_id' => $customer->id, 'status' => 'requested', 'reason' => 'faulty', 'items_value' => 100]);
        $this->assertStringContainsString('return or dispute', (string) $accounts->eraseProblem($customer));
        $return->forceFill(['status' => 'rejected'])->save();
        $this->assertNull($accounts->eraseProblem($customer));

        // What they wrote, and texts to their delivery number
        $product = Product::factory()->create();
        $review = ProductReview::create(['product_id' => $product->id, 'user_id' => $customer->id, 'reviewer_name' => 'Aisha', 'reviewer_email' => 'aisha@example.com', 'rating' => 4, 'title' => 'Nice', 'comment' => 'Call me on 7771234 about it', 'is_approved' => true]);
        $question = ProductQuestion::create(['product_id' => $product->id, 'user_id' => $customer->id, 'question' => 'Is it waterproof? Aisha']);
        SmsMessage::create(['to' => '+9607775555', 'message' => 'Your order is out for delivery', 'status' => 'sent', 'created_at' => now()]);
        DB::table('password_reset_tokens')->insert(['email' => 'aisha@example.com', 'token' => 'x', 'created_at' => now()]);

        $this->assertNull($accounts->erase($customer, null, 'asked'));
        $this->assertSame(['Erased customer', ''], [$review->fresh()->reviewer_name, $review->fresh()->comment]);
        $this->assertStringNotContainsString('aisha', $review->fresh()->reviewer_email);
        $this->assertSame('Question removed', $question->fresh()->question);
        $this->assertSame(0, SmsMessage::where('to', '+9607775555')->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', 'aisha@example.com')->count());
        $this->assertSame(4, (int) $review->fresh()->rating, 'the rating stays');
    }

    public function test_support_staff_can_do_what_their_role_allows(): void
    {
        Notification::fake();
        Storage::fake('local');
        $support = $this->staff('support');
        $customer = User::factory()->create();
        $shop = $this->seller();
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'processing', 'payment_status' => 'paid']);
        (new SellerOrder)->forceFill(['order_id' => $order->id, 'seller_id' => $shop->id, 'status' => 'processing', 'subtotal' => 100])->save();
        $part = SellerOrder::where('order_id', $order->id)->sole();

        // Writing to the customer from the order page, and saving delivery details
        $this->actingAs($support)->post(route('admin.orders.messages.store', [$order, $part]), ['body' => 'Your order is on its way soon.'])->assertRedirect();
        $this->assertSame(1, Message::count());
        $this->post(route('admin.orders.parts.tracking', [$order, $part]), ['courier' => 'Island Express', 'tracking_number' => 'IE1'])->assertRedirect();
        $this->assertSame('Island Express', $part->fresh()->courier);

        // The photo with a return they are deciding
        Storage::disk('local')->put('returns/broken.jpg', 'jpg');
        $return = ReturnRequest::create(['order_id' => $order->id, 'seller_order_id' => $part->id, 'user_id' => $customer->id, 'status' => 'requested', 'reason' => 'faulty', 'items_value' => 100, 'photo_path' => 'returns/broken.jpg']);
        $this->get(route('returns.photo', $return))->assertOk();
        $this->actingAs(User::factory()->create())->get(route('returns.photo', $return))->assertForbidden();
    }

    public function test_a_visitor_sees_the_photos_in_their_own_conversation(): void
    {
        Storage::fake(\App\Services\MessagingService::DISK);
        $conversation = app(\App\Services\MessagingService::class)->guestConversation(Conversation::SUPPORT, 'Visitor', 'v@example.com', null);
        $conversation->save();
        Storage::disk(\App\Services\MessagingService::DISK)->put('messages/photo.jpg', 'jpg');
        $message = Message::create(['conversation_id' => $conversation->id, 'sender_role' => 'admin', 'body' => 'Is this it?', 'attachment_path' => 'messages/photo.jpg']);

        $url = route('messages.guest.attachment', ['conversation' => $conversation->id, 'token' => $conversation->access_token, 'message' => $message->id]);
        $this->get($conversation->guestUrl())->assertOk()->assertSee($url, false);
        $this->get($url)->assertOk();
        $this->get(route('messages.guest.attachment', ['conversation' => $conversation->id, 'token' => str_repeat('a', 48), 'message' => $message->id]))->assertNotFound();
    }

    public function test_a_visitor_with_only_a_foreign_number_is_asked_for_one_we_can_reach(): void
    {
        $this->post(route('messages.support.store'), ['name' => 'Tourist', 'phone' => '+44 7700 900123', 'body' => 'Do you deliver to resorts?'])
            ->assertSessionHasErrors(['phone' => __('We can text Maldivian mobile numbers only. Give a Maldivian mobile number or an email address, so we can answer you.')]);
        $this->assertSame(0, Conversation::count());
    }

    public function test_the_api_names_the_shop_not_its_owner(): void
    {
        $shop = User::factory()->create(['name' => 'Ahmed Private', 'is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reef Goods']);
        Product::factory()->create(['seller_id' => $shop->id, 'is_active' => true]);

        $this->getJson('/api/v1/products')->assertOk()->assertSee('Reef Goods')->assertDontSee('Ahmed Private');
    }

    public function test_exports_neutralise_formulas_typed_by_the_public(): void
    {
        NewsletterSubscriber::create(['email' => "=cmd|'/Ccalc'!A0@x.mv", 'locale' => 'en', 'confirmed_at' => now()]);
        $csv = $this->actingAs($this->staff('admin'))->get(route('admin.newsletter', ['export' => 'csv']))->streamedContent();
        $this->assertStringContainsString("'=cmd", $csv);
    }

    public function test_newsletters_and_cart_reminders_go_to_confirmed_customers_only(): void
    {
        $confirmed = User::factory()->create(['email_verified_at' => now()]);
        $unconfirmed = User::factory()->create(['email_verified_at' => null]);
        $shop = $this->seller();
        $ids = app(NewsletterService::class)->customers()->pluck('id')->all();
        $this->assertContains($confirmed->id, $ids);
        $this->assertNotContains($unconfirmed->id, $ids);
        $this->assertNotContains($shop->id, $ids);
    }

    public function test_public_questions_answers_and_shop_replies_hide_contact_details(): void
    {
        $shop = $this->seller();
        $product = Product::factory()->create(['seller_id' => $shop->id, 'is_active' => true]);
        $this->actingAs(User::factory()->create())->post(route('questions.store', $product), ['question' => 'Can I call you on 777,1234 or mail ali@gmail?'])->assertRedirect();
        $question = ProductQuestion::sole();
        $this->assertStringNotContainsString('1234', $question->question);
        $this->assertStringNotContainsString('ali@gmail', $question->question);

        $this->actingAs($shop)->post(route('questions.answer', $question), ['answer' => 'Sure, WhatsApp 7771234'])->assertRedirect();
        $this->assertStringNotContainsString('7771234', $question->fresh()->answer);
        $this->assertSame('Delivered on 12/11/2026', \App\Services\MessagingService::mask('Delivered on 12/11/2026'), 'a date is not a phone number');
    }

    public function test_a_shop_cannot_review_its_own_products(): void
    {
        $shop = $this->seller();
        $product = Product::factory()->create(['seller_id' => $shop->id, 'is_active' => true]);
        $this->actingAs($shop)->post(route('reviews.store', $product), ['rating' => 5, 'comment' => 'Best product in the Maldives!'])->assertSessionHasErrors('comment');
        $this->assertSame(0, ProductReview::count());
    }

    public function test_push_addresses_must_be_a_browser_push_service(): void
    {
        $this->actingAs(User::factory()->create())->postJson('/account/push', ['endpoint' => 'https://169.254.169.254/latest/meta-data', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertStatus(422);
        $this->postJson('/account/push', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'p', 'auth' => 'a']])->assertSuccessful();
    }

    public function test_the_error_list_keeps_no_links_that_open_something_on_their_own(): void
    {
        $this->assertSame('https://iruali.mv/orders/guest/12/[token]?page=2', ErrorTracker::withoutSecrets('https://iruali.mv/orders/guest/12/'.str_repeat('a', 40).'?page=2'));
        $this->assertSame('https://iruali.mv/pay/12?expires=1&signature=[hidden]', ErrorTracker::withoutSecrets('https://iruali.mv/pay/12?expires=1&signature=abc123'));

        // BML's webhook has its own limit, not the 60-a-minute one for one visitor
        $route = app('router')->getRoutes()->getByName('payments.bml.webhook');
        $this->assertNotContains('throttle:api', $route->gatherMiddleware());
    }

    public function test_five_wrong_two_step_codes_lock_any_account_whatever_address_they_come_from(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $user = User::factory()->create();
        $user->forceFill(['two_factor_enabled' => true, 'two_factor_secret' => encrypt($secret)])->save();

        foreach (range(1, 5) as $i) {
            $this->withSession(['2fa_user_id' => $user->id])->withServerVariables(['REMOTE_ADDR' => '10.0.0.'.$i])
                ->post(route('2fa.verify'), ['code' => '000000']);
        }
        // Even the right code now sends them back to the password step
        $this->withSession(['2fa_user_id' => $user->id])->withServerVariables(['REMOTE_ADDR' => '10.0.0.9'])
            ->post(route('2fa.verify'), ['code' => (new Google2FA)->getCurrentOtp($secret)])->assertRedirect(route('login.email'));
        $this->assertGuest();
    }
}
