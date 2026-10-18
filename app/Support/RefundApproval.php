<?php

namespace App\Support;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\User;

/**
 * Four eyes on big refunds: a refund over the amount in Admin → Settings → Payments needs one staff
 * member to approve it and a different one to record it (card, bank transfer or wallet).
 */
class RefundApproval
{
    public static function threshold(): float
    {
        return max(0, (float) Setting::get('refund_approval_over', 0));
    }

    public static function amount(Order|ReturnRequest $refundable): float
    {
        return (float) $refundable->refund_amount;
    }

    public static function needed(Order|ReturnRequest $refundable): bool
    {
        $over = self::threshold();

        return $over > 0 && self::amount($refundable) > $over;
    }

    public static function approved(Order|ReturnRequest $refundable): bool
    {
        return $refundable->getAttribute('refund_approved_at') !== null;
    }

    /**
     * Why this person can't record the refund yet, or null.
     */
    public static function problem(Order|ReturnRequest $refundable, User $recorder): ?string
    {
        if (! self::needed($refundable)) {
            return null;
        }
        if (! self::approved($refundable)) {
            return 'This refund is over '.Money::format(self::threshold()).', so another staff member has to approve it first.';
        }
        if ((int) $refundable->getAttribute('refund_approved_by') === (int) $recorder->id) {
            return 'You approved this refund, so someone else has to record it.';
        }

        return null;
    }

    public static function approve(Order|ReturnRequest $refundable, User $by): void
    {
        $refundable->forceFill(['refund_approved_by' => $by->id, 'refund_approved_at' => now()])->save();
        Audit::record('refund.approved', $refundable, ['amount' => self::amount($refundable)]);
    }
}
