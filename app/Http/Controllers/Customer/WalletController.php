<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Services\GiftCardService;
use App\Services\NotificationService;
use App\Services\WalletService;
use App\Support\Money;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Account → Wallet: the store-credit balance, its history and redeeming a gift card.
 */
class WalletController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $history = $user->walletTransactions()->with(['order', 'giftCard'])->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        $giftCardsBought = $user->giftCardsBought()->orderByDesc('created_at')->take(10)->get();

        return view('account.wallet', compact('user', 'history', 'giftCardsBought'));
    }

    public function redeem(Request $request, GiftCardService $giftCards, WalletService $wallet)
    {
        $data = $request->validate(['code' => 'required|string|max:30']);

        try {
            $card = $giftCards->redeem($request->user(), $data['code'], $wallet);
        } catch (RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        NotificationService::success(__(':amount added to your wallet.', ['amount' => Money::format($card->amount)]));

        return redirect()->route('account.wallet');
    }
}
