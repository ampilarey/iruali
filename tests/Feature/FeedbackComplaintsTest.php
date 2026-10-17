<?php

namespace Tests\Feature;

use App\Jobs\SendTeamAlert;
use App\Mail\ComplaintsDigest;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\OrderFeedback;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ComplaintReceived;
use App\Notifications\OrderStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Rating a delivered order, the complaint form, Admin → Complaints and the reminders.
 */
class FeedbackComplaintsTest extends TestCase
{
    use RefreshDatabase;

    protected function staff(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function alertsByTelegram(): void
    {
        config(['services.telegram.bot_token' => 'test-token']);
        Setting::set(['telegram_chat_id' => '-100123', 'telegram_events' => 'complaint']);
    }

    public function test_customers_rate_a_delivered_order_once_and_low_ratings_alert_the_team(): void
    {
        Bus::fake([SendTeamAlert::class]);
        $this->alertsByTelegram();
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'delivered']);

        // The delivered email carries the signed link, and the order page shows it
        $mail = (new OrderStatusChanged($order))->toMail($customer);
        $this->assertStringContainsString('/feedback/'.$order->id, implode(' ', array_map('strval', $mail->introLines)));
        $this->actingAs($customer)->get(route('orders.show', $order))->assertSee('data-order-feedback', false);

        $this->get(route('orders.feedback', $order))->assertForbidden(); // unsigned
        $url = $order->feedbackUrl();
        $this->post($url, ['rating' => 9])->assertSessionHasErrors('rating');
        $this->post($url, ['rating' => 2, 'comment' => 'Box was crushed'])->assertRedirect($url);
        $this->post($url, ['rating' => 5]); // only the first one counts

        $feedback = OrderFeedback::sole();
        $this->assertSame(2, $feedback->rating);
        $this->assertSame($customer->id, $feedback->user_id);
        Bus::assertDispatched(SendTeamAlert::class, 1);
        $this->get($url)->assertSee(__('You rated this order :rating out of 5. Thank you!', ['rating' => 2]));
    }

    public function test_orders_not_delivered_cannot_be_rated(): void
    {
        $order = Order::factory()->create(['status' => 'shipped']);
        $this->get($order->feedbackUrl())->assertOk()->assertSee(__('You can rate this order once it has been delivered.'));
        $this->post($order->feedbackUrl(), ['rating' => 5])->assertForbidden();
        $this->assertSame(0, OrderFeedback::count());
    }

    public function test_anyone_can_send_a_complaint_with_photos(): void
    {
        Notification::fake();
        Storage::fake('local');
        $customer = User::factory()->create(['email' => 'sara@example.com']);
        $order = Order::factory()->create(['user_id' => $customer->id]);
        $other = Order::factory()->create();

        $this->get(route('complaints.create', ['order' => $order->order_number]))->assertOk()->assertSee($order->order_number);
        $this->post(route('complaints.store'), [
            'name' => 'Sara', 'email' => 'sara@example.com', 'order_number' => strtolower($order->order_number), 'category' => 'delivery',
            'details' => 'The parcel arrived wet and the box was torn.', 'photos' => [UploadedFile::fake()->image('box.jpg')],
        ])->assertRedirect(route('complaints.create'))->assertSessionHas('complaint_reference');

        $complaint = Complaint::sole();
        $this->assertSame($order->id, $complaint->order_id);
        $this->assertSame('open', $complaint->status);
        $this->assertStringStartsWith('C-', $complaint->reference);
        Storage::disk('local')->assertExists($complaint->photos[0]);
        Notification::assertSentOnDemand(ComplaintReceived::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'sara@example.com' && $n->reference === $complaint->reference);

        // Someone else's order number is not linked
        $this->post(route('complaints.store'), ['name' => 'X', 'email' => 'x@example.com', 'order_number' => $other->order_number, 'category' => 'other', 'details' => 'Something happened here.']);
        $this->assertNull(Complaint::latest('id')->first()->order_id);

        $this->post(route('complaints.store'), ['name' => 'X', 'email' => 'x@example.com', 'category' => 'nonsense', 'details' => 'short'])->assertSessionHasErrors(['category', 'details']);
    }

    public function test_support_staff_handle_complaints(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('complaint-photos/a.jpg', 'img');
        $complaint = Complaint::create(['name' => 'Sara', 'email' => 'sara@example.com', 'category' => 'product', 'details' => 'Wrong colour sent.', 'photos' => ['complaint-photos/a.jpg']]);
        OrderFeedback::create(['order_id' => Order::factory()->create()->id, 'rating' => 1, 'comment' => 'Never again']);

        $this->actingAs($this->staff('support'))->get(route('admin.complaints'))->assertOk()->assertSee($complaint->reference)->assertSee('Never again');
        $this->get(route('admin.complaints.show', $complaint))->assertOk()->assertSee('Wrong colour sent.');
        $this->get(route('admin.complaints.photo', [$complaint, 0]))->assertOk();
        $this->get(route('admin.complaints.photo', [$complaint, 5]))->assertNotFound();

        $this->put(route('admin.complaints.update', $complaint), ['status' => 'resolved', 'note' => 'Shop is sending the right one.'])->assertSessionHas('success');
        $complaint->refresh();
        $this->assertSame('resolved', $complaint->status);
        $this->assertNotNull($complaint->resolved_at);
        $this->assertSame('Shop is sending the right one.', $complaint->notes[0]['body']);
        $this->get(route('admin.complaints'))->assertDontSee($complaint->reference); // open filter by default

        $this->actingAs($this->staff('catalogue'))->get(route('admin.complaints'))->assertForbidden();
    }

    public function test_old_open_complaints_are_reminded_and_a_weekly_summary_goes_out(): void
    {
        Mail::fake();
        config(['mail.alerts_to' => 'owner@iruali.mv']);
        $old = Complaint::create(['name' => 'A', 'email' => 'a@example.com', 'category' => 'shop', 'details' => 'Rude shop owner.']);
        $old->forceFill(['created_at' => now()->subDays(5), 'updated_at' => now()->subDays(5)])->saveQuietly();
        Complaint::create(['name' => 'B', 'email' => 'b@example.com', 'category' => 'other', 'details' => 'Fresh one today.']);

        $this->artisan('complaints:remind')->expectsOutputToContain('Reminded about 1')->assertSuccessful();
        Mail::assertQueued(ComplaintsDigest::class, fn ($m) => $m->kind === 'stale' && count($m->summary['complaints']) === 1);
        $this->artisan('complaints:remind')->expectsOutputToContain('No complaints waiting')->assertSuccessful(); // not again tomorrow

        $this->artisan('complaints:weekly-summary')->assertSuccessful();
        Mail::assertQueued(ComplaintsDigest::class, fn ($m) => $m->kind === 'weekly' && $m->summary['new'] === 2 && $m->summary['open'] === 2);
    }
}
