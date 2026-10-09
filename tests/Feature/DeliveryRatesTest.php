<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\AuditLog;
use App\Models\DeliveryRate;
use App\Models\Island;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * Delivery rates per area (Admin → Delivery), the area an order is charged for, and the delivery
 * estimates on the product page and at checkout.
 */
class DeliveryRatesTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected Island $hithadhoo;

    protected Island $thinadhoo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBml();
        Setting::set(['delivery_fee_greater_male' => 25, 'delivery_fee_islands' => 75, 'free_delivery_over' => 1000]);
        $this->island('Malé', 'Kaafu');
        $this->island('Maafushi', 'Kaafu');
        $this->hithadhoo = $this->island('Hithadhoo', 'Seenu');
        $this->thinadhoo = $this->island('Thinadhoo', 'Gaafu Dhaalu');
    }

    /**
     * The table as the page posts it: one row per area.
     */
    protected function ratesPayload(array $overrides = []): array
    {
        $rows = [];
        foreach (array_keys(app(DeliveryService::class)->areas()) as $area) {
            $rows[$area] = array_merge(
                ['area' => $area, 'fee' => match ($area) {
                    'greater_male' => 25, 'islands' => 75, default => ''
                }, 'min' => '', 'max' => ''],
                $overrides[$area] ?? []
            );
        }

        return array_values($rows);
    }

    protected function saveDelivery(User $admin, array $rates = [], array $fields = [])
    {
        return $this->actingAs($admin)->put('/admin/delivery', array_merge([
            'rates' => $this->ratesPayload($rates),
            'free_delivery_over' => 1000,
            'delivery_slots_enabled' => '0',
            'delivery_slots' => "09:00-12:00\n12:00-15:00",
            'delivery_slot_days_ahead' => 3,
            'delivery_slot_cutoff_hours' => 3,
            'delivery_slot_capacity' => 10,
        ], $fields));
    }

    public function test_the_delivery_page_lists_greater_male_every_atoll_and_other_islands_for_admins_only(): void
    {
        $this->actingAs($this->admin())->get('/admin/delivery')->assertOk()
            ->assertSee('Greater Malé')
            ->assertSee('Kaafu Atoll')
            ->assertSee('Seenu Atoll')
            ->assertSee('Gaafu Dhaalu Atoll')
            ->assertSee('Other islands')
            ->assertSee('name="free_delivery_over"', false)
            ->assertSee('name="delivery_slots_enabled"', false)
            ->assertSee('09:00-12:00');

        $this->actingAs($this->withRole('support'))->get('/admin/delivery')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/delivery')->assertForbidden();
        $this->actingAs(User::factory()->create())->put('/admin/delivery', [])->assertForbidden();
        auth()->logout();
        $this->get('/admin/delivery')->assertRedirect('/login');

        // Linked from the admin menu and from Settings
        $this->actingAs($this->admin())->get('/admin/settings')->assertOk()->assertSee(route('admin.delivery'), false);
    }

    public function test_saving_fees_and_transit_days_is_audited_and_blank_atolls_fall_back(): void
    {
        $admin = $this->admin();

        $this->saveDelivery($admin, [
            'greater_male' => ['fee' => 30, 'min' => 0, 'max' => 1],
            'Seenu' => ['fee' => 120, 'min' => 3, 'max' => 5],
            'Kaafu' => ['fee' => '', 'min' => 1, 'max' => 2],
            'islands' => ['fee' => 80, 'min' => 2, 'max' => 6],
        ], ['free_delivery_over' => 1500])->assertRedirect(route('admin.delivery'))->assertSessionHas('success');

        $this->assertSame('30', (string) Setting::get('delivery_fee_greater_male'));
        $this->assertSame('80', (string) Setting::get('delivery_fee_islands'));
        $this->assertSame('1500', (string) Setting::get('free_delivery_over'));
        $this->assertEquals(120, DeliveryRate::where('area', 'Seenu')->value('fee'));
        $this->assertNull(DeliveryRate::where('area', 'Kaafu')->value('fee'));
        $this->assertNull(DeliveryRate::where('area', 'Gaafu Dhaalu')->first(), 'an untouched atoll has no row');

        $delivery = app(DeliveryService::class);
        $this->assertSame(30.0, $delivery->areaFee('greater_male'));
        $this->assertSame(120.0, $delivery->areaFee('Seenu'));
        $this->assertSame(80.0, $delivery->areaFee('Kaafu'), 'an atoll without a fee pays the other islands fee');
        $this->assertSame(80.0, $delivery->areaFee('Gaafu Dhaalu'));
        $this->assertSame([3, 5], $delivery->transitDays('Seenu'));
        $this->assertSame([1, 2], $delivery->transitDays('Kaafu'));
        $this->assertSame([2, 6], $delivery->transitDays('Gaafu Dhaalu'), 'an atoll without days uses the other islands days');
        $this->assertSame([0, 1], $delivery->transitDays('greater_male'));

        $log = AuditLog::where('action', 'delivery.saved')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(['from' => '25.00', 'to' => '30.00'], $log->changes['fee:greater_male']);
        $this->assertSame(['from' => null, 'to' => '120.00'], $log->changes['fee:Seenu']);
        $this->assertSame(['from' => null, 'to' => '3-5'], $log->changes['days:Seenu']);
        $this->assertArrayNotHasKey('slot_capacity', $log->changes, 'only what changed is recorded');

        // Clearing an atoll's fee and days removes its row; saving nothing new writes no audit entry
        $this->saveDelivery($admin, ['greater_male' => ['fee' => 30, 'min' => 0, 'max' => 1], 'Seenu' => ['fee' => '', 'min' => '', 'max' => ''], 'Kaafu' => ['min' => 1, 'max' => 2], 'islands' => ['fee' => 80, 'min' => 2, 'max' => 6]], ['free_delivery_over' => 1500]);
        $this->assertNull(DeliveryRate::where('area', 'Seenu')->first());
        $this->assertSame(80.0, app(DeliveryService::class)->areaFee('Seenu'));
        $this->saveDelivery($admin, ['greater_male' => ['fee' => 30, 'min' => 0, 'max' => 1], 'Kaafu' => ['min' => 1, 'max' => 2], 'islands' => ['fee' => 80, 'min' => 2, 'max' => 6]], ['free_delivery_over' => 1500]);
        $this->assertSame(2, AuditLog::where('action', 'delivery.saved')->count());
    }

    public function test_the_delivery_page_rejects_bad_rows(): void
    {
        $admin = $this->admin();

        $this->saveDelivery($admin, ['greater_male' => ['fee' => '']])->assertSessionHasErrors('rates.0.fee');
        $this->saveDelivery($admin, ['Seenu' => ['min' => 5, 'max' => 2]])->assertSessionHasErrors();
        $this->saveDelivery($admin, ['Seenu' => ['min' => 2, 'max' => '']])->assertSessionHasErrors();
        $this->saveDelivery($admin, ['Seenu' => ['fee' => -5]])->assertSessionHasErrors();
        $this->actingAs($admin)->put('/admin/delivery', [
            'rates' => [['area' => 'Atlantis', 'fee' => 10]], 'free_delivery_over' => 0,
            'delivery_slot_days_ahead' => 3, 'delivery_slot_cutoff_hours' => 3, 'delivery_slot_capacity' => 10,
        ])->assertSessionHasErrors('rates.0.area');

        $this->assertSame(0, DeliveryRate::count());
        $this->assertSame(0, AuditLog::where('action', 'delivery.saved')->count());
    }

    public function test_the_order_records_the_area_it_was_charged_for(): void
    {
        DeliveryRate::create(['area' => 'Seenu', 'fee' => 100]);
        DeliveryRate::create(['area' => 'Kaafu', 'fee' => 40]);
        $customer = User::factory()->create();
        $shop = $this->shop('Reef Crafts');
        $product = $this->product($shop, 200);

        $cases = [
            // typed island and atoll, zone radio => fee, area, zone
            [['shipping_city' => 'Hithadhoo', 'shipping_state' => 'Seenu', 'delivery_zone' => 'islands'], 100, 'Seenu', 'islands'],
            [['shipping_city' => 'Maafushi', 'shipping_state' => 'Kaafu', 'delivery_zone' => 'islands'], 40, 'Kaafu', 'islands'],
            [['shipping_city' => 'Malé', 'shipping_state' => 'Kaafu'], 25, 'greater_male', 'greater_male'],
            [['shipping_city' => 'Thinadhoo', 'shipping_state' => 'Gaafu Dhaalu', 'delivery_zone' => 'islands'], 75, 'islands', 'islands'],
        ];

        foreach ($cases as [$address, $fee, $area, $zone]) {
            $this->cartFor($customer, [[$product, 1]]);
            $this->placeOrder($customer, $address)->assertRedirect();
            $order = Order::latest('id')->first();
            $this->assertEquals($fee, $order->shipping_amount, $address['shipping_city']);
            $this->assertEquals(200 + $fee, $order->total_amount);
            $this->assertSame($area, $order->delivery_area);
            $this->assertSame($zone, $order->delivery_zone, 'delivery_zone keeps working');
        }

        // An island picked from the list decides the area too
        $this->cartFor($customer, [[$product, 1]]);
        $this->placeOrder($customer, ['island_id' => $this->hithadhoo->id, 'shipping_city' => '', 'shipping_state' => '', 'delivery_zone' => 'greater_male'])->assertRedirect();
        $this->assertSame('Seenu', Order::latest('id')->first()->delivery_area);
        $this->assertEquals(100, Order::latest('id')->first()->shipping_amount);

        // The order pages name the area
        $this->actingAs($this->admin())->get(route('admin.orders.show', Order::latest('id')->first()))->assertOk()->assertSee('Seenu Atoll');
    }

    public function test_free_delivery_over_the_threshold_still_covers_area_fees(): void
    {
        DeliveryRate::create(['area' => 'Seenu', 'fee' => 100]);
        $customer = User::factory()->create();
        $product = $this->product($this->shop('Reef Crafts'), 1200);
        $this->cartFor($customer, [[$product, 1]]);

        $this->placeOrder($customer, ['shipping_city' => 'Hithadhoo', 'shipping_state' => 'Seenu', 'delivery_zone' => 'islands'])->assertRedirect();

        $order = Order::sole();
        $this->assertEquals(0, $order->shipping_amount);
        $this->assertEquals(1200, $order->total_amount);
        $this->assertSame('Seenu', $order->delivery_area);
    }

    public function test_the_product_page_shows_the_fee_and_arrival_for_the_chosen_island(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
        DeliveryRate::create(['area' => 'Seenu', 'fee' => 100, 'transit_days_min' => 2, 'transit_days_max' => 4]);
        DeliveryRate::create(['area' => 'greater_male', 'transit_days_min' => 0, 'transit_days_max' => 1]);
        $shop = $this->shop('Reef Crafts', null, 2);
        $product = $this->product($shop, 200);

        // Nobody chose an island: Greater Malé (Malé), the shop's 2 dispatch days plus 0-1 transit days
        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Delivery to Malé')
            ->assertSee('MVR 25.00')
            ->assertSee('Arrives 14–15 Oct')
            ->assertSee('Free delivery on orders over MVR 1,000.00')
            ->assertSee('name="island_id"', false);

        // Picking an island is remembered for the next pages
        $this->from(route('products.show', $product))->post('/delivery/island', ['island_id' => $this->hithadhoo->id])
            ->assertRedirect(route('products.show', $product).'#delivery-box')
            ->assertSessionHas(DeliveryService::SESSION_ISLAND, $this->hithadhoo->id);
        $this->get(route('products.show', $product))->assertOk()
            ->assertSee('Delivery to Hithadhoo')
            ->assertSee('MVR 100.00')
            ->assertSee('Arrives 16–18 Oct');

        $this->post('/delivery/island', ['island_id' => 999999])->assertSessionHasErrors('island_id');
    }

    public function test_the_product_page_starts_at_the_customers_default_address(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
        $customer = User::factory()->create();
        $address = new Address(['label' => 'Home', 'recipient_name' => 'Aisha', 'phone' => '7771234', 'island_id' => $this->thinadhoo->id, 'house_name_or_street' => 'Blue House', 'country' => 'Maldives']);
        $address->user_id = $customer->id;
        $address->syncIsland()->save();
        $product = $this->product($this->shop('Reef Crafts'), 200);

        // No row for Gaafu Dhaalu: the other islands' fee and days (2-5) after 1 dispatch day
        $this->actingAs($customer)->get(route('products.show', $product))->assertOk()
            ->assertSee('Delivery to Thinadhoo')
            ->assertSee('MVR 75.00')
            ->assertSee('Arrives 15–18 Oct');
    }

    public function test_checkout_shows_the_estimate_for_each_shop(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 12)->setTime(10, 0));
        $customer = User::factory()->create();
        $fast = $this->product($this->shop('Fast Shop', null, 0), 100);
        $slow = $this->product($this->shop('Slow Shop', null, 3), 100);
        $this->cartFor($customer, [[$fast, 1], [$slow, 1]]);

        // A typed Malé address: Greater Malé's default 1-2 days
        $customer->update(['city' => 'Malé']);
        $this->actingAs($customer)->get('/checkout')->assertOk()
            ->assertSee('From Fast Shop')
            ->assertSee('Arrives 13–14 Oct')
            ->assertSee('From Slow Shop')
            ->assertSee('Arrives 16–17 Oct');
    }

    public function test_the_delivery_policy_and_help_list_atolls_with_their_own_fee(): void
    {
        DeliveryRate::create(['area' => 'Seenu', 'fee' => 120]);
        DeliveryRate::create(['area' => 'Kaafu', 'transit_days_min' => 1, 'transit_days_max' => 2]);

        $this->get(route('policies.delivery'))->assertOk()
            ->assertSee('Seenu Atoll')
            ->assertSee('MVR 120.00 per order.')
            ->assertDontSee('Kaafu Atoll')
            ->assertSee('extra delivery charge per item')
            ->assertSee('collect your order from them');
        $this->get(route('help'))->assertOk()->assertSee('Seenu Atoll')->assertSee('MVR 120.00');
    }

    public function test_the_delivery_sections_are_in_dhivehi(): void
    {
        $customer = User::factory()->create(['city' => 'Malé']);
        $pickupShop = $this->shop('Coral Corner', $this->thinadhoo);
        $product = $this->product($pickupShop, 200, 30);
        $this->cartFor($customer, [[$product, 1]]);

        $this->withSession(['locale' => 'dv'])->get('/dv/products/'.$product->slug)->assertOk()
            ->assertSee('Malé އަށް ޑެލިވަރީ')
            ->assertSee('ލިބޭނެ ދުވަސް:')
            ->assertSee('ޑެލިވަރީ ފީއެއް ނުދައްކައި');

        $this->actingAs($customer)->withSession(['locale' => 'dv'])->get('/checkout')->assertOk()
            ->assertSee('ޑެލިވަރީ ނުވަތަ ޕިކަޕް')
            ->assertSee('Coral Corner އިން ނަގާ')
            ->assertSee('މިއީ ހަދިޔާއެއް');
    }

    public function test_estimates_are_calendar_days_and_ranges_read_well(): void
    {
        $from = \Carbon\CarbonImmutable::parse('2026-10-29 09:00');
        $delivery = app(DeliveryService::class);
        DeliveryRate::create(['area' => 'islands', 'transit_days_min' => 2, 'transit_days_max' => 5]);

        $this->assertSame('Arrives 1–4 Nov', $delivery->arrivalText(1, 'islands', $from));
        $this->assertSame('Arrives 31 Oct – 3 Nov', $delivery->arrivalText(0, 'islands', $from));
        DeliveryRate::create(['area' => 'greater_male', 'transit_days_min' => 1, 'transit_days_max' => 1]);
        $this->assertSame('Arrives 31 Oct', $delivery->arrivalText(1, 'greater_male', $from));
    }
}
