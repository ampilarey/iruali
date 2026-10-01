<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\User;
use App\Notifications\BackInStock;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderVariantsTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $shop;

    protected Product $product;

    protected ProductVariant $medium;

    protected ProductVariant $large;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->customer = User::factory()->create();
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Threads']);
        $this->shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        $this->product = Product::factory()->create(['seller_id' => $this->shop->id, 'price' => 100, 'has_variants' => true, 'name' => ['en' => 'Hoodie']]);
        $this->medium = ProductVariant::factory()->for($this->product)->attributes(['Size' => 'M'])->stock(2)->create(['sku' => 'HOOD-M']);
        $this->large = ProductVariant::factory()->for($this->product)->attributes(['Size' => 'L'])->stock(10)->priced(120)->create(['sku' => 'HOOD-L']);
        $this->product->refresh();
    }

    protected function shipping(): array
    {
        return [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Male', 'shipping_state' => 'Male',
            'shipping_zip' => '20000', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml',
        ];
    }

    protected function cartWith(array $lines, ?User $user = null): Cart
    {
        $cart = Cart::factory()->create(['user_id' => ($user ?? $this->customer)->id, 'status' => 'active']);
        foreach ($lines as [$variant, $qty]) {
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $this->product->id, 'product_variant_id' => $variant?->id, 'quantity' => $qty, 'price' => 100]);
        }

        return $cart;
    }

    public function test_order_items_snapshot_the_variant_and_stock_comes_off_the_variant_row(): void
    {
        $this->cartWith([[$this->large, 3], [$this->medium, 1]]);

        $result = app(OrderService::class)->createOrderFromCart($this->customer, $this->shipping());
        $this->assertTrue($result['success'], $result['message'] ?? '');

        $order = $result['order'];
        $line = $order->items()->where('product_variant_id', $this->large->id)->firstOrFail();
        $this->assertSame('L', $line->variant_name);
        $this->assertSame('HOOD-L', $line->variant_sku);
        $this->assertSame(120.0, (float) $line->price);
        $this->assertSame('Hoodie – L', $line->displayName());
        $this->assertSame(100.0, (float) $order->items()->where('product_variant_id', $this->medium->id)->value('price'));

        $this->assertSame(7, $this->large->fresh()->stock_quantity);
        $this->assertSame(1, $this->medium->fresh()->stock_quantity);
        $this->assertSame(8, (int) $this->product->fresh()->stock_quantity);
        $this->assertEquals(3 * 120 + 100 + $order->shipping_amount, $order->total_amount);

        // Everyone sees which variant was bought
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()->assertSee('HOOD-L');
        $this->get(route('orders.receipt', $order))->assertOk()->assertSee('Hoodie – L');
        $this->actingAs($this->shop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('Hoodie – L');
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Hoodie – M');
    }

    public function test_overselling_one_variant_is_blocked_even_when_the_product_total_would_cover_it(): void
    {
        $this->cartWith([[$this->medium, 3]]); // product has 12 in total, the medium only 2

        $result = app(OrderService::class)->createOrderFromCart($this->customer, $this->shipping());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not enough stock', $result['message']);
        $this->assertSame(2, $this->medium->fresh()->stock_quantity);
        $this->assertSame(0, Order::count());

        // A line without a variant on a product sold in variants is refused too
        $this->customer->carts()->delete();
        $this->cartWith([[null, 1]]);
        $result = app(OrderService::class)->createOrderFromCart($this->customer, $this->shipping());
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('needs an option', $result['message']);

        // The last two mediums go to the first checkout; the second one loses the race
        $this->customer->carts()->delete();
        $this->cartWith([[$this->medium, 2]]);
        $other = User::factory()->create();
        $this->cartWith([[$this->medium, 2]], $other);
        $this->assertTrue(app(OrderService::class)->createOrderFromCart($this->customer, $this->shipping())['success']);
        $this->assertFalse(app(OrderService::class)->createOrderFromCart($other, $this->shipping())['success']);
        $this->assertSame(0, $this->medium->fresh()->stock_quantity);
    }

    public function test_cancelling_restores_the_variant_row(): void
    {
        $this->cartWith([[$this->large, 4]]);
        $order = app(OrderService::class)->createOrderFromCart($this->customer, $this->shipping())['order'];
        $this->assertSame(6, $this->large->fresh()->stock_quantity);

        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order, 'cancelled'));

        $this->assertSame(10, $this->large->fresh()->stock_quantity);
        $this->assertSame(2, $this->medium->fresh()->stock_quantity);
        $this->assertSame(12, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_an_approved_return_restocks_the_variant(): void
    {
        Storage::fake('local');
        Setting::set(['return_window_days' => 7, 'contact_email' => 'hello@iruali.mv']);
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->cartWith([[$this->large, 2]]);
        $order = app(OrderService::class)->createOrderFromCart($this->customer, $this->shipping())['order'];
        $this->actingAs($admin);
        foreach (['processing', 'shipped', 'delivered'] as $status) {
            $this->post(route('admin.orders.status', $order), ['status' => $status]);
        }
        app(PaymentService::class)->confirm($order->fresh());

        $part = $order->sellerOrders()->firstOrFail();
        $item = $part->items()->firstOrFail();
        $this->actingAs($this->customer)->post(route('orders.returns.store', [$order, $part]), [
            'quantities' => [$item->id => 1], 'reason' => 'change_of_mind', 'details' => 'Too big.',
        ])->assertSessionHasNoErrors();

        $return = ReturnRequest::sole();
        $this->actingAs($admin)->get(route('admin.returns.show', $return))->assertOk()->assertSee('Hoodie – L');
        $this->post(route('admin.returns.approve', $return), ['refund_amount' => 120, 'restock' => 1])->assertSessionHas('success');

        $this->assertSame(9, $this->large->fresh()->stock_quantity);
        $this->assertSame(11, (int) $this->product->fresh()->stock_quantity);
    }

    public function test_back_in_stock_alerts_fire_for_the_restocked_variant(): void
    {
        $this->medium->update(['stock_quantity' => 0]);
        StockAlert::create(['product_id' => $this->product->id, 'product_variant_id' => $this->medium->id, 'email' => 'medium@example.com', 'locale' => 'en']);
        StockAlert::create(['product_id' => $this->product->id, 'product_variant_id' => $this->large->id, 'email' => 'large@example.com', 'locale' => 'en']);

        $this->large->update(['stock_quantity' => 11]); // never went out, nothing to say
        Notification::assertNothingSent();

        $this->medium->update(['stock_quantity' => 4]);
        Notification::assertSentTimes(BackInStock::class, 1);
        Notification::assertSentTo(new AnonymousNotifiable, BackInStock::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'medium@example.com' && $n->variant?->id === $this->medium->id);
        $this->assertNotNull(StockAlert::where('email', 'medium@example.com')->first()->notified_at);
        $this->assertNull(StockAlert::where('email', 'large@example.com')->first()->notified_at);

        // "Any option" alerts go out when the product as a whole comes back
        $this->medium->update(['stock_quantity' => 0]);
        $this->large->update(['stock_quantity' => 0]);
        StockAlert::create(['product_id' => $this->product->id, 'email' => 'any@example.com', 'locale' => 'en']);
        $this->assertSame(0, (int) $this->product->fresh()->stock_quantity);
        $this->large->update(['stock_quantity' => 1]);
        Notification::assertSentTo(new AnonymousNotifiable, BackInStock::class, fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'any@example.com' && $n->variant === null);
    }
}
