<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SellerVerification;
use App\Models\Setting;
use App\Services\SellerVerificationService;
use App\Support\Audit;
use Illuminate\Http\Request;

/**
 * Admin → Verifications: shops' business documents waiting to be checked. Approve, or reject with a
 * reason; the shop is emailed either way. Also the "Require verified business before payouts" rule,
 * switched on the payouts page. Admins only (no other staff role is given these routes).
 */
class VerificationController extends Controller
{
    public const FILTERS = ['pending', 'approved', 'rejected', 'all'];

    public function __construct(protected SellerVerificationService $verifications) {}

    public function index(Request $request)
    {
        $status = in_array($request->query('status'), self::FILTERS, true) ? (string) $request->query('status') : 'pending';

        $verifications = SellerVerification::query()->forShops()->with(['user', 'reviewer'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($status === 'pending', fn ($q) => $q->oldest('submitted_at'), fn ($q) => $q->latest('updated_at'))
            ->paginate(20)
            ->withQueryString();

        $counts = SellerVerification::query()->forShops()->toBase()
            ->selectRaw('status, count(*) as aggregate')->groupBy('status')
            ->pluck('aggregate', 'status');
        $counts['all'] = $counts->sum();

        return view('admin.verifications.index', compact('verifications', 'status', 'counts'));
    }

    /** One of a shop's documents, for checking it. */
    public function document(SellerVerification $verification, string $document)
    {
        abort_unless(array_key_exists($document, SellerVerification::DOCUMENTS), 404);

        return $this->verifications->documentResponse($verification, $document);
    }

    public function approve(Request $request, SellerVerification $verification)
    {
        if ($this->changedSinceShown($request, $verification)) {
            return back()->with('error', __('This shop sent new documents while you were looking. Check them again before deciding.'));
        }

        $shop = $verification->user?->shopName();

        return $this->verifications->approve($verification, $request->user())
            ? back()->with('success', __(':shop is now a verified business. We have emailed the shop.', ['shop' => $shop]))
            : back()->with('error', __(':shop is already verified.', ['shop' => $shop]));
    }

    public function reject(Request $request, SellerVerification $verification)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000'], [
            'reason.required' => __('Give the shop a reason, so it knows what to fix.'),
        ]);

        if ($this->changedSinceShown($request, $verification)) {
            return back()->with('error', __('This shop sent new documents while you were looking. Check them again before deciding.'));
        }

        $shop = $verification->user?->shopName();

        return $this->verifications->reject($verification, $request->user(), $data['reason'])
            ? back()->with('success', __('Verification for :shop rejected. We have emailed the shop the reason.', ['shop' => $shop]))
            : back()->with('error', __('Verification for :shop is already rejected.', ['shop' => $shop]));
    }

    /**
     * Admin → Payouts: "Require verified business before payouts". When on, payouts and payout
     * batches skip shops whose business is not verified.
     */
    public function updatePayoutRule(Request $request)
    {
        $required = (bool) $request->validate(['required' => 'required|boolean'])['required'];
        $before = SellerVerification::requiredForPayouts();

        if ($before !== $required) {
            Setting::set([SellerVerification::PAYOUT_SETTING => $required ? 1 : 0]);
            Audit::record('payouts.verified_business_rule', null, ['from' => $before, 'to' => $required]);
        }

        return back()->with('success', $required
            ? __('Payouts now need a verified business. Shops that are not verified are held until they are.')
            : __('Payouts no longer need a verified business.'));
    }

    /** The decision form carries the time of the submission it was shown; refuse if newer documents came in. */
    protected function changedSinceShown(Request $request, SellerVerification $verification): bool
    {
        return (string) $request->input('version') !== (string) $verification->submitted_at?->getTimestamp();
    }
}
