<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\Role;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\OrderStatusChanged;
use App\Notifications\SellerOrderShipped;
use App\Services\FulfilmentService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Delivery tracking: the out_for_delivery step, courier / boat details, and where they show up.
 */
class DeliveryTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $customer;

    protected User $shop;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->customer = User::factory()->create();
        $this->shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Island Crafts']);
        $this->shop->roles()->attach(Role::firstOrCreate(['name' => 'seller'], ['display_name' => 'Seller'])->id);
        $this->admin = User::factory()->create();
        $this->admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);
    }

    protected function order(array $sellers, string $status = 'processing'): Order
    {
        $order = Order::factory()->create(['user_id' => $this->customer->id, 'status' => $status, 'order_number' => 'ORD-TRK'.random_int(100, 999)]);
        foreach ($sellers as $seller) {
            $order->items()->create(['product_id' => Product::factory()->create(['seller_id' => $seller?->id])->id, 'quantity' => 1, 'price' => 100]);
        }
        $order->sellerOrders()->update(['status' => $status]);

        return $order->fresh();
    }

    protected function part(Order $order, ?User $seller = null): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', ($seller ?? $this->shop)->id)->firstOrFail();
    }

    public function test_out_for_delivery_sits_between_shipped_and_delivered(): void
    {
        $fulfilment = app(FulfilmentService::class);
        $orders = app(OrderService::class);
        $order = $this->order([$this->shop]);
        $part = $this->part($order);

        $this->assertSame(['shipped'], $fulfilment->nextStatuses($part));
        $this->assertFalse($fulfilment->advance($part, 'out_for_delivery'), 'cannot skip shipped');

        $this->assertTrue($fulfilment->advance($part, 'shipped'));
        $this->assertSame(['out_for_delivery', 'delivered'], $fulfilment->nextStatuses($part->fresh()));
        $this->assertTrue($fulfilment->advance($part->fresh(), 'out_for_delivery'));

        $part->refresh();
        $this->assertSame('out_for_delivery', $part->status);
        $this->assertNotNull($part->out_for_delivery_at);
        $this->assertSame('out_for_delivery', $order->fresh()->status, 'the order follows its only part');
        $this->assertSame(['delivered'], $orders->nextStatuses($order->fresh()));
        $this->assertFalse($orders->canTransition($order->fresh(), 'cancelled'));
        Notification::assertSentTo($this->customer, OrderStatusChanged::class, fn ($n) => $n->order->status === 'out_for_delivery');

        $this->assertTrue($fulfilment->advance($part->fresh(), 'delivered'));
        $this->assertSame('delivered', $order->fresh()->status);

        // Whole-order moves (admin) pass through the step and stamp the parts
        $second = $this->order([$this->shop], 'shipped');
        $this->assertTrue($orders->updateOrderStatus($second, 'out_for_delivery'));
        $this->assertSame('out_for_delivery', $this->part($second)->status);
        $this->assertNotNull($this->part($second)->out_for_delivery_at);
        $this->assertTrue($orders->updateOrderStatus($second->fresh(), 'delivered'));
    }

    public function test_the_order_status_follows_the_slowest_part(): void
    {
        $other = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Reefline']);
        $fulfilment = app(FulfilmentService::class);
        $order = $this->order([$this->shop, $other], 'shipped');

        $fulfilment->advance($this->part($order), 'out_for_delivery');
        $this->assertSame('shipped', $order->fresh()->status, 'the other shop is still only shipped');

        $fulfilment->advance($this->part($order, $other), 'out_for_delivery');
        $this->assertSame('out_for_delivery', $order->fresh()->status);

        $fulfilment->advance($this->part($order), 'delivered');
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $fulfilment->advance($this->part($order, $other), 'delivered');
        $this->assertSame('delivered', $order->fresh()->status);
    }

    public function test_shop_records_courier_tracking_when_sending_and_everyone_sees_it(): void
    {
        $order = $this->order([$this->shop]);

        $this->actingAs($this->shop)->get(route('seller.orders.show', $order))->assertOk()->assertSee('name="courier"', false)->assertSee('name="vessel_or_flight"', false);
        $this->post(route('seller.orders.status', $order), [
            'status' => 'shipped', 'courier' => 'Maldives Post', 'tracking_number' => 'MP123456789MV', 'tracking_url' => 'https://track.example/MP123456789MV',
        ])->assertSessionHas('success');

        $part = $this->part($order);
        $this->assertSame(['Maldives Post', 'MP123456789MV', 'https://track.example/MP123456789MV'], [$part->courier, $part->tracking_number, $part->tracking_url]);
        $this->assertSame('Maldives Post MP123456789MV · https://track.example/MP123456789MV', $part->trackingSummary());

        $this->get(route('seller.orders.show', $order))->assertSee('MP123456789MV')->assertSee('Out for delivery');
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertOk()
            ->assertSee('Maldives Post')->assertSee('MP123456789MV')->assertSee('href="https://track.example/MP123456789MV"', false)->assertSee('Out for delivery');

        // The public tracking page, reached through the signed link
        $this->get(URL::temporarySignedRoute('order.track.show', now()->addMinutes(5), $order))->assertOk()
            ->assertSee('Island Crafts')->assertSee('MP123456789MV')->assertSee('Open tracking page');

        // Email and SMS carry the details
        Notification::assertSentTo($this->customer, OrderStatusChanged::class, function ($n) {
            $mail = $n->toMail($this->customer);
            $sms = $n->toSms($this->customer);

            return $n->order->status === 'shipped'
                && str_contains(implode("\n", array_map('strval', $mail->introLines)), 'Maldives Post MP123456789MV')
                && str_contains($sms, 'MP123456789MV');
        });

        // Out for delivery with one click
        $this->actingAs($this->shop)->post(route('seller.orders.status', $order), ['status' => 'out_for_delivery'])->assertSessionHas('success');
        $this->assertSame('out_for_delivery', $order->fresh()->status);
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Out for delivery since');
        Notification::assertSentTo($this->customer, OrderStatusChanged::class, fn ($n) => $n->order->status === 'out_for_delivery' && str_contains($n->toSms($this->customer), 'out for delivery'));
    }

    public function test_island_delivery_by_boat_with_an_expected_date_and_admin_edits(): void
    {
        $other = User::factory()->create(['is_seller' => true, 'seller_approved' => true]);
        $order = $this->order([$this->shop, $other]);
        $date = now()->addDays(3)->toDateString();

        $this->actingAs($this->shop)->post(route('seller.orders.status', $order), [
            'status' => 'shipped', 'vessel_or_flight' => 'Hithadhoo ferry', 'expected_delivery_date' => $date, 'tracking_note' => 'Collect at the jetty office',
        ])->assertSessionHas('success');

        $part = $this->part($order);
        $this->assertSame('Hithadhoo ferry', $part->vessel_or_flight);
        $this->assertSame($date, $part->expected_delivery_date->toDateString());

        // Only this shop's part moved, so the customer hears about this part alone, with its details
        Notification::assertSentTo($this->customer, SellerOrderShipped::class, function ($n) use ($date) {
            $sms = $n->toSms($this->customer);
            $lines = implode("\n", array_map('strval', $n->toMail($this->customer)->introLines));

            return str_contains($sms, 'Hithadhoo ferry') && str_contains($sms, 'expected') && str_contains($lines, 'Hithadhoo ferry')
                && str_contains($lines, \Carbon\Carbon::parse($date)->format('l j F'));
        });
        $this->actingAs($this->customer)->get(route('orders.show', $order))->assertSee('Hithadhoo ferry')->assertSee('Expected delivery')->assertSee('Collect at the jetty office');

        // Bad input is refused
        $this->actingAs($this->shop)->post(route('seller.orders.tracking', [$order, $part]), ['tracking_url' => 'not a url'])->assertSessionHasErrors('tracking_url');
        $this->post(route('seller.orders.tracking', [$order, $part]), ['expected_delivery_date' => 'soon'])->assertSessionHasErrors('expected_delivery_date');

        // Admin corrects the details; another shop can't touch them
        $this->actingAs($this->admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Hithadhoo ferry')->assertSee('Edit delivery details');
        $this->post(route('admin.orders.parts.tracking', [$order, $part]), ['vessel_or_flight' => 'Q2 flight 221', 'expected_delivery_date' => $date, 'courier' => ''])->assertSessionHas('success');
        $this->assertSame('Q2 flight 221', $part->fresh()->vessel_or_flight);
        $this->assertSame('shipped', $part->fresh()->status, 'editing details does not change the status');

        $this->actingAs($other)->post(route('seller.orders.tracking', [$order, $part]), ['courier' => 'Mine now'])->assertForbidden();
        $this->actingAs($this->customer)->post(route('admin.orders.parts.tracking', [$order, $part]), ['courier' => 'x'])->assertForbidden();
        $this->assertSame('Q2 flight 221', $part->fresh()->vessel_or_flight);
    }
}
