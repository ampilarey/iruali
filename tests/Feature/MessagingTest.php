<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Order threads between the customer, the shop and iruali support.
 */
class MessagingTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $shop;

    protected User $admin;

    protected Order $order;

    protected SellerOrder $part;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('local');

        $this->customer = User::factory()->create();
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $this->shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->order = Order::factory()->create(['user_id' => $this->customer->id, 'status' => 'processing', 'order_number' => 'ORD-MSG1']);
        $this->order->items()->create(['product_id' => Product::factory()->create(['seller_id' => $this->shop->id])->id, 'quantity' => 1, 'price' => 50]);
        $this->part = $this->order->sellerOrders()->where('seller_id', $this->shop->id)->firstOrFail();
    }

    protected function customerSends(string $body, array $extra = [])
    {
        return $this->actingAs($this->customer)->post(route('orders.messages.store', [$this->order, $this->part]), array_merge(['body' => $body], $extra));
    }

    public function test_customer_and_shop_exchange_messages_with_unread_counts_and_badges(): void
    {
        $this->actingAs($this->customer)->get(route('orders.show', $this->order))->assertOk()->assertSee('Message the shop');

        $this->customerSends('Is the blue one in stock?')->assertRedirect()->assertSessionHas('success');

        $conversation = Conversation::sole();
        $this->assertSame([$this->order->id, $this->part->id, $this->customer->id, $this->shop->id], [$conversation->order_id, $conversation->seller_order_id, $conversation->customer_id, $conversation->seller_id]);
        $this->assertSame([0, 1, 1], [$conversation->customer_unread_count, $conversation->seller_unread_count, $conversation->admin_unread_count]);
        $this->assertSame('customer', Message::sole()->sender_role);
        Notification::assertSentTo($this->shop, NewMessage::class, fn ($n) => $n->message->body === 'Is the blue one in stock?');
        Notification::assertNotSentTo($this->customer, NewMessage::class);

        // The shop sees the badge, opens the order (which marks it read) and replies
        $this->actingAs($this->shop)->get(route('seller.dashboard'))->assertOk()->assertSee('Unread messages');
        $this->get(route('seller.orders.show', $this->order))->assertOk()->assertSee('Is the blue one in stock?')->assertSee($this->customer->name);
        $this->assertSame(0, $conversation->fresh()->seller_unread_count);
        $this->assertNotNull(Message::sole()->fresh()->read_at);

        $this->post(route('seller.orders.messages.store', [$this->order, $this->part]), ['body' => 'Yes, sending it today.'])->assertRedirect();
        $this->assertSame(1, $conversation->fresh()->customer_unread_count);
        Notification::assertSentTo($this->customer, NewMessage::class, fn ($n) => $n->message->sender_role === 'seller');

        // The customer's menu shows one unread message until they open the order
        $this->actingAs($this->customer)->get('/')->assertOk()->assertSee('Unread messages');
        $this->get(route('orders.show', $this->order))->assertOk()->assertSee('Yes, sending it today.')->assertSee('Island Crafts');
        $this->assertSame(0, $conversation->fresh()->customer_unread_count);
        $this->get('/')->assertDontSee('Unread messages');

        // One conversation per (order, shop)
        $this->customerSends('Thanks!');
        $this->assertSame(1, Conversation::count());
        $this->assertSame(3, Message::count());
    }

    public function test_new_message_alerts_are_sent_at_most_every_ten_minutes_per_thread(): void
    {
        $this->customerSends('One');
        $this->customerSends('Two');
        $this->customerSends('Three');
        Notification::assertSentToTimes($this->shop, NewMessage::class, 1);

        $this->travel(11)->minutes();
        $this->customerSends('Four');
        Notification::assertSentToTimes($this->shop, NewMessage::class, 2);

        // The customer's own alerts are separate, and follow their SMS preference
        $this->actingAs($this->shop)->post(route('seller.orders.messages.store', [$this->order, $this->part]), ['body' => 'Reply']);
        Notification::assertSentTo($this->customer, NewMessage::class, fn ($n, $channels) => $channels === ['mail']);
        $this->assertStringContainsString('ORD-MSG1', (new NewMessage(Message::latest('id')->first()))->toSms($this->customer));
    }

    public function test_admin_reads_everything_and_replies_as_iruali_support(): void
    {
        $this->customerSends('Where is my parcel?');
        $conversation = Conversation::sole();

        $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()->assertSee('1 unread');
        $this->get(route('admin.messages'))->assertOk()->assertSee('ORD-MSG1')->assertSee('Island Crafts');
        $this->get(route('admin.orders.show', $this->order))->assertOk()->assertSee('Where is my parcel?');
        $this->assertSame(0, $conversation->fresh()->admin_unread_count);

        $this->travel(11)->minutes(); // past the shop's 10-minute alert window
        $this->post(route('admin.orders.messages.store', [$this->order, $this->part]), ['body' => 'We are checking with the shop.'])->assertRedirect();
        $message = Message::latest('id')->first();
        $this->assertSame('admin', $message->sender_role);
        // The shop still has the customer's message unread, plus this one
        $this->assertSame([1, 2], [$conversation->fresh()->customer_unread_count, $conversation->fresh()->seller_unread_count]);
        Notification::assertSentTo($this->customer, NewMessage::class);
        Notification::assertSentTo($this->shop, NewMessage::class, fn ($n) => $n->message->sender_role === 'admin');

        $this->actingAs($this->customer)->get(route('orders.show', $this->order))->assertSee('iruali support')->assertSee('We are checking with the shop.');

        // Closing stops the customer and the shop, not the admin
        $this->actingAs($this->admin)->post(route('admin.conversations.status', $conversation), ['status' => 'closed']);
        $this->customerSends('Hello?')->assertForbidden();
        $this->actingAs($this->shop)->post(route('seller.orders.messages.store', [$this->order, $this->part]), ['body' => 'Hi'])->assertForbidden();
        $this->actingAs($this->admin)->post(route('admin.orders.messages.store', [$this->order, $this->part]), ['body' => 'Still here'])->assertRedirect();
        $this->post(route('admin.conversations.status', $conversation), ['status' => 'open']);
        $this->customerSends('Hello again')->assertSessionHas('success');
    }

    public function test_only_participants_can_post_or_read_attachments(): void
    {
        $this->customerSends('Photo attached', ['attachment' => UploadedFile::fake()->image('box.jpg')]);
        $message = Message::sole();
        Storage::disk('local')->assertExists($message->attachment_path);
        $this->assertStringEndsWith('.jpg', $message->attachment_path);

        $stranger = User::factory()->create();
        $otherShop = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $otherShop->roles()->attach(Role::where('name', 'seller')->first()->id);

        $this->actingAs($stranger)->post(route('orders.messages.store', [$this->order, $this->part]), ['body' => 'Hi'])->assertForbidden();
        $this->actingAs($otherShop)->post(route('seller.orders.messages.store', [$this->order, $this->part]), ['body' => 'Hi'])->assertForbidden();
        $this->actingAs($this->shop)->post(route('admin.orders.messages.store', [$this->order, $this->part]), ['body' => 'Hi'])->assertForbidden();

        $this->actingAs($stranger)->get(route('messages.attachment', $message))->assertForbidden();
        $this->actingAs($otherShop)->get(route('messages.attachment', $message))->assertForbidden();
        $this->actingAs($this->customer)->get(route('messages.attachment', $message))->assertOk();
        $this->actingAs($this->shop)->get(route('messages.attachment', $message))->assertOk();
        $this->actingAs($this->admin)->get(route('messages.attachment', $message))->assertOk();

        // Only images, and a body is required
        $this->customerSends('Doc', ['attachment' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])->assertSessionHasErrors('attachment');
        $this->customerSends('')->assertSessionHasErrors('body');
        $this->customerSends(str_repeat('x', 2001))->assertSessionHasErrors('body');
        $this->assertSame(1, Message::count());
    }

    public function test_messages_close_90_days_after_the_order_unless_a_return_is_open(): void
    {
        $this->order->forceFill(['created_at' => now()->subDays(91)])->save();

        $this->actingAs($this->customer)->get(route('orders.show', $this->order))->assertOk()->assertSee('more than 90 days old')->assertDontSee('name="body"', false);
        $this->customerSends('Too late?')->assertRedirect()->assertSessionHasErrors('body', null, 'conversation-'.Conversation::sole()->id);
        $this->assertSame(0, Message::count());

        ReturnRequest::create(['order_id' => $this->order->id, 'seller_order_id' => $this->part->id, 'user_id' => $this->customer->id, 'status' => 'requested', 'reason' => 'faulty', 'items_value' => 50]);
        $this->customerSends('My return is open')->assertSessionHas('success');
        $this->assertSame(1, Message::count());

        // Admins can always write
        ReturnRequest::query()->update(['status' => 'rejected']);
        $this->actingAs($this->admin)->post(route('admin.orders.messages.store', [$this->order, $this->part]), ['body' => 'From support'])->assertSessionHas('success');
    }
}
