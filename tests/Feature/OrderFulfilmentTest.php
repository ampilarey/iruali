<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Models\Voucher;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderFulfilmentTest extends TestCase
{
    use RefreshDatabase;

    protected function withRole(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::firstOrCreate(['name' => $role], ['display_name' => ucfirst($role)])->id);

        return $user;
    }

    protected function orderWith(User $customer, array $lines, string $status = 'pending', array $attributes = []): Order
    {
        $order = Order::factory()->create(array_merge(['user_id' => $customer->id, 'status' => $status], $attributes));
        foreach ($lines as [$product, $qty]) {
            $order->items()->create(['product_id' => $product->id, 'quantity' => $qty, 'price' => $product->price]);
        }

        return $order;
    }

    public function test_status_must_follow_the_allowed_steps(): void
    {
        $order = $this->orderWith(User::factory()->create(), [[Product::factory()->create(), 1]]);
        $service = app(OrderService::class);

        $this->assertFalse($service->updateOrderStatus($order, 'delivered'));
        $this->assertTrue($service->updateOrderStatus($order, 'processing'));
        $this->assertTrue($service->updateOrderStatus($order->fresh(), 'shipped'));
        $this->assertFalse($service->updateOrderStatus($order->fresh(), 'cancelled'));
        $this->assertTrue($service->updateOrderStatus($order->fresh(), 'delivered'));
        $this->assertSame([], $service->nextStatuses($order->fresh()));
    }

    public function test_cancelling_restocks_and_reverses_points_and_voucher(): void
    {
        $customer = User::factory()->create(['loyalty_points' => 12]);
        $product = Product::factory()->create(['stock_quantity' => 3, 'price' => 100]);
        Voucher::factory()->create(['code' => 'SAVE10', 'used_count' => 1]);
        $order = $this->orderWith($customer, [[$product, 2]], 'pending', [
            'loyalty_points_earned' => 2, 'points_redeemed' => 10, 'voucher_code' => 'SAVE10',
        ]);

        $this->assertTrue(app(OrderService::class)->updateOrderStatus($order, 'cancelled'));

        $this->assertSame('cancelled', $order->fresh()->status);
        $this->assertSame(5, $product->fresh()->stock_quantity);
        $this->assertSame(20, $customer->fresh()->loyalty_points); // +10 refunded, −2 earned
        $this->assertSame(0, Voucher::where('code', 'SAVE10')->value('used_count'));
    }

    public function test_customer_can_cancel_only_their_own_pending_order(): void
    {
        $customer = User::factory()->create();
        $pending = $this->orderWith($customer, [[Product::factory()->create(), 1]]);
        $processing = $this->orderWith($customer, [[Product::factory()->create(), 1]], 'processing');
        $someoneElses = $this->orderWith(User::factory()->create(), [[Product::factory()->create(), 1]]);

        $this->actingAs($customer);
        $this->post(route('orders.cancel', $pending))->assertRedirect(route('orders.show', $pending));
        $this->assertSame('cancelled', $pending->fresh()->status);

        $this->post(route('orders.cancel', $processing))->assertForbidden();
        $this->post(route('orders.cancel', $someoneElses))->assertForbidden();
        $this->assertSame('pending', $someoneElses->fresh()->status);
    }

    public function test_admin_can_view_and_advance_any_order(): void
    {
        $admin = $this->withRole('admin');
        $order = $this->orderWith(User::factory()->create(), [[Product::factory()->create(['name' => ['en' => 'Lacquer box']]), 1]]);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))
            ->assertOk()->assertSee('Lacquer box')->assertSee('Mark as processing');

        $this->post(route('admin.orders.status', $order), ['status' => 'processing'])->assertRedirect();
        $this->assertSame('processing', $order->fresh()->status);

        $this->post(route('admin.orders.status', $order), ['status' => 'delivered'])->assertSessionHas('error');
        $this->assertSame('processing', $order->fresh()->status);

        $this->actingAs(User::factory()->create())
            ->post(route('admin.orders.status', $order), ['status' => 'shipped'])->assertForbidden();
    }

    public function test_seller_can_advance_orders_that_are_entirely_theirs(): void
    {
        $seller = $this->withRole('seller');
        $mine = Product::factory()->create(['seller_id' => $seller->id]);
        $theirs = Product::factory()->create();

        $ownOrder = $this->orderWith(User::factory()->create(), [[$mine, 1]]);
        $mixedOrder = $this->orderWith(User::factory()->create(), [[$mine, 1], [$theirs, 1]]);

        $this->actingAs($seller);
        $this->get(route('seller.orders.show', $ownOrder))->assertOk()->assertSee('Mark as processing');
        $this->post(route('seller.orders.status', $ownOrder), ['status' => 'processing'])->assertRedirect();
        $this->assertSame('processing', $ownOrder->fresh()->status);

        $this->get(route('seller.orders.show', $mixedOrder))->assertOk()->assertSee('an admin updates its status');
        $this->post(route('seller.orders.status', $mixedOrder), ['status' => 'processing'])->assertForbidden();
        $this->assertSame('pending', $mixedOrder->fresh()->status);
    }

    public function test_seller_can_view_an_order_they_placed_as_a_customer(): void
    {
        $seller = $this->withRole('seller', ['is_seller' => true, 'seller_approved' => true]);
        $order = $this->orderWith($seller, [[Product::factory()->create(), 1]]);

        $this->actingAs($seller)->get(route('orders.show', $order))->assertOk();
    }
}
