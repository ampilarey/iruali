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
        $cards = fn () => Product::query()->active()->inStock()
            ->with(['category', 'mainImage', 'seller'])
            ->withCount(['reviews as rating_count' => fn ($r) => $r->where('is_approved', true)])
            ->withAvg(['reviews as rating_avg' => fn ($r) => $r->where('is_approved', true)], 'rating');

        $deals = $cards()->onSale()->orderByRaw('(compare_price - price) / compare_price DESC')->take(10)->get();

        $featured = $cards()->featured()->latest()->take(10)->get();
        if ($featured->isEmpty()) {
            $featured = $cards()->latest()->take(10)->get();
        }

        $newArrivals = $cards()->latest()->take(10)->get();

        $departments = Category::active()->root()
            ->withCount(['products' => fn ($q) => $q->active()])
            ->get()
            ->sortBy(fn ($c) => $c->localized_name)
            ->values();

        $shops = User::query()
            ->where('is_seller', true)->where('seller_approved', true)
            ->withCount(['products' => fn ($q) => $q->active()])
            ->whereHas('products', fn ($q) => $q->active())
            ->orderByDesc('products_count')
            ->take(8)
            ->get();

        return view('home', compact('deals', 'featured', 'newArrivals', 'departments', 'shops'));
    }
}
