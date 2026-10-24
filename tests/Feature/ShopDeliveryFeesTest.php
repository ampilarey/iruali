<?php

namespace Tests\Feature;

use App\Models\Island;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SellerAdjustment;
use App\Models\SellerDeliverySetting;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\GstService;
use App\Services\ReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * Shops' own delivery fees: a shop that delivers its orders itself sets fees to its home island,
 * Greater Malé and other islands (up to iruali's limit). Its fee is charged for its part and paid
 * to it with its earnings; iruali's fee is charged once for the other shops and kept by iruali.
 */
class ShopDeliveryFeesTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected Island $male;

    protected Island $fuvahmulah;

    protected function setUp(): void
    {
        parent::setUp();
        $this->enableBml();
        Setting::set(['delivery_fee_greater_male' => 25, 'delivery_fee_islands' => 75, 'free_delivery_over' => 1000, 'default_commission_rate' => 10]);
        $this->male = $this->island('Malé', 'Kaafu');
        $this->fuvahmulah = $this->island('Fuvahmulah', 'Gnaviyani');
    }

    /**
     * A shop on $home with its own fees.
     */
    protected function ownFeeShop(string $name, ?Island $home, array $fees = []): User
    {
        $seller = $this->shop($name);
        $seller->forceFill(['home_island_id' => $home?->id])->save();
        SellerDeliverySetting::where('seller_id', $seller->id)->update(array_merge([
            'own_fees' => true, 'fee_local' => null, 'fee_greater_male' => 40, 'fee_islands' => 90, 'free_over' => null,
        ], $fees));

        return $seller;
    }

    protected function part(Order $order, User $seller): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $seller->id)->sole();
    }

    public function test_a_shop_sets_its_fees_within_the_limit(): void
    {
        Setting::set(['shop_delivery_fee_max' => 200]);
        $seller = $this->shop('Fuvah Fresh');
        $seller->forceFill(['home_island_id' => $this->fuvahmulah->id])->save();

        $this->actingAs($seller)->get(route('seller.settings.delivery'))->assertOk()
            ->assertSee('Your delivery fees')
            ->assertSee('To Fuvahmulah (your island)')
            ->assertSee('at most MVR 200.00');

        $base = ['ships_within_days' => 1, 'pickup_enabled' => 0, 'own_fees' => 1];
        $this->put(route('seller.settings.delivery.update'), $base)->assertSessionHasErrors(['fee_greater_male', 'fee_islands']);
        $this->put(route('seller.settings.delivery.update'), $base + ['fee_greater_male' => 250, 'fee_islands' => 90])->assertSessionHasErrors('fee_greater_male');

        $this->put(route('seller.settings.delivery.update'), $base + ['fee_local' => 10, 'fee_greater_male' => 60, 'fee_islands' => 90, 'free_over' => 500])
            ->assertRedirect(route('seller.settings.delivery'));
        $setting = SellerDeliverySetting::where('seller_id', $seller->id)->sole();
        $this->assertTrue($setting->hasOwnFees());
        $this->assertEquals([10, 60, 90, 500], [$setting->fee_local, $setting->fee_greater_male, $setting->fee_islands, $setting->free_over]);

        // Turned off: the fees stay saved but are not charged
        $this->put(route('seller.settings.delivery.update'), ['own_fees' => 0] + $base + ['fee_greater_male' => 60, 'fee_islands' => 90]);
        $this->assertFalse(SellerDeliverySetting::where('seller_id', $seller->id)->sole()->hasOwnFees());
    }

    public function test_when_iruali_turns_own_fees_off_shops_cannot_change_them_and_iruali_fees_apply(): void
    {
        $seller = $this->ownFeeShop('Fuvah Fresh', $this->fuvahmulah);
        $this->actingAs($this->admin())->put(route('admin.delivery.update'), [
            'rates' => [['area' => 'greater_male', 'fee' => 25], ['area' => 'islands', 'fee' => 75]],
            'free_delivery_over' => 1000, 'shop_own_fees_enabled' => 0, 'shop_delivery_fee_max' => 300,
            'delivery_slot_days_ahead' => 3, 'delivery_slot_cutoff_hours' => 2, 'delivery_slot_capacity' => 0,
        ])->assertRedirect(route('admin.delivery'));

        $this->assertFalse(SellerDeliverySetting::ownFeesAllowed());
        $this->assertEquals(300, SellerDeliverySetting::maxFee());
        $this->actingAs($seller)->get(route('seller.settings.delivery'))->assertOk()->assertDontSee('Your delivery fees');
        $this->put(route('seller.settings.delivery.update'), ['ships_within_days' => 2, 'own_fees' => 0, 'fee_greater_male' => 1, 'fee_islands' => 1]);
        $setting = SellerDeliverySetting::where('seller_id', $seller->id)->sole();
        $this->assertTrue((bool) $setting->own_fees);
        $this->assertEquals(40, $setting->fee_greater_male);
        $this->assertSame(2, $setting->ships_within_days);

        $customer = User::factory()->create();
        $this->cartFor($customer, [[$this->product($seller, 100), 1]]);
        $this->placeOrder($customer, ['island_id' => $this->male->id])->assertRedirect();
        $order = Order::sole();
        $this->assertEquals(25, $order->shipping_amount);
        $this->assertEquals(0, $order->delivery_fee_shops);
        $this->assertFalse($this->part($order, $seller)->delivery_fee_to_shop);
    }

    public function test_each_own_fee_shop_charges_its_fee_and_iruali_charges_once_for_the_rest(): void
    {
        $customer = User::factory()->create();
        $fresh = $this->ownFeeShop('Fuvah Fresh', $this->fuvahmulah);
        $plain = $this->shop('Malé Mart');
        $other = $this->shop('Reef Books');
        $this->cartFor($customer, [[$this->product($fresh, 200, 15), 2], [$this->product($plain, 50), 1], [$this->product($other, 30), 1]]);

        $this->placeOrder($customer, ['island_id' => $this->male->id])->assertRedirect();

        $order = Order::sole();
        // Fuvah Fresh: its Greater Malé fee 40 and its bulky charges 30; iruali's Malé fee once for the other two
        $this->assertEquals(40 + 30 + 25, $order->shipping_amount);
        $this->assertEquals(70, $order->delivery_fee_shops);
        $this->assertEquals(400 + 50 + 30 + 95, $order->total_amount);

        $part = $this->part($order, $fresh);
        $this->assertEquals(40, $part->delivery_fee);
        $this->assertTrue($part->delivery_fee_to_shop);
        $this->assertEquals(70, $part->shopDeliveryCharge());
        // Commission on the goods only; the delivery is the shop's in full
        $this->assertEquals(40, $part->commission_amount);
        $this->assertEquals(400 - 40 + 70, $part->seller_earnings);

        $plainPart = $this->part($order, $plain);
        $this->assertEquals(0, $plainPart->delivery_fee);
        $this->assertFalse($plainPart->delivery_fee_to_shop);
        $this->assertEquals(45, $plainPart->seller_earnings);

        $this->actingAs($fresh)->get(route('seller.orders.show', $order))->assertOk()
            ->assertSee('Your delivery fee (no commission)')->assertSee('MVR 70.00');

        // iruali's delivery income is the rest
        $this->assertEquals(25, app(GstService::class)->deliveryFee($order));
    }

    public function test_only_own_fee_shops_means_no_iruali_fee_and_the_local_fee_and_free_amount_apply(): void
    {
        $customer = User::factory()->create();
        $fresh = $this->ownFeeShop('Fuvah Fresh', $this->fuvahmulah, ['fee_local' => 10, 'free_over' => 500]);
        $this->cartFor($customer, [[$this->product($fresh, 100), 2]]);

        // To its own island: the local fee, and no iruali fee at all
        $this->placeOrder($customer, ['island_id' => $this->fuvahmulah->id, 'shipping_city' => 'Fuvahmulah', 'shipping_state' => 'Gnaviyani'])->assertRedirect();
        $order = Order::latest('id')->first();
        $this->assertEquals(10, $order->shipping_amount);
        $this->assertEquals(10, $order->delivery_fee_shops);
        $this->assertEquals(0, app(GstService::class)->deliveryFee($order));

        // To another island: its other-islands fee
        $hithadhoo = $this->island('Hithadhoo', 'Seenu');
        $this->cartFor($customer, [[$this->product($fresh, 100), 2]]);
        $this->placeOrder($customer, ['island_id' => $hithadhoo->id, 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Seenu'])->assertRedirect();
        $this->assertEquals(90, Order::latest('id')->first()->shipping_amount);

        // Its items reach its free-delivery amount: free
        $this->cartFor($customer, [[$this->product($fresh, 100), 5]]);
        $this->placeOrder($customer, ['island_id' => $hithadhoo->id, 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Seenu'])->assertRedirect();
        $order = Order::latest('id')->first();
        $this->assertEquals(0, $order->shipping_amount);
        $this->assertTrue($this->part($order, $fresh)->delivery_fee_to_shop);
    }

    public function test_iruali_limit_caps_saved_fees_and_pickup_has_no_fee(): void
    {
        Setting::set(['shop_delivery_fee_max' => 30]);
        $customer = User::factory()->create();
        $fresh = $this->ownFeeShop('Fuvah Fresh', $this->fuvahmulah);
        SellerDeliverySetting::where('seller_id', $fresh->id)->update(['pickup_enabled' => true, 'pickup_address' => 'Fresh Store', 'pickup_island_id' => $this->fuvahmulah->id, 'pickup_hours' => '9 to 5']);
        $this->cartFor($customer, [[$this->product($fresh, 100), 1]]);

        $this->placeOrder($customer, ['island_id' => $this->male->id])->assertRedirect();
        $this->assertEquals(30, Order::latest('id')->first()->shipping_amount);

        $this->cartFor($customer, [[$this->product($fresh, 100), 1]]);
        $this->placeOrder($customer, ['fulfilment' => [$fresh->id => 'pickup']])->assertRedirect();
        $order = Order::latest('id')->first();
        $this->assertEquals(0, $order->shipping_amount);
        $this->assertFalse($this->part($order, $fresh)->delivery_fee_to_shop);
        $this->assertEquals(90, $this->part($order, $fresh)->seller_earnings);
    }

    public function test_checkout_and_product_page_show_the_shop_fee(): void
    {
        $customer = User::factory()->create();
        $fresh = $this->ownFeeShop('Fuvah Fresh', $this->fuvahmulah, ['free_over' => 800]);
        $plain = $this->shop('Malé Mart');
        $fish = $this->product($fresh, 120);
        $this->cartFor($customer, [[$fish, 1], [$this->product($plain, 50), 1]]);

        $this->actingAs($customer)->get('/checkout')->assertOk()
            ->assertSee('Delivered by Fuvah Fresh')
            ->assertSee('free over MVR 800.00 of its items')
            ->assertSee('data-shop-fee', false)
            // The Greater Malé total: the shop's 40 and iruali's 25
            ->assertSee('data-fee="65"', false);

        $this->get(route('products.show', $fish))->assertOk()
            ->assertSee('The shop delivers this itself, at its own fee.')
            ->assertSee('Free delivery when your items from this shop come to MVR 800.00')
            ->assertSee('MVR 40.00');
    }

    public function test_a_shop_fault_return_refunds_and_takes_back_the_shops_delivery(): void
    {
        $customer = User::factory()->create();
        $fresh = $this->ownFeeShop('Fuvah Fresh', $this->fuvahmulah);
        $plain = $this->shop('Malé Mart');
        $this->cartFor($customer, [[$this->product($fresh, 100), 1], [$this->product($plain, 50), 1]]);
        $this->placeOrder($customer, ['island_id' => $this->male->id])->assertRedirect();
        $order = Order::sole();
        $order->forceFill(['payment_status' => 'paid', 'paid_at' => now()])->save(); // money goes back only on a paid order
        $part = $this->part($order, $fresh);

        $return = ReturnRequest::create([
            'order_id' => $order->id, 'seller_order_id' => $part->id, 'user_id' => $customer->id,
            'status' => 'requested', 'reason' => 'faulty', 'details' => 'Broken', 'items_value' => 100,
        ]);
        $returns = app(ReturnService::class);
        // Its items and its own delivery, even with another shop in the order
        $this->assertEquals(140, $returns->suggestedRefund($return));

        $this->assertTrue($returns->approve($return, 140, false, null, $this->admin()));
        // The shop gives back its items less commission, and the delivery it was paid in full
        $this->assertEquals(-(90 + 40), (float) SellerAdjustment::where('seller_id', $fresh->id)->sole()->amount);
    }

    public function test_a_gst_registered_shop_invoices_its_delivery_and_iruali_only_its_own(): void
    {
        Setting::set(['gst_registered' => 1, 'gst_tin' => '1000001GST501', 'gst_rate' => 8]);
        $customer = User::factory()->create();
        $fresh = $this->ownFeeShop('Fuvah Fresh', $this->fuvahmulah);
        \App\Models\ShopTaxProfile::create(['user_id' => $fresh->id, 'gst_registered' => true, 'tin' => '2000002GST502', 'registered_name' => 'Fuvah Fresh Pvt Ltd', 'business_address' => 'Fuvahmulah']);
        $plain = $this->shop('Malé Mart');
        $this->cartFor($customer, [[$this->product($fresh, 100), 1], [$this->product($plain, 50), 1]]);
        $this->placeOrder($customer, ['island_id' => $this->male->id])->assertRedirect();

        $order = Order::sole();
        $part = $this->part($order, $fresh);
        $this->assertEquals(140, $part->gst_taxable);
        $this->assertEquals(GstService::gstIncluded(140, 8), (float) $part->gst_amount);
        $this->assertEquals(GstService::gstIncluded(25, 8), (float) $order->delivery_gst);

        // Paid: the shop's tax invoice lists its delivery and totals it with the goods
        app(\App\Services\PaymentService::class)->confirm($order->fresh());
        $this->actingAs($customer)->get(route('orders.invoice', [$order, $part->fresh()]))->assertOk()
            ->assertSee('data-shop-delivery', false)
            ->assertSee('MVR 140.00')
            ->assertDontSee('Shop discount');
    }

    public function test_policies_explain_shops_own_fees_while_they_are_allowed(): void
    {
        Setting::set(['shop_delivery_fee_max' => 200]);
        $this->get(route('policies.delivery'))->assertOk()
            ->assertSee('Some shops deliver their orders themselves and set their own delivery fee, up to MVR 200.00.')
            ->assertSee('Not every shop delivers to every island.');
        $this->actingAs(User::factory()->create())->get(route('policies.seller_terms'))->assertOk()
            ->assertSee('you deliver your parts yourself and are paid your fee and your bulky-item charges with your earnings', false)
            ->assertDontSee('Delivery fees are set and charged by');

        Setting::set(['shop_own_fees_enabled' => 0]);
        $this->get(route('policies.delivery'))->assertOk()->assertDontSee('Some shops deliver their orders themselves');
    }
}
