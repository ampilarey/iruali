<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CatalogService;
use Illuminate\Http\Request;

class ShopController extends Controller
{
    public function index(Request $request, CatalogService $catalog)
    {
        return view('catalog.index', $catalog->listing($request) + [
            'title' => __('Shop all products'),
            'crumbs' => [],
        ]);
    }

    public function deals(Request $request, CatalogService $catalog)
    {
        return view('catalog.index', $catalog->listing($request, ['deals' => true]) + [
            'title' => __('Deals'),
            'subtitle' => __('Marked-down prices from shops across the islands.'),
            'crumbs' => [],
        ]);
    }

    public function brand(Request $request, string $brand, CatalogService $catalog)
    {
        abort_unless(\App\Models\Product::query()->active()->where('brand', $brand)->exists(), 404);

        return view('catalog.index', $catalog->listing($request, ['brand' => $brand]) + [
            'title' => $brand,
            'subtitle' => __('Everything from :brand on iruali.', ['brand' => $brand]),
            'crumbs' => [['label' => __('Brands'), 'url' => null]],
        ]);
    }

    /**
     * A seller's storefront: their details on top, their products below.
     */
    public function seller(Request $request, User $seller, CatalogService $catalog)
    {
        abort_unless($seller->isSeller(), 404);

        return view('catalog.index', $catalog->listing($request, ['seller' => $seller]) + [
            'title' => $seller->business_name ?: $seller->name,
            'seller' => $seller,
            'crumbs' => [['label' => __('Shops'), 'url' => null]],
        ]);
    }
}
