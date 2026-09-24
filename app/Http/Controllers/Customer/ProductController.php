<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Setting;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request, CatalogService $catalog)
    {
        return view('catalog.index', $catalog->listing($request) + [
            'title' => __('All products'),
            'crumbs' => [],
        ]);
    }

    public function show(Request $request, Product $product)
    {
        abort_unless($product->is_active, 404);

        $product->load([
            'category.parent', 'images', 'variants', 'seller',
            'reviews' => fn ($q) => $q->where('is_approved', true)->latest(),
        ]);

        $cards = fn ($q) => $q->active()->with(['category', 'mainImage', 'seller'])
            ->withCount(['reviews as rating_count' => fn ($r) => $r->where('is_approved', true)])
            ->withAvg(['reviews as rating_avg' => fn ($r) => $r->where('is_approved', true)], 'rating');

        $moreFromSeller = $product->seller_id
            ? $cards(Product::query())->where('seller_id', $product->seller_id)->whereKeyNot($product->id)->inStock()->latest()->take(8)->get()
            : collect();

        // Other shops' products in the same department (this shop's are already in the row above).
        $relatedProducts = $cards(Product::query())
            ->where('category_id', $product->category_id)
            ->whereKeyNot($product->id)
            ->whereNotIn('id', $moreFromSeller->pluck('id'))
            ->orderByRaw('CASE WHEN stock_quantity > 0 THEN 0 ELSE 1 END')
            ->inRandomOrder()
            ->take(8)
            ->get();

        // Recently viewed, newest first; this product goes to the front for the next page.
        $viewed = collect($request->session()->get('recently_viewed', []))->reject(fn ($id) => $id === $product->id);
        $recentlyViewed = $viewed->isEmpty() ? collect() : $cards(Product::query())->whereIn('id', $viewed)->get()
            ->sortBy(fn ($p) => $viewed->search($p->id))->values();
        $request->session()->put('recently_viewed', $viewed->prepend($product->id)->take(12)->values()->all());

        $delivery = [
            'male' => (float) Setting::get('delivery_fee_greater_male'),
            'islands' => (float) Setting::get('delivery_fee_islands'),
            'free_over' => (float) Setting::get('free_delivery_over'),
        ];

        return view('products.show', compact('product', 'relatedProducts', 'moreFromSeller', 'recentlyViewed', 'delivery'));
    }
}
