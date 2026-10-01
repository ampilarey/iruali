<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\CampaignProduct;
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
            ->withCount(['participations', 'participations as pending_count' => fn ($q) => $q->whereNull('approved_at')])
            ->orderByDesc('starts_at')
            ->paginate(20);

        return view('admin.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        return view('admin.campaigns.form', ['campaign' => new Campaign(['theme_colour' => '#0E7C86', 'is_active' => true, 'placement' => 'home_hero', 'type' => 'sale'])]);
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
        $campaign->load(['participations.product', 'participations.seller']);

        return view('admin.campaigns.form', compact('campaign'));
    }

    public function update(Request $request, Campaign $campaign)
    {
        $campaign->fill($this->validated($request, $campaign));
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
            'sort_order' => 'nullable|integer|min:0|max:1000',
            'banner_image' => 'nullable|image|max:4096',
        ]);

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
        ];
    }
}
