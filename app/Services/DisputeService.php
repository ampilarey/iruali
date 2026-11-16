<?php

namespace App\Services;

use App\Enums\DisputeStatus;
use App\Models\Dispute;
use App\Models\ReturnRequest;
use App\Models\SellerAdjustment;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\DisputeOpened;
use App\Notifications\DisputeResolved;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Disputes: a customer's formal complaint about one shop's part, discussed in the order thread
 * and decided by iruali with a full refund, a partial refund or a rejection.
 */
class DisputeService
{
    /** Days of grace on top of the late-shipment setting before "not delivered" can be raised. */
    public const NON_DELIVERY_GRACE_DAYS = 5;

    public function __construct(protected MessagingService $messaging, protected PaymentService $payments) {}

    public function lateShipmentDays(): int
    {
        return max(0, (int) Setting::get('late_shipment_days', 3));
    }

    /**
     * Days after payment a part may take before the customer can report it as not delivered.
     */
    public function nonDeliveryAfterDays(): int
    {
        return $this->lateShipmentDays() + self::NON_DELIVERY_GRACE_DAYS;
    }

    /**
     * The dispute types this customer may open on this part right now (empty when none).
     *
     * @return array<int, string>
     */
    public function availableTypes(SellerOrder $part, User $user): array
    {
        $order = $part->order;
        if (! $order || $order->user_id !== $user->id || $part->status === 'cancelled' || $order->status === 'cancelled') {
            return [];
        }
        if ($part->disputes()->open()->exists()) {
            return [];
        }

        $types = [];
        $paidAt = $order->paid_at ?? $order->created_at;
        $paidAt = $paidAt ? app(PreorderService::class)->dispatchClockStart($part, $paidAt) : null; // pre-orders: from when their stock is in

        if ($order->payment_status === 'paid' && $part->status !== 'delivered' && $paidAt && $paidAt->copy()->addDays($this->nonDeliveryAfterDays())->isPast()) {
            $types[] = 'non_delivery';
        }
        if ($this->rejectedReturn($part)) {
            $types[] = 'return_rejected';
        }
        if ($part->status === 'delivered' && ($part->delivered_at ?? $part->updated_at)?->copy()->addDays(Dispute::NOT_AS_DESCRIBED_DAYS)->endOfDay()->isFuture()) {
            $types[] = 'item_not_as_described';
        }
        if ($types) {
            $types[] = 'other';
        }

        return $types;
    }

    public function canOpen(SellerOrder $part, User $user): bool
    {
        return $this->availableTypes($part, $user) !== [];
    }

    /**
     * The latest rejected return on this part that has not been disputed yet.
     */
    public function rejectedReturn(SellerOrder $part): ?ReturnRequest
    {
        return $part->returnRequests()->where('status', 'rejected')
            ->whereNotIn('id', Dispute::where('seller_order_id', $part->id)->whereNotNull('return_request_id')->select('return_request_id'))
            ->latest('id')->first();
    }

    /**
     * The most a customer can claim on this part: its items as paid for (after the shop's own
     * discounts and their share of the iruali voucher and points: SellerOrder::paidSubtotal()), plus
     * delivery (the shop's own charge when it delivered the part itself, else the order's delivery
     * fee when it is the only shop), less what has already been refunded on it through returns or
     * earlier disputes. Never more than is left of what the whole order was paid.
     */
    public function maxClaim(SellerOrder $part): float
    {
        $order = $part->order;
        $cap = $part->paidSubtotal();
        if ($part->delivery_fee_to_shop) {
            $cap += $part->shopDeliveryCharge();
        } elseif ($order->sellerOrders()->count() === 1) {
            $cap += (float) $order->shipping_amount;
        }
        $cap -= (float) $part->returnRequests()->whereIn('status', ['approved', 'refunded'])->sum('refund_amount');
        $cap -= (float) Dispute::where('seller_order_id', $part->id)->whereNotNull('amount_resolved')->sum('amount_resolved');

        return max(0, round(min($cap, $this->orderLeft($order)), 2));
    }

