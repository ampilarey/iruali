<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Conversation;
use App\Models\ItemRequest;
use App\Models\Message;
use App\Models\Product;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Services\MessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\Feature\Concerns\ActsAsShopStaff;
use Tests\Support\PreorderFixtures;
use Tests\TestCase;

/**
 * The messages centre (App\Services\MessagingService): "Ask about this item", "Message iruali" for
 * customers and shops, item request threads, My messages and Seller Centre → Messages. iruali's team sees
 * and can join every conversation, contact details are hidden in item questions, messages can be reported,
 * accounts blocked, and nothing is ever deleted.
 */
class MessagesCentreTest extends TestCase
{
    use ActsAsShopStaff, PreorderFixtures, RefreshDatabase {
        ActsAsShopStaff::staffMember insteadof PreorderFixtures;
        PreorderFixtures::staffMember as teamMember;
    }

    protected User $shop;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->shop = $this->staffedShop('Reef Goods', ['onboarding_completed_at' => now()]);
        $this->product = Product::factory()->create([
            'seller_id' => $this->shop->id, 'category_id' => Category::factory()->create(['status' => 'active'])->id,
            'name' => 'Blue kettle', 'price' => 300, 'stock_quantity' => 5, 'is_active' => true,
        ]);
    }

    public function test_a_customer_asks_about_an_item_and_contact_details_are_hidden(): void
    {
        $this->get(route('products.show', $this->product))->assertOk()->assertSee('data-ask-about-item', false)->assertSee('data-guest-fields', false);
        $customer = User::factory()->create(['name' => 'Aishath Mohamed']);

        $this->actingAs($customer)->post(route('messages.product.store', $this->product), ['body' => 'Is it original? Call me on +960 777 1234 or aishath@example.com, or see https://other-shop.mv/kettle'])
            ->assertRedirect()->assertSessionHas('success', 'Message sent. Phone numbers, emails and links were hidden: please keep the conversation on iruali.');

        $conversation = Conversation::sole();
        $message = Message::sole();
        $this->assertSame([Conversation::PRODUCT, $customer->id, $this->shop->id, $this->product->id, 'Blue kettle'], [$conversation->kind, $conversation->customer_id, $conversation->seller_id, $conversation->product_id, $conversation->subject]);
        $this->assertStringNotContainsString('777', $message->body);
        $this->assertStringNotContainsString('example.com', $message->body);
        $this->assertStringNotContainsString('other-shop', $message->body);
        $this->assertStringContainsString('[contact details hidden]', $message->body);
        $this->assertStringContainsString('aishath@example.com', $message->original_body);
        Notification::assertSentTo($this->shop, NewMessage::class);

        // A second question goes into the same conversation; My messages lists it
        $this->post(route('messages.product.store', $this->product), ['body' => 'And the warranty?']);
        $this->assertSame(1, Conversation::count());
        $this->get(route('messages.index'))->assertOk()->assertSee('About: Blue kettle')->assertSee('data-conversation-row="'.$conversation->id.'"', false);
        $this->get(route('products.show', $this->product))->assertSee('data-ask-conversation', false);

        // The shop answers in Seller Centre → Messages, seeing a shortened name and no contact details
        $this->actingAs($this->shop)->get(route('seller.messages'))->assertOk()->assertSee('data-seller-conversation="'.$conversation->id.'"', false)->assertSee('Ai****');
        $this->get(route('seller.messages.show', $conversation))->assertOk()->assertSee('Asked by Ai****')->assertDontSee('aishath@example.com')->assertDontSee('Aishath Mohamed');
        $this->post(route('seller.messages.reply', $conversation), ['body' => 'Yes, original. WhatsApp me on 7654321.'])->assertRedirect(route('seller.messages.show', $conversation));
        $reply = Message::latest('id')->first();
        $this->assertStringNotContainsString('7654321', $reply->body);
        Notification::assertSentTo($customer, NewMessage::class);

        // The customer sees one store: the shop's reply is from "iruali"
        $page = $this->actingAs($customer)->get(route('messages.show', $conversation))->assertOk()->assertSee('Yes, original.')->getContent();
        $this->assertStringNotContainsString('Reef Goods', $page);
        $this->assertSame('iruali', $reply->senderName('customer'));

        // The team sees everything as written
        $this->actingAs($this->teamMember('admin'))->get(route('admin.messages', ['show' => 'all']))->assertOk()->assertSee('data-admin-conversation="'.$conversation->id.'"', false);
        $this->get(route('admin.messages.show', $conversation))->assertOk()->assertSee('data-chat-original', false)
            ->assertSee('aishath@example.com')->assertSee('WhatsApp me on 7654321')->assertSee('Aishath Mohamed');

        // Not for outsiders, and not on the shop's own items
        $this->actingAs(User::factory()->create())->get(route('messages.show', $conversation))->assertNotFound();
        $this->actingAs($this->staffedShop('Other Shop'))->get(route('seller.messages.show', $conversation))->assertNotFound();
        $this->actingAs($this->shop)->post(route('messages.product.store', $this->product), ['body' => 'Testing'])->assertSessionHas('error');
    }

    public function test_shop_staff_who_answer_customers_see_the_messages(): void
    {
        $conversation = app(MessagingService::class)->productConversation(User::factory()->create(), $this->product);
        app(MessagingService::class)->send($conversation, $conversation->customer, 'customer', 'Do you deliver to Addu?');

        $this->asShopStaff($this->shop, 'manager')->get(route('seller.messages'))->assertOk()->assertSee('About: Blue kettle');
        $this->get(route('seller.messages.show', $conversation))->assertOk();
        $this->asShopStaff($this->shop, 'packer')->get(route('seller.messages'))->assertForbidden();
    }

    public function test_customers_and_shops_message_iruali_and_the_team_answers(): void
    {
        $customer = User::factory()->create();
        $this->actingAs($customer)->get(route('messages.support'))->assertOk()->assertSee('Message iruali');
        $this->post(route('messages.support.store'), ['body' => 'My payment failed twice.'])->assertRedirect();
        $support = Conversation::where('kind', Conversation::SUPPORT)->sole();
        $this->assertSame([$customer->id, null, 1], [$support->customer_id, $support->seller_id, $support->admin_unread_count]);
        // The next message goes into the same open conversation
        $this->post(route('messages.support.store'), ['body' => 'It says declined.']);
        $this->assertSame(2, $support->messages()->count());

        $this->actingAs($this->shop)->get(route('seller.messages.support'))->assertOk();
        $this->post(route('seller.messages.support.store'), ['body' => 'When is my payout?'])->assertRedirect();
        $shopSupport = Conversation::where('kind', Conversation::SHOP_SUPPORT)->sole();
        $this->assertSame($this->shop->id, $shopSupport->seller_id);

        // Support staff (not only admins) read and answer from Admin → Messages
        $agent = $this->teamMember('support');
        $this->actingAs($agent)->get(route('admin.messages'))->assertOk()->assertSee('data-admin-conversation="'.$support->id.'"', false);
        $this->get(route('admin.messages.show', $support))->assertOk()->assertSee('My payment failed twice.');
        $this->assertSame(0, $support->fresh()->admin_unread_count);
        $this->post(route('admin.messages.reply', $support), ['body' => 'We are checking with the bank.'])->assertRedirect(route('admin.messages.show', $support));
        Notification::assertSentTo($customer, NewMessage::class);
        $this->post(route('admin.messages.reply', $shopSupport), ['body' => 'This Sunday.']);
        Notification::assertSentTo($this->shop, NewMessage::class);

        $this->actingAs($customer)->get(route('messages.show', $support))->assertSee('We are checking with the bank.')->assertSee('iruali support');
        $this->actingAs($this->shop)->get(route('seller.messages.show', $shopSupport))->assertSee('This Sunday.');

        // A closed conversation takes no more replies; "Message iruali" then starts a new one
        $this->actingAs($agent)->post(route('admin.messages.status', $support), ['status' => 'closed']);
        $this->actingAs($customer)->post(route('messages.reply', $support), ['body' => 'Hello?'])->assertSessionHas('error');
        $this->post(route('messages.support.store'), ['body' => 'New question.']);
        $this->assertSame(2, Conversation::where('kind', Conversation::SUPPORT)->count());
    }

    public function test_an_item_request_has_its_own_conversation(): void
    {
        $customer = User::factory()->create();
        $request = new ItemRequest(['title' => 'Trail running shoes', 'quantity' => 1]);
        $request->forceFill(['user_id' => $customer->id, 'status' => ItemRequest::NEW])->save();

        $this->actingAs($customer)->get(route('item-requests.show', $request))->assertOk()->assertSee('data-item-request-messages', false);
        $this->post(route('item-requests.messages.store', $request), ['body' => 'Size 42 please.'])->assertRedirect(route('item-requests.show', $request).'#messages');
        $conversation = Conversation::where('kind', Conversation::ITEM_REQUEST)->sole();
        $this->assertSame($request->id, $conversation->item_request_id);

        $admin = $this->teamMember('admin');
        $this->actingAs($admin)->get(route('admin.item-requests.show', $request))->assertOk()->assertSee('Size 42 please.');
        $this->post(route('admin.item-requests.messages', $request), ['body' => 'Black or white?'])->assertSessionHas('success');
        $this->actingAs($customer)->get(route('item-requests.show', $request))->assertSee('Black or white?');
        $this->actingAs(User::factory()->create())->post(route('item-requests.messages.store', $request), ['body' => 'Hi'])->assertNotFound();
    }

    public function test_the_storefront_shows_a_way_to_message_iruali_on_every_page(): void
    {
        // Visitors: the header icon and "Message us" lead to "Message iruali" (no account needed)
        $this->get('/')->assertOk()->assertSee('data-header-messages', false)->assertSee('data-floating-message', false)->assertSee(route('messages.support'), false);
        $this->get(route('messages.support'))->assertOk()->assertSee('data-guest-fields', false);

        // Signed in: the header icon opens My messages, with the unread count
        $customer = User::factory()->create();
        $conversation = app(MessagingService::class)->supportConversation($customer);
        app(MessagingService::class)->send($conversation, $customer, 'customer', 'Hello');
        app(MessagingService::class)->send($conversation->fresh(), $this->teamMember('admin'), 'admin', 'Hi, how can we help?');
        $this->actingAs($customer)->get(route('products.show', $this->product))->assertOk()
            ->assertSee(route('messages.index'), false)->assertSee('data-floating-message', false);
        $this->get(route('messages.index'))->assertOk()->assertDontSee('data-floating-message', false);
    }

    public function test_visitors_without_an_account_can_message_iruali_and_ask_about_items(): void
    {
        $this->assertGuest();

        // "Message iruali": a name and an email or mobile, a private link back
        $this->post(route('messages.support.store'), ['body' => 'Do you deliver to Fuvahmulah?', 'name' => 'Hawwa'])->assertSessionHasErrors(['email', 'phone']);
        $this->post(route('messages.support.store'), ['body' => 'Hi', 'name' => 'Bot', 'email' => 'bot@example.com', 'website' => 'http://spam.example'])->assertSessionHasErrors('website');
        $this->assertSame(0, Conversation::count());

        $response = $this->post(route('messages.support.store'), ['body' => 'Do you deliver to Fuvahmulah?', 'name' => 'Hawwa', 'email' => 'Hawwa@Example.com']);
        $support = Conversation::sole();
        $this->assertSame([Conversation::SUPPORT, null, 'Hawwa', 'hawwa@example.com', 1], [$support->kind, $support->customer_id, $support->guest_name, $support->guest_email, $support->admin_unread_count]);
        $this->assertTrue($support->isGuest());
        $this->assertSame(48, strlen($support->access_token));
        $this->assertArrayNotHasKey('access_token', $support->toArray());
        $response->assertRedirect($support->guestUrl());
        $this->assertNull(Message::sole()->sender_id);
        $this->get($support->guestUrl())->assertOk()->assertSee('data-guest-link', false)->assertSee('Do you deliver to Fuvahmulah?');

        // The private link is the only way in
        $this->get(route('messages.guest.show', ['conversation' => $support->id, 'token' => str_repeat('a', 48)]))->assertNotFound();
        $this->get(route('messages.show', $support))->assertRedirect(route('login'));

        // The team answers; the visitor gets it by email with the link, and replies there
        $admin = $this->teamMember('admin');
        $this->actingAs($admin)->get(route('admin.messages.show', $support))->assertOk()->assertSee('data-admin-guest', false)->assertSee('hawwa@example.com');
        $this->post(route('admin.messages.reply', $support), ['body' => 'Yes, by cargo boat.']);
        Notification::assertSentOnDemand(NewMessage::class, function ($notification, $channels, $notifiable) use ($support) {
            return $notifiable->routes['mail'] === 'hawwa@example.com' && $channels === ['mail']
                && str_contains((string) $notification->toMail($notifiable)->actionUrl, $support->access_token);
        });
        \Illuminate\Support\Facades\Auth::logout();
        $this->post($support->guestUrl(), ['body' => 'Thank you!'])->assertRedirect($support->guestUrl());
        $this->assertSame(3, $support->messages()->count());
        $this->assertSame('Hawwa', $support->messages()->first()->senderName('admin'));

        // "Ask about this item" with a mobile number: the shop answers, contact details stay hidden
        $this->post(route('messages.product.store', $this->product), ['body' => 'Is it original? My email is me@example.com', 'name' => 'Ali Hassan', 'phone' => '777 1234'])->assertRedirect();
        $question = Conversation::where('kind', Conversation::PRODUCT)->sole();
        $this->assertSame([$this->shop->id, '+9607771234'], [$question->seller_id, $question->guest_phone]);
        $this->assertStringNotContainsString('me@example.com', $question->messages()->sole()->body);
        Notification::assertSentTo($this->shop, NewMessage::class);
        $this->actingAs($this->shop)->get(route('seller.messages.show', $question))->assertOk()->assertSee('Asked by Al*')->assertDontSee('7771234');
        $this->post(route('seller.messages.reply', $question), ['body' => 'Yes, it is original.']);
        Notification::assertSentOnDemand(NewMessage::class, fn ($n, $channels, $notifiable) => ($notifiable->routes['sms'] ?? null) === '+9607771234' && $channels === ['sms']);
    }

    public function test_reporting_blocking_and_nothing_is_ever_deleted(): void
    {
        $customer = User::factory()->create();
        $messaging = app(MessagingService::class);
        $conversation = $messaging->productConversation($customer, $this->product);
        $mine = $messaging->send($conversation, $customer, 'customer', 'Is it in stock?');
        $theirs = $messaging->send($conversation, $this->shop, 'seller', 'Buy it somewhere else, it is cheaper.');

        // A customer reports what the shop wrote, not their own message
        $this->actingAs($customer)->post(route('messages.report', $mine), ['reason' => 'Mine'])->assertSessionHas('error');
        $this->post(route('messages.report', $theirs), ['reason' => 'Sending me elsewhere'])->assertSessionHas('success');
        $this->assertNotNull($theirs->fresh()->reported_at);
        $admin = $this->teamMember('admin');
        $this->actingAs($admin)->get(route('admin.messages', ['show' => 'reported']))->assertOk()->assertSee('data-admin-conversation="'.$conversation->id.'"', false);
        $this->get(route('admin.messages.show', $conversation))->assertSee('data-chat-reported', false)->assertSee('Sending me elsewhere');

        // The team stops the shop writing; what it wrote stays
        $this->post(route('admin.messages.block', $this->shop), ['blocked' => 1])->assertSessionHas('success');
        $this->actingAs($this->shop->fresh())->post(route('seller.messages.reply', $conversation), ['body' => 'Hello'])->assertSessionHas('error', 'Your account can no longer send messages. Please contact iruali.');
        $this->actingAs($admin)->post(route('admin.messages.block', $this->shop), ['blocked' => 0]);
        $this->actingAs($this->shop->fresh())->post(route('seller.messages.reply', $conversation), ['body' => 'Sorry, yes it is in stock.'])->assertRedirect();

        // Nothing deletes a conversation or a message, not even deleting the item
        try {
            $conversation->delete();
            $this->fail('A conversation was deleted.');
        } catch (LogicException) {
        }
        try {
            $mine->delete();
            $this->fail('A message was deleted.');
        } catch (LogicException) {
        }
        DB::table('products')->where('id', $this->product->id)->delete();
        $conversation->refresh();
        $this->assertNull($conversation->product_id);
        $this->assertSame(3, $conversation->messages()->count());
        $this->assertSame('About: Blue kettle', $conversation->title());
        $this->actingAs($admin)->get(route('admin.messages.show', $conversation))->assertOk()->assertSee('Is it in stock?');
    }
}
