<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\OrderingBackRequest;
use App\Models\Product;
use App\Models\SellerDeliverySetting;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\OrderingBack;
use App\Services\OrderService;
use App\Support\OrderRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\Support\DeliveryFixtures;
use Tests\TestCase;

/**
 * Minimum orders (site and shop), the small-order fee, pausing ordering with "tell me when it's
 * back", and shop opening hours.
 */
class OrderRulesTest extends TestCase
{
    use DeliveryFixtures, RefreshDatabase;

    protected User $customer;

    protected User $seller;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 50, 'delivery_fee_greater_male' => 30]);
        $this->customer = User::factory()->create();
        $this->seller = User::factory()->create();
    }

    protected function cartWorth(float $price, int $quantity = 1): Cart
    {
        $product = Product::factory()->create(['price' => $price, 'sale_price' => null, 'stock_quantity' => 50, 'category_id' => Category::factory()->create()->id, 'seller_id' => $this->seller->id]);
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity, 'price' => $price]);

        return $cart->fresh();
    }

    protected function order(array $extra = []): array
    {
        return app(OrderService::class)->createOrderFromCart($this->customer, $extra + [
            'shipping_address' => 'Blue Villa', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu', 'shipping_zip' => '',
            'shipping_phone' => '7771234', 'shipping_country' => 'Maldives',
        ]);
    }

    public function test_site_and_shop_minimums_stop_small_orders(): void
    {
        $cart = $this->cartWorth(80);
        Setting::set(['min_order_amount' => 100]);
        $result = $this->order();
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('minimum order', $result['message']);
        $this->actingAs($this->customer)->get(route('checkout'))->assertRedirect(route('cart'));
        $this->get(route('cart'))->assertSee('data-minimum-order', false);

        Setting::set(['min_order_amount' => 0]);
        SellerDeliverySetting::updateOrCreate(['seller_id' => $this->seller->id], ['min_order_amount' => 200]);
        $this->assertStringContainsString('minimum order of', (string) OrderRules::minimumProblem($cart, 80));
        $this->assertFalse($this->order()['success']);

        SellerDeliverySetting::where('seller_id', $this->seller->id)->update(['min_order_amount' => null]);
        $this->assertTrue($this->order()['success']);
    }

    public function test_the_small_order_fee_is_added_to_delivery(): void
    {
        $this->cartWorth(80);
        Setting::set(['small_order_fee' => 15, 'small_order_threshold' => 100]);

        $this->actingAs($this->customer)->get(route('checkout'))->assertOk()->assertSee('data-small-order-fee', false);
        $order = $this->order()['order'];
        $this->assertEqualsWithDelta(15, (float) $order->small_order_fee, 0.001);
        $this->assertEqualsWithDelta((float) $order->shipping_amount - 15, $order->deliveryCharge(), 0.001);
        $this->assertEqualsWithDelta(80 + (float) $order->shipping_amount, (float) $order->total_amount, 0.001);
        $this->get(route('orders.show', $order))->assertSee(__('Small order fee'));

        $this->assertSame(0.0, OrderRules::smallOrderFee(100));
    }

    public function test_pausing_ordering_with_a_window_and_tell_me_when_back(): void
    {
        Notification::fake();
        $this->cartWorth(150);
        Setting::set(['ordering_pause_mode' => 'all', 'ordering_pause_message' => 'Closed for Eid.']);

        $this->actingAs($this->customer)->get(route('home'))->assertSee('Closed for Eid.')->assertSee(route('ordering.notify'));
        $this->assertSame('Closed for Eid.', $this->order()['message']);
        $this->post(route('ordering.notify'), ['email' => 'Fan@Example.com'])->assertSessionHas('success');
        $this->post(route('ordering.notify'), ['email' => 'fan@example.com']);
        $this->assertSame(1, OrderingBackRequest::count());

        $this->artisan('ordering:notify-back');
        Notification::assertNothingSent(); // still paused

        // A planned window that has ended counts as open again
        Setting::set(['ordering_pause_from' => now()->subDays(2)->format('Y-m-d\TH:i'), 'ordering_pause_until' => now()->subHour()->format('Y-m-d\TH:i')]);
        $this->assertNull(OrderRules::pause());
        $this->artisan('ordering:notify-back')->assertSuccessful();
        Notification::assertSentOnDemand(OrderingBack::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'fan@example.com');
        $this->assertNotNull(OrderingBackRequest::sole()->notified_at);
        $this->assertTrue($this->order()['success']);
    }

    public function test_pausing_delivery_or_cash_on_delivery_only(): void
    {
        $this->cartWorth(150);
        Setting::set(['ordering_pause_mode' => 'delivery']);
        $this->assertStringContainsString('Delivery is paused', $this->order()['message']);

        Setting::set(['ordering_pause_mode' => 'cod']);
        $this->assertNull(OrderRules::pauseProblem(['payment_method' => 'bml', 'fulfilment_parts' => []]));
        $this->assertNotNull(OrderRules::pauseProblem(['payment_method' => 'cod', 'fulfilment_parts' => []]));
    }

    public function test_opening_hours_move_the_arrival_estimate_and_show_on_the_product(): void
    {
        // Open Sunday to Thursday 09:00-17:00; the test runs on a Friday afternoon
        Carbon::setTestNow(Carbon::parse('2026-10-23 15:00', 'Indian/Maldives'));
        $hours = collect([7, 1, 2, 3, 4])->mapWithKeys(fn ($d) => [(string) $d => ['09:00', '17:00']])->all();
        $setting = SellerDeliverySetting::updateOrCreate(['seller_id' => $this->seller->id], ['opening_hours' => $hours, 'ships_within_days' => 0]);

        $this->assertSame('2026-10-25', $setting->nextWorkingDay()->toDateString()); // Sunday
        $this->assertSame(__('Closed now, opens :day :time', ['day' => 'Sunday', 'time' => '09:00']), $setting->hoursText());

        Carbon::setTestNow(Carbon::parse('2026-10-25 10:00', 'Indian/Maldives'));
        $this->assertSame(__('Open today :from–:until', ['from' => '09:00', 'until' => '17:00']), $setting->hoursText());
        $this->assertTrue($setting->nextWorkingDay()->isSameDay(now()));

        Carbon::setTestNow();

        // The shop saves its hours and minimum from Delivery & pickup; a day with one time only is refused
        $shop = $this->shop('Reef Goods');
        $base = ['ships_within_days' => 1, 'pickup_enabled' => 0];
        $this->actingAs($shop)->get(route('seller.settings.delivery'))->assertOk()->assertSee('data-opening-hours', false);
        $this->put(route('seller.settings.delivery.update'), $base + ['hours' => ['6' => ['09:00', '']]])->assertSessionHasErrors('hours');
        $this->put(route('seller.settings.delivery.update'), $base + ['min_order_amount' => 150, 'hours' => ['6' => ['09:00', '18:00'], '7' => ['', '']]])->assertSessionHas('success');
        $saved = SellerDeliverySetting::for($shop->id);
        $this->assertSame(['6' => ['09:00', '18:00']], $saved->opening_hours);
        $this->assertEqualsWithDelta(150, $saved->minimumOrder(), 0.001);
    }
}
