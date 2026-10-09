<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Campaign landing pages: the banner, a countdown and the participating products at the campaign price.
 */
class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::live()->ordered()->withCount('approvedProducts')->get();

        return view('campaigns.index', compact('campaigns'));
    }

    public function show(Request $request, Campaign $campaign)
    {
        // Live and upcoming campaigns have a page; switched-off or finished ones do not
        abort_unless($campaign->isJoinable(), 404);

        $campaign->loadMissing('brand');
        $products = $campaign->approvedProducts()
            ->active()
            // A brand campaign shows only that brand's products (one that changed brand gets no campaign price)
            ->when($campaign->brand_id, fn (Builder $q) => $q->where('products.brand_id', $campaign->brand_id))
            ->with(['category', 'mainImage', 'seller', 'brandModel'])
            ->withCount(['reviews as rating_count' => fn ($r) => $r->where('is_approved', true)])
            ->withAvg(['reviews as rating_avg' => fn ($r) => $r->where('is_approved', true)], 'rating')
            ->orderByDesc('campaign_products.approved_at')
            ->paginate(24)
            ->withQueryString();

        return view('campaigns.show', compact('campaign', 'products'));
    }
}
