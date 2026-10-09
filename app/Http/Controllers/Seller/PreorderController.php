<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\PreorderService;
use Illuminate\Http\Request;

/**
 * Seller Centre → Pre-orders: the shop's open pre-orders per product, "Stock arrived" (the units
 * that came go to the paid pre-orders, oldest first, and the rest go on sale) and moving the
 * expected date (the waiting customers are emailed). A shop only ever sees its own.
 */
class PreorderController extends Controller
{
    public function index(PreorderService $preorders)
    {
        $shop = $this->shop();

        // Pre-order lines of the shop's products still waiting for stock, oldest first
        $waiting = OrderItem::query()
            ->whereIn('product_id', Product::where('seller_id', $shop->id)->select('id'))
            ->where('is_preorder', true)
            ->whereNull('preorder_allocated_at')
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->with(['order.user', 'variant'])
            ->orderBy('id')
            ->get();

        // Products with pre-orders waiting first (the longest waiting at the top), then those taking pre-orders
        $products = Product::where('seller_id', $shop->id)
            ->where(fn ($q) => $q->whereIn('id', $waiting->pluck('product_id')->unique())->orWhere('preorder_enabled', true))
            ->with(['variants' => fn ($q) => $q->ordered()])
            ->get()
            ->sortBy(fn (Product $product) => $waiting->firstWhere('product_id', $product->id)->id ?? PHP_INT_MAX)
            ->values();

        $rows = $products->map(fn (Product $product) => [
            'product' => $product,
            'items' => $waiting->where('product_id', $product->id)->values(),
            'left' => $preorders->unitsLeft($product),
            'accepting' => $preorders->accepting($product),
        ]);

        return view('seller.preorders.index', ['withWaiting' => $rows->filter(fn ($row) => $row['items']->isNotEmpty())->values(), 'taking' => $rows->filter(fn ($row) => $row['items']->isEmpty())->values()]);
    }

    /**
     * "Stock arrived": the units received (per option for a product with options).
     */
    public function stockArrived(Request $request, Product $product, PreorderService $preorders)
    {
        $this->authorizeProduct($product);
        $data = $request->validate([
            'received' => 'required|array|max:200',
            'received.*' => 'nullable|integer|min:0|max:'.PreorderService::MAX_LIMIT,
        ]);

        // Keys are the product's own variant ids, or 0 for a product without variants
        $allowed = $product->has_variants ? $product->variants()->pluck('id')->map(fn ($id) => (int) $id)->all() : [0];
        $received = [];
        foreach ($data['received'] as $key => $units) {
            abort_unless(in_array((int) $key, $allowed, true), 404);
            if ((int) $units > 0) {
                $received[(int) $key] = (int) $units;
            }
        }

        if ($received === [] && $product->effectiveStock() <= 0) {
            return back()->withErrors(['received' => __('Enter how many units arrived.')]);
        }

        $result = $preorders->receiveStock($product, $received);

        return back()->with('success', __('Stock recorded: :allocated went to waiting pre-orders and :stock are on sale. Customers whose pre-orders are complete have been emailed.', [
            'allocated' => $result['allocated'], 'stock' => $result['to_stock'],
        ]));
    }

    /**
     * Move the expected date: the pre-orders still waiting take it and their customers are emailed.
     */
    public function moveDate(Request $request, Product $product)
    {
        $this->authorizeProduct($product);
        $data = $request->validate([
            'preorder_ship_date' => ['required', 'date', 'after:today', 'before_or_equal:'.today()->addYear()->toDateString()],
        ], PreorderService::formMessages());

        $product->forceFill(['preorder_ship_date' => $data['preorder_ship_date']])->save();
        if (! $product->wasChanged('preorder_ship_date')) {
            return back()->with('success', __('That is already the expected date.'));
        }

        return back()->with('success', __('The expected date is now :date. Customers waiting for this product have been emailed.', [
            'date' => $product->preorder_ship_date?->translatedFormat('j M Y'),
        ]));
    }

    protected function authorizeProduct(Product $product): void
    {
        abort_unless((int) $product->seller_id === (int) $this->shop()->id, 403);
    }

    /**
     * The shop whose pre-orders these are: the owner signed in, or a member of its staff.
     */
    protected function shop(): User
    {
        return \App\Support\CurrentShop::get();
    }
}