    /**
     * What is left to refund on the whole order: what was paid, less every approved return and
     * dispute refund. Nothing on an unpaid or cancelled order (a cancellation refunds the payment).
     */
    public function orderLeft(?\App\Models\Order $order): float
    {
        if (! $order || $order->payment_status !== 'paid' || $order->status === 'cancelled') {
            return 0.0;
        }
        $taken = (float) ReturnRequest::where('order_id', $order->id)->whereIn('status', ['approved', 'refunded'])->sum('refund_amount')
            + (float) Dispute::where('order_id', $order->id)->whereNotNull('amount_resolved')->sum('amount_resolved');

        return max(0, round((float) $order->total_amount - $taken, 2));
    }

    public function open(SellerOrder $part, User $customer, string $type, float $amountClaimed, string $details): ?Dispute
    {
        if (! in_array($type, $this->availableTypes($part, $customer), true)) {
            return null;
        }

        $order = $part->order;
        $return = $type === 'return_rejected' ? $this->rejectedReturn($part) : null;
        $amount = round(min(max(0, $amountClaimed), $this->maxClaim($part)), 2);

        $dispute = DB::transaction(function () use ($part, $customer, $type, $amount, $details, $return, $order) {
            $conversation = $this->messaging->conversationFor($part);
            if ($conversation->status !== 'open') {
                $conversation->update(['status' => 'open']);
            }

            $dispute = Dispute::create([
                'order_id' => $order->id,
                'seller_order_id' => $part->id,
                'customer_id' => $customer->id,
                'seller_id' => $part->seller_id,
                'return_request_id' => $return?->id,
                'conversation_id' => $conversation->id,
                'type' => $type,
                'status' => 'open',
                'details' => $details,
                'amount_claimed' => $amount,
                'opened_at' => now(),
            ]);

            // The customer's account of the problem opens the thread so the shop can answer there
            $this->messaging->send($conversation, $customer, 'customer', __('Dispute opened (:type): :details', ['type' => $dispute->typeLabel(), 'details' => $details]), null, false);

            return $dispute;
        });

        $this->notifyOpened($dispute);

        return $dispute;
    }

    /**
     * Ask one side for more information; the note goes into the thread as iruali support.
     */
    public function requestInfo(Dispute $dispute, User $admin, string $from, string $note): bool
    {
        if (! $dispute->isOpen() || ! in_array($from, ['customer', 'seller'], true)) {
            return false;
        }

        $dispute->update(['status' => 'awaiting_'.$from, 'admin_id' => $admin->id]);
        if ($dispute->conversation) {
            $this->messaging->send($dispute->conversation, $admin, 'admin', $note);
        }

        return true;
    }

