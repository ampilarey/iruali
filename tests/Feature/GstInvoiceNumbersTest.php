<?php

namespace Tests\Feature;

use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\GiftCardService;
use App\Services\GstService;
use App\Services\OrderService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\GstFixtures;
use Tests\TestCase;

/**
 * Invoice numbers: given when an order is paid (never before), one running series per shop,
 * unique, never reused. GstInvoiceConcurrencyTest covers payments landing at the same moment.
 */
class GstInvoiceNumbersTest extends TestCase
{
    use GstFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(Carbon::parse('2026-10-15 10:00'));
        Setting::set(['free_delivery_over' => 0, 'delivery_fee_islands' => 81]);
    }

    public function test_only_paid_orders_are_numbered_and_each_shop_has_its_own_series(): void
    {
        $customer = User::factory()->create();
        $crafts = $this->shop('Island Crafts', '1012345GST501');
        $reef = $this->shop('Reefline Marine');

        $both = $this->placeOrder($customer, [[$crafts, 100], [$reef, 50]]);
        $craftsOnly = $this->placeOrder($customer, [[$crafts, 200]]);
        $unpaid = $this->placeOrder($customer, [[$reef, 70]]);
        $cancelled = $this->placeOrder($customer, [[$crafts, 80]]);
        app(OrderService::class)->updateOrderStatus($cancelled, 'cancelled');

        $this->assertSame(0, SellerOrder::whereNotNull('invoice_number')->count());

        $this->pay($craftsOnly);
        $this->travelTo(Carbon::parse('2026-10-16 09:30'));
        $both = $this->pay($both);

        $prefix = 'INV-'.$crafts->id.'-';
        $this->assertSame($prefix.'000001', $this->part($craftsOnly, $crafts)->invoice_number);
        $this->assertSame($prefix.'000002', $this->part($both, $crafts)->invoice_number);
        $this->assertSame('INV-'.$reef->id.'-000001', $this->part($both, $reef)->invoice_number);
        $this->assertSame(1, $this->part($both, $reef)->invoice_sequence);
        $this->assertSame($both->paid_at->toDateTimeString(), $this->part($both, $reef)->invoiced_at->toDateTimeString());

        // Unpaid and cancelled orders have none
        $this->assertNull($this->part($unpaid, $reef)->invoice_number);
        $this->assertNull($this->part($cancelled, $crafts)->invoice_number);
        app(GstService::class)->assignInvoiceNumbers($unpaid->fresh());
        $this->assertNull($this->part($unpaid, $reef)->invoice_number);

        // Paying again (BML's webhook and the customer's return both arriving) changes nothing
        app(GstService::class)->assignInvoiceNumbers($both->fresh());
        $this->assertSame($prefix.'000002', $this->part($both, $crafts)->invoice_number);
        $this->assertSame(3, SellerOrder::whereNotNull('invoice_number')->count());

        // A new prefix applies to new numbers; each shop's count carries on
        Setting::set(['invoice_prefix' => 'IRU']);
        $this->pay($unpaid);
        $this->assertSame('IRU-'.$reef->id.'-000002', $this->part($unpaid, $reef)->invoice_number);

        // Numbers are unique in the database too
        $this->expectException(UniqueConstraintViolationException::class);
        $this->part($unpaid, $reef)->forceFill(['invoice_number' => $prefix.'000001'])->save();
    }

    public function test_a_number_is_never_issued_twice_even_if_the_counter_is_reset(): void
    {
        $customer = User::factory()->create();
        $crafts = $this->shop('Island Crafts');
        $first = $this->pay($this->placeOrder($customer, [[$crafts, 100]]));
        $second = $this->pay($this->placeOrder($customer, [[$crafts, 100]]));

        // e.g. an older backup of the counters restored
        DB::table('invoice_sequences')->where('series', 'shop:'.$crafts->id)->update(['last_number' => 0]);

        $third = $this->pay($this->placeOrder($customer, [[$crafts, 100]]));
        $this->assertSame([1, 2, 3], [$this->part($first, $crafts)->invoice_sequence, $this->part($second, $crafts)->invoice_sequence, $this->part($third, $crafts)->invoice_sequence]);
        $this->assertSame(3, (int) DB::table('invoice_sequences')->where('series', 'shop:'.$crafts->id)->value('last_number'));
    }

    public function test_gift_card_orders_get_no_shop_invoice(): void
    {
        $buyer = User::factory()->create();
        $this->staff('admin');
        ['order' => $order] = app(GiftCardService::class)->purchase($buyer, 250, 'friend@example.com', 'Friend', null);

        $order = $this->pay($order);
        $this->assertSame(0, $order->sellerOrders()->whereNotNull('invoice_number')->count());
    }

    public function test_an_order_paid_from_the_wallet_is_numbered_straight_away(): void
    {
        $customer = User::factory()->create();
        $customer->forceFill(['wallet_balance' => 500])->save();
        $crafts = $this->shop('Island Crafts');

        $order = $this->placeOrder($customer, [[$crafts, 100]], ['use_wallet' => true]);

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('INV-'.$crafts->id.'-000001', $this->part($order, $crafts)->invoice_number);
    }

    protected function part($order, User $shop): SellerOrder
    {
        return $order->sellerOrders()->where('seller_id', $shop->id)->sole();
    }
}
