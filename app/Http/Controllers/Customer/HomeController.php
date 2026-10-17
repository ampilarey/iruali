<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;

class HomeController extends Controller
{
    public function index()
    {
        // "Delivering to <island>": every row shows what reaches the island (or only its own shops)
        $shopping = app(\App\Services\ShoppingIsland::class);
        $coverage = app(\App\Services\ShopCoverage::class);
        $island = $shopping->filterIsland();
        $view = $shopping->view();

        // Every listed product (with what its card needs); the rows below are what can be bought now
        $listed = fn () => $coverage->apply(Product::query(), $island, $view)->active()
            ->with(['category', 'mainImage', 'seller', 'brandModel'])
            ->withCount(['reviews as rating_count' => fn ($r) => $r->where('is_approved', true)])
            ->withAvg(['reviews as rating_avg' => fn ($r) => $r->where('is_approved', true)], 'rating');
        $cards = fn () => $listed()->inStock()->notComingSoon();

        $deals = $cards()->onSale()->orderByRaw('(compare_price - price) / compare_price DESC')->take(10)->get();

        $featured = $cards()->featured()->latest()->take(10)->get();
        if ($featured->isEmpty()) {
            $featured = $cards()->latest()->take(10)->get();
        }

        $newArrivals = $cards()->latest()->take(10)->get();

        // Counts follow the island too, so a department never promises products the shopper can't get
        $departments = Category::active()->root()
            ->withCount(['products' => fn ($q) => $coverage->apply($q->active(), $island, $view)])
            ->get()
            ->sortBy(fn ($c) => $c->localized_name)
            ->values();

        $shops = User::query()
            ->where('is_seller', true)->where('seller_approved', true)
            ->when($island && $view === \App\Services\ShoppingIsland::VIEW_LOCAL, fn ($q) => $q->where('home_island_id', $island->id))
            ->when($island && $view === \App\Services\ShoppingIsland::VIEW_DELIVERS, fn ($q) => $q->whereNotIn('id', $coverage->blockedSellerIds($island)))
            ->withCount(['products' => fn ($q) => $q->active()])
            ->whereHas('products', fn ($q) => $q->active())
            ->orderByDesc('products_count')
            ->take(8)
            ->get();

        // Live campaigns: the first hero one replaces the default hero, strip ones line up under it
        $campaigns = \App\Models\Campaign::live()->ordered()->get();
        $heroCampaign = $campaigns->firstWhere('placement', 'home_hero');
        $stripCampaigns = $campaigns->where('placement', 'home_strip')->values();

        // Shop by brand: the brands people buy most, then the ones with the most products
        $brands = app(\App\Services\BrandService::class)->popular(12);

        // "Shops on <island>": the shops based on the chosen island, when there are any
        $chosen = $shopping->island();
        $localShops = $chosen ? $coverage->localShops($chosen) : collect();

        // "Pre-order now" (soonest to ship first) and "Coming soon" (soonest launch first, undated last)
        $preorders = $listed()->preorderable()->orderBy('preorder_ship_date')->take(10)->get();
        $comingSoon = $listed()->comingSoon()->orderByRaw('available_on IS NULL')->orderBy('available_on')->latest()->take(10)->get();

        return view('home', compact('deals', 'featured', 'newArrivals', 'preorders', 'comingSoon', 'departments', 'shops', 'brands', 'heroCampaign', 'stripCampaigns', 'chosen', 'localShops'));
    }
}
