<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\BulkProductService;
use App\Support\CurrentShop;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Bulk edit of selected products (price, stock, active) and product duplication.
 */
class BulkProductController extends Controller
{
    public function __construct(protected BulkProductService $bulk) {}

    /**
     * Confirm page: what the action will do to each selected product.
     */
    public function confirm(Request $request)
    {
        $data = $this->validated($request);
        $products = $this->ownProducts($data['ids']);

        return view('seller.products.bulk', [
            'action' => $data['action'],
            'value' => $data['value'] ?? null,
            'rows' => $this->bulk->describe($products, $data['action'], isset($data['value']) ? (float) $data['value'] : null),
            'ids' => $products->pluck('id')->all(),
        ]);
    }

    public function apply(Request $request)
    {
        $data = $this->validated($request);
        $products = $this->ownProducts($data['ids']);

        $count = $this->bulk->apply($products, $data['action'], isset($data['value']) ? (float) $data['value'] : null);

        return redirect()->route('seller.products.index')
            ->with('success', trans_choice(':count product updated.|:count products updated.', $count, ['count' => $count]));
    }

    public function duplicate(Product $product)
    {
        $this->authorize('update', $product);

        $copy = $this->bulk->duplicate($product);

        return redirect()->route('seller.products.edit', $copy)
            ->with('success', __('Product duplicated. Check the copy and submit it for approval.'));
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'action' => ['required', Rule::in(BulkProductService::ACTIONS)],
            'value' => [
                Rule::requiredIf(in_array($request->action, ['price_percent', 'stock_set'], true)),
                'nullable', 'numeric',
                ...($request->action === 'price_percent' ? ['min:-99', 'max:1000'] : ['integer', 'min:0', 'max:999999']),
            ],
        ], [], ['value' => $request->action === 'price_percent' ? __('percentage') : __('stock')]);
    }

    /**
     * Only the shop's own products; any other id is refused outright.
     */
    protected function ownProducts(array $ids)
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        $products = Product::whereIn('id', $ids)->with('variants')->get();
        abort_if($products->count() !== count($ids) || $products->contains(fn ($p) => (int) $p->seller_id !== (int) CurrentShop::id()), 403);

        return $products;
    }
}
