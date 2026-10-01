<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\PointsTransaction;
use App\Models\Setting;
use App\Models\User;
use App\Services\PointsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Account → Rewards: the referral code and link, who signed up with it, and the points ledger.
 * /r/{code} remembers the code in a cookie for a month so a friend who signs up later still counts.
 */
class RewardsController extends Controller
{
    public const COOKIE = 'referral_code';

    public const COOKIE_DAYS = 30;

    public function index(Request $request, PointsService $points)
    {
        $user = $request->user();
        if (! $user->referral_code) {
            $user->forceFill(['referral_code' => $this->freshCode()])->save();
        }

        $referrals = $user->referrals()->orderByDesc('created_at')->get()->map(function (User $referee) use ($user) {
            $firstPaid = Order::where('user_id', $referee->id)->where('payment_status', 'paid')->orderBy('paid_at')->first();
            $reward = PointsTransaction::where('user_id', $user->id)->where('type', 'referral')
                ->when($firstPaid, fn ($q) => $q->where('order_id', $firstPaid->id))
                ->when(! $firstPaid, fn ($q) => $q->whereRaw('1 = 0'))
                ->first();

            return [
                'name' => PointsService::maskName($referee->name),
                'signed_up_at' => $referee->created_at,
                'status' => $referee->referral_rewarded_at ? 'rewarded' : ($firstPaid ? 'paid' : 'signed_up'),
                'status_at' => $referee->referral_rewarded_at ?? $firstPaid?->paid_at,
                'points' => $reward?->points ?? ($referee->referral_rewarded_at ? (int) Setting::get('referral_referrer_points') : 0),
            ];
        });

        $history = $user->pointsTransactions()->with('order')->orderByDesc('created_at')->orderByDesc('id')->paginate(20);
        $expiryMonths = $points->expiryMonths();
        $expiringSoon = $expiryMonths > 0 ? min($points->pointsExpiringBefore($user, now()->addMonth()->subMonths($expiryMonths)), max(0, (int) $user->loyalty_points)) : 0;

        $shareUrl = route('referral.visit', $user->referral_code);
        $shareText = __('Join me on iruali: shops from every island, delivered to yours. Sign up with my link and we both get loyalty points on your first order.');

        return view('account.rewards', compact('user', 'referrals', 'history', 'shareUrl', 'shareText', 'expiryMonths', 'expiringSoon'));
    }

    /**
     * A shared referral link: remember the code for a month and show the home page.
     */
    public function visit(string $code)
    {
        $code = strtoupper($code);
        $exists = User::where('referral_code', $code)->exists();

        $response = redirect()->route('home');

        return $exists ? $response->withCookie(cookie(self::COOKIE, $code, self::COOKIE_DAYS * 24 * 60)) : $response;
    }

    protected function freshCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }
}
