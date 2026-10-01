<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Services\GiftCardService;
use App\Services\WalletService;
use Illuminate\Http\Request;

/**
 * Admin: gift cards list (with cancel) and "refund to wallet" on orders and returns.
 */
class WalletAdminController extends Controller
{
    public function giftCards(Request $request)
    {
        $status = $request->query('status');
        $cards = GiftCard::with(['purchaser', 'redeemer', 'order'])
            ->when(in_array($status, ['pending', 'active', 'redeemed', 'expired', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('code', 'like', '%'.$request->q.'%')->orWhere('recipient_email', 'like', '%'.$request->q.'%')))
            ->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString();
        $counts = GiftCard::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');
        $outstanding = (float) GiftCard::where('status', 'active')->sum('balance');

        return view('admin.gift-cards.index', compact('cards', 'status', 'counts', 'outstanding'));
    }

    public function cancelGiftCard(GiftCard $giftCard, GiftCardService $giftCards)
    {
        if (! $giftCards->cancel($giftCard)) {
            return back()->with('error', 'Only an active or pending gift card can be cancelled.');
        }

        return back()->with('success', 'Gift card '.($giftCard->code ?? '#'.$giftCard->id).' cancelled.');
    }

    public function refundOrderToWallet(Order $order, WalletService $wallet)
    {
        if (! $wallet->refundOrderToWallet($order)) {
            return back()->with('error', 'No refund is due on this order, or it has no customer to credit.');
        }

        return back()->with('success', 'Refunded to the customer\'s wallet. The customer has been emailed.');
    }

    public function refundReturnToWallet(ReturnRequest $return, WalletService $wallet)
    {
        if (! $wallet->refundReturnToWallet($return)) {
            return back()->with('error', 'Only an approved return can be refunded to the wallet.');
        }

        return back()->with('success', 'Refunded to the customer\'s wallet. The customer has been emailed.');
    }
}
