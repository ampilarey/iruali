<?php

namespace Tests\Feature;

use App\Jobs\SendTeamAlert;
use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ShopLateOrder;
use App\Support\AdminInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The live order page (polling for changes) and the hourly late-order alerts.
 */
class LiveOrderAndLateAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function part(Order $order, User $seller, array $attributes = []): SellerOrder
    {
        return tap((new SellerOrder)->forceFill($attributes + ['order_id' => $order->id, 'seller_id' => $seller->id, 'status' => 'pending', 'subtotal' => 100, 'commission_rate' => 10, 'commission_amount' => 10, 'seller_earnings' => 90]))->save();
    }

    public function test_the_order_page_checks_for_changes_and_only_the_owner_can_ask(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'processing', 'payment_status' => 'paid']);
        $part = $this->part($order, User::factory()->create());

        $this->actingAs($customer)->get(route('orders.show', $order))->assertSee('data-live-url="'.route('orders.live', $order).'"', false);
        $before = $this->getJson(route('orders.live', $order))->assertOk()->json('state');
        $this->assertSame($before, $this->getJson(route('orders.live', $order))->json('state'));

        $part->forceFill(['status' => 'shipped'])->save();
        $this->assertNotSame($before, $this->getJson(route('orders.live', $order))->json('state'));

        $this->actingAs(User::factory()->create())->getJson(route('orders.live', $order))->assertForbidden();

        // Delivered orders stop checking
        $order->update(['status' => 'delivered']);
        $this->actingAs($customer)->get(route('orders.show', $order))->assertDontSee('data-live-url', false);
    }

    public function test_guests_poll_with_their_signed_link(): void
    {
        $order = Order::factory()->create(['user_id' => null, 'guest_email' => 'g@example.com', 'guest_token' => str_repeat('a', 40), 'status' => 'pending']);
        $this->getJson($order->guestUrl('live'))->assertOk()->assertJsonStructure(['state', 'settled']);
        $this->getJson(route('guest.orders.live', ['order' => $order->id, 'token' => $order->guest_token]))->assertForbidden();
    }

    public function test_shops_and_the_team_hear_about_late_orders_once(): void
    {
        Notification::fake();
        Bus::fake([SendTeamAlert::class]);
        config(['services.telegram.bot_token' => 'x']);
        Setting::set(['telegram_chat_id' => '-1', 'telegram_events' => 'late_order', 'late_start_hours' => 24]);
        $seller = User::factory()->create();

        $late = Order::factory()->create(['status' => 'processing', 'payment_status' => 'paid', 'paid_at' => now()->subHours(30)]);
        $fresh = Order::factory()->create(['status' => 'processing', 'payment_status' => 'paid', 'paid_at' => now()->subHours(2)]);
        $sent = Order::factory()->create(['status' => 'shipped', 'payment_status' => 'paid', 'paid_at' => now()->subDays(6)]);
        $this->part($late, $seller);
        $this->part($fresh, $seller);
        $this->part($late, User::factory()->create(), ['awaiting_stock' => true]); // a pre-order waits for stock
        $this->part($sent, $seller, ['status' => 'shipped', 'expected_delivery_date' => now()->subDays(2)->toDateString()]);

        $rows = collect(AdminInbox::all())->keyBy('key');
        $this->assertSame(1, $rows['orders_not_started']['count']);
        $this->assertSame(1, $rows['orders_past_delivery']['count']);

        $this->artisan('orders:alert-late')->expectsOutputToContain('Not started: 1; past delivery date: 1')->assertSuccessful();
        Notification::assertSentTo($seller, ShopLateOrder::class, fn ($n) => $n->kind === 'unstarted' && $n->orderNumbers === [$late->order_number]);
        Notification::assertSentTo($seller, ShopLateOrder::class, fn ($n) => $n->kind === 'overdue' && $n->orderNumbers === [$sent->order_number]);
        Bus::assertDispatched(SendTeamAlert::class, 1);

        $this->artisan('orders:alert-late')->expectsOutputToContain('Not started: 0; past delivery date: 0');
        Bus::assertDispatched(SendTeamAlert::class, 1);
    }
}
