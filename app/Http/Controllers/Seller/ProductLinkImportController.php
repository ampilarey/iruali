<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\ProductImportService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Seller Centre → Products → "Import from a link" (ProductImportService): for shops an admin allowed
 * pre-orders. The shop pastes a product's address on an online shop abroad; the product form then opens
 * filled from that page, for the shop to check, finish and save.
 */
class ProductLinkImportController extends Controller
{
    public function create()
    {
        abort_unless(CurrentShop::get()->allowsPreorders(), 404);

        return view('seller.products.from-link');
    }

    public function store(Request $request, ProductImportService $imports)
    {
        abort_unless(CurrentShop::get()->allowsPreorders(), 404);
        $data = $request->validate(['url' => 'required|url:http,https|max:500'], [
            'url.required' => __('Paste the link to the product.'),
            'url.url' => __('That is not a web link. Copy the address from your browser and paste it here.'),
        ]);

        try {
            $imports->import($data['url']);
        } catch (RuntimeException $e) {
            return back()->withInput()->withErrors(['url' => $e->getMessage()]);
        }

        return redirect()->route('seller.products.create', ['from_link' => 1])
            ->with('success', __('Filled in from the page. Check everything, choose a category and the price, then save.'));
    }
}
