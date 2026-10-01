<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Product;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Seller Centre → Campaigns: see the campaigns that are open, put products in at a discount
 * (at least the campaign's minimum) and take them out again while they wait for approval.
 */
class CampaignController extends Controller
{
    public function index(Request $request)
    {
        $seller = $request->user();

        $campaigns = Campaign::joinable()->ordered()
            ->withCount([
                'participations as mine_count' => fn ($q) => $q->where('seller_id', $seller->id),
                'participations as mine_approved_count' => fn ($q) => $q->where('seller_id', $seller->id)->whereNotNull('approved_at'),
            ])
            ->get();

        return view('seller.campaigns.index', compact('campaigns'));
    }

    public function show(Request $request, Campaign $campaign)
    {
        abort_unless($campaign->isJoinable(), 404);
        $seller = $request->user();

        $products = Product::where('seller_id', $seller->id)->where('is_active', true)->orderBy('id')->get();
        $participations = $campaign->participations()->where('seller_id', $seller->id)->get()->keyBy('product_id');

        return view('seller.campaigns.show', compact('campaign', 'products', 'participations'));
    }

    /**
     * Put the chosen products in. A product already in the campaign keeps its approval only if the
     * discount is unchanged; a changed discount goes back to pending.
     */
    public function store(Request $request, Campaign $campaign)
    {
        abort_unless($campaign->isJoinable(), 404);
        $seller = $request->user();
        $minimum = $campaign->minimumDiscount();

        $data = $request->validate([
            'products' => 'required|array|min:1',
            'products.*' => 'integer',
            'discount_percent' => ['required', 'numeric', 'min:'.$minimum, 'max:90'],
        ], [
            'discount_percent.min' => __('The discount must be at least :min% for this campaign.', ['min' => rtrim(rtrim(number_format($minimum, 2, '.', ''), '0'), '.')]),
            'products.required' => __('Choose at least one product.'),
        ]);

        // Only the shop's own active products can go in
        $products = Product::where('seller_id', $seller->id)->where('is_active', true)->whereIn('id', $data['products'])->get();
        if ($products->count() !== count(array_unique($data['products']))) {
            abort(403);
        }

        $discount = round((float) $data['discount_percent'], 2);
        $added = 0;
        foreach ($products as $product) {
            $row = CampaignProduct::firstOrNew(['campaign_id' => $campaign->id, 'product_id' => $product->id]);
            if ($row->exists && $row->seller_id !== $seller->id) {
                continue;
            }
            if ($row->exists && (float) $row->discount_percent !== $discount) {
                $row->approved_at = null; // a new discount needs a fresh look
            }
            $row->seller_id = $seller->id;
            $row->discount_percent = $discount;
            $row->save();
            $added++;
        }

        NotificationService::success(trans_choice(':count product submitted to the campaign. An admin will approve it shortly.|:count products submitted to the campaign. An admin will approve them shortly.', $added, ['count' => $added]));

        return redirect()->route('seller.campaigns.show', $campaign);
    }

    public function destroy(Request $request, Campaign $campaign, CampaignProduct $participation)
    {
        abort_unless($participation->campaign_id === $campaign->id && $participation->seller_id === $request->user()->id, 403);
        $participation->delete();

        NotificationService::success(__('Product taken out of the campaign.'));

        return redirect()->route('seller.campaigns.show', $campaign);
    }
}
