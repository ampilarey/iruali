<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Island;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Saved delivery addresses: the address book, the default, checkout with a saved or new address,
 * and the island → delivery zone mapping.
 */
class AddressesTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = User::factory()->create(['phone' => '7770001']);
    }

    protected function island(string $en, string $atoll): Island
    {
        return Island::create(['name' => ['en' => $en, 'dv' => $en], 'atoll' => $atoll, 'is_active' => true]);
    }

    protected function fields(array $overrides = []): array
    {
        return array_merge([
            'label' => 'Home', 'recipient_name' => 'Aisha', 'phone' => '777 1234',
            'island' => 'Hithadhoo', 'atoll' => 'Seenu', 'house_name_or_street' => 'Blue House', 'postal_code' => '19020',
        ], $overrides);
    }

    protected function cartWorth(float $price, User $user): void
    {
        $product = Product::factory()->create(['price' => $price, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $price]);
    }

    public function test_address_book_crud_and_default_handling(): void
    {
        $this->get('/account/addresses')->assertRedirect('/login');

        $this->actingAs($this->customer)->get('/account/addresses')->assertOk()->assertSee('No saved addresses yet.');
        $this->actingAs($this->customer)->get('/account/addresses/new')->assertOk()->assertSee('name="island_id"', false);

        // The first address becomes the default even when the box is not ticked
        $this->actingAs($this->customer)->post('/account/addresses', $this->fields())->assertRedirect('/account/addresses');
        $home = Address::sole();
        $this->assertTrue($home->is_default);
        $this->assertSame('7771234', $home->phone);
        $this->assertSame('Maldives', $home->country);

        $this->actingAs($this->customer)->post('/account/addresses', $this->fields(['label' => 'Office', 'house_name_or_street' => 'Orchid Building']))->assertRedirect();
        $office = Address::where('label', 'Office')->sole();
        $this->assertFalse($office->is_default);
        $this->assertTrue($home->fresh()->is_default);

        $this->actingAs($this->customer)->get('/account/addresses')->assertOk()->assertSeeInOrder(['Home', 'Default', 'Office', 'Set as default']);

        // Set default moves it; only one default at a time
        $this->actingAs($this->customer)->post("/account/addresses/{$office->id}/default")->assertRedirect();
        $this->assertTrue($office->fresh()->is_default);
        $this->assertFalse($home->fresh()->is_default);
        $this->assertSame(1, Address::where('user_id', $this->customer->id)->where('is_default', true)->count());

        // Edit
        $this->actingAs($this->customer)->get("/account/addresses/{$home->id}/edit")->assertOk()->assertSee('Blue House');
        $this->actingAs($this->customer)->put("/account/addresses/{$home->id}", $this->fields(['house_name_or_street' => 'Green House', 'is_default' => '1']))->assertRedirect();
        $this->assertSame('Green House', $home->fresh()->house_name_or_street);
        $this->assertTrue($home->fresh()->is_default);
        $this->assertFalse($office->fresh()->is_default);

        // Validation
        $this->actingAs($this->customer)->post('/account/addresses', $this->fields(['island' => '', 'island_id' => '']))->assertSessionHasErrors('island');
        $this->actingAs($this->customer)->post('/account/addresses', $this->fields(['phone' => 'call me']))->assertSessionHasErrors('phone');
        $this->actingAs($this->customer)->post('/account/addresses', $this->fields(['house_name_or_street' => '']))->assertSessionHasErrors('house_name_or_street');

        // Deleting the default promotes another address
        $this->actingAs($this->customer)->delete("/account/addresses/{$home->id}")->assertRedirect();
        $this->assertSoftDeleted('addresses', ['id' => $home->id]);
        $this->assertTrue($office->fresh()->is_default);
    }

    public function test_addresses_belong_to_their_owner_only(): void
    {
        $this->actingAs($this->customer)->post('/account/addresses', $this->fields());
        $address = Address::sole();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)->get("/account/addresses/{$address->id}/edit")->assertForbidden();
        $this->actingAs($stranger)->put("/account/addresses/{$address->id}", $this->fields(['house_name_or_street' => 'Hacked']))->assertForbidden();
        $this->actingAs($stranger)->post("/account/addresses/{$address->id}/default")->assertForbidden();
        $this->actingAs($stranger)->delete("/account/addresses/{$address->id}")->assertForbidden();
        $this->actingAs($stranger)->get('/account/addresses')->assertOk()->assertDontSee('Blue House');
        $this->assertSame('Blue House', $address->fresh()->house_name_or_street);
    }

    public function test_islands_map_to_delivery_zones_and_picked_islands_fill_the_address(): void
    {
        $male = $this->island('Malé', 'Kaafu');
        $maafushi = $this->island('Maafushi', 'Kaafu');
        $hithadhoo = $this->island('Hithadhoo', 'Seenu');
        $delivery = app(DeliveryService::class);

        $this->assertSame('greater_male', $delivery->zoneForIsland($male));
        $this->assertSame('islands', $delivery->zoneForIsland($maafushi), 'Kaafu alone is not Greater Malé');
        $this->assertSame('islands', $delivery->zoneForIsland($hithadhoo));
        $this->assertSame('greater_male', $delivery->zoneForIsland(null, 'Hulhumalé'));
        $this->assertSame('islands', $delivery->zoneForIsland(null, 'Thinadhoo'));
        $this->assertSame(['Kaafu', 'Seenu'], $delivery->islandsByAtoll()->keys()->all());

        // Picking an island from the list stores its name and atoll, whatever was typed
        $this->actingAs($this->customer)->post('/account/addresses', $this->fields(['island_id' => $male->id, 'island' => 'typed', 'atoll' => 'typed']))->assertRedirect();
        $address = Address::sole();
        $this->assertSame('Malé', $address->island);
        $this->assertSame('Kaafu', $address->atoll);
        $this->assertSame($male->id, $address->island_id);
        $this->assertSame('greater_male', $address->deliveryZone());
        $this->assertSame('greater_male', $address->toShippingData()['delivery_zone']);

        $this->actingAs($this->customer)->get('/account/addresses/new')->assertOk()
            ->assertSee('<optgroup label="Kaafu">', false)
            ->assertSee('<optgroup label="Seenu">', false)
            ->assertSee('data-zone="greater_male"', false);
    }

    public function test_checkout_offers_saved_addresses_and_uses_the_chosen_one(): void
    {
        $this->enableBml();
        $male = $this->island('Malé', 'Kaafu');
        $this->island('Hithadhoo', 'Seenu');
        $this->actingAs($this->customer)->post('/account/addresses', $this->fields(['label' => 'Island home']));
        $this->actingAs($this->customer)->post('/account/addresses', $this->fields(['label' => 'City flat', 'island_id' => $male->id, 'house_name_or_street' => 'H. Lily', 'phone' => '9990000', 'is_default' => '1']));
        $islandHome = Address::where('label', 'Island home')->sole();
        $cityFlat = Address::where('label', 'City flat')->sole();
        $this->cartWorth(200, $this->customer);

        // The default is pre-selected; a "new address" option and the island list are there too
        $this->actingAs($this->customer)->get('/checkout')->assertOk()
            ->assertSee('Island home')->assertSee('City flat')
            ->assertSee('name="address_id" value="'.$cityFlat->id.'" data-zone="greater_male" checked', false)
            ->assertSee('Deliver to a new address')
            ->assertSee('name="save_address"', false)
            ->assertSee('<optgroup label="Seenu">', false);

        // Checking out with the island address fills shipping_* and picks the islands zone; the form fields are not needed
        $this->actingAs($this->customer)->post('/orders', [
            'address_id' => $islandHome->id, 'delivery_zone' => 'greater_male',
            'payment_method' => 'bml', 'agree_terms' => '1',
        ])->assertRedirect();
        $order = Order::sole();
        $this->assertSame('Blue House', $order->shipping_address);
        $this->assertSame('Hithadhoo', $order->shipping_city);
        $this->assertSame('Seenu', $order->shipping_state);
        $this->assertSame('19020', $order->shipping_zip);
        $this->assertSame('7771234', $order->shipping_phone);
        $this->assertSame('islands', $order->delivery_zone, 'the island decides the zone, not the radio');
        $this->assertEquals(75, $order->shipping_amount);

        // The Malé address gets the Greater Malé rate
        $this->cartWorth(200, $this->customer);
        $this->actingAs($this->customer)->post('/orders', ['address_id' => $cityFlat->id, 'payment_method' => 'bml', 'agree_terms' => '1'])->assertRedirect();
        $second = Order::latest('id')->first();
        $this->assertSame('H. Lily', $second->shipping_address);
        $this->assertSame('Malé', $second->shipping_city);
        $this->assertSame('greater_male', $second->delivery_zone);
        $this->assertEquals(25, $second->shipping_amount);
        $this->assertSame('9990000', $second->shipping_phone);

        // Someone else's address is refused
        $stranger = User::factory()->create();
        $this->actingAs($stranger)->post('/account/addresses', $this->fields(['label' => 'Theirs']));
        $theirs = Address::where('label', 'Theirs')->sole();
        $this->cartWorth(200, $this->customer);
        $this->actingAs($this->customer)->post('/orders', ['address_id' => $theirs->id, 'payment_method' => 'bml', 'agree_terms' => '1'])
            ->assertSessionHasErrors('address_id');
        $this->assertSame(2, Order::count());
    }

    public function test_a_new_address_at_checkout_can_pick_an_island_and_be_saved(): void
    {
        $this->enableBml();
        $hulhumale = $this->island('Hulhumalé', 'Kaafu');
        $this->cartWorth(200, $this->customer);

        $this->actingAs($this->customer)->post('/orders', [
            'address_id' => '', 'island_id' => $hulhumale->id, 'shipping_city' => '', 'shipping_state' => '',
            'shipping_address' => 'Lot 10406', 'shipping_zip' => '', 'shipping_country' => 'Maldives', 'shipping_phone' => '7775555',
            'delivery_zone' => 'islands', 'payment_method' => 'bml', 'agree_terms' => '1',
            'save_address' => '1', 'address_label' => 'Hulhumalé flat',
        ])->assertRedirect();

        $order = Order::sole();
        $this->assertSame('Hulhumalé', $order->shipping_city);
        $this->assertSame('Kaafu', $order->shipping_state);
        $this->assertSame('greater_male', $order->delivery_zone);
        $this->assertEquals(25, $order->shipping_amount);

        $saved = Address::sole();
        $this->assertSame('Hulhumalé flat', $saved->label);
        $this->assertSame($hulhumale->id, $saved->island_id);
        $this->assertSame('Lot 10406', $saved->house_name_or_street);
        $this->assertSame('7775555', $saved->phone);
        $this->assertTrue($saved->is_default);

        // Typed addresses still work exactly as before, and are not saved unless asked
        $this->cartWorth(200, $this->customer);
        $this->actingAs($this->customer)->post('/orders', [
            'address_id' => '', 'shipping_address' => 'M. Blue House', 'shipping_city' => 'Thinadhoo', 'shipping_state' => 'Gaafu Dhaalu',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml', 'agree_terms' => '1',
        ])->assertRedirect();
        $this->assertSame('islands', Order::latest('id')->first()->delivery_zone);
        $this->assertSame(1, Address::count());
    }

    public function test_profile_edit_still_works_alongside_the_address_book(): void
    {
        $this->actingAs($this->customer)->put('/account', ['name' => 'Aisha', 'address' => 'M. Blue House', 'city' => 'Malé', 'state' => 'Kaafu'])->assertRedirect(route('account'));
        $this->assertSame('M. Blue House', $this->customer->fresh()->address);
        $this->actingAs($this->customer)->get('/account')->assertOk()->assertSee('My addresses')->assertSee('Manage saved addresses');
    }
}
