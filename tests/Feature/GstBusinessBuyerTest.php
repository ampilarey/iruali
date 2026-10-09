<?php

namespace Tests\Feature;

use App\Models\BusinessProfile;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\Support\GstFixtures;
use Tests\TestCase;

/**
 * Business buyers: details kept in the profile, offered at checkout ("Buying for a business?"),
 * stored on the order and printed on the shops' invoices. Guests can give them too.
 */
class GstBusinessBuyerTest extends TestCase
{
    use GstFixtures, RefreshDatabase;

    protected User $shopUser;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableBml();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 81]);
        $this->shopUser = $this->shop('Island Crafts', '1012345GST501');
    }

    protected function cartFor(?User $user, array $cart = []): Cart
    {
        $product = Product::factory()->create(['seller_id' => $this->shopUser->id, 'price' => 540, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create($cart + ['user_id' => $user?->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 540]);

        return $cart;
    }

    protected function checkoutFields(array $overrides = []): array
    {
        return array_merge([
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu', 'shipping_zip' => '',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'delivery_zone' => 'islands', 'payment_method' => 'bml', 'agree_terms' => '1',
        ], $overrides);
    }

    public function test_profile_business_details_are_saved_checked_and_removed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('account.edit'))->assertOk()->assertSee('Business details')->assertSee(route('account.business.update'), false);

        $this->actingAs($user)->put(route('account.business.update'), ['company_name' => '', 'tin' => '12345', 'business_address' => ''])
            ->assertSessionHasErrorsIn('business', ['company_name', 'tin', 'business_address']);
        $this->assertSame(0, BusinessProfile::count());

        $this->actingAs($user)->put(route('account.business.update'), ['company_name' => 'Blue Lagoon Pvt Ltd', 'tin' => '1023456 gst 501', 'business_address' => 'M. Lagoon View, Malé'])
            ->assertRedirect(route('account.edit').'#business');
        $profile = BusinessProfile::where('user_id', $user->id)->sole();
        $this->assertSame('1023456GST501', $profile->tin); // stored normalised
        $this->assertSame('Blue Lagoon Pvt Ltd', $profile->company_name);

        // The TIN is optional
        $this->actingAs($user)->put(route('account.business.update'), ['company_name' => 'Blue Lagoon Pvt Ltd', 'tin' => '', 'business_address' => 'M. Lagoon View, Malé'])->assertSessionHasNoErrors();
        $this->assertNull($profile->fresh()->tin);

        $this->actingAs($user)->delete(route('account.business.destroy'))->assertRedirect(route('account.edit').'#business');
        $this->assertSame(0, BusinessProfile::count());

        auth()->logout();
        $this->put(route('account.business.update'), ['company_name' => 'X', 'business_address' => 'Y'])->assertRedirect(route('login'));
    }

    public function test_checkout_offers_the_profile_details_and_they_go_on_the_invoice(): void
    {
        $user = User::factory()->create(['name' => 'Aishath Shifa']);
        BusinessProfile::create(['user_id' => $user->id, 'company_name' => 'Blue Lagoon Pvt Ltd', 'tin' => '1023456GST501', 'business_address' => 'M. Lagoon View, Malé']);
        $this->cartFor($user);

        $page = $this->actingAs($user)->get(route('checkout'))->assertOk()
            ->assertSee('Buying for a business? Add details for the invoice')
            ->assertSee('value="Blue Lagoon Pvt Ltd"', false)->assertSee('value="1023456GST501"', false);
        // The block sits just before the payment section
        $this->assertLessThan(strpos($page->getContent(), 'Payment Method'), strpos($page->getContent(), 'data-business-details'));

        $this->actingAs($user)->post(route('orders.store'), $this->checkoutFields([
            'business_invoice' => '1', 'buyer_business_name' => 'Blue Lagoon Pvt Ltd', 'buyer_tin' => '1023456gst501', 'buyer_business_address' => 'M. Lagoon View, Malé',
        ]))->assertRedirect('https://pay.bml.test/txn_test');

        $order = Order::sole();
        $this->assertSame('Blue Lagoon Pvt Ltd', $order->buyer_business_name);
        $this->assertSame('1023456GST501', $order->buyer_tin);
        $this->assertSame('M. Lagoon View, Malé', $order->buyer_business_address);

        $order = $this->pay($order);
        $part = $order->sellerOrders()->sole();
        $this->actingAs($user)->get(route('orders.invoice', [$order, $part]))->assertOk()
            ->assertSee('data-buyer-business', false)->assertSee('Blue Lagoon Pvt Ltd')->assertSee('1023456GST501')
            ->assertSee('M. Lagoon View, Malé')->assertSee('Ordered by Aishath Shifa');
        $this->actingAs($this->shopUser)->get(route('seller.orders.invoice', $order))->assertOk()->assertSee('Blue Lagoon Pvt Ltd');
        $this->actingAs($this->staff('admin'))->get(route('admin.orders.show', $order))->assertOk()->assertSee('Blue Lagoon Pvt Ltd')->assertSee('1023456GST501');
    }

    public function test_incomplete_business_details_send_the_buyer_back_before_any_order_exists(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user);

        $this->actingAs($user)->from(route('checkout'))->post(route('orders.store'), $this->checkoutFields([
            'business_invoice' => '1', 'buyer_business_name' => '', 'buyer_tin' => '12345GST1', 'buyer_business_address' => '',
        ]))->assertRedirect(route('checkout'))->assertSessionHasErrors(['buyer_business_name', 'buyer_tin', 'buyer_business_address']);

        $this->assertSame(0, Order::count());
    }

    public function test_left_unticked_the_details_are_not_used(): void
    {
        $user = User::factory()->create();
        $this->cartFor($user);

        $this->actingAs($user)->post(route('orders.store'), $this->checkoutFields([
            'buyer_business_name' => 'Typed but not ticked', 'buyer_tin' => 'nonsense', 'buyer_business_address' => 'Somewhere',
        ]))->assertRedirect('https://pay.bml.test/txn_test');

        $order = Order::sole();
        $this->assertNull($order->buyer_business_name);
        $this->assertNull($order->buyer_tin);
        $this->actingAs($user)->get(route('checkout'))->assertRedirect(); // cart is empty now
    }

    public function test_a_guest_can_buy_for_a_business(): void
    {
        Setting::set(['guest_checkout_enabled' => 1]);
        $token = Str::random(40);
        $this->cartFor(null, ['session_id' => $token]);

        $this->withSession(['cart_token' => $token])->get(route('checkout.guest'))->assertOk()
            ->assertSee('Buying for a business? Add details for the invoice')->assertDontSee('Save business details to your profile');

        $this->withSession(['cart_token' => $token])->post(route('checkout.guest.store'), $this->checkoutFields([
            'guest_email' => 'buyer@example.com', 'guest_name' => 'Ahmed Visitor',
            'business_invoice' => '1', 'buyer_business_name' => 'Reef Traders', 'buyer_tin' => '', 'buyer_business_address' => 'G. Sunny, Malé',
        ]))->assertRedirect('https://pay.bml.test/txn_test');

        $order = $this->pay(Order::sole());
        $this->assertSame('Reef Traders', $order->buyer_business_name);
        $this->assertNull($order->buyer_tin);

        $part = $order->sellerOrders()->sole();
        $link = URL::signedRoute('guest.orders.invoice', ['order' => $order->id, 'token' => $order->guest_token, 'part' => $part->id]);
        $this->get($link)->assertOk()->assertSee('Reef Traders')->assertSee('G. Sunny, Malé')->assertSee('Ordered by Ahmed Visitor');
    }
}
