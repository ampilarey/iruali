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
            ->having('referrals_count', '>', 0)
            ->orderByDesc('rewarded_referrals_count')->orderByDesc('referrals_count')
            ->take(20)
            ->get();
        $referralPoints = PointsTransaction::where('type', 'referral')->whereIn('user_id', $topReferrers->pluck('id'))
            ->select('user_id', DB::raw('SUM(points) as points'))->groupBy('user_id')->pluck('points', 'user_id');

        $recent = PointsTransaction::with(['user', 'order'])->orderByDesc('created_at')->orderByDesc('id')->take(30)->get();

        return view('admin.rewards.index', compact('totals', 'topReferrers', 'referralPoints', 'recent'));
    }
}
