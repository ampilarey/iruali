<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin → Campaigns: create and schedule sales and events, upload the banner, approve the products
 * shops put in.
 */
class CampaignController extends Controller
{
    public function index()
    {
        $campaigns = Campaign::query()
            ->with('brand:id,name')
            ->withCount(['participations', 'participations as pending_count' => fn ($q) => $q->whereNull('approved_at')])
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('admin.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        return view('admin.campaigns.form', ['campaign' => new Campaign(['theme_colour' => '#0E7C86', 'is_active' => true, 'placement' => 'home_hero', 'type' => 'sale']), 'brands' => $this->brandChoices()]);
    }

    public function store(Request $request)
    {
        $campaign = new Campaign($this->validated($request));
        $campaign->slug = $campaign->uniqueSlug($request->input('slug') ?: $campaign->name);
        if ($request->hasFile('banner_image')) {
            $campaign->banner_image = Storage::url($request->file('banner_image')->store('campaigns', 'public'));
        }
        $campaign->save();

        return redirect()->route('admin.campaigns.edit', $campaign)->with('success', 'Campaign created.');
    }

    public function edit(Campaign $campaign)
    {
        $campaign->load(['participations.product', 'participations.seller', 'brand']);

        return view('admin.campaigns.form', ['campaign' => $campaign, 'brands' => $this->brandChoices()]);
    }

    public function update(Request $request, Campaign $campaign)
    {
        $data = $this->validated($request, $campaign);
        if ($error = $this->brandChangeBlocked($campaign, $data['brand_id'])) {
            return back()->withErrors(['brand_id' => $error])->withInput();
        }
        $campaign->fill($data);
        if ($request->filled('slug')) {
            $campaign->slug = $campaign->uniqueSlug($request->input('slug'));
        }
        if ($request->hasFile('banner_image')) {
            $campaign->banner_image = Storage::url($request->file('banner_image')->store('campaigns', 'public'));
        } elseif ($request->boolean('remove_banner')) {
            $campaign->banner_image = null;
        }
        $campaign->save();

        return redirect()->route('admin.campaigns.edit', $campaign)->with('success', 'Campaign saved.');
    }

    public function destroy(Campaign $campaign)
    {
        $campaign->delete();

        return redirect()->route('admin.campaigns.index')->with('success', 'Campaign deleted.');
    }

    public function approve(Campaign $campaign, CampaignProduct $participation)
    {
        abort_unless($participation->campaign_id === $campaign->id, 404);
        $product = Product::withTrashed()->find($participation->product_id);
        if ($product && ! $campaign->acceptsProduct($product)) {
            return back()->withErrors(['participation' => __('This product is not a :brand product, so it cannot be approved for this campaign.', ['brand' => $campaign->brand?->name])]);
        }
        $participation->update(['approved_at' => now()]);

        return back()->with('success', 'Product approved for the campaign.');
    }

    public function reject(Campaign $campaign, CampaignProduct $participation)
    {
        abort_unless($participation->campaign_id === $campaign->id, 404);
        $participation->delete();

        return back()->with('success', 'Product removed from the campaign.');
    }

    protected function validated(Request $request, ?Campaign $campaign = null): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'slug' => ['nullable', 'string', 'max:140', 'regex:/^[a-z0-9-]+$/'],
            'type' => ['required', Rule::in(Campaign::TYPES)],
            'starts_at' => 'required|date',
            'ends_at' => 'required|date|after:starts_at',
            'headline_en' => 'required|string|max:120',
            'headline_dv' => 'nullable|string|max:120',
            'subheadline_en' => 'nullable|string|max:200',
            'subheadline_dv' => 'nullable|string|max:200',
            'cta_text_en' => 'nullable|string|max:40',
            'cta_text_dv' => 'nullable|string|max:40',
            'cta_url' => 'nullable|string|max:255',
            'theme_colour' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_active' => 'sometimes|boolean',
            'discount_percent' => 'nullable|numeric|min:0|max:90',
            'placement' => ['required', Rule::in(Campaign::PLACEMENTS)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')],
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'banner_image' => 'nullable|image|max:4096',
        ], ['brand_id.exists' => __('Choose a brand from the list.')]);

        $translation = fn (string $field) => array_filter([
            'en' => $data[$field.'_en'] ?? null,
            'dv' => $data[$field.'_dv'] ?? null,
        ], fn ($v) => filled($v));

        return [
            'name' => $data['name'],
            'type' => $data['type'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'],
            'headline' => $translation('headline'),
            'subheadline' => $translation('subheadline'),
            'cta_text' => $translation('cta_text'),
            'cta_url' => $data['cta_url'] ?? null,
            'theme_colour' => $data['theme_colour'],
            'is_active' => $request->boolean('is_active'),
            'discount_percent' => $data['discount_percent'] ?? null,
            'placement' => $data['placement'],
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'brand_id' => isset($data['brand_id']) ? (int) $data['brand_id'] : null,
        ];
    }

    /**
     * Every brand, A to Z, for the "Only for brand" picker.
     *
     * @return EloquentCollection<int, Brand>
     */
    protected function brandChoices(): EloquentCollection
    {
        return Brand::query()->orderBy('name')->get(['id', 'name']);
    }

    /**
     * Products from other brands must not keep a brand campaign's price, so a campaign cannot be
     * given a brand (or another brand) while it holds products that are not that brand's. Returns
     * why, or null when the change is fine. The admin removes those products first (below the form).
     */
    protected function brandChangeBlocked(Campaign $campaign, ?int $brandId): ?string
    {
        if ($brandId === null || $brandId === (int) $campaign->brand_id) {
            return null;
        }

        $others = $campaign->participations()
            ->whereDoesntHave('product', fn (Builder $product) => $product->where('brand_id', $brandId))
            ->count();
        if ($others === 0) {
            return null;
        }

        return trans_choice(':count product in this campaign is not a :brand product. Remove it below first, or keep the campaign open to every brand.|:count products in this campaign are not :brand products. Remove them below first, or keep the campaign open to every brand.', $others, [
            'count' => $others,
            'brand' => Brand::query()->whereKey($brandId)->value('name'),
        ]);
    }
}
