<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerOrder;
use App\Models\SellerPayout;
use App\Models\Setting;
use App\Models\User;
use App\Services\PayoutService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * What iruali owes each shop, paying shops out, and commission rates.
 */
class PayoutController extends Controller
{
    public function index(PayoutService $payouts)
    {
        $sellers = User::query()
            ->where(fn ($q) => $q->where('is_seller', true)->orWhereHas('sellerOrders'))
            ->orderByRaw('COALESCE(business_name, name)')
            ->with('bankAccount')
            ->get();
        $all = $payouts->balancesForAll();
        $sellers = $sellers->map(function (User $seller) use ($all) {
            // Shown on the page only (not a column): the view reads $seller->balances
            $seller->setAttribute('balances', $all[$seller->id] ?? ['available' => 0.0, 'pending' => 0.0, 'adjustments' => 0.0, 'paid' => 0.0, 'commission' => 0.0]);

            return $seller;
        });

        $total = fn (string $key) => $sellers->sum(fn (User $s) => $s->getAttribute('balances')[$key]);
        $totals = [
            'available' => $total('available'),
            'pending' => $total('pending'),
            'paid' => $total('paid'),
            'commission' => $total('commission'),
        ];

        $recent = SellerPayout::with(['seller', 'batch'])->latest('id')->take(15)->get();
        $defaultRate = (float) Setting::get('default_commission_rate');

        return view('admin.payouts.index', compact('sellers', 'totals', 'recent', 'defaultRate'));
    }

    public function create(User $seller, PayoutService $payouts)
    {
        $parts = SellerOrder::where('seller_id', $seller->id)->payable()->with(['order', 'returnRequests'])->oldest()->get();
        $adjustments = $payouts->openAdjustments($seller);
        $blocked = $payouts->payoutBlockedReason($seller);

        return view('admin.payouts.create', compact('seller', 'parts', 'adjustments', 'blocked'));
    }

    public function store(Request $request, User $seller, PayoutService $payouts)
    {
        $data = $request->validate([
            'parts' => 'required|array|min:1',
            'parts.*' => 'integer',
            'reference' => 'required|string|max:100',
            'note' => 'nullable|string|max:1000',
        ]);

        if ($blocked = $payouts->payoutBlockedReason($seller)) {
            return back()->with('error', $blocked);
        }

        $payout = $payouts->createPayout($seller, array_map('intval', $data['parts']), $data['reference'], $data['note'] ?? null, $request->user());

        if (! $payout) {
            return back()->with('error', 'Nothing to pay: the selected orders are no longer payable, or return deductions are more than they add up to.');
        }

        return redirect()->route('admin.payouts.show', $payout)->with('success', 'Payout recorded.');
    }

    public function show(Request $request, SellerPayout $payout)
    {
        $payout->load(['seller', 'creator', 'sellerOrders.order', 'adjustments']);

        if ($request->query('export') === 'csv') {
            return $this->csv($payout);
        }

        return view('admin.payouts.show', compact('payout'));
    }

    public function updateCommission(Request $request, User $seller)
    {
        $data = $request->validate(['commission_rate' => 'nullable|numeric|min:0|max:100']);

        // Applies to new orders; existing orders keep the rate they were placed with.
        $seller->forceFill(['commission_rate' => $data['commission_rate'] === null || $data['commission_rate'] === '' ? null : $data['commission_rate']])->save();

        return back()->with('success', 'Commission for '.($seller->business_name ?: $seller->name).' updated. It applies to new orders.');
    }

    /**
     * Tick or untick "verified" on a shop's bank account once it has been checked.
     */
    public function toggleBankVerified(User $seller)
    {
        $account = $seller->bankAccount;
        if (! $account) {
            return back()->with('error', __('This shop has not added a bank account yet, so it cannot be paid out.'));
        }

        $account->forceFill(['verified_at' => $account->verified_at ? null : now()])->save();

        return back()->with('success', $account->verified_at
            ? __('Bank account for :shop marked as verified.', ['shop' => $seller->shopName()])
            : __('Bank account for :shop is no longer marked as verified.', ['shop' => $seller->shopName()]));
    }

    protected function csv(SellerPayout $payout): StreamedResponse
    {
        return response()->streamDownload(function () use ($payout) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['payout_id', 'shop', 'paid_at', 'reference', 'order', 'order_date', 'items', 'commission_rate', 'commission', 'shop_earnings']);
            $shop = $payout->seller?->business_name ?: $payout->seller?->name;
            foreach ($payout->sellerOrders as $part) {
                fputcsv($out, [
                    $payout->id, $shop, $payout->paid_at?->toDateString(), $payout->reference,
                    $part->order?->order_number, $part->order?->created_at?->toDateString(),
                    $part->subtotal, $part->commission_rate, $part->commission_amount, $part->seller_earnings,
                ]);
            }
            foreach ($payout->adjustments as $adjustment) {
                fputcsv($out, [
                    $payout->id, $shop, $payout->paid_at?->toDateString(), $payout->reference,
                    $adjustment->reason, $adjustment->created_at?->toDateString(), '', '', '', $adjustment->amount,
                ]);
            }
            fclose($out);
        }, 'payout-'.$payout->id.'.csv', ['Content-Type' => 'text/csv']);
    }
}
