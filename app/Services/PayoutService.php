<?php

namespace App\Services;

use App\Models\PayoutBatch;
use App\Models\SellerAdjustment;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\User;
use App\Notifications\PayoutPaid;
use App\Support\BankFileFormat;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * What iruali owes each shop, and recording the payouts.
 *
 * A shop earns its item subtotal minus commission on every order part. Earnings become payable
 * once the part is delivered and the customer's payment is confirmed. Delivery fees, vouchers and
 * loyalty-point discounts are iruali's and don't change what the shop earns. The shop's own
 * discounts (multi-buy offers, its discount codes) are the shop's: they come off its subtotal
 * before commission (SellerOrder::shop_discount, see FulfilmentService::refreshPart).
 *
 * Adjustments (e.g. the shop's share of an approved return, taken back) are settled in the shop's
 * next payout, whether or not the order they relate to was already paid out.
 */
class PayoutService
{
    /**
     * @return array{pending: float, available: float, adjustments: float, paid: float, processing: float, commission: float}
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
            'paid' => round((float) SellerPayout::where('seller_id', $seller->id)->where('status', 'paid')->sum('amount'), 2),
            'processing' => round((float) SellerPayout::where('seller_id', $seller->id)->where('status', 'pending')->sum('amount'), 2),
            'commission' => round((float) (clone $parts)->where('status', 'delivered')->sum('commission_amount'), 2),
        ];
    }

    /**
     * balances() for every shop at once (the admin payouts page): a few grouped queries instead of five per shop.
     *
     * @return array<int, array{pending: float, available: float, adjustments: float, paid: float, processing: float, commission: float}>
     */
    public function balancesForAll(): array
    {
        $parts = SellerOrder::query()->where('seller_orders.status', '!=', 'cancelled')->whereNotNull('seller_orders.seller_id')
            ->leftJoin('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->groupBy('seller_orders.seller_id')
            ->selectRaw("seller_orders.seller_id,
                SUM(CASE WHEN seller_orders.payout_id IS NULL AND seller_orders.status = 'delivered' AND seller_orders.seller_earnings > 0 AND orders.payment_status = 'paid' THEN seller_orders.seller_earnings ELSE 0 END) AS payable,
                SUM(CASE WHEN seller_orders.payout_id IS NULL THEN seller_orders.seller_earnings ELSE 0 END) AS unpaid,
                SUM(CASE WHEN seller_orders.status = 'delivered' THEN seller_orders.commission_amount ELSE 0 END) AS commission")
            ->get()->keyBy('seller_id');
        $adjustments = SellerAdjustment::whereNull('payout_id')->groupBy('seller_id')->selectRaw('seller_id, SUM(amount) AS total')->pluck('total', 'seller_id');
        $paid = SellerPayout::where('status', 'paid')->groupBy('seller_id')->selectRaw('seller_id, SUM(amount) AS total')->pluck('total', 'seller_id');
        $processing = SellerPayout::where('status', 'pending')->groupBy('seller_id')->selectRaw('seller_id, SUM(amount) AS total')->pluck('total', 'seller_id');

        $out = [];
        foreach (array_unique(array_merge($parts->keys()->all(), $adjustments->keys()->all(), $paid->keys()->all())) as $sellerId) {
            $p = $parts[$sellerId] ?? null;
            $adj = round((float) ($adjustments[$sellerId] ?? 0), 2);
            $out[$sellerId] = [
                'available' => round((float) ($p->payable ?? 0) + $adj, 2),
                'pending' => round((float) ($p->unpaid ?? 0) - (float) ($p->payable ?? 0), 2),
                'adjustments' => $adj,
                'paid' => round((float) ($paid[$sellerId] ?? 0), 2),
                'processing' => round((float) ($processing[$sellerId] ?? 0), 2),
                'commission' => round((float) ($p->commission ?? 0), 2),
            ];
        }

        return $out;
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
     * Why this shop can't be paid right now, or null when it can. A shop has to add its bank account
     * (Seller Centre → Settings → Bank) before any payout or bank file can include it.
     */
    public function payoutBlockedReason(User $seller): ?string
    {
        if (! $seller->bankAccount) {
            return __('This shop has not added a bank account yet, so it cannot be paid out.');
        }
        // Admin → Payouts: "Require verified business before payouts" (off by default)
        if (\App\Models\SellerVerification::payoutHeld($seller)) {
            return __('Held: payouts need a verified business, and this shop\'s business has not been verified yet (Admin → Verifications).');
        }

        return null;
    }

    /**
     * Record a payout for the chosen payable parts (all of them when none are chosen), with every
     * open adjustment settled in it. Refused when deductions leave nothing to pay; the parts then
     * wait until later sales cover the deductions.
     * Locks the rows so the same earnings can't be paid twice.
     */
    public function createPayout(User $seller, ?array $partIds, ?string $reference, ?string $note, ?User $admin): ?SellerPayout
    {
        if ($this->payoutBlockedReason($seller)) {
            return null;
        }

        return DB::transaction(fn () => $this->allocate($seller, $partIds, $reference, $note, $admin, null));
    }

    /**
     * Draft a batch: one pending payout per chosen shop, for everything that shop is owed right now.
     * Shops with no bank account or nothing to pay are skipped; null when no line could be made.
     * The parts and adjustments are locked, so the same earnings can never land in two batches.
     *
     * @param  int[]  $sellerIds
     */
    public function createBatch(array $sellerIds, ?User $admin, ?string $notes = null): ?PayoutBatch
    {
        DB::beginTransaction();
        try {
            $batch = PayoutBatch::create([
                'reference' => PayoutBatch::nextReference(),
                'created_by' => $admin?->id,
                'status' => 'draft',
                'notes' => $notes,
            ]);

            $total = 0.0;
            $count = 0;
            foreach (User::whereIn('id', $sellerIds)->with(['bankAccount', 'businessVerification'])->get() as $seller) {
                if ($this->payoutBlockedReason($seller)) {
                    continue;
                }
                $payout = $this->allocate($seller, null, null, null, $admin, $batch);
                if ($payout) {
                    $total += (float) $payout->amount;
                    $count++;
                }
            }

            if ($count === 0) {
                DB::rollBack();

                return null;
            }

            $batch->update(['total' => round($total, 2), 'count' => $count]);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $batch;
    }

    /**
     * The bank file for a batch. Downloading it moves a draft to "exported".
     */
    public function exportBatch(PayoutBatch $batch): string
    {
        if ($batch->isDraft()) {
            $batch->update(['status' => 'exported', 'exported_at' => now()]);
        }

        return BankFileFormat::forBatch($batch);
    }

    /**
     * The bank has made the transfers: every payout in the batch is paid, with the bank's reference and
     * date, and each shop is told. Only a draft or exported batch can be marked paid, once.
     */
    public function markBatchPaid(PayoutBatch $batch, string $bankReference, Carbon $paidAt, ?User $admin): bool
    {
        $payouts = DB::transaction(function () use ($batch, $bankReference, $paidAt) {
            $locked = PayoutBatch::whereKey($batch->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isOpen()) {
                return null;
            }

            $payouts = SellerPayout::where('payout_batch_id', $batch->id)->where('status', 'pending')->lockForUpdate()->get();
            foreach ($payouts as $payout) {
                $payout->update(['status' => 'paid', 'paid_at' => $paidAt, 'reference' => BankFileFormat::remark($batch, $payout->id)]);
                app(GstService::class)->payoutPaid($payout); // iruali's commission invoice
            }

            $locked->update(['status' => 'paid', 'paid_at' => $paidAt, 'bank_reference' => $bankReference]);
            \App\Support\Audit::record('payout.batch_paid', $locked, ['reference' => $locked->reference, 'bank_reference' => $bankReference, 'total' => $locked->total, 'count' => $locked->count]);

            return $payouts;
        });

        if ($payouts === null) {
            return false;
        }

        $batch->refresh();
        foreach ($payouts as $payout) {
            $this->notifyPaid($payout->fresh());
        }

        return true;
    }

    /**
     * Drop a draft batch: its pending payouts are deleted and their parts and adjustments released,
     * so the earnings are available for a later batch. An exported file may already be at the bank,
     * so only drafts can be cancelled.
     */
    public function cancelBatch(PayoutBatch $batch): bool
    {
        return DB::transaction(function () use ($batch) {
            $locked = PayoutBatch::whereKey($batch->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->isDraft()) {
                return false;
            }

            $ids = SellerPayout::where('payout_batch_id', $batch->id)->where('status', 'pending')->lockForUpdate()->pluck('id');
            SellerOrder::whereIn('payout_id', $ids)->update(['payout_id' => null]);
            SellerAdjustment::whereIn('payout_id', $ids)->update(['payout_id' => null]);
            SellerPayout::whereIn('id', $ids)->delete();

            $locked->update(['status' => 'cancelled', 'total' => 0, 'count' => 0]);
            $batch->refresh();

            return true;
        });
    }

    /**
     * Shops that could go in a new batch: everything owed to each, and why it can't be included if so.
     *
     * @return \Illuminate\Support\Collection<int, array{seller: User, available: float, blocked: ?string}>
     */
    public function batchCandidates()
    {
        $all = $this->balancesForAll();
        $sellers = User::whereIn('id', array_keys($all))->with(['bankAccount', 'businessVerification'])->orderByRaw('COALESCE(business_name, name)')->get();

        return $sellers
            ->map(fn (User $seller) => ['seller' => $seller, 'available' => $all[$seller->id]['available'], 'blocked' => $this->payoutBlockedReason($seller)])
            ->filter(fn ($row) => $row['available'] > 0)
            ->values();
    }

    /**
     * Allocate a shop's payable parts and open adjustments to a new payout. Inside a transaction.
     */
    protected function allocate(User $seller, ?array $partIds, ?string $reference, ?string $note, ?User $admin, ?PayoutBatch $batch): ?SellerPayout
    {
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
            'status' => $batch ? 'pending' : 'paid',
            'reference' => $reference,
            'note' => $note,
            'created_by' => $admin?->id,
            'payout_batch_id' => $batch?->id,
            'paid_at' => $batch ? null : now(),
        ]);

        SellerOrder::whereIn('id', $parts->pluck('id'))->update(['payout_id' => $payout->id]);
        SellerAdjustment::whereIn('id', $adjustments->pluck('id'))->update(['payout_id' => $payout->id]);
        app(GstService::class)->payoutPaid($payout); // numbers iruali's commission invoice once the payout is paid
        \App\Support\Audit::record('payout.created', $payout, ['seller_id' => $seller->id, 'shop' => $seller->business_name ?: $seller->name, 'amount' => $amount, 'reference' => $reference, 'batch' => $batch?->reference, 'parts' => $parts->count()]);

        return $payout;
    }

    protected function notifyPaid(SellerPayout $payout): void
    {
        $seller = $payout->seller;
        if (! $seller?->email) {
            return;
        }

        try {
            $seller->notify(new PayoutPaid($payout));
        } catch (Throwable $e) {
            report($e);
        }
    }
}
