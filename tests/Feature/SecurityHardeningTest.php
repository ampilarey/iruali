<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_description_is_escaped(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'description' => ['en' => '<script>alert("x")</script>Nice mat'],
        ]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('Nice mat');
    }

    public function test_product_name_cannot_break_out_of_the_json_ld_block(): void
    {
        $product = Product::factory()->create([
            'is_active' => true,
            'name' => ['en' => 'Mat</script><script>alert(1)</script>'],
        ]);

        $this->get(route('products.show', $product->slug))
            ->assertOk()
            ->assertDontSee('</script><script>alert(1)', false);
    }

    public function test_users_cannot_change_or_remove_other_peoples_cart_items(): void
    {
        $owner = User::factory()->create();
        $cart = Cart::factory()->create(['user_id' => $owner->id, 'status' => 'active']);
        $item = CartItem::factory()->create(['cart_id' => $cart->id, 'quantity' => 1]);

        $this->actingAs(User::factory()->create());
        $this->put(route('cart.update', $item), ['quantity' => 9])->assertForbidden();
        $this->delete(route('cart.remove', $item))->assertForbidden();

        $this->assertSame(1, $item->fresh()->quantity);
    }

    public function test_order_status_page_needs_a_signed_link(): void
    {
        $order = Order::factory()->create();

        // Guessing ids doesn't work
        $this->get('/track/order/'.$order->id)->assertForbidden();

        // Submitting the order number gives a signed link that works
        $response = $this->post(route('order.track.submit'), ['order_code' => $order->order_number]);
        $response->assertRedirect();
        $this->get($response->headers->get('Location'))->assertOk()->assertSee($order->order_number);

        $this->post(route('order.track.submit'), ['order_code' => 'ORD-NOPE'])->assertSessionHasErrors('order_code');
    }

    public function test_login_starts_a_new_session(): void
    {
        // Laravel's session guard migrates the session id on login; keep it that way.
        $user = User::factory()->create(['password' => bcrypt('Secret#123'), 'status' => 'active', 'is_active' => true]);

        $this->get('/login');
        $before = session()->getId();

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret#123']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotSame($before, session()->getId());
    }

    public function test_two_factor_users_are_not_signed_in_before_the_code(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('Secret#123'), 'status' => 'active', 'is_active' => true,
            'two_factor_enabled' => true, 'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
        ]);

        $this->post('/login', ['email' => $user->email, 'password' => 'Secret#123'])
            ->assertRedirect(route('2fa.show'));

        $this->assertGuest();
        $this->get('/account')->assertRedirect('/login');
        $this->get(route('2fa.show'))->assertOk();
    }

    public function test_order_numbers_are_random(): void
    {
        $method = new \ReflectionMethod(OrderService::class, 'generateOrderNumber');
        $method->setAccessible(true);
        $service = app(OrderService::class);

        $a = $method->invoke($service);
        $b = $method->invoke($service);

        $this->assertMatchesRegularExpression('/^ORD-[A-Z0-9]{10}$/', $a);
        $this->assertNotSame(substr($a, 0, 10), substr($b, 0, 10));
    }
}
