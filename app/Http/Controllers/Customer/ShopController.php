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
