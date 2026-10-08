<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogService;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function search(Request $request, CatalogService $catalog)
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '') {
            return redirect()->route('shop', $request->only('category'));
        }

        // The header's department picker sends a department; show that department's page with the search applied.
        return view('catalog.index', $catalog->listing($request) + [
            'title' => __('Results for “:q”', ['q' => $q]),
            'crumbs' => [],
        ]);
    }

    /**
     * Suggestions for the header search box as the shopper types.
     */
    public function suggest(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['products' => [], 'departments' => [], 'brands' => []]);
        }

        $like = '%'.mb_strtolower(str_replace(['%', '_'], ['\%', '\_'], $q)).'%';

        $products = Product::query()->active()
            ->with('mainImage')
            ->where('search_text', 'like', $like)
            ->orderByRaw('CASE WHEN stock_quantity > 0 THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN LOWER(CAST(name AS CHAR)) LIKE ? THEN 0 ELSE 1 END', [$like])
            ->take(6)
            ->get()
            ->map(fn (Product $p) => [
                'name' => $p->name,
                'url' => route('products.show', $p),
                'image' => $p->mainImage->url ?? '/images/product-placeholder.svg',
                'price' => Money::format($p->final_price),
                'in_stock' => $p->stock_quantity > 0,
            ]);

        $departments = Category::active()->whereRaw('LOWER(CAST(name AS CHAR)) LIKE ?', [$like])->take(3)->get()
            ->map(fn (Category $c) => ['name' => $c->localized_name, 'url' => route('categories.show', $c)]);

        // By either name; Dhivehi pages show the Dhivehi one
        $brands = Brand::query()->listed()
            ->where(fn (Builder $names) => $names->where('name', 'like', $like)->orWhere('name_dv', 'like', $like))
            ->orderBy('name')->take(3)->get(['id', 'name', 'name_dv', 'slug'])
            ->map(fn (Brand $b) => ['name' => $b->localizedName(), 'url' => route('brands.show', $b)]);

        return response()->json(['products' => $products, 'departments' => $departments, 'brands' => $brands]);
    }
}
