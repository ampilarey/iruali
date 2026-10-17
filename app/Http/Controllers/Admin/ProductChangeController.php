<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProductChangeRequest;
use App\Models\User;
use App\Services\ProductReviewService;
use App\Support\Audit;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Admin → Products → Changes waiting: edits shops made to approved products (ProductReviewService),
 * each with a before → after page to approve or reject (admin and catalogue staff). Also the
 * admin-only switch that makes a shop trusted, so its listings and edits skip review.
 */
class ProductChangeController extends Controller
{
    public function __construct(protected ProductReviewService $review) {}

    public function index()
    {
        $changes = ProductChangeRequest::pending()
            ->whereHas('product')
            ->with(['product', 'seller', 'submitter'])
            ->oldest()
            ->paginate(20);

        return view('admin.products.changes', compact('changes'));
    }

    public function show(ProductChangeRequest $change)
    {
        $change->load(['product.category', 'seller', 'submitter', 'reviewer']);

        return view('admin.products.change', [
            'change' => $change,
            'diff' => $this->review->changes($change),
            'newer' => $change->isPending() ? null : ProductChangeRequest::pending()->where('product_id', $change->product_id)->first(),
        ]);
    }

    public function approve(Request $request, ProductChangeRequest $change)
    {
        try {
            $this->review->approve($change, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('admin.product-changes.show', $change)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.product-changes')
            ->with('success', __('Changes approved. Shoppers see the new version now, and we have emailed the shop.'));
    }

    public function reject(Request $request, ProductChangeRequest $change)
    {
        $data = $request->validate(['reason' => 'required|string|min:5|max:1000'], [
            'reason.required' => __('Give the shop a reason, so it knows what to fix.'),
        ]);

        try {
            $this->review->reject($change, $request->user(), $data['reason']);
        } catch (RuntimeException $e) {
            return redirect()->route('admin.product-changes.show', $change)->with('error', $e->getMessage());
        }

        return redirect()->route('admin.product-changes')
            ->with('success', __('Changes rejected. The product stays as it was, and we have emailed the shop the reason.'));
    }

    /**
     * Trusted shop on or off (Admin → Sellers → the shop's page). Admin role only: the route is on
     * no staff role's list in config/staff.php.
     */
    public function autoApprove(Request $request, User $seller)
    {
        abort_unless($seller->is_seller, 404);
        $on = (bool) $request->validate(['enabled' => 'required|boolean'])['enabled'];

        if ($on !== (bool) $seller->auto_approve_listings) {
            // Not mass-assignable (User::$fillable): only this admin action sets it
            $seller->forceFill([
                'auto_approve_listings' => $on,
                'auto_approve_listings_at' => $on ? now() : null,
                'auto_approve_listings_by' => $on ? $request->user()->id : null,
            ])->save();
            Audit::record($on ? 'seller.auto_approve_on' : 'seller.auto_approve_off', $seller, ['business_name' => $seller->business_name]);
        }

        return back()->with('success', $on
            ? __(':shop is now trusted: its new listings and product changes go live without review.', ['shop' => $seller->shopName()])
            : __(':shop is no longer trusted: its new listings and product changes wait for review again.', ['shop' => $seller->shopName()]));
    }
}
