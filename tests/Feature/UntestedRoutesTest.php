<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Expense;
use App\Models\NewsletterSubscriber;
use App\Models\Order;
use App\Models\Role;
use App\Models\SmsMessage;
use App\Models\User;
use App\Models\WebhookEndpoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Deleting, toggling and private-link routes that had no test of their own (audit plan 6.5).
 */
class UntestedRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_an_admin_removes_an_expense_and_its_receipt(): void
    {
        Storage::fake(Expense::DISK);
        $path = UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf')->store('expense-receipts', Expense::DISK);
        $expense = Expense::create(['spent_on' => today(), 'category' => 'hosting', 'description' => 'Server', 'amount' => 100, 'gst_amount' => 0, 'receipt_path' => $path]);

        $this->actingAs(User::factory()->create())->delete(route('admin.finance.expenses.destroy', $expense))->assertForbidden();
        $this->assertModelExists($expense);

        $this->actingAs($this->admin())->delete(route('admin.finance.expenses.destroy', $expense))->assertRedirect()->assertSessionHas('success');
        $this->assertModelMissing($expense);
        Storage::disk(Expense::DISK)->assertMissing($path);
    }

    public function test_an_admin_removes_a_newsletter_subscriber(): void
    {
        $subscriber = NewsletterSubscriber::create(['email' => 'fan@example.com', 'locale' => 'en']);

        $this->actingAs(User::factory()->create())->delete(route('admin.newsletter.destroy', $subscriber))->assertForbidden();
        $this->assertModelExists($subscriber);

        $this->actingAs($this->admin())->delete(route('admin.newsletter.destroy', $subscriber))->assertRedirect();
        $this->assertModelMissing($subscriber);
    }

    public function test_an_admin_switches_a_webhook_off_and_on_and_removes_it(): void
    {
        $endpoint = WebhookEndpoint::create(['url' => 'https://hooks.example.com/x', 'secret' => 'abc', 'events' => ['order.created'], 'is_active' => true]);

        $this->actingAs(User::factory()->create())->post(route('admin.webhooks.toggle', $endpoint))->assertForbidden();
        $this->actingAs(User::factory()->create())->delete(route('admin.webhooks.destroy', $endpoint))->assertForbidden();
        $this->assertTrue($endpoint->fresh()->is_active);

        $admin = $this->admin();
        $this->actingAs($admin)->post(route('admin.webhooks.toggle', $endpoint))->assertRedirect();
        $this->assertFalse($endpoint->fresh()->is_active);
        $this->actingAs($admin)->post(route('admin.webhooks.toggle', $endpoint))->assertRedirect();
        $this->assertTrue($endpoint->fresh()->is_active);

        $this->actingAs($admin)->delete(route('admin.webhooks.destroy', $endpoint))->assertRedirect();
        $this->assertModelMissing($endpoint);
    }

    public function test_a_guest_reply_needs_the_private_link(): void
    {
        $this->post(route('messages.support.store'), ['body' => 'Do you deliver to Fuvahmulah?', 'name' => 'Hawwa', 'email' => 'hawwa@example.com']);
        $conversation = Conversation::sole();

        $this->post(route('messages.guest.reply', ['conversation' => $conversation->id, 'token' => str_repeat('a', 48)]), ['body' => 'Hello?'])->assertNotFound();
        $this->assertSame(1, $conversation->messages()->count());

        $this->post($conversation->guestUrl(), ['body' => 'Any news?'])->assertRedirect($conversation->guestUrl());
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_the_order_tracking_page_needs_a_valid_signed_link(): void
    {
        $order = Order::factory()->create();

        $this->get(route('order.track.show', $order))->assertForbidden();
        $this->get(URL::temporarySignedRoute('order.track.show', now()->subMinute(), $order))->assertForbidden();
        $this->get(URL::temporarySignedRoute('order.track.show', now()->addMinutes(5), $order).'x')->assertForbidden();
        $this->get(URL::temporarySignedRoute('order.track.show', now()->addMinutes(5), $order))->assertOk()->assertSee($order->order_number);
    }

    public function test_sms_prune_keeps_recent_and_held_texts(): void
    {
        $old = SmsMessage::create(['to' => '+9607771234', 'message' => 'Old', 'status' => 'sent', 'created_at' => now()->subDays(200)]);
        $held = SmsMessage::create(['to' => '+9607771234', 'message' => 'Held', 'status' => 'held', 'created_at' => now()->subDays(200)]);
        $recent = SmsMessage::create(['to' => '+9607771234', 'message' => 'Recent', 'status' => 'sent', 'created_at' => now()->subDays(20)]);

        $this->artisan('sms:prune')->assertSuccessful();
        $this->assertModelMissing($old);
        $this->assertModelExists($held);
        $this->assertModelExists($recent);

        // Never fewer than 30 days, whatever is asked
        $this->artisan('sms:prune', ['--days' => 1])->assertSuccessful();
        $this->assertModelExists($recent);
    }

    public function test_the_daily_clean_up_removes_expired_cache_rows_and_old_codes_only(): void
    {
        $this->artisan('schedule:list')->assertSuccessful(); // loads routes/console.php
        $event = collect(app(\Illuminate\Console\Scheduling\Schedule::class)->events())->first(fn ($e) => $e->description === 'prune-cache-and-codes');
        $this->assertNotNull($event, 'The daily clean-up is not scheduled');

        \Illuminate\Support\Facades\DB::table('cache')->insert([
            ['key' => 'expired-key', 'value' => 'x', 'expiration' => time() - 60],
            ['key' => 'live-key', 'value' => 'x', 'expiration' => time() + 3600],
        ]);
        \Illuminate\Support\Facades\DB::table('cache_locks')->insert([
            ['key' => 'expired-lock', 'owner' => 'a', 'expiration' => time() - 60],
            ['key' => 'live-lock', 'owner' => 'a', 'expiration' => time() + 3600],
        ]);
        $old = \App\Models\OTP::createForPhone('+9607771234', 'login');
        $old->forceFill(['created_at' => now()->subDays(2)])->save();
        $new = \App\Models\OTP::createForPhone('+9607775678', 'login');

        $event->run($this->app);

        $this->assertSame(['live-key'], \Illuminate\Support\Facades\DB::table('cache')->pluck('key')->all());
        $this->assertSame(['live-lock'], \Illuminate\Support\Facades\DB::table('cache_locks')->pluck('key')->all());
        $this->assertModelMissing($old);
        $this->assertModelExists($new);
    }
}
