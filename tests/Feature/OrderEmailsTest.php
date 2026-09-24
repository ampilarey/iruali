<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\NewSellerOrder;
use App\Notifications\OrderPlaced;
use App\Notifications\OrderStatusChanged;
use App\Notifications\PaymentSlipSubmitted;
use App\Notifications\PaymentUpdated;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function placeOrderFromTwoShops(User $customer, User $shopA, User $shopB): Order
    {
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        foreach ([$shopA, $shopB] as $seller) {
            $product = Product::factory()->create(['seller_id' => $seller->id, 'price' => 100, 'stock_quantity' => 5]);
            CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
        }

        $result = app(OrderService::class)->createOrderFromCart($customer, [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Malé', 'shipping_state' => 'Kaafu',
            'shipping_zip' => '20026', 'shipping_country' => 'Maldives',
        ]);
        $this->assertTrue($result['success']);

        return $result['order'];
    }

    public function test_customer_and_each_seller_are_emailed_when_an_order_is_placed(): void
    {
        Notification::fake();
        [$customer, $shopA, $shopB] = User::factory()->count(3)->create()->all();

        $order = $this->placeOrderFromTwoShops($customer, $shopA, $shopB);

        Notification::assertSentTo($customer, OrderPlaced::class, fn ($n) => $n->order->is($order));
        Notification::assertSentTo($shopA, NewSellerOrder::class, fn ($n) => $n->items->count() === 1);
        Notification::assertSentTo($shopB, NewSellerOrder::class);
    }

    public function test_customer_is_emailed_when_the_status_changes(): void
    {
        Notification::fake();
        [$customer, $shopA, $shopB] = User::factory()->count(3)->create()->all();
        $order = $this->placeOrderFromTwoShops($customer, $shopA, $shopB);

        app(OrderService::class)->updateOrderStatus($order, 'processing');
        app(OrderService::class)->updateOrderStatus($order->fresh(), 'shipped');

        Notification::assertSentToTimes($customer, OrderStatusChanged::class, 2);
    }

    public function test_payment_emails_go_to_customer_and_store(): void
    {
        Notification::fake();
        Storage::fake('local');
        Setting::set(['contact_email' => 'orders@iruali.mv']);
        $customer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $customer->id, 'payment_method' => 'bank_transfer', 'payment_status' => 'unpaid', 'status' => 'pending']);

        app(PaymentService::class)->submitSlip($order, UploadedFile::fake()->image('slip.jpg'));
        Notification::assertSentTo(new AnonymousNotifiable, PaymentSlipSubmitted::class,
            fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'orders@iruali.mv');

        app(PaymentService::class)->confirm($order);
        Notification::assertSentTo($customer, PaymentUpdated::class);
    }

    public function test_a_mail_failure_does_not_stop_the_order(): void
    {
        config(['mail.default' => 'does-not-exist']);
        [$customer, $shopA, $shopB] = User::factory()->count(3)->create()->all();

        $order = $this->placeOrderFromTwoShops($customer, $shopA, $shopB);

        $this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'pending']);
        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order, 'processing'));
    }

    public function test_emails_use_the_customers_language(): void
    {
        $customer = User::factory()->create(['preferred_language' => 'dv']);
        $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'shipped', 'order_number' => 'ORD-1']);

        $this->assertSame('dv', $customer->preferredLocale());

        app()->setLocale('dv');
        $mail = (new OrderStatusChanged($order))->toMail($customer);
        $this->assertSame('އޯޑަރ ORD-1 ފޮނުވައިފި', $mail->subject);
    }

    public function test_switching_language_is_remembered_for_emails(): void
    {
        $customer = User::factory()->create(['preferred_language' => 'en']);

        $this->actingAs($customer)->post(route('locale.switch'), ['locale' => 'dv']);

        $this->assertSame('dv', $customer->fresh()->preferred_language);
    }
}
