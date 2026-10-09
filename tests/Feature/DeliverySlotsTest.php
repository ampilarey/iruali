<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\DeliverySlotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * Malé delivery time slots: offered for deliveries to Greater Malé when on, "Any time" by
 * default; full and too-late slots are disabled and checked again on the server.
 */
class DeliverySlotsTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBml();
        // Monday 12 October 2026, 08:00 in Malé
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(8, 0));
        Setting::set([
            'delivery_fee_greater_male' => 25, 'delivery_fee_islands' => 75, 'free_delivery_over' => 1000,
            'delivery_slots_enabled' => 1, 'delivery_slots' => "09:00-12:00\n12:00-15:00\n15:00-18:00\n18:00-21:00",
            'delivery_slot_days_ahead' => 3, 'delivery_slot_cutoff_hours' => 3, 'delivery_slot_capacity' => 2,
        ]);
        $this->customer = User::factory()->create();
        $this->cartFor($this->customer, [[$this->product($this->shop('Reef Crafts'), 200), 1]]);
    }

    /**
     * An existing order holding a slot.
     */
    protected function booking(string $start, string $status = 'pending'): Order
    {
        return Order::factory()->create(['status' => $status, 'delivery_slot_starts_at' => $start, 'delivery_slot_ends_at' => $start]);
    }

    public function test_slots_follow_the_days_ahead_and_the_same_day_cut_off(): void
    {
        $days = app(DeliverySlotService::class)->days();

        $this->assertSame(['2026-10-12', '2026-10-13', '2026-10-14', '2026-10-15'], $days->keys()->all(), 'today and 3 days ahead');
        $today = $days['2026-10-12']->keyBy('time');
        $this->assertTrue($today['09:00–12:00']['too_late'], '09:00 starts within the 3-hour cut-off');
        $this->assertFalse($today['12:00–15:00']['too_late']);
        $this->assertTrue($today['12:00–15:00']['available']);

        $service = app(DeliverySlotService::class);
        $this->assertNull($service->find('2026-10-12 09:00'));
        $this->assertNotNull($service->find('2026-10-12 12:00'));
        $this->assertNotNull($service->find('2026-10-15 18:00'));
        $this->assertNull($service->find('2026-10-16 09:00'), 'beyond the days ahead');
        $this->assertNull($service->find('2026-10-13 10:00'), 'not one of the slots');
        $this->assertNull($service->find('yesterday'));
    }

    public function test_checkout_offers_slots_for_greater_male_with_any_time_by_default(): void
    {
        $this->actingAs($this->customer)->get('/checkout')->assertOk()
            ->assertSee('Delivery time in Malé')
            ->assertSee('name="delivery_slot" value="" checked', false)
            ->assertSee('value="2026-10-12 12:00"', false)
            ->assertSee('value="2026-10-15 18:00"', false)
            ->assertSee('Too late to book');

        // Any time: no slot on the order
        $this->placeOrder($this->customer)->assertRedirect();
        $this->assertNull(Order::sole()->delivery_slot_starts_at);
    }

    public function test_the_chosen_slot_is_stored_and_shown_to_the_shop_and_admin(): void
    {
        $this->placeOrder($this->customer, ['delivery_slot' => '2026-10-13 15:00'])->assertRedirect();

        $order = Order::sole();
        $this->assertSame('2026-10-13 15:00:00', $order->delivery_slot_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-13 18:00:00', $order->delivery_slot_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('Tue 13 Oct, 15:00–18:00', $order->deliverySlotLabel());

        $seller = $order->sellerOrders()->sole()->seller;
        $this->actingAs($seller)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Tue 13 Oct, 15:00–18:00');
        $this->actingAs($this->admin())->get(route('admin.orders.show', $order))->assertOk()->assertSee('Tue 13 Oct, 15:00–18:00');
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()->assertSee('Tue 13 Oct, 15:00–18:00');
        $this->actingAs($seller)->get(route('seller.orders.packing-slip', $order))->assertOk()->assertSee('Tue 13 Oct, 15:00–18:00');
    }

    public function test_full_and_too_late_slots_are_refused_on_the_server(): void
    {
        $this->booking('2026-10-13 09:00:00');
        $this->booking('2026-10-13 09:00:00', 'processing');

        $this->actingAs($this->customer)->get('/checkout')->assertOk()->assertSee('(Full)');
        $this->placeOrder($this->customer, ['delivery_slot' => '2026-10-13 09:00'])->assertSessionHasErrors('delivery_slot');
        $this->placeOrder($this->customer, ['delivery_slot' => '2026-10-12 09:00'])->assertSessionHasErrors('delivery_slot');
        $this->placeOrder($this->customer, ['delivery_slot' => '2026-10-20 09:00'])->assertSessionHasErrors('delivery_slot');
        $this->assertSame(2, Order::count());

        // A cancelled order gives its place back
        Order::where('status', 'processing')->update(['status' => 'cancelled']);
        $this->placeOrder($this->customer, ['delivery_slot' => '2026-10-13 09:00'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(2, Order::where('delivery_slot_starts_at', '2026-10-13 09:00:00')->where('status', '!=', 'cancelled')->count());
    }

    public function test_a_slot_that_fills_up_while_the_order_is_placed_is_not_overbooked(): void
    {
        $this->booking('2026-10-14 12:00:00');
        $service = app(DeliverySlotService::class);

        DB::transaction(function () use ($service) {
            $this->assertSame('2026-10-14 12:00', $service->reserve('2026-10-14 12:00')['key']);
        });

        $this->booking('2026-10-14 12:00:00');
        $this->expectException(RuntimeException::class);
        DB::transaction(fn () => $service->reserve('2026-10-14 12:00'));
    }

    public function test_slots_are_only_for_deliveries_to_greater_male_while_switched_on(): void
    {
        // An island delivery ignores a slot
        $this->placeOrder($this->customer, ['shipping_city' => 'Hithadhoo', 'shipping_state' => 'Seenu', 'delivery_zone' => 'islands', 'delivery_slot' => '2026-10-13 15:00'])->assertRedirect();
        $this->assertNull(Order::latest('id')->first()->delivery_slot_starts_at);

        // Switched off: no slot section, and a posted slot is ignored
        Setting::set(['delivery_slots_enabled' => 0]);
        $this->cartFor($this->customer, [[$this->product($this->shop('Second Shop'), 100), 1]]);
        $this->actingAs($this->customer)->get('/checkout')->assertOk()->assertDontSee('Delivery time in Malé');
        $this->placeOrder($this->customer, ['delivery_slot' => '2026-10-13 15:00'])->assertRedirect();
        $this->assertNull(Order::latest('id')->first()->delivery_slot_starts_at);
    }

    public function test_the_admin_sets_up_the_slots(): void
    {
        $admin = $this->admin();
        $rates = collect(array_keys(app(\App\Services\DeliveryService::class)->areas()))
            ->map(fn ($area) => ['area' => $area, 'fee' => in_array($area, ['greater_male', 'islands'], true) ? 25 : ''])->all();
        $fields = ['rates' => $rates, 'free_delivery_over' => 1000, 'delivery_slots_enabled' => '1', 'delivery_slot_days_ahead' => 2, 'delivery_slot_cutoff_hours' => 4, 'delivery_slot_capacity' => 5];

        $this->actingAs($admin)->put('/admin/delivery', $fields + ['delivery_slots' => "10:00-13:00\nlunchtime"])->assertSessionHasErrors('delivery_slots');
        $this->actingAs($admin)->put('/admin/delivery', $fields + ['delivery_slots' => '15:00-12:00'])->assertSessionHasErrors('delivery_slots');
        $this->actingAs($admin)->put('/admin/delivery', $fields + ['delivery_slots' => ''])->assertSessionHasErrors('delivery_slots');

        $this->actingAs($admin)->put('/admin/delivery', $fields + ['delivery_slots' => "16:00-19:00\r\n10:00-13:00"])->assertRedirect(route('admin.delivery'));

        $service = app(DeliverySlotService::class);
        $this->assertTrue($service->enabled());
        $this->assertSame([['10:00', '13:00'], ['16:00', '19:00']], $service->definitions());
        $this->assertSame(2, $service->daysAhead());
        $this->assertSame(4, $service->cutoffHours());
        $this->assertSame(5, $service->capacity());
        $this->actingAs($admin)->get('/admin/delivery')->assertOk()->assertSee('Booked so far')->assertSee("10:00-13:00\n16:00-19:00");
    }
}
