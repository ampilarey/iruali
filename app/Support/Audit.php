<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Throwable;

/**
 * Audit::record('order.status', $order, ['from' => 'pending', 'to' => 'shipped']);
 *
 * Records who (the signed-in user, if any) did what to which record. Never throws: a failed
 * audit write must not undo the action it describes.
 */
class Audit
{
    /** Actions the admin audit page offers as filters, with a label. */
    public const ACTIONS = [
        'order.status' => 'Order status changed',
        'order.cancelled' => 'Order cancelled',
        'refund.flagged' => 'Refund flagged',
        'refund.recorded' => 'Refund recorded',
        'payout.created' => 'Payout created / paid',
        'seller.approved' => 'Seller approved',
        'seller.rejected' => 'Seller rejected',
        'seller.suspended' => 'Seller suspended',
        'product.approved' => 'Product approved',
        'product.rejected' => 'Product rejected',
        'brand.updated' => 'Brand edited',
        'brand.reviewed' => 'Brand reviewed',
        'brand.merged' => 'Brands merged',
        'brand.deleted' => 'Brand deleted',
        'brand.seller_authorised' => 'Brand authorised seller added',
        'brand.seller_unauthorised' => 'Brand authorised seller removed',
        'settings.saved' => 'Settings saved',
        'legal.saved' => 'Legal page saved',
        'demo.removed' => 'Sample data removed',
        'demo.restored' => 'Sample data restored',
        'voucher.created' => 'Voucher created',
        'voucher.updated' => 'Voucher updated',
        'voucher.deleted' => 'Voucher deleted',
        'user.role' => 'User role changed',
        'staff.login' => 'Staff signed in',
        'seller.verification_submitted' => 'Business documents sent',
        'seller.verification_approved' => 'Business verified',
        'seller.verification_rejected' => 'Business verification rejected',
        'seller.holiday' => 'Shop holiday mode changed',
        'payouts.verified_business_rule' => 'Payout rule (verified business) changed',
        'delivery.saved' => 'Delivery rates and time slots saved',
        'pickup.confirmed' => 'Pickup confirmed for a shop',
        'newsletter.sent' => 'Newsletter sent',
        'quote.closed' => 'Quote request closed by staff',
        'quote.message' => 'Quote request message from staff',
        'tax.settings_saved' => 'Tax settings saved',
        'shop.tax_details_saved' => 'Shop tax details saved',
    ];

    public static function record(string $action, ?Model $subject = null, array $changes = []): ?AuditLog
    {
        try {
            $request = app()->bound('request') ? app('request') : null;

            return AuditLog::create([
                'user_id' => auth()->id(),
                'action' => Str::limit($action, 60, ''),
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject?->getKey(),
                'changes' => $changes ?: null,
                'ip' => $request?->ip(),
                'user_agent' => $request ? Str::limit((string) $request->userAgent(), 255, '') : null,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }
}
