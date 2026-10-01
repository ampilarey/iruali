<?php

namespace Tests\Unit;

use App\Enums\DisputeStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PayoutBatchStatus;
use App\Enums\PayoutStatus;
use App\Enums\ReturnStatus;
use App\Enums\SellerOrderStatus;
use App\Models\Dispute;
use App\Models\Order;
use App\Models\PayoutBatch;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Services\PaymentService;
use App\Support\OrderStatus as OrderStatusLabels;
use Tests\TestCase;

class StatusEnumsTest extends TestCase
{
    public function test_enum_values_match_what_is_stored_in_the_database(): void
    {
        $this->assertSame(['pending', 'processing', 'shipped', 'out_for_delivery', 'delivered', 'cancelled'], OrderStatus::values());
        $this->assertSame(OrderStatus::values(), SellerOrderStatus::values());
        $this->assertSame(['unpaid', 'paid'], PaymentStatus::values());
        $this->assertSame(['requested', 'approved', 'refunded', 'rejected'], ReturnStatus::values());
        $this->assertSame(['open', 'awaiting_customer', 'awaiting_seller', 'resolved_refund', 'resolved_partial', 'resolved_rejected'], DisputeStatus::values());
        $this->assertSame(['pending', 'paid'], PayoutStatus::values());
        $this->assertSame(['draft', 'exported', 'paid', 'cancelled'], PayoutBatchStatus::values());
    }

    public function test_every_case_has_a_label_and_a_badge(): void
    {
        foreach ([OrderStatus::class, SellerOrderStatus::class, PaymentStatus::class, ReturnStatus::class, DisputeStatus::class, PayoutStatus::class, PayoutBatchStatus::class] as $enum) {
            foreach ($enum::cases() as $case) {
                $this->assertNotSame('', $case->label(), $enum.'::'.$case->name);
                $this->assertMatchesRegularExpression('/^bg-\w+-\d+ text-\w+-\d+$/', $case->badgeClass(), $enum.'::'.$case->name);
                $this->assertSame($case->label(), $enum::labelFor($case->value));
                $this->assertSame($case->badgeClass(), $enum::badgeFor($case->value));
            }
        }
    }

    public function test_unknown_or_legacy_values_fall_back_instead_of_breaking(): void
    {
        $this->assertSame('Completed', OrderStatus::labelFor('completed'));
        $this->assertSame('bg-gray-100 text-gray-800', OrderStatus::badgeFor('completed'));
        $this->assertSame('', OrderStatus::labelFor(null));
        $this->assertSame('Unpaid', PaymentStatus::labelFor('failed'));
        $this->assertSame('Unpaid', PaymentStatus::labelFor(null));
        $this->assertSame(-1, SellerOrderStatus::rankFor('cancelled'));
        $this->assertSame(-1, SellerOrderStatus::rankFor(null));
        $this->assertSame(4, SellerOrderStatus::rankFor('delivered'));
    }

    public function test_labels_are_translated(): void
    {
        app()->setLocale('dv');
        $this->assertNotSame('Delivered', OrderStatus::Delivered->label());
        $this->assertNotSame('Paid', PaymentStatus::Paid->label());
        $this->assertNotSame('Return requested', ReturnStatus::Requested->label());
        app()->setLocale('en');
    }

    public function test_models_and_helpers_read_from_the_enums(): void
    {
        $order = new Order(['status' => 'shipped', 'payment_status' => 'paid']);
        $this->assertSame(OrderStatus::Shipped->badgeClass(), $order->status_badge);
        $this->assertSame(OrderStatus::Shipped, $order->statusEnum());
        $this->assertSame(PaymentStatus::Paid, $order->paymentStatusEnum());
        $this->assertSame('Shipped', OrderStatusLabels::label('shipped'));
        $this->assertSame(OrderStatus::steps(), OrderStatusLabels::steps());
        $this->assertArrayNotHasKey('cancelled', OrderStatus::steps());

        $part = new SellerOrder(['status' => 'out_for_delivery']);
        $this->assertSame(SellerOrderStatus::OutForDelivery->badgeClass(), $part->status_badge);

        $this->assertSame('Paid', PaymentService::statusLabel('paid'));
        $this->assertSame(PaymentStatus::Paid->badgeClass(), PaymentService::statusBadge('paid'));

        $dispute = new Dispute(['status' => 'awaiting_seller']);
        $this->assertTrue($dispute->isOpen());
        $this->assertSame('Waiting for the shop', $dispute->statusLabel());
        $this->assertSame(DisputeStatus::AwaitingSeller->badgeClass(), $dispute->status_badge);

        $return = new ReturnRequest(['status' => 'rejected']);
        $this->assertFalse($return->isOpen());
        $this->assertSame('Return not accepted', $return->statusLabel());

        $batch = new PayoutBatch(['status' => 'exported']);
        $this->assertTrue($batch->isOpen());
        $this->assertSame('Exported', $batch->statusLabel());
        $this->assertFalse((new PayoutBatch(['status' => 'paid']))->isOpen());

        $payout = new SellerPayout(['status' => 'pending']);
        $this->assertFalse($payout->isPaid());
        $this->assertSame('Pending', $payout->statusLabel());
        $this->assertSame(PayoutStatus::Pending->badgeClass(), $payout->status_badge);
    }
}
