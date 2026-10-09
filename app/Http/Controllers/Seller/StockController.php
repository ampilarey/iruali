<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
     */
    public function update(Request $request)
    {
        $data = $request->validate([
            'products' => 'nullable|array|max:500',
            'products.*' => 'required|integer|min:0|max:999999',
            'variants' => 'nullable|array|max:2000',
            'variants.*' => 'required|integer|min:0|max:999999',
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

        $changed = 0;
        DB::transaction(function () use ($data, $products, $variants, &$changed) {
            foreach ($data['products'] ?? [] as $id => $stock) {
                $product = $products[(int) $id];
                if (! $product->has_variants && (int) $product->stock_quantity !== (int) $stock) {
                    $product->update(['stock_quantity' => (int) $stock]);
                    $changed++;
                }
            }
            foreach ($data['variants'] ?? [] as $id => $stock) {
                $variant = $variants[(int) $id];
                if ((int) $variant->stock_quantity !== (int) $stock) {
                    $variant->update(['stock_quantity' => (int) $stock]);
                    $changed++;
                }
            }
        });

        return redirect()->route('seller.stock', $request->only('low', 'q'))
            ->with('success', trans_choice(':count stock level updated.|:count stock levels updated.', $changed, ['count' => $changed]));
    }
}
