<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\BmlConnect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * BML_FAKE=1: checkout works without BML (browser tests, local dev); never in production.
 */
class BmlFakeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.bml.api_key' => null, 'services.bml.fake' => true]);
        Http::fake();
        Notification::fake();
    }

    public function test_fake_mode_enables_card_payment_without_an_api_key_and_never_calls_bml(): void
    {
        $bml = app(BmlConnect::class);

        $this->assertTrue($bml->isFake());
        $this->assertTrue($bml->enabled());

        $remote = $bml->createTransaction(['localId' => 'ORD1P1', 'redirectUrl' => 'http://localhost/payments/bml/return/1']);
        $this->assertSame('FAKEORD1P1', $remote['id']);
        $this->assertSame('INITIATED', $remote['state']);
        $this->assertStringStartsWith('http://localhost/payments/bml/return/1?transactionId=FAKEORD1P1', $remote['url']);

        $this->assertSame('CREATED', $bml->getTransaction('FAKEORD1P1')['state']);
        Http::assertNothingSent();
    }

    public function test_fake_mode_is_ignored_in_production(): void
    {
        $this->app['env'] = 'production';

        $bml = app(BmlConnect::class);
        $this->assertFalse($bml->isFake());
        $this->assertFalse($bml->enabled(), 'without an API key card payment stays off in production');

        $this->app['env'] = 'testing';
    }

    public function test_checkout_with_fake_bml_lands_on_the_order_page_with_pay_now_and_the_order_stays_unpaid(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 120, 'stock_quantity' => 5, 'is_active' => true]);
        $cart = Cart::factory()->create(['user_id' => $customer->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 120]);

        $this->actingAs($customer)->get('/checkout')->assertOk()->assertSee('Card payment (BML)');

        $response = $this->actingAs($customer)->post('/orders', [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_country' => 'Maldives', 'shipping_phone' => '7771234', 'delivery_zone' => 'islands',
            'payment_method' => 'bml', 'agree_terms' => '1',
        ]);

        $order = Order::latest('id')->firstOrFail();
        $response->assertRedirect();
        $this->assertStringContainsString('/payments/bml/return/'.$order->id, $response->headers->get('Location'));

        // Following the fake "payment page" comes straight back to the order page, still unpaid, with "Pay now"
        $this->actingAs($customer)->get($response->headers->get('Location'))->assertRedirect(route('orders.show', $order));
        $this->actingAs($customer)->get(route('orders.show', $order))->assertOk()->assertSee('/orders/'.$order->id.'/pay');
        $this->assertSame('unpaid', $order->fresh()->payment_status);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertSame(4, $product->fresh()->stock_quantity);
        Http::assertNothingSent();
    }
}
