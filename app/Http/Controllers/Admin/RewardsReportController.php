<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PointsTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Rewards: how many points went out, came back and expired, and who refers the most.
 */
class RewardsReportController extends Controller
{
    public function index()
    {
        $byType = PointsTransaction::query()
            ->select('type', DB::raw('SUM(points) as points'), DB::raw('COUNT(*) as rows_count'))
            ->groupBy('type')->get()->keyBy('type');

        $totals = [
            'issued' => (int) ($byType['earned']->points ?? 0) + (int) ($byType['referral']->points ?? 0),
            'earned' => (int) ($byType['earned']->points ?? 0),
            'referral' => (int) ($byType['referral']->points ?? 0),
            'redeemed' => -(int) ($byType['redeemed']->points ?? 0),
            'refunded' => (int) ($byType['refund']->points ?? 0),
            'expired' => -(int) ($byType['expired']->points ?? 0),
            'adjustments' => (int) ($byType['adjustment']->points ?? 0),
            'outstanding' => (int) User::where('loyalty_points', '>', 0)->sum('loyalty_points'),
        ];

        $topReferrers = User::query()
            ->withCount(['referrals', 'referrals as rewarded_referrals_count' => fn ($q) => $q->whereNotNull('referral_rewarded_at')])
            ->whereHas('referrals') // (not HAVING on the count: SQLite refuses it without GROUP BY)
            ->orderByDesc('rewarded_referrals_count')->orderByDesc('referrals_count')
            ->take(20)
            ->get();
        $referralPoints = PointsTransaction::where('type', 'referral')->whereIn('user_id', $topReferrers->pluck('id'))
            ->select('user_id', DB::raw('SUM(points) as points'))->groupBy('user_id')->pluck('points', 'user_id');

        $recent = PointsTransaction::with(['user', 'order'])->orderByDesc('created_at')->orderByDesc('id')->take(30)->get();

        return view('admin.rewards.index', compact('totals', 'topReferrers', 'referralPoints', 'recent'));
    }

    /**
     * Give or take points by hand (a goodwill gesture, a correction), with a reason, audited.
     */
    public function adjust(\Illuminate\Http\Request $request, \App\Services\PointsService $points)
    {
        $data = $request->validate([
            'email' => 'required|email|exists:users,email',
            'points' => 'required|integer|between:-100000,100000|not_in:0',
            'reason' => 'required|string|max:200',
        ], ['email.exists' => 'No account has that email.']);

        $user = User::where('email', $data['email'])->firstOrFail();
        if ((int) $user->loyalty_points + (int) $data['points'] < 0) {
            return back()->withInput()->withErrors(['points' => 'That would take the balance below zero (it is '.(int) $user->loyalty_points.').']);
        }

        $points->record($user, (int) $data['points'], 'adjustment', null, $data['reason']);
        \App\Support\Audit::record('points.adjusted', $user, ['points' => (int) $data['points'], 'reason' => $data['reason']]);

        return back()->with('success', ($data['points'] > 0 ? 'Gave ' : 'Took ').abs((int) $data['points']).' points '.($data['points'] > 0 ? 'to ' : 'from ').$user->name.'. New balance: '.(int) $user->fresh()->loyalty_points.'.');
    }
}
