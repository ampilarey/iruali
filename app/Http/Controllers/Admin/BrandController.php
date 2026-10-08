<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandService;
use App\Support\Audit;
use App\Traits\SecureFileUpload;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Brands: the shared brand list. Shops add brands by typing them on a product; here an
 * admin checks each new one, fixes the spelling, adds a logo and description, and merges
 * duplicates ("Samsung Electronics" into "Samsung").
 */
class BrandController extends Controller
{
    use SecureFileUpload;

    public function __construct(protected BrandService $brands) {}

    public function index(Request $request)
    {
        $show = $request->query('show') === 'review' ? 'review' : 'all';
        $q = trim((string) $request->query('q', ''));

        $brands = Brand::query()
            ->withCount(['products', 'products as active_products_count' => fn ($products) => $products->active()])
            ->with('creator:id,name,business_name')
            ->when($show === 'review', fn ($query) => $query->whereNull('reviewed_at'))
            ->when($q !== '', fn ($query) => $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
            ->orderBy('name')
            ->paginate(50)
            ->withQueryString();

        return view('admin.brands.index', [
            'brands' => $brands,
            'show' => $show,
            'q' => $q,
            'toReview' => Brand::whereNull('reviewed_at')->count(),
            'total' => Brand::count(),
        ]);
    }

    public function edit(Brand $brand)
    {
        $brand->loadCount(['products', 'products as active_products_count' => fn ($products) => $products->active()])
            ->load(['aliases', 'creator:id,name,business_name']);
        $similar = $this->brands->similar($brand);

        return view('admin.brands.edit', [
            'brand' => $brand,
            'similar' => $similar,
            'others' => Brand::where('id', '!=', $brand->id)->whereNotIn('id', $similar->pluck('id'))->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description_en' => ['nullable', 'string', 'max:600'],
            'description_dv' => ['nullable', 'string', 'max:600'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024'],
            'remove_logo' => ['sometimes', 'boolean'],
            'reviewed' => ['sometimes', 'boolean'],
        ], [
            'slug.regex' => 'Use lower-case letters, digits and single hyphens only, like "dr-martens".',
            'logo.max' => 'The logo must be under 1 MB.',
        ]);

        $name = Brand::cleanName($data['name']);
        $key = Brand::keyFor($name);
        if (Brand::isNoBrand($key)) {
            return back()->withErrors(['name' => 'That is not a brand name.'])->withInput();
        }
        if ($owner = $this->brands->keyOwner($key, $brand)) {
            return back()->withErrors(['name' => "“{$owner->name}” already has this name. Merge the two brands instead."])->withInput();
        }
        if ($this->brands->slugTaken($data['slug'], $brand)) {
            return back()->withErrors(['slug' => 'Another brand uses this web address, now or as an old address.'])->withInput();
        }

        $oldLogo = $brand->logo;
        $logo = $oldLogo;
        if ($request->hasFile('logo')) {
            $logo = $this->storeFileSecurely($request->file('logo'), 'brands', ['image/jpeg', 'image/png', 'image/webp'], 1024);
            if (! $logo) {
                return back()->withErrors(['logo' => 'The logo must be a JPG, PNG or WebP image under 1 MB.'])->withInput();
            }
        } elseif ($request->boolean('remove_logo')) {
            $logo = null;
        }

        $before = $brand->only(['name', 'slug']);
        $this->brands->update($brand, [
            'name' => $name,
            'slug' => $data['slug'],
            'description' => ['en' => $data['description_en'] ?? null, 'dv' => $data['description_dv'] ?? null],
            'logo' => $logo,
            'reviewed_at' => $request->boolean('reviewed') ? ($brand->reviewed_at ?? now()) : null,
        ]);
        if ($oldLogo && $oldLogo !== $logo) {
            $this->deleteFile($oldLogo);
        }

        Audit::record('brand.updated', $brand, array_filter([
            'name' => $before['name'] !== $brand->name ? $before['name'].' → '.$brand->name : null,
            'slug' => $before['slug'] !== $brand->slug ? $before['slug'].' → '.$brand->slug : null,
            'logo' => $oldLogo !== $logo ? ($logo ? 'new logo' : 'removed') : null,
            'reviewed' => $brand->reviewed_at ? 'yes' : 'no',
        ]));

        return redirect()->route('admin.brands.edit', $brand)->with('success', 'Brand saved.');
    }

    public function review(Brand $brand)
    {
        $brand->update(['reviewed_at' => $brand->reviewed_at ?? now()]);
        Audit::record('brand.reviewed', $brand, ['name' => $brand->name]);

        return back()->with('success', "“{$brand->name}” marked as reviewed.");
    }

    public function merge(Request $request, Brand $brand)
    {
        $data = $request->validate([
            'target_id' => ['required', 'integer', Rule::exists('brands', 'id'), Rule::notIn([$brand->id])],
        ], ['target_id.required' => 'Choose the brand to merge into.']);

        $target = Brand::findOrFail($data['target_id']);
        $name = $brand->name;
        $moved = $this->brands->merge($brand, $target);
        Audit::record('brand.merged', $target, ['from' => $name, 'into' => $target->name, 'products' => $moved]);

        return redirect()->route('admin.brands.edit', $target)
            ->with('success', "Merged “{$name}” into “{$target->name}”: {$moved} product(s) moved. The old brand page now redirects here.");
    }

    public function destroy(Brand $brand)
    {
        if (! $this->brands->delete($brand)) {
            return back()->with('error', 'Shops still list products under this brand. Merge it into another brand instead.');
        }
        Audit::record('brand.deleted', null, ['name' => $brand->name, 'slug' => $brand->slug]);

        return redirect()->route('admin.brands')->with('success', "Deleted “{$brand->name}”.");
    }
}
