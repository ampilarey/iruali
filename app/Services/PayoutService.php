<?php

namespace App\Services;

use App\Models\SellerAdjustment;
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
 *
 * Adjustments (e.g. the shop's share of an approved return, taken back) are settled in the shop's
 * next payout, whether or not the order they relate to was already paid out.
 */
class PayoutService
{
    /**
     * @return array{pending: float, available: float, adjustments: float, paid: float, commission: float}
     */
    public function balances(User $seller): array
    {
        $parts = SellerOrder::where('seller_id', $seller->id)->where('status', '!=', 'cancelled');

        $payable = (float) (clone $parts)->payable()->sum('seller_earnings');
        $unpaid = (float) (clone $parts)->whereNull('payout_id')->sum('seller_earnings');
        $adjustments = $this->openAdjustmentsTotal($seller);

        return [
            'available' => round($payable + $adjustments, 2),
            'pending' => round($unpaid - $payable, 2),
            'adjustments' => $adjustments,
            'paid' => round((float) SellerPayout::where('seller_id', $seller->id)->sum('amount'), 2),
            'commission' => round((float) (clone $parts)->where('status', 'delivered')->sum('commission_amount'), 2),
        ];
    }

    /**
     * Adjustments not yet settled in a payout.
     */
    public function openAdjustments(User $seller)
    {
        return SellerAdjustment::where('seller_id', $seller->id)->whereNull('payout_id')->with('returnRequest.order')->oldest()->get();
    }

    public function openAdjustmentsTotal(User $seller): float
    {
        return round((float) SellerAdjustment::where('seller_id', $seller->id)->whereNull('payout_id')->sum('amount'), 2);
    }

    /**
     * Record a payout for the chosen payable parts (all of them when none are chosen), with every
     * open adjustment settled in it. Refused when deductions leave nothing to pay; the parts then
     * wait until later sales cover the deductions.
     * Locks the rows so the same earnings can't be paid twice.
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

            $adjustments = SellerAdjustment::where('seller_id', $seller->id)->whereNull('payout_id')->lockForUpdate()->get();
            $amount = round($parts->sum('seller_earnings') + $adjustments->sum('amount'), 2);
            if ($amount <= 0) {
                return null;
            }

            $payout = SellerPayout::create([
                'seller_id' => $seller->id,
                'amount' => $amount,
                'reference' => $reference,
                'note' => $note,
                'created_by' => $admin?->id,
                'paid_at' => now(),
            ]);

            SellerOrder::whereIn('id', $parts->pluck('id'))->update(['payout_id' => $payout->id]);
            SellerAdjustment::whereIn('id', $adjustments->pluck('id'))->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }
}
