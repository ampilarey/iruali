<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\ItemRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Notifications\ItemRequestDeclined;
use App\Notifications\ItemRequestQuoted;
use App\Support\Audit;
use App\Support\Money;
use App\Support\TeamAlerts;
use App\Traits\SecureFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * "Can't find it? We'll bring it for you": a customer asks for an item iruali doesn't list, usually from
 * a shop abroad. Only admins answer (Admin → Item requests): they decline, or send a price, choosing the
 * shop that fills it (one allowed pre-orders, e.g. iruali's own), how it is paid (a booking fee, 30% by
 * default, or the full price) and the days until it ships. The price becomes a pre-order product only this
 * customer can buy: products.item_request_id, never active (so it is in no listing, search, feed or review
 * queue), price and units fixed. Accepting puts it in the customer's cart; checkout, booking fee, "Stock
 * arrived", the balance and refunds are the usual pre-order ones (PreorderService, BookingFeeService).
 */
class ItemRequestService
{
    use SecureFileUpload;

    public const DEFAULT_DEPOSIT = 30;

    public const DEFAULT_SHIPS_IN_DAYS = 21;

    public const DEFAULT_VALID_DAYS = 7;

    /**
     * @param  array{link?: ?string, title: string, details?: ?string, options?: ?string, quantity: int, island_id?: ?int, budget?: mixed, needed_by?: ?string}  $data
     */
    public function submit(User $customer, array $data, ?UploadedFile $photo = null): ItemRequest
    {
        if (ItemRequest::where('user_id', $customer->id)->open()->count() >= ItemRequest::MAX_OPEN) {
            throw new RuntimeException(__('You already have :n requests waiting. Please wait for our answer, or cancel one, before asking for more.', ['n' => ItemRequest::MAX_OPEN]));
        }

        $request = new ItemRequest($data);
        $request->forceFill(['user_id' => $customer->id, 'status' => ItemRequest::NEW]);
        if ($photo) {
            $request->photo_path = $this->storeFileSecurely($photo, 'item-requests', ['image/jpeg', 'image/png', 'image/webp'], 5120);
        }
        $request->save();

        TeamAlerts::send('item_request', 'iruali: item request #'.$request->id.': '.Str::limit($request->title, 80).' (x'.$request->quantity.').', route('admin.item-requests.show', $request));

        return $request;
    }

    /**
     * The admin's price: creates (or updates) the customer's own pre-order product and tells them.
     *
     * @param  array{seller_id: int, category_id: int, unit_price: mixed, deposit_percent: ?int, ships_in_days: int, valid_days: int, quote_message?: ?string, title?: ?string}  $data
     */
    public function quote(ItemRequest $request, User $admin, array $data): ItemRequest
    {
        if (in_array($request->status, [ItemRequest::ORDERED, ItemRequest::CANCELLED], true) && ! $this->orderCancelled($request)) {
            throw new RuntimeException(__('This request has already been ordered or cancelled.'));
        }
        $shop = User::find($data['seller_id']);
        if (! $shop || ! $shop->maySell() || ! $shop->is_seller || ! $shop->allowsPreorders()) {
            throw new RuntimeException(__('Choose a shop that is allowed to take pre-orders.'));
        }

        DB::transaction(function () use ($request, $admin, $data, $shop) {
            $title = trim((string) ($data['title'] ?? '')) ?: $request->title;
            $product = $request->product ?? new Product;
            $product->forceFill([
                'seller_id' => $shop->id,
                'category_id' => (int) $data['category_id'],
                'price' => round((float) $data['unit_price'], 2),
                'stock_quantity' => 0,
                'is_active' => false,
                'sku' => $product->sku ?: 'REQ-'.$request->id.'-'.Str::upper(Str::random(4)),
                'source_url' => $request->link,
                'preorder_enabled' => true,
                'preorder_ship_date' => null,
                'preorder_lead_days' => (int) $data['ships_in_days'],
                'preorder_limit' => $request->quantity,
                'preorder_deposit_type' => $data['deposit_percent'] ? BookingFeeService::PERCENT : null,
                'preorder_deposit_value' => $data['deposit_percent'] ? (float) $data['deposit_percent'] : null,
                'preorder_confirm_first' => false,
                'preorder_closes_on' => null,
            ]);
            $product->setTranslations('name', ['en' => $title]);
            $product->setTranslations('description', ['en' => trim(($request->details ?? '').($request->options ? "\n".__('Options: :options', ['options' => $request->options]) : '')) ?: $title]);
            $product->item_request_id = $request->id;
            $product->save();

            if ($request->photo_path && ! $product->images()->exists()) {
                ProductImage::create(['product_id' => $product->id, 'url' => $request->photo_path, 'alt_text' => $title, 'is_main' => true, 'sort_order' => 0]);
            }

            // A price changed after the customer accepted: the old one comes out of their cart
            CartItem::where('product_id', $product->id)->delete();

            $request->forceFill([
                'status' => ItemRequest::QUOTED,
                'seller_id' => $shop->id,
                'product_id' => $product->id,
                'unit_price' => round((float) $data['unit_price'], 2),
                'deposit_percent' => $data['deposit_percent'] ?: null,
                'ships_in_days' => (int) $data['ships_in_days'],
                'valid_until' => today()->addDays(max(1, (int) $data['valid_days'])),
                'quote_message' => filled($data['quote_message'] ?? null) ? trim((string) $data['quote_message']) : null,
                'quoted_at' => now(),
                'quoted_by' => $admin->id,
                'declined_reason' => null,
                'declined_at' => null,
                'accepted_at' => null,
                'order_id' => null,
                'ordered_at' => null,
            ])->save();
        });

        Audit::record('item_request.quoted', $request, ['price' => Money::format($request->total()), 'shop' => $shop->shopName()]);
        $request->user?->notify(new ItemRequestQuoted($request));

        return $request;
    }

