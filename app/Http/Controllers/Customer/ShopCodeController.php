<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CartService;
use App\Services\NotificationService;
use App\Services\ShopDiscountService;
use Illuminate\Http\Request;

/**
 * The cart page's "Shop code" box: a shop's own discount code, one per shop at a time, on that
 * shop's items only. It can be combined with one iruali voucher (worked out on what is left).
 */
class ShopCodeController extends Controller
{
    public function __construct(protected CartService $cartService, protected ShopDiscountService $shopDiscounts) {}

    public function apply(Request $request)
    {
        $data = $request->validate(['shop_code' => 'required|string|max:30']);

        $cart = $this->cartService->currentCart();
        if (! $cart || $this->cartService->isCartEmpty($cart)) {
            return back()->withErrors(['shop_code' => __('Your cart is empty.')]);
        }

        $user = $request->user();
        $result = $this->shopDiscounts->apply($cart, $data['shop_code'], $user instanceof User ? $user : null);
        if (! $result['ok']) {
            return back()->withErrors(['shop_code' => $result['message']])->withInput();
        }

        NotificationService::success($result['message']);

        return back();
    }

    public function remove(Request $request)
    {
        $data = $request->validate(['seller_id' => 'required|integer']);

        $this->shopDiscounts->forgetCode((int) $data['seller_id']);
        NotificationService::success(__('Shop code removed.'));

        return back();
    }
}
