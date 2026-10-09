<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Http\Requests\Seller\ShopDiscountCodeRequest;
use App\Models\Product;
use App\Models\ShopDiscountCode;
use App\Services\ShopDiscountService;
use Illuminate\Http\Request;

/**
 * Seller Centre → Discount codes: the shop's own codes (the shop pays for the discount). Shops
 * create, edit and pause them, and see each code's uses and sales. A shop only ever sees its own.
 */
class DiscountCodeController extends Controller
{
    public function index(Request $request, ShopDiscountService $discounts)
    {
        $codes = ShopDiscountCode::where('seller_id', $request->user()->id)
            ->withCount('products')
            ->latest('id')
            ->get();
        $stats = $discounts->stats($codes);

        return view('seller.discounts.index', compact('codes', 'stats'));
    }

    public function create(Request $request)
    {
        $code = new ShopDiscountCode(['type' => 'percent', 'applies_to' => 'all', 'is_active' => true, 'max_uses_per_customer' => 1]);

        return view('seller.discounts.form', ['code' => $code, 'products' => $this->products($request), 'selected' => []]);
    }

    public function store(ShopDiscountCodeRequest $request)
    {
        $code = new ShopDiscountCode($request->codeAttributes());
        $code->seller_id = $request->user()->id;
        $code->save();
        $code->products()->sync($request->productIds());

        return redirect()->route('seller.discounts')->with('success', __('Discount code :code created.', ['code' => $code->code]));
    }

    public function edit(Request $request, ShopDiscountCode $discount)
    {
        $this->authorizeOwner($request, $discount);

        return view('seller.discounts.form', [
            'code' => $discount,
            'products' => $this->products($request),
            'selected' => $discount->products()->pluck('products.id')->all(),
        ]);
    }

    public function update(ShopDiscountCodeRequest $request, ShopDiscountCode $discount)
    {
        $this->authorizeOwner($request, $discount);

        $discount->update($request->codeAttributes());
        $discount->products()->sync($request->productIds());

        return redirect()->route('seller.discounts')->with('success', __('Discount code :code saved.', ['code' => $discount->code]));
    }

    /**
     * Pause a code (customers can't use it until it is resumed), or resume it.
     */
    public function toggle(Request $request, ShopDiscountCode $discount)
    {
        $this->authorizeOwner($request, $discount);

        $discount->update(['is_active' => ! $discount->is_active]);

        return back()->with('success', $discount->is_active
            ? __('Discount code :code is on again.', ['code' => $discount->code])
            : __('Discount code :code is paused.', ['code' => $discount->code]));
    }

    protected function authorizeOwner(Request $request, ShopDiscountCode $code): void
    {
        abort_unless((int) $code->seller_id === (int) $request->user()->id, 403);
    }

    /**
     * The shop's products a code can be limited to.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, Product>
     */
    protected function products(Request $request)
    {
        return Product::where('seller_id', $request->user()->id)->orderBy('id')->get(['id', 'name', 'sku', 'is_active']);
    }
}