    /**
     * Decide the dispute: 'full' refunds the amount claimed, 'partial' the given amount, 'reject' nothing.
     * A refund is flagged on the order (the admin sends it through BML and records the reference)
     * and the shop's share is taken back from its next payout.
     */
    public function resolve(Dispute $dispute, User $admin, string $outcome, ?float $amount, ?string $note): bool
    {
        if (! $dispute->isOpen()) {
            return false;
        }

        $refund = match ($outcome) {
            'full' => (float) $dispute->amount_claimed,
            'partial' => round((float) $amount, 2),
            'reject' => 0.0,
            default => null,
        };
        if ($refund === null || $refund < 0 || $refund > (float) $dispute->amount_claimed + 0.001) {
            return false;
        }
        if ($outcome === 'partial' && ($refund <= 0 || $refund >= (float) $dispute->amount_claimed)) {
            return false;
        }

        $done = DB::transaction(function () use ($dispute, $admin, $outcome, $refund, $note) {
            // The order locked, then the room left worked out again: a return or another dispute decided
            // since this one was opened has already taken some of it
            \App\Models\Order::whereKey($dispute->order_id)->lockForUpdate()->first(['id']);
            if ($refund > 0) {
                $cap = $dispute->sellerOrder ? $this->maxClaim($dispute->sellerOrder->fresh()) : 0.0;
                if ($outcome === 'full') {
                    $refund = round(min($refund, $cap), 2);
                } elseif ($refund > $cap + 0.001) {
                    return false;
                }
                if ($refund <= 0) {
                    return false; // nothing left to refund: reject it instead
                }
            }

            // Only from an open status, in one statement: a second decision at the same moment changes no
            // row and stops here (no second refund or shop deduction)
            $changes = [
                'status' => ['full' => 'resolved_refund', 'partial' => 'resolved_partial', 'reject' => 'resolved_rejected'][$outcome],
                'amount_resolved' => $refund,
                'resolution_note' => $note,
                'resolved_at' => now(),
                'admin_id' => $admin->id,
            ];
            if (Dispute::whereKey($dispute->id)->whereIn('status', DisputeStatus::openValues())->update($changes) === 0) {
                return false;
            }
            $dispute->forceFill($changes)->syncOriginal();

            if ($refund > 0) {
                $order = $dispute->order;
                $this->payments->flagRefund($order, $refund, 'Dispute #'.$dispute->id.' on order '.$order->order_number);

                $part = $dispute->sellerOrder;
                if ($part?->seller_id) {
                    // The shop gives back its earning on the refunded items and its own delivery charge
                    // refunded beyond them (App\Support\RefundSplit); iruali forgoes its commission
                    $shopShare = \App\Support\RefundSplit::of($part, $refund, $part->paidSubtotal(), $part->netSubtotal())['shop_gives_back'];
                    if ($shopShare > 0) {
                        SellerAdjustment::create([
                            'seller_id' => $part->seller_id,
                            'amount' => -$shopShare,
                            'reason' => 'Dispute #'.$dispute->id.' on order '.$order->order_number,
                            'return_request_id' => $dispute->return_request_id,
                            'dispute_id' => $dispute->id,
                        ]);
                    }
                }
            }

            if ($dispute->conversation) {
                $this->messaging->send($dispute->conversation, $admin, 'admin', $this->resolutionMessage($dispute->fresh()), null, false);
            }

            return true;
        });
        if (! $done) {
            return false;
        }

        $this->notifyResolved($dispute->fresh());

        return true;
    }

    protected function resolutionMessage(Dispute $dispute): string
    {
        $text = match ($dispute->status) {
            'resolved_refund' => __('Dispute resolved: full refund of :amount.', ['amount' => \App\Support\Money::format($dispute->amount_resolved)]),
            'resolved_partial' => __('Dispute resolved: partial refund of :amount.', ['amount' => \App\Support\Money::format($dispute->amount_resolved)]),
            default => __('Dispute resolved: the claim was not upheld.'),
        };

        return $dispute->resolution_note ? $text.' '.$dispute->resolution_note : $text;
    }

    protected function notifyOpened(Dispute $dispute): void
    {
        \App\Support\TeamAlerts::send('dispute_opened', 'iruali: dispute opened on '.$dispute->order?->order_number.($dispute->seller ? ' ('.$dispute->seller->shopName().')' : '').', '.\App\Support\Money::format($dispute->amount_claimed).' claimed.', route('admin.disputes.show', $dispute));

        try {
            if ($seller = $dispute->seller) {
                $seller->notify(new DisputeOpened($dispute));
            }
            if ($email = trim((string) Setting::get('contact_email'))) {
                Notification::route('mail', $email)->notify(new DisputeOpened($dispute));
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function notifyResolved(Dispute $dispute): void
    {
        try {
            $dispute->customer?->notify(new DisputeResolved($dispute));
            $dispute->seller?->notify(new DisputeResolved($dispute));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
