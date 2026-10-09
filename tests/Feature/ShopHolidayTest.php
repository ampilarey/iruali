<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\ErrorEvent;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SavedItem;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Holiday mode: a shop's products stay listed, but nobody can add them to a cart or order them until
 * the back-on date or until the shop switches it off. Orders placed before carry on as normal.
 */
class ShopHolidayTest extends TestCase
{
    use RefreshDatabase;

    protected User $shop;

    protected User $customer;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->enableBml();
        $this->travelTo(Carbon::parse('2026-10-09 10:00:00'));

        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $this->shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->customer = User::factory()->create();
        $this->category = Category::factory()->create(['status' => 'active']);
    }

    protected function product(array $attributes = []): Product
    {
        return Product::factory()->create(array_merge([
            'seller_id' => $this->shop->id, 'category_id' => $this->category->id,
            'is_active' => true, 'stock_quantity' => 5, 'price' => 100, 'compare_price' => null,
        ], $attributes));
    }

    protected function goOnHoliday(?string $until = '2026-10-15', ?string $message = 'Back after Eid, thank you for waiting!'): void
    {
        $this->shop->forceFill(['holiday_mode' => true, 'holiday_until' => $until, 'holiday_message' => $message, 'holiday_started_at' => now()])->save();
    }

    protected function cartWith(Product $product, int $quantity = 1): Cart
    {
        $cart = Cart::factory()->create(['user_id' => $this->customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity, 'price' => $product->price]);

        return $cart;
    }

    protected function shipping(): array
    {
        return [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234',
            'payment_method' => 'bml', 'agree_terms' => '1',
        ];
    }

    public function test_a_shop_switches_holiday_mode_on_and_off_in_its_settings(): void
    {
        $this->actingAs($this->shop)->get(route('seller.settings.holiday'))->assertOk()
            ->assertSee('Going away?')
            ->assertSee(route('seller.settings.holiday'), false)
            ->assertSee('data-holiday-status="off"', false);

        // The back-on date must be in the coming year, and after today
        foreach (['2026-10-09', '2026-10-01', '2027-10-10', 'soon'] as $bad) {
            $this->put(route('seller.settings.holiday.update'), ['on_holiday' => 1, 'holiday_until' => $bad])->assertSessionHasErrors('holiday_until');
        }
        $this->put(route('seller.settings.holiday.update'), ['on_holiday' => 1, 'holiday_message' => str_repeat('x', 501)])->assertSessionHasErrors('holiday_message');
        $this->assertFalse($this->shop->fresh()->holiday_mode);

        $this->put(route('seller.settings.holiday.update'), ['on_holiday' => 1, 'holiday_until' => '2026-10-15', 'holiday_message' => '  Back after Eid!  '])
            ->assertRedirect(route('seller.settings.holiday'))
            ->assertSessionHas('success', 'Holiday mode is on. Customers can see your products but cannot order until 15 Oct.');

        $shop = $this->shop->fresh();
        $this->assertTrue($shop->isOnHoliday());
        $this->assertSame('2026-10-15', $shop->holiday_until->toDateString());
        $this->assertSame('Back after Eid!', $shop->holiday_message);
        $this->assertNotNull($shop->holiday_started_at);
        $log = AuditLog::where('action', 'seller.holiday')->sole();
        $this->assertSame(['on' => true, 'until' => '2026-10-15'], $log->changes);
        $this->assertSame($shop->id, $log->subject_id);

        // Every Seller Centre page reminds the shop
        $this->get(route('seller.dashboard'))->assertOk()->assertSee('data-holiday-reminder', false)->assertSee('Holiday mode is on until 15 Oct');
        $this->get(route('seller.settings.holiday'))->assertOk()->assertSee('data-holiday-status="on"', false)->assertSee('Your shop is on holiday until 15 Oct.');

        // No back-on date: on until switched off
        $this->put(route('seller.settings.holiday.update'), ['on_holiday' => 1, 'holiday_until' => ''])->assertSessionHasNoErrors();
        $this->assertTrue($this->shop->fresh()->isOnHoliday());
        $this->assertNull($this->shop->fresh()->holiday_until);

        $this->put(route('seller.settings.holiday.update'), ['on_holiday' => 0, 'holiday_until' => '2026-10-01'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', 'Holiday mode is off. Customers can order from your shop again.');
        $this->assertFalse($this->shop->fresh()->isOnHoliday());
        $this->assertSame(['on' => false, 'until' => null], AuditLog::where('action', 'seller.holiday')->latest('id')->first()->changes);
        $this->get(route('seller.dashboard'))->assertOk()->assertDontSee('data-holiday-reminder', false);

        // Customers have no such setting
        $this->actingAs($this->customer)->put(route('seller.settings.holiday.update'), ['on_holiday' => 1])->assertForbidden();
    }

    public function test_the_product_page_says_until_when_with_the_message_and_disables_add_to_cart(): void
    {
        $product = $this->product();
        $url = route('products.show', $product);

        $this->get($url)->assertOk()->assertDontSee('data-shop-holiday', false)->assertDontSee('data-holiday', false);

        $this->goOnHoliday();
        $this->get($url)->assertOk()
            ->assertSee('data-shop-holiday', false)
            ->assertSee('This shop is on holiday until 15 Oct')
            ->assertSee('Back after Eid, thank you for waiting!')
            ->assertSee('Ordering opens again on 15 Oct.')
            ->assertSee('data-add-to-cart  disabled data-holiday', false)
            ->assertSee('form="buy-form" disabled', false)
            ->assertSee(route('wishlist.add', $product), false);

        $this->goOnHoliday(null, null);
        $this->get($url)->assertOk()->assertSee('This shop is on holiday for now')->assertSee('disabled data-holiday', false);

        // Search engines keep the page: it is still there, with the product
        $this->get($url)->assertOk()->assertSee($product->name);
    }

    public function test_adding_to_the_cart_is_refused_on_the_web_and_in_the_api(): void
    {
        $product = $this->product();
        $other = Product::factory()->create(['is_active' => true, 'stock_quantity' => 5, 'has_variants' => false]);
        $this->goOnHoliday();

        // Web: back to the product page with the reason, nothing in the cart, nothing reported as an error
        $this->actingAs($this->customer)->from(route('products.show', $product))
            ->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])
            ->assertRedirect(route('products.show', $product))
            ->assertSessionHas('notification', fn ($n) => $n['type'] === 'error' && str_contains($n['message'], 'Island Crafts is on holiday until 15 Oct'));
        $this->assertSame(0, CartItem::count());
        $this->assertSame(0, ErrorEvent::count());

        // Guests too
        $this->post(route('logout'));
        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect();
        $this->assertSame(0, CartItem::count());

        // "Add all to cart" and "Buy again" skip it and add the rest
        $this->actingAs($this->customer)->post(route('cart.addMany'), ['product_ids' => [$product->id, $other->id]])->assertRedirect(route('cart'));
        $this->assertSame([$other->id], CartItem::pluck('product_id')->all());
        CartItem::query()->delete();
        $order = Order::factory()->create(['user_id' => $this->customer->id, 'status' => 'delivered']);
        $order->items()->create(['product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
        $order->items()->create(['product_id' => $other->id, 'quantity' => 1, 'price' => 100]);
        $this->post(route('orders.buyAgain', $order))->assertRedirect(route('cart'));
        $this->assertSame([$other->id], CartItem::pluck('product_id')->all());

        // "Move to cart" from Saved for later is refused and the saved item stays
        $saved = SavedItem::create(['user_id' => $this->customer->id, 'product_id' => $product->id, 'quantity' => 1]);
        $this->from(route('cart'))->post(route('saved.moveToCart', $saved))->assertRedirect(route('cart'))->assertSessionHas('notification', fn ($n) => $n['type'] === 'error');
        $this->assertNotNull($saved->fresh());
        $this->assertSame(0, CartItem::where('product_id', $product->id)->count());

        // API: a JSON error in the usual shape
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/cart/add', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Island Crafts is on holiday until 15 Oct, so it is not taking orders. Add it to your wishlist to buy it when the shop is back.');
        $this->assertSame(0, CartItem::where('product_id', $product->id)->count());
        $this->getJson('/api/v1/products/'.$product->slug)->assertOk()->assertJsonPath('data.seller.on_holiday', true)->assertJsonPath('data.seller.holiday_until', '2026-10-15');
        $this->assertSame(0, ErrorEvent::count());
    }

    public function test_cart_and_checkout_refuse_items_from_a_shop_on_holiday_but_keep_them(): void
    {
        $product = $this->product();
        $this->cartWith($product, 2);
        $this->goOnHoliday();
        $message = '"'.$product->name.'" can\'t be ordered until 15 Oct: Island Crafts is on holiday. It stays in your cart.';

        $this->actingAs($this->customer)->get(route('cart'))->assertOk()
            ->assertSee('data-cart-holiday', false)
            ->assertSee($message)
            ->assertSee('Shop on holiday until 15 Oct');
        $this->get(route('checkout'))->assertOk()->assertSee('data-cart-holiday', false)->assertSee($message);

        // Placing the order (web) is refused with the reason; the cart keeps the item
        $this->from(route('checkout'))->post(route('orders.store'), $this->shipping())
            ->assertRedirect(route('checkout'))
            ->assertSessionHasErrors(['cart' => $message]);
        $this->assertSame(0, Order::count());
        $this->assertSame(2, CartItem::where('product_id', $product->id)->value('quantity'));

        // The service refuses too (the API and anything else that places orders)
        $result = app(OrderService::class)->createOrderFromCart($this->customer, $this->shipping());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Island Crafts is on holiday', $result['message']);
        Sanctum::actingAs($this->customer);
        $this->postJson('/api/v1/orders', $this->shipping())->assertStatus(400)->assertJsonPath('success', false);
        $this->assertSame(0, Order::count());
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(1, CartItem::count());
    }

    public function test_guest_checkout_refuses_items_from_a_shop_on_holiday(): void
    {
        Setting::set(['guest_checkout_enabled' => 1]);
        $product = $this->product();
        $token = Str::random(40);
        $cart = Cart::factory()->create(['user_id' => null, 'session_id' => $token, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
        $this->goOnHoliday();

        $this->withSession(['cart_token' => $token])->get(route('checkout.guest'))->assertOk()->assertSee('data-cart-holiday', false);
        $this->withSession(['cart_token' => $token])->post(route('checkout.guest.store'), $this->shipping() + ['guest_email' => 'visitor@example.com', 'guest_name' => 'Mariyam'])
            ->assertSessionHasErrors('cart');
        $this->assertSame(0, Order::count());
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_holiday_mode_ends_by_itself_on_the_back_on_date(): void
    {
        $product = $this->product();
        $this->goOnHoliday('2026-10-15');

        $this->travelTo(Carbon::parse('2026-10-14 23:59:00'));
        $this->assertTrue($this->shop->fresh()->isOnHoliday());

        // The back-on date is the first day the shop takes orders again
        $this->travelTo(Carbon::parse('2026-10-15 00:00:01'));
        $this->assertFalse($this->shop->fresh()->isOnHoliday());
        $this->get(route('products.show', $product))->assertOk()->assertDontSee('data-shop-holiday', false)->assertDontSee('disabled data-holiday', false);
        $this->actingAs($this->customer)->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 1])->assertRedirect(route('cart'));
        $this->assertSame(1, CartItem::count());
        $this->post(route('orders.store'), $this->shipping())->assertSessionHasNoErrors();
        $this->assertSame(1, Order::count());

        // The admin list no longer shows it either
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->get(route('admin.sellers'))->assertOk()->assertDontSee('data-seller-holiday', false);
    }

    public function test_orders_placed_before_the_holiday_are_unaffected(): void
    {
        $product = $this->product();
        $this->cartWith($product);
        $result = app(OrderService::class)->createOrderFromCart($this->customer, $this->shipping());
        $this->assertTrue($result['success'], $result['message']);
        $order = $result['order'];

        $this->goOnHoliday();

        // The customer still sees the order and can pay for it
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk();
        $this->post(route('payments.bml.pay', $order))->assertRedirect('https://pay.bml.test/txn_test');

        // The shop still sends it
        $this->actingAs($this->shop)->get(route('seller.orders.show', $order))->assertOk();
        $this->post(route('seller.orders.status', $order), ['status' => 'processing'])->assertSessionHas('success');
        $this->post(route('seller.orders.status', $order), ['status' => 'shipped'])->assertSessionHas('success');
        $this->assertSame('shipped', SellerOrder::where('order_id', $order->id)->value('status'));
    }

    public function test_the_shop_page_catalog_cards_and_admin_sellers_list_show_the_holiday(): void
    {
        $this->product(['name' => 'Coir Rope Basket']);
        $this->goOnHoliday();

        $this->get(route('sellers.show', $this->shop))->assertOk()
            ->assertSee('data-shop-holiday', false)
            ->assertSee('This shop is on holiday until 15 Oct')
            ->assertSee('Back after Eid, thank you for waiting!')
            ->assertSee('You can browse as usual; ordering opens again on 15 Oct.')
            ->assertSee('Coir Rope Basket');

        // Cards: still listed, with an "On holiday" label and no add-to-cart button
        $this->get(route('shop'))->assertOk()
            ->assertSee('Coir Rope Basket')
            ->assertSee('data-holiday-label', false)
            ->assertSee('data-holiday-details', false)
            ->assertDontSee('action="'.route('cart.add').'"', false);
        $this->get(route('shop', ['view' => 'list']))->assertOk()->assertSee('data-holiday-label', false)->assertDontSee('action="'.route('cart.add').'"', false);

        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->get(route('admin.sellers'))->assertOk()
            ->assertSee('data-seller-holiday', false)
            ->assertSee('On holiday until 15 Oct');
    }

    public function test_dhivehi_shoppers_read_the_holiday_in_dhivehi(): void
    {
        $product = $this->product();
        $this->goOnHoliday();

        $this->withSession(['locale' => 'dv'])->get(route('products.show', $product))->assertOk()
            ->assertSee(__('This shop is on holiday until :date', ['date' => '15 '.Carbon::parse('2026-10-15')->locale('dv')->translatedFormat('M')], 'dv'))
            ->assertDontSee('This shop is on holiday until');
    }
}
