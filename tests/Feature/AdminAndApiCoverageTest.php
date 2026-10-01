<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Wishlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Behavioural coverage for the money and admin routes that had none:
 * admin voucher CRUD, product approval, seller suspension, the JSON API
 * (orders, wishlist, featured / on-sale lists) and loyalty-point redemption.
 */
class AdminAndApiCoverageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected User $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['services.bml.api_key' => 'test-key', 'services.bml.environment' => 'sandbox']);

        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
        $this->customer = User::factory()->create();
    }

    protected function seller(string $name = 'Island Crafts'): User
    {
        $user = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => $name]);
        $user->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);

        return $user;
    }

    /**
     * An active cart for the customer with one MVR 100 product in it.
     */
    protected function fillCart(User $user, int $quantity = 2): Product
    {
        $product = Product::factory()->create(['seller_id' => $this->seller()->id, 'price' => 100, 'stock_quantity' => 10]);
        $cart = Cart::factory()->create(['user_id' => $user->id, 'status' => 'active']);
        CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => $quantity, 'price' => 100]);

        return $product;
    }

    protected function voucherInput(array $overrides = []): array
    {
        return array_merge([
            'code' => 'summer10',
            'type' => 'percent',
            'amount' => 10,
            'min_order' => 50,
            'max_uses' => 100,
            'valid_from' => now()->toDateString(),
            'valid_until' => now()->addMonth()->toDateString(),
            'is_active' => 1,
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Admin vouchers
    // ------------------------------------------------------------------

    public function test_admin_can_create_edit_and_delete_vouchers(): void
    {
        $this->actingAs($this->admin);

        $this->get(route('admin.vouchers.index'))->assertOk();
        $this->get(route('admin.vouchers.create'))->assertOk();

        $this->post(route('admin.vouchers.store'), $this->voucherInput())
            ->assertRedirect(route('admin.vouchers.index'))
            ->assertSessionHas('success');

        // The code is stored upper-cased and the voucher is usable
        $voucher = Voucher::where('code', 'SUMMER10')->first();
        $this->assertNotNull($voucher);
        $this->assertSame('percent', $voucher->type);
        $this->assertEquals(10, $voucher->amount);
        $this->assertTrue($voucher->is_active);
        $this->get(route('admin.vouchers.index'))->assertOk()->assertSee('SUMMER10');

        $this->get(route('admin.vouchers.edit', $voucher))->assertOk();
        $this->put(route('admin.vouchers.update', $voucher), $this->voucherInput(['amount' => 25, 'type' => 'fixed', 'is_active' => 0]))
            ->assertRedirect(route('admin.vouchers.index'));
        $voucher->refresh();
        $this->assertSame('fixed', $voucher->type);
        $this->assertEquals(25, $voucher->amount);
        $this->assertFalse($voucher->is_active);

        $this->delete(route('admin.vouchers.destroy', $voucher))->assertRedirect(route('admin.vouchers.index'));
        $this->assertDatabaseMissing('vouchers', ['id' => $voucher->id]);
    }

    public function test_voucher_input_is_validated(): void
    {
        $this->actingAs($this->admin);
        Voucher::factory()->create(['code' => 'TAKEN']);

        $this->from(route('admin.vouchers.create'))
            ->post(route('admin.vouchers.store'), $this->voucherInput([
                'code' => 'taken',            // already exists (case-insensitively, since codes are upper-cased)
                'type' => 'bogus',
                'amount' => -5,
                'max_uses' => 0,
                'valid_from' => now()->addMonth()->toDateString(),
                'valid_until' => now()->toDateString(),
            ]))
            ->assertRedirect(route('admin.vouchers.create'))
            ->assertSessionHasErrors(['code', 'type', 'amount', 'max_uses', 'valid_from', 'valid_until']);

        $this->post(route('admin.vouchers.store'), [])->assertSessionHasErrors(['code', 'type', 'amount']);
        $this->assertSame(1, Voucher::count());
    }

    public function test_only_admins_manage_vouchers(): void
    {
        $voucher = Voucher::factory()->create();

        foreach ([$this->customer, $this->seller()] as $user) {
            $this->actingAs($user);
            $this->get(route('admin.vouchers.index'))->assertForbidden();
            $this->post(route('admin.vouchers.store'), $this->voucherInput())->assertForbidden();
            $this->put(route('admin.vouchers.update', $voucher), $this->voucherInput())->assertForbidden();
            $this->delete(route('admin.vouchers.destroy', $voucher))->assertForbidden();
        }

        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
        $this->assertSame(1, Voucher::count());

        auth()->logout();
        $this->get(route('admin.vouchers.index'))->assertRedirect(route('login'));
    }

    // ------------------------------------------------------------------
    // Admin products and sellers
    // ------------------------------------------------------------------

    public function test_admin_approves_a_product(): void
    {
        $product = Product::factory()->create(['is_active' => false]);

        $this->actingAs($this->customer)->post(route('admin.products.approve', $product->id))->assertForbidden();
        $this->assertFalse($product->fresh()->is_active);

        $this->actingAs($this->admin)->post(route('admin.products.approve', $product->id))
            ->assertRedirect()
            ->assertSessionHas('success');
        $this->assertTrue($product->fresh()->is_active);
    }

    public function test_admin_suspends_a_seller_and_their_products_go_off_sale(): void
    {
        $seller = $this->seller();
        $other = $this->seller('Reefline Marine');
        $own = Product::factory()->count(2)->create(['seller_id' => $seller->id, 'is_active' => true]);
        $theirs = Product::factory()->create(['seller_id' => $other->id, 'is_active' => true]);

        $this->actingAs($this->customer)->post(route('admin.sellers.suspend', $seller))->assertForbidden();
        $this->assertSame('active', $seller->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.sellers.suspend', $seller))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame('suspended', $seller->fresh()->status);
        $this->assertSame(0, Product::whereIn('id', $own->pluck('id'))->where('is_active', true)->count());
        $this->assertTrue($theirs->fresh()->is_active, 'Other shops are untouched');

        // The suspended shop's products can no longer be bought
        $this->get(route('products.show', $own->first()))->assertNotFound();
        $this->actingAs($this->admin)->get(route('admin.sellers'))->assertOk()->assertSee('Suspended');
    }

    // ------------------------------------------------------------------
    // JSON API: orders
    // ------------------------------------------------------------------

    protected function shipping(): array
    {
        return [
            'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
            'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'payment_method' => 'bml',
        ];
    }

    public function test_api_creates_an_order_from_the_cart_with_card_payment(): void
    {
        $product = $this->fillCart($this->customer, 2);
        Sanctum::actingAs($this->customer);

        $response = $this->postJson('/api/v1/orders', $this->shipping())
            ->assertCreated()
            ->assertJsonPath('success', true);

        $order = Order::find($response->json('data.id'));
        $this->assertNotNull($order);
        $this->assertSame($this->customer->id, $order->user_id);
        $this->assertSame('bml', $order->payment_method);
        $this->assertSame('pending', $order->status);
        $this->assertNotSame('paid', $order->payment_status);
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(2, $order->items()->first()->quantity);
        $this->assertSame(8, $product->fresh()->stock_quantity, 'Stock is reserved');

        // The cart used for the order is no longer the active one
        $this->assertSame(0, Cart::where('user_id', $this->customer->id)->where('status', 'active')->whereHas('items')->count());
    }

    public function test_api_order_needs_a_filled_cart_and_a_real_payment_method(): void
    {
        Sanctum::actingAs($this->customer);

        $this->postJson('/api/v1/orders', $this->shipping())
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Your cart is empty.');

        $this->fillCart($this->customer);
        $this->postJson('/api/v1/orders', array_merge($this->shipping(), ['payment_method' => 'cod']))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['payment_method']);

        $this->assertSame(0, Order::count());
    }

    public function test_api_order_is_private_to_its_owner(): void
    {
        $order = Order::factory()->create(['user_id' => $this->customer->id]);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/orders/{$order->id}")->assertForbidden()->assertJsonPath('success', false);
        $this->getJson("/api/v1/orders/{$order->id}/track")->assertForbidden();

        Sanctum::actingAs($this->customer);
        $this->getJson("/api/v1/orders/{$order->id}")->assertOk()->assertJsonPath('data.id', $order->id);

        $this->app['auth']->forgetGuards();
        $this->getJson("/api/v1/orders/{$order->id}")->assertUnauthorized();
    }

    // ------------------------------------------------------------------
    // JSON API: wishlist
    // ------------------------------------------------------------------

    public function test_api_wishlist_add_list_and_remove(): void
    {
        $product = Product::factory()->create();
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('data.total_items', 0);

        $this->postJson('/api/v1/wishlist/add', ['product_id' => $product->id])->assertOk()->assertJsonPath('success', true);
        $this->assertDatabaseHas('wishlists', ['user_id' => $this->customer->id, 'product_id' => $product->id]);

        // Adding twice is refused, not duplicated
        $this->postJson('/api/v1/wishlist/add', ['product_id' => $product->id])->assertStatus(400)->assertJsonPath('success', false);
        $this->postJson('/api/v1/wishlist/add', ['product_id' => 999999])->assertStatus(422);
        $this->assertSame(1, Wishlist::where('user_id', $this->customer->id)->count());

        $this->getJson('/api/v1/wishlist')
            ->assertOk()
            ->assertJsonPath('data.total_items', 1)
            ->assertJsonPath('data.items.0.product.id', $product->id);

        // Products are bound by slug in URLs
        $this->deleteJson("/api/v1/wishlist/remove/{$product->slug}")->assertOk();
        $this->assertDatabaseMissing('wishlists', ['user_id' => $this->customer->id, 'product_id' => $product->id]);
        $this->deleteJson("/api/v1/wishlist/remove/{$product->slug}")->assertNotFound();

        // Someone else's wishlist is not visible
        Wishlist::create(['user_id' => User::factory()->create()->id, 'product_id' => $product->id]);
        $this->getJson('/api/v1/wishlist')->assertOk()->assertJsonPath('data.total_items', 0);
    }

    // ------------------------------------------------------------------
    // JSON API: featured and on-sale lists
    // ------------------------------------------------------------------

    public function test_api_featured_and_on_sale_lists_are_reachable_and_filtered(): void
    {
        $featured = Product::factory()->create(['is_featured' => true, 'is_active' => true, 'stock_quantity' => 5]);
        Product::factory()->create(['is_featured' => true, 'is_active' => false]);
        Product::factory()->create(['is_featured' => true, 'is_active' => true, 'stock_quantity' => 0]);
        $onSale = Product::factory()->create(['price' => 80, 'compare_price' => 100, 'is_active' => true, 'stock_quantity' => 5]);
        Product::factory()->create(['price' => 100, 'compare_price' => 100, 'is_active' => true, 'stock_quantity' => 5]);
        Product::factory()->create(['price' => 60, 'compare_price' => null, 'is_active' => true, 'stock_quantity' => 5]);

        $this->getJson('/api/v1/products/featured')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $featured->id);

        $this->getJson('/api/v1/products/on-sale')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $onSale->id);

        $this->getJson('/api/v1/products/featured?limit=0')->assertStatus(422);

        // The single-product route (bound by slug) still resolves after the reorder
        $this->getJson("/api/v1/products/{$featured->slug}")->assertOk()->assertJsonPath('data.id', $featured->id);
    }

    // ------------------------------------------------------------------
    // Web checkout: loyalty points
    // ------------------------------------------------------------------

    public function test_redeeming_points_at_checkout_is_capped_by_balance_and_cart(): void
    {
        $this->customer->forceFill(['loyalty_points' => 150])->save();
        $this->fillCart($this->customer, 2); // MVR 200 in the cart
        $this->actingAs($this->customer);

        // More than the balance
        $this->from(route('checkout'))->post(route('checkout.redeemPoints'), ['points' => 151])
            ->assertRedirect(route('checkout'))
            ->assertSessionHasErrors('points')
            ->assertSessionMissing('points_redeemed');

        $this->post(route('checkout.redeemPoints'), ['points' => 0])->assertSessionHasErrors('points');
        $this->post(route('checkout.redeemPoints'), [])->assertSessionHasErrors('points');

        // Within the balance
        $this->from(route('checkout'))->post(route('checkout.redeemPoints'), ['points' => 120])
            ->assertRedirect(route('checkout'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('points_redeemed', 120)
            ->assertSessionHas('success');

        $this->assertSame(150, $this->customer->fresh()->loyalty_points, 'Points are only deducted when the order is placed');

        $this->post(route('checkout.removePoints'))->assertRedirect()->assertSessionMissing('points_redeemed');
    }

    public function test_points_cannot_exceed_the_cart_total(): void
    {
        $this->customer->forceFill(['loyalty_points' => 500])->save();
        $this->fillCart($this->customer, 1); // MVR 100 in the cart
        $this->actingAs($this->customer);

        $this->post(route('checkout.redeemPoints'), ['points' => 101])->assertSessionHasErrors('points');
        $this->post(route('checkout.redeemPoints'), ['points' => 100])->assertSessionHasNoErrors()->assertSessionHas('points_redeemed', 100);
    }
}
