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
            ->get()
            ->map(function (User $seller) use ($payouts) {
                $seller->balances = $payouts->balances($seller);

                return $seller;
            });

        $totals = [
            'available' => $sellers->sum(fn ($s) => $s->balances['available']),
            'pending' => $sellers->sum(fn ($s) => $s->balances['pending']),
            'paid' => $sellers->sum(fn ($s) => $s->balances['paid']),
            'commission' => $sellers->sum(fn ($s) => $s->balances['commission']),
        ];

        $recent = SellerPayout::with('seller')->latest('paid_at')->take(15)->get();
        $defaultRate = (float) Setting::get('default_commission_rate');

        return view('admin.payouts.index', compact('sellers', 'totals', 'recent', 'defaultRate'));
    }

    public function create(User $seller)
    {
        $parts = SellerOrder::where('seller_id', $seller->id)->payable()->with('order')->oldest()->get();

        return view('admin.payouts.create', compact('seller', 'parts'));
    }

    public function store(Request $request, User $seller, PayoutService $payouts)
    {
        $data = $request->validate([
            'parts' => 'required|array|min:1',
            'parts.*' => 'integer',
            'reference' => 'required|string|max:100',
            'note' => 'nullable|string|max:1000',
        ]);

        $payout = $payouts->createPayout($seller, array_map('intval', $data['parts']), $data['reference'], $data['note'] ?? null, $request->user());

        if (! $payout) {
            return back()->with('error', 'Nothing to pay: the selected orders are no longer payable.');
        }

        return redirect()->route('admin.payouts.show', $payout)->with('success', 'Payout recorded.');
    }

    public function show(Request $request, SellerPayout $payout)
    {
        $payout->load(['seller', 'creator', 'sellerOrders.order']);

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

    protected function csv(SellerPayout $payout): StreamedResponse
    {
        return response()->streamDownload(function () use ($payout) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['payout_id', 'shop', 'paid_at', 'reference', 'order', 'order_date', 'items', 'commission_rate', 'commission', 'shop_earnings']);
            foreach ($payout->sellerOrders as $part) {
                fputcsv($out, [
                    $payout->id, $payout->seller?->business_name ?: $payout->seller?->name, $payout->paid_at->toDateString(), $payout->reference,
                    $part->order?->order_number, $part->order?->created_at?->toDateString(),
                    $part->subtotal, $part->commission_rate, $part->commission_amount, $part->seller_earnings,
                ]);
            }
            fclose($out);
        }, 'payout-'.$payout->id.'.csv', ['Content-Type' => 'text/csv']);
    }
}