    public function decline(ItemRequest $request, User $admin, string $reason): void
    {
        if (! $request->canCancel()) {
            throw new RuntimeException(__('This request has already been ordered or cancelled.'));
        }
        $request->forceFill(['status' => ItemRequest::DECLINED, 'declined_reason' => trim($reason), 'declined_at' => now()])->save();
        $this->clearFromCarts($request);
        Audit::record('item_request.declined', $request, ['reason' => trim($reason)]);
        $request->user?->notify(new ItemRequestDeclined($request));
    }

    /**
     * The customer accepts the price: their own pre-order product goes into their cart, quantity fixed.
     */
    public function accept(ItemRequest $request, User $customer): CartItem
    {
        abort_unless((int) $request->user_id === (int) $customer->id, 404);
        if (! $request->canAccept() || ! $request->product) {
            throw new RuntimeException($request->isExpired()
                ? __('This price has expired. Ask us again and we will send a new one.')
                : __('This request has no price to accept.'));
        }

        $cart = Auth::id() === $customer->id
            ? app(CartService::class)->getOrCreateCart()
            : (Cart::where('user_id', $customer->id)->where('status', 'active')->latest('id')->first()
                ?? Cart::create(['user_id' => $customer->id, 'session_id' => Str::random(40), 'status' => 'active']));

        CartItem::where('cart_id', $cart->id)->where('product_id', $request->product_id)->delete();
        $line = new CartItem(['product_id' => $request->product_id, 'quantity' => $request->quantity, 'price' => (float) $request->unit_price]);
        $line->forceFill(['cart_id' => $cart->id])->save();
        $cart->touch();

        $request->forceFill(['status' => ItemRequest::ACCEPTED, 'accepted_at' => now()])->save();

        return $line;
    }

    public function cancel(ItemRequest $request, User $customer): void
    {
        abort_unless((int) $request->user_id === (int) $customer->id, 404);
        if (! $request->canCancel()) {
            throw new RuntimeException(__('This request has already been ordered or cancelled.'));
        }
        $request->forceFill(['status' => ItemRequest::CANCELLED, 'cancelled_at' => now()])->save();
        $this->clearFromCarts($request);
    }

    /**
     * Placing the order (OrderService, in its transaction): a request whose product is in the order is
     * marked ordered.
     */
    public function placeOrder(Order $order, Cart $cart): void
    {
        $ids = $cart->items->map(fn (CartItem $item) => $item->product?->item_request_id)->filter()->unique()->values()->all();
        if ($ids !== []) {
            ItemRequest::whereIn('id', $ids)->update(['status' => ItemRequest::ORDERED, 'order_id' => $order->id, 'ordered_at' => now(), 'updated_at' => now()]);
        }
    }

    /** Who may see a request's private product: the customer, iruali's team and the shop filling it. */
    public function canView(ItemRequest $request, ?User $user): bool
    {
        return $user !== null && ((int) $user->id === (int) $request->user_id || $user->isStaff() || (int) $user->id === (int) $request->seller_id);
    }

    protected function orderCancelled(ItemRequest $request): bool
    {
        return $request->status === ItemRequest::ORDERED && $request->order?->status === 'cancelled';
    }

    protected function clearFromCarts(ItemRequest $request): void
    {
        if ($request->product_id) {
            CartItem::where('product_id', $request->product_id)->delete();
        }
    }
}
