<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\SavedItem;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\DiscountService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartController extends Controller
{
    protected $cartService;

    protected $discountService;

    public function __construct(CartService $cartService, DiscountService $discountService)
    {
        $this->cartService = $cartService;
        $this->discountService = $discountService;
    }

    public function index()
    {
        $cart = $this->cartService->getOrCreateCart();
        $cart->load(['items.product.mainImage', 'items.product.seller', 'items.variant']);

        $cartSummary = $this->cartService->getCartSummary($cart);

        $saved = Auth::check()
            ? SavedItem::where('user_id', Auth::id())->with('product.mainImage')->latest()->get()->filter(fn ($s) => $s->product)
            : collect();

        return view('cart.index', [
            'cart' => $cart,
            'voucher' => $cartSummary['voucher'],
            'discount' => $cartSummary['voucher_discount'],
            'saved' => $saved,
            'freeOver' => (float) Setting::get('free_delivery_over'),
        ]);
    }

    public function add(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $product = Product::find($request->product_id);

        if (! $product->is_active) {
            NotificationService::error(__('This product is not available.'));

            return back();
        }

        $inCart = (int) ($this->cartService->getOrCreateCart()->items()->where('product_id', $product->id)->sum('quantity'));
        if ($product->stock_quantity < $inCart + (int) $request->quantity) {
            NotificationService::error(__('Only :count left in stock.', ['count' => max(0, $product->stock_quantity - $inCart)]));

            return back();
        }

        $this->cartService->addToCart(
            $request->product_id,
            $request->quantity
        );

        NotificationService::addedToCart($product->name);

        return redirect()->route('cart');
    }

    /**
     * "Add all to cart" (frequently bought together): one of each product that is available.
     */
    public function addMany(Request $request)
    {
        $data = $request->validate(['product_ids' => 'required|array|max:10', 'product_ids.*' => 'integer|exists:products,id']);

        $cart = $this->cartService->getOrCreateCart();
        $added = 0;
        foreach (Product::whereIn('id', $data['product_ids'])->get() as $product) {
            $inCart = (int) $cart->items()->where('product_id', $product->id)->sum('quantity');
            if ($product->is_active && $product->stock_quantity > $inCart) {
                $this->cartService->addToCart($product->id, 1);
                $added++;
            }
        }

        $added
            ? NotificationService::success(trans_choice(':count item added to your cart.|:count items added to your cart.', $added, ['count' => $added]))
            : NotificationService::error(__('This product is not available.'));

        return redirect()->route('cart');
    }

    public function update(Request $request, CartItem $item)
    {
        $this->authorizeItem($item);

        $request->validate([
            'quantity' => 'required|integer|min:1|max:999',
        ]);

        $quantity = min((int) $request->quantity, max(1, (int) $item->product->stock_quantity));
        $this->cartService->updateCartItem($item, $quantity);

        if (! $request->expectsJson()) {
            return redirect()->route('cart');
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart updated successfully',
            'cart' => new CartResource($item->cart),
        ]);
    }

    /**
     * Move a cart line to "Saved for later" (kept on the account, out of the order total).
     */
    public function saveForLater(CartItem $item)
    {
        $this->authorizeItem($item);

        SavedItem::updateOrCreate(
            ['user_id' => Auth::id(), 'product_id' => $item->product_id],
            ['quantity' => $item->quantity]
        );
        $this->cartService->removeFromCart($item);

        NotificationService::success(__('Saved for later.'));

        return redirect()->route('cart');
    }

    public function moveToCart(SavedItem $saved)
    {
        abort_unless($saved->user_id === Auth::id(), 403);

        $product = $saved->product;
        if (! $product || ! $product->is_active || $product->stock_quantity < 1) {
            NotificationService::error(__('This product is not available.'));

            return redirect()->route('cart');
        }

        $this->cartService->addToCart($product->id, min($saved->quantity, $product->stock_quantity));
        $saved->delete();

        NotificationService::addedToCart($product->name);

        return redirect()->route('cart');
    }

    public function removeSaved(SavedItem $saved)
    {
        abort_unless($saved->user_id === Auth::id(), 403);
        $saved->delete();

        return redirect()->route('cart');
    }

    public function remove(CartItem $item)
    {
        $this->authorizeItem($item);

        $productName = $item->product->name;
        $this->cartService->removeFromCart($item);

        NotificationService::removedFromCart($productName);

        return redirect()->route('cart');
    }

    public function clear()
    {
        $this->cartService->clearCart();

        NotificationService::success('Cart cleared successfully!');

        return redirect()->route('cart');
    }

    public function applyVoucher(Request $request)
    {
        $request->validate(['voucher_code' => 'required|string']);

        $cart = $this->cartService->getOrCreateCart();
        $result = $this->discountService->validateVoucher($request->voucher_code, $cart);

        if (! $result['valid']) {
            return back()->withErrors(['voucher_code' => $result['message']]);
        }

        $this->discountService->applyVoucher($request->voucher_code);
        NotificationService::voucherApplied($request->voucher_code);

        return back();
    }

    public function removeVoucher()
    {
        $this->discountService->removeVoucher();
        NotificationService::voucherRemoved();

        return back();
    }

    /**
     * A cart item may only be changed by the owner of the (active) cart it is in.
     */
    protected function authorizeItem(CartItem $item): void
    {
        $cart = $item->cart;
        abort_unless($cart && $cart->status === 'active' && $cart->id === $this->cartService->currentCart()?->id, 403);
    }
}
