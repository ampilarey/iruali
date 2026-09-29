<?php

namespace App\Services;

use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * What iruali owes each shop, and recording the payouts.
 *
 * A shop earns its item subtotal minus commission on every order part. Earnings become payable
 * once the part is delivered and the customer's payment is confirmed. Delivery fees, vouchers and
 * loyalty-point discounts are iruali's and don't change what the shop earns.
 */
class PayoutService
{
    /**
     * @return array{pending: float, available: float, paid: float, commission: float}
     */
    public function balances(User $seller): array
    {
        $parts = SellerOrder::where('seller_id', $seller->id)->where('status', '!=', 'cancelled');

        $available = (float) (clone $parts)->payable()->sum('seller_earnings');
        $unpaid = (float) (clone $parts)->whereNull('payout_id')->sum('seller_earnings');

        return [
            'available' => round($available, 2),
            'pending' => round($unpaid - $available, 2),
            'paid' => round((float) SellerPayout::where('seller_id', $seller->id)->sum('amount'), 2),
            'commission' => round((float) (clone $parts)->where('status', 'delivered')->sum('commission_amount'), 2),
        ];
    }

    /**
     * Record a payout for the chosen payable parts (all of them when none are chosen).
     * Locks the parts so the same earnings can't be paid twice.
     */
    public function createPayout(User $seller, ?array $partIds, ?string $reference, ?string $note, ?User $admin): ?SellerPayout
    {
        return DB::transaction(function () use ($seller, $partIds, $reference, $note, $admin) {
            $parts = SellerOrder::where('seller_id', $seller->id)
                ->payable()
                ->when($partIds !== null, fn ($q) => $q->whereIn('id', $partIds))
                ->lockForUpdate()
                ->get();

            if ($parts->isEmpty()) {
                return null;
            }

            $payout = SellerPayout::create([
                'seller_id' => $seller->id,
                'amount' => round($parts->sum('seller_earnings'), 2),
                'reference' => $reference,
                'note' => $note,
                'created_by' => $admin?->id,
                'paid_at' => now(),
            ]);

            SellerOrder::whereIn('id', $parts->pluck('id'))->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }
}
