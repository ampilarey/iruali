<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LowStockDigest;
use App\Notifications\NewSellerOrder;
use App\Notifications\PayoutPaid;
use App\Notifications\ReturnRequested;
use App\Services\OrderService;
use App\Services\ReturnService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SellerNotificationsTest extends TestCase
{
    use RefreshDatabase;

    protected function seller(string $name = 'Island Crafts'): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    protected function placeOrder(User $customer, Product $product)
    {
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => $product->price]);

        $result = app(OrderService::class)->createOrderFromCart($customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'payment_method' => 'bml',
        ]);
        $this->assertTrue($result['success'], $result['message'] ?? '');

        return $result['order'];
    }

    public function test_shop_notifications_are_queued(): void
    {
        foreach ([NewSellerOrder::class, ReturnRequested::class, PayoutPaid::class, LowStockDigest::class] as $class) {
            $this->assertContains(ShouldQueue::class, class_implements($class), "{$class} should be queued");
        }
    }

    public function test_seller_manages_notification_preferences(): void
    {
        $seller = $this->seller();
        $this->assertSame(['new_order' => true, 'return' => true, 'payout' => true, 'low_stock' => true, 'quotes' => true], $seller->notificationPreferences());

        $this->actingAs($seller)->get('/seller/settings/notifications')->assertOk()
            ->assertSee('New order')->assertSee('Return requested')->assertSee('Payout sent')->assertSee('Low stock (daily)');

        $this->put('/seller/settings/notifications', ['new_order' => 1, 'return' => 0, 'payout' => 1, 'low_stock' => 0])->assertRedirect('/seller/settings/notifications');

        $seller->refresh();
        $this->assertTrue($seller->wantsNotification('new_order'));
        $this->assertFalse($seller->wantsNotification('return'));
        $this->assertFalse($seller->wantsNotification('low_stock'));
        $this->get('/seller/settings/notifications')->assertOk();

        $this->actingAs(User::factory()->create())->get('/seller/settings/notifications')->assertForbidden();
    }

    public function test_new_order_and_return_emails_respect_the_shop_preferences(): void
    {
        Notification::fake();
        $customer = User::factory()->create();
        $wants = $this->seller('Island Crafts');
        $silent = $this->seller('Reefline Marine');
        $silent->forceFill(['notification_preferences' => ['new_order' => false, 'return' => false]])->save();

        $a = Product::factory()->create(['seller_id' => $wants->id, 'price' => 100, 'stock_quantity' => 5]);
        $b = Product::factory()->create(['seller_id' => $silent->id, 'price' => 100, 'stock_quantity' => 5]);

        $orderA = $this->placeOrder($customer, $a);
        $orderB = $this->placeOrder($customer, $b);
        Notification::assertSentTo($wants, NewSellerOrder::class, fn ($n) => $n->order->is($orderA));
        Notification::assertNotSentTo($silent, NewSellerOrder::class);

        // Returns: deliver each part, then the customer asks for a return
        foreach ([[$orderA, $wants], [$orderB, $silent]] as [$order, $shop]) {
            $order->update(['status' => 'delivered']);
            $part = $order->sellerOrders()->where('seller_id', $shop->id)->firstOrFail();
            $part->update(['status' => 'delivered', 'delivered_at' => now()]);
            $item = $part->items()->firstOrFail();
            $this->assertNotNull(app(ReturnService::class)->request($part, $customer, [$item->id => 1], 'faulty', 'Broken', null));
        }
        $this->assertSame(2, ReturnRequest::count());
        Notification::assertSentTo($wants, ReturnRequested::class);
        Notification::assertNotSentTo($silent, ReturnRequested::class);
    }

    public function test_low_stock_digest_lists_products_and_variants_at_or_below_their_threshold(): void
    {
        Notification::fake();
        $shop = $this->seller('Island Crafts');
        $fine = $this->seller('Reefline Marine');
        $off = $this->seller('Atoll Spices');
        $off->forceFill(['notification_preferences' => ['low_stock' => false]])->save();

        Product::factory()->create(['seller_id' => $shop->id, 'name' => ['en' => 'Lacquer Box'], 'sku' => 'LB-1', 'stock_quantity' => 2, 'reorder_point' => 0]); // default threshold 3
        Product::factory()->create(['seller_id' => $shop->id, 'name' => ['en' => 'Coir Rope'], 'sku' => 'CR-1', 'stock_quantity' => 5, 'reorder_point' => 5]);
        Product::factory()->create(['seller_id' => $shop->id, 'name' => ['en' => 'Plenty'], 'stock_quantity' => 50, 'reorder_point' => 5]);
        Product::factory()->create(['seller_id' => $shop->id, 'name' => ['en' => 'Inactive'], 'stock_quantity' => 0, 'is_active' => false]);
        $shirt = Product::factory()->create(['seller_id' => $shop->id, 'name' => ['en' => 'Shirt'], 'stock_quantity' => 100, 'reorder_point' => 2]);
        $shirt->variants()->create(['name' => ['en' => 'Large'], 'type' => 'size', 'sku' => 'SH-L', 'price_adjustment' => 0, 'stock_quantity' => 1, 'is_active' => true]);
        $shirt->variants()->create(['name' => ['en' => 'Small'], 'type' => 'size', 'sku' => 'SH-S', 'price_adjustment' => 0, 'stock_quantity' => 9, 'is_active' => true]);

        Product::factory()->create(['seller_id' => $fine->id, 'stock_quantity' => 50]);
        Product::factory()->create(['seller_id' => $off->id, 'stock_quantity' => 0]);

        $this->artisan('seller:low-stock-digest')->assertSuccessful();

        Notification::assertSentTo($shop, LowStockDigest::class, function (LowStockDigest $n) use ($shop) {
            $names = collect($n->lines)->pluck('name')->all();
            $this->assertSame(['Lacquer Box', 'Coir Rope', 'Shirt – Large'], $names);
            $this->assertSame([3, 5, 2], collect($n->lines)->pluck('threshold')->all());
            $mail = $n->toMail($shop)->render();
            $this->assertStringContainsString('Lacquer Box (LB-1)', $mail);
            $this->assertStringContainsString('2 left, low-stock level 3', $mail);

            return true;
        });
        Notification::assertNotSentTo($fine, LowStockDigest::class);
        Notification::assertNotSentTo($off, LowStockDigest::class);

        // Scheduled every morning at 08:00 Maldives time
        $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command ?? '', 'seller:low-stock-digest'));
        $this->assertNotNull($event);
        $this->assertSame('0 8 * * *', $event->expression);
        $this->assertSame('Indian/Maldives', (string) $event->timezone);
    }
}
