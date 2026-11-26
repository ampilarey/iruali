<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CurrentShop;
use Illuminate\Http\Request;

/**
 * One table of everything the shop sells, with inline stock editing.
 */
class StockController extends Controller
{
    public function index(Request $request)
    {
        $lowOnly = $request->boolean('low');

        $products = CurrentShop::get()->products()
            ->with(['variants' => fn ($q) => $q->ordered()])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->q.'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)->orWhere('sku', 'like', $term)
                    ->orWhereHas('variants', fn ($v) => $v->where('sku', 'like', $term)));
            })
            ->orderBy('name')
            ->get();

        if ($lowOnly) {
            $products = $products->filter(fn ($p) => $p->isLowStock())->values();
        }

        $lowCount = CurrentShop::get()->products()->with('variants')->get()->filter(fn ($p) => $p->isLowStock())->count();

        return view('seller.stock', compact('products', 'lowOnly', 'lowCount'));
    }

    /**
     * Save the stock numbers typed into the table (products without variants, and variants).
     *
     * The page sends each number as it was when the page opened (original[...]) beside the one typed:
     * only rows the shop changed are saved, and as the difference, so a sale (or a cancelled order's
     * units coming back) while the page was open is not written over. More units for a product with
     * pre-orders waiting go to those orders first (PreorderService::receiveStock()).
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'products' => 'nullable|array|max:500',
            'products.*' => 'required|integer|min:0|max:999999',
            'variants' => 'nullable|array|max:2000',
            'variants.*' => 'required|integer|min:0|max:999999',
            'original.products' => 'nullable|array|max:500',
            'original.products.*' => 'nullable|integer|min:0|max:999999',
            'original.variants' => 'nullable|array|max:2000',
            'original.variants.*' => 'nullable|integer|min:0|max:999999',
        ]);

        $sellerId = CurrentShop::id();
        $productIds = array_map('intval', array_keys($data['products'] ?? []));
        $variantIds = array_map('intval', array_keys($data['variants'] ?? []));

        // Every row must be this shop's; otherwise nothing is saved
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');
        $variants = ProductVariant::whereIn('id', $variantIds)->with('product')->get()->keyBy('id');
        abort_if(
            $products->contains(fn ($p) => (int) $p->seller_id !== (int) $sellerId)
            || $variants->contains(fn ($v) => (int) $v->product?->seller_id !== (int) $sellerId)
            || count($productIds) !== $products->count() || count($variantIds) !== $variants->count(),
            403
        );

        $changed = app(\App\Services\StockEdits::class)->apply(
            array_filter($data['products'] ?? [], fn ($id) => ! $products[(int) $id]->has_variants, ARRAY_FILTER_USE_KEY),
            $data['variants'] ?? [],
            $data['original'] ?? [],
            'stock_page',
        );

        return redirect()->route('seller.stock', $request->only('low', 'q'))
            ->with('success', trans_choice(':count stock level updated.|:count stock levels updated.', $changed, ['count' => $changed]));
    }
}
