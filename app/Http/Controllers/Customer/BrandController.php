<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Services\BrandService;
use App\Services\CatalogService;
use Illuminate\Http\Request;

/**
 * The brands directory (A to Z) and each brand's page: its products from every shop, with the
 * usual filters, under one web address per brand.
 */
class BrandController extends Controller
{
    public function __construct(protected BrandService $brands) {}

    public function index()
    {
        $brands = $this->brands->directory();
        $letters = $brands->groupBy(fn (Brand $brand) => $brand->indexLetter())
            ->sortKeysUsing(fn ($a, $b) => ($a === '#') <=> ($b === '#') ?: strcmp($a, $b));

        return view('brands.index', [
            'brands' => $brands,
            'letters' => $letters,
            // Worth a separate row only when the A–Z list is long
            'popular' => $brands->count() > 12 ? $this->brands->popular(12) : collect(),
        ]);
    }

    public function show(Request $request, string $brand, CatalogService $catalog)
    {
        $model = $this->brands->findForUrl($brand);
        abort_if($model === null, 404);

        // One address per brand: old names, old slugs and other capitalisations move here for good
        if ($model->slug !== $brand) {
            $query = $request->getQueryString();

            return redirect()->to(route('brands.show', $model).($query ? '?'.$query : ''), 301);
        }

        $model->setAttribute('active_products_count', \App\Models\Product::query()->active()->where('brand_id', $model->id)->count());
        abort_if($model->activeProductCount() === 0, 404);

        // The layout's SEO tags and language links read the brand from the route
        $request->route()->setParameter('brand', $model);
        $shops = $this->brands->shops($model);

        return view('catalog.index', $catalog->listing($request, ['brand' => $model]) + [
            'title' => $model->localizedName(),
            'subtitle' => $model->localizedDescription() ?: __('Everything from :brand on iruali.', ['brand' => $model->localizedName()]),
            'crumbs' => [['label' => __('Brands'), 'url' => route('brands.index')]],
            'brand' => $model,
            'brandShops' => $shops['shops'],
            'brandShopCount' => $shops['total'],
            'brandDepartments' => $this->brands->departments($model),
            // Follow / Following (brands._follow) and, once there are a few, how many follow it
            'brandFollowers' => $model->followers()->count(),
            'brandFollowing' => $request->user() !== null && $request->user()->followedBrands()->whereKey($model->id)->exists(),
        ]);
    }
}
