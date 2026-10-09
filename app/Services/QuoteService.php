<?php

namespace App\Services;

use App\Enums\QuoteStatus;
use App\Exceptions\QuoteException;
use App\Models\BusinessProfile;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Island;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\QuoteMessage;
use App\Models\QuoteRequest;
use App\Models\User;
use App\Notifications\QuoteAccepted;
use App\Notifications\QuoteDeclined;
use App\Notifications\QuoteMessageReceived;
use App\Notifications\QuoteReceived;
use App\Notifications\QuoteRequested;
use App\Notifications\QuoteWithdrawn;
use App\Support\Money;
use App\Support\ShopHoliday;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bulk quotes for businesses (resorts, offices, cafés buying in quantity).
 *
 *  1. A signed-in customer asks a shop for a price on a quantity of one product (at least the
 *     product's minimum: the shop's own, else 10), with the delivery island, the day it is needed,
 *     notes and the business details for the invoice. One open request per customer and product.
 *  2. The shop replies with a unit price in MVR (it may be under the list price), the quantity it
 *     can supply, the last day the price holds (7 days unless it says otherwise) and a message, or
 *     declines with a reason. It may change its quote until the customer accepts it. Either side can
 *     add short messages to the request's thread.
 *  3. The customer accepts the quote before it expires: it goes into the cart as a quoted line
 *     ("Quote #123") whose quantity is locked and whose price is fixed (CartItem::unit_price).
 *     Stock is checked then and again at checkout; a quote never holds stock. A quoted line whose
 *     quote expired (or was closed) is taken out of the cart with a notice. Checkout is the normal
 *     one; the order item keeps the quote, the shop's commission and earnings are on the quoted
 *     price, and the order (so every shop's invoice) carries the quote's business details unless
 *     the buyer gave others at checkout.
 *
 * Discounts on quoted lines. The quote is the shop's negotiated price, so nothing else comes off it:
 *  - no multi-buy saving and no shop code (ShopDiscountService leaves quoted lines out);
 *  - no iruali voucher either: the voucher, and its minimum order, work on the rest of the cart
 *    only (voucherBase). A percent voucher on a large business order would otherwise cost iruali a
 *    share of a price it had no part in, and the shop already gave its best price;
 *  - loyalty points and wallet credit can pay for quoted lines like anything else (they are the
 *    customer's own balance), and the order earns points as usual.
 * Every amount is worked in laari, so the lines less the shop discounts, the voucher and the points,
 * plus delivery, add up to the order total exactly (the shop-deals rules, ShopDiscountService).
 */
class QuoteService
{
    public const DEFAULT_MIN_QUANTITY = 10;

    public const MAX_QUANTITY = 99999;

    /** Days a quote holds unless the shop picks another day. */
    public const DEFAULT_VALID_DAYS = 7;

    public const MAX_VALID_DAYS = 60;

    /** Largest quote (price × quantity) in MVR, well inside what an order can hold. */
    public const MAX_LINE_TOTAL = 9999999.99;

    /** Admin → Inbox lists new requests a shop has not answered for this many days. */
    public const WAITING_DAYS = 2;

    public const MESSAGE_MAX_LENGTH = 1000;

    /** Minutes between "new message" emails to the same person about the same request. */
    public const NOTIFY_EVERY_MINUTES = 10;

    /** Quoted lines taken out of the cart, with the reason, for the cart page. */
    public const NOTICES_KEY = 'quote_notices';

    // ---- Asking for a quote -----------------------------------------------------------------------

    /**
     * The smallest quantity a customer can ask a quote for: the shop's own minimum for the product
     * (product form), else DEFAULT_MIN_QUANTITY.
     */
    public function minimumFor(Product $product): int
    {
        $own = (int) $product->getAttribute('quote_min_quantity');

        return $own > 0 ? min($own, self::MAX_QUANTITY) : self::DEFAULT_MIN_QUANTITY;
    }

    /**
     * Why a quote can't be asked for this product now (null when it can). $customer is null for a
     * guest, who is asked to sign in.
     */
    public function requestProblem(?User $customer, Product $product): ?string
    {
        $shop = $product->seller;

        return match (true) {
            ! $product->is_active || $product->trashed() => __('This product is not available.'),
            ! $shop || ! $shop->isSeller() => __('Bulk quotes are for products sold by a shop on iruali.'),
            $customer !== null && $customer->id === $shop->id => __('This is one of your own products.'),
            $shop->isOnHoliday() => __(':shop is on holiday, so it is not taking quote requests right now.', ['shop' => $shop->shopName()]),
            default => null,
        };
    }

    /**
     * The customer's request for this product that is still going (new, quoted or accepted).
     */
    public function openRequest(User $customer, Product $product): ?QuoteRequest
    {
        return QuoteRequest::where('customer_id', $customer->id)->where('product_id', $product->id)
            ->open()->latest('id')->first();
    }

    /**
     * Send a request to the product's shop. $data is the checked form: quantity, island_id or
     * island/atoll, needed_by, notes and the business details (buyer_business_name, buyer_tin,
     * buyer_business_address; save_business to keep them on the customer's account).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws QuoteException when the product can't be quoted or the customer already has an open request for it
     */
    public function request(User $customer, Product $product, ?ProductVariant $variant, array $data): QuoteRequest
    {
        if ($problem = $this->requestProblem($customer, $product)) {
            throw new QuoteException($problem);
        }
        $this->expireDue();

        $island = ! empty($data['island_id']) ? Island::find((int) $data['island_id']) : null;
        $quote = DB::transaction(function () use ($customer, $product, $variant, $data, $island) {
            // The customer's row is locked first, so two sends at once can't both pass the check below
            User::whereKey($customer->id)->lockForUpdate()->first();
            if ($open = $this->openRequest($customer, $product)) {
                throw new QuoteException(__('You already have an open quote request for this product.'), $open);
            }

            $quote = new QuoteRequest([
                'product_id' => $product->id,
                'product_variant_id' => $variant?->id,
                'product_name' => Str::limit($product->getTranslation('name', 'en', false) ?: (string) $product->name, 250, ''),
                'variant_name' => $variant ? Str::limit($variant->displayName(), 250, '') : null,
                'quantity' => (int) $data['quantity'],
                'island_id' => $island?->id,
                'delivery_island' => Str::limit($island ? ($island->getTranslation('name', 'en', false) ?: (string) $island->localized_name) : trim((string) ($data['island'] ?? '')), 100, ''),
                'delivery_atoll' => $island ? $island->atoll : (filled($data['atoll'] ?? null) ? Str::limit(trim((string) $data['atoll']), 100, '') : null),
                'needed_by' => $data['needed_by'] ?? null,
                'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
                'business_name' => trim((string) $data['buyer_business_name']),
                'business_tin' => $data['buyer_tin'] ?? null,
                'business_address' => trim((string) $data['buyer_business_address']),
            ]);
            $quote->forceFill(['customer_id' => $customer->id, 'seller_id' => $product->seller_id, 'status' => QuoteStatus::New->value])->save();

            return $quote;
        });

        // "The buyer's business details are prefilled and can be completed": kept for checkout and the next request
        if (! empty($data['save_business'])) {
            BusinessProfile::updateOrCreate(['user_id' => $customer->id], [
                'company_name' => $quote->business_name,
                'tin' => $quote->business_tin,
                'business_address' => $quote->business_address,
            ]);
        }

        $this->notify($quote->seller, new QuoteRequested($quote));

        return $quote;
    }

    // ---- The shop's reply -------------------------------------------------------------------------

    /**
     * The shop's quote (or a new version of it, until the customer accepts): unit_price (MVR),
     * quantity, valid_until (Y-m-d) and an optional message.
     *
     * @param  array{unit_price: float|string, quantity: int|string, valid_until: string, message?: ?string}  $data
     *
     * @throws QuoteException
     */
    public function sendQuote(QuoteRequest $quote, array $data): void
    {
        $this->expireDue();

        $updated = DB::transaction(function () use ($quote, $data) {
            $locked = $this->lock($quote);
            if (! $locked->hasStatus(QuoteStatus::New, QuoteStatus::Quoted)) {
                throw new QuoteException(__('This request can no longer be quoted: it is :status.', ['status' => mb_strtolower($locked->statusLabel())]));
            }

            $wasQuoted = $locked->hasStatus(QuoteStatus::Quoted);
            $locked->forceFill([
                'unit_price' => round((float) $data['unit_price'], 2),
                'quoted_quantity' => (int) $data['quantity'],
                'valid_until' => $data['valid_until'],
                'shop_message' => filled($data['message'] ?? null) ? trim((string) $data['message']) : null,
                'list_price' => $this->listPrice($locked),
                'status' => QuoteStatus::Quoted->value,
                'quoted_at' => now(),
            ])->save();

            return [$locked, $wasQuoted];
        });

        $quote->setRawAttributes($updated[0]->getAttributes(), true);
        $this->notify($quote->customer, new QuoteReceived($quote, $updated[1]));
    }

    /**
     * Decline or close a request. By the shop (new or quoted), by the customer (anything not yet
     * ordered: a request they no longer need or a quote they don't want) or by iruali (Admin →
     * Quotes). A quote already in the cart comes out of it. The other side is told.
     *
     * @param  'shop'|'customer'|'admin'  $by
     *
     * @throws QuoteException
     */
    public function decline(QuoteRequest $quote, string $by, ?string $reason = null): void
    {
        $this->expireDue();
        $allowed = $by === 'shop' ? [QuoteStatus::New, QuoteStatus::Quoted] : [QuoteStatus::New, QuoteStatus::Quoted, QuoteStatus::Accepted];

        $locked = DB::transaction(function () use ($quote, $by, $reason, $allowed) {
            $locked = $this->lock($quote);
            if (! $locked->hasStatus(...$allowed)) {
                throw new QuoteException(__('This request is already :status.', ['status' => mb_strtolower($locked->statusLabel())]));
            }

            $locked->cartItems()->delete();
            $locked->forceFill([
                'status' => QuoteStatus::Declined->value,
                'declined_by' => $by,
                'decline_reason' => filled($reason) ? Str::limit(trim((string) $reason), 500, '') : null,
                'declined_at' => now(),
            ])->save();

            return $locked;
        });
        $quote->setRawAttributes($locked->getAttributes(), true);

        if ($by !== 'customer') {
            $this->notify($quote->customer, new QuoteDeclined($quote));
        }
        if ($by !== 'shop') {
            $this->notify($quote->seller, new QuoteWithdrawn($quote));
        }
    }

    // ---- The customer takes the quote ---------------------------------------------------------------

    /**
     * Accept a quote: it goes into the customer's cart as a quoted line and the shop is told.
     *
     * @throws QuoteException when it can't be accepted (already taken, expired, the product or shop
     *                        is not available, not enough stock)
     */
    public function accept(QuoteRequest $quote, User $customer): CartItem
    {
        $this->expireDue();

        [$locked, $line] = DB::transaction(function () use ($quote, $customer) {
            $locked = $this->lock($quote);
            if ($locked->customer_id !== $customer->id) {
                throw new QuoteException(__('This quote is not yours.'));
            }
            if ($locked->isExpired() || $locked->hasStatus(QuoteStatus::Expired)) {
                throw new QuoteException(__('This quote expired on :date, so it can no longer be accepted. You can ask the shop for a new one.', ['date' => $locked->valid_until?->translatedFormat('j M Y')]));
            }
            if (! $locked->hasStatus(QuoteStatus::Quoted)) {
                throw new QuoteException($locked->hasStatus(QuoteStatus::Accepted)
                    ? __('You have already accepted this quote.')
                    : __('This quote can no longer be accepted: it is :status.', ['status' => mb_strtolower($locked->statusLabel())]));
            }

            $line = $this->putInCart($locked, $customer);
            $locked->forceFill(['status' => QuoteStatus::Accepted->value, 'accepted_at' => now()])->save();

            return [$locked, $line];
        });
        $quote->setRawAttributes($locked->getAttributes(), true);

        $this->notify($quote->seller, new QuoteAccepted($quote));

        return $line;
    }

    /**
     * An accepted quote whose line was taken out of the cart (removed, cart cleared, an order
     * cancelled before it was paid) goes back in while the quote holds.
     *
     * @throws QuoteException
     */
    public function addToCartAgain(QuoteRequest $quote, User $customer): CartItem
    {
        $this->expireDue();

        return DB::transaction(function () use ($quote, $customer) {
            $locked = $this->lock($quote);
            if ($locked->customer_id !== $customer->id || ! $locked->hasStatus(QuoteStatus::Accepted) || $locked->isExpired()) {
                throw new QuoteException(__('This quote can no longer be added to your cart.'));
            }
            if ($this->cartLineFor($locked, $customer)) {
                throw new QuoteException(__('This quote is already in your cart.'));
            }

            return $this->putInCart($locked, $customer);
        });
    }

    /**
     * The quoted line in the customer's active cart, if it is there.
     */
    public function cartLineFor(QuoteRequest $quote, User $customer): ?CartItem
    {
        return CartItem::where('quote_request_id', $quote->id)
            ->whereHas('cart', fn ($q) => $q->where('user_id', $customer->id)->where('status', 'active'))
            ->first();
    }

    /**
     * Can the quote still be bought: the product on sale, from the same shop, not on holiday, the
     * option still offered and enough stock for the quoted quantity (a quote does not hold stock)?
     *
     * @throws QuoteException
     */
    public function assertBuyable(QuoteRequest $quote): void
    {
        $product = $quote->product;
        if (! $product || $product->trashed() || ! $product->is_active) {
            throw new QuoteException(__('":product" is no longer on sale, so this quote can\'t be used.', ['product' => $quote->productName()]));
        }
        $shop = $product->seller;
        if (! $shop || (int) $product->seller_id !== (int) $quote->seller_id || ! $shop->isSeller()) {
            throw new QuoteException(__(':shop is not selling on iruali right now, so this quote can\'t be used.', ['shop' => $quote->shopName()]));
        }
        if ($shop->isOnHoliday()) {
            throw new QuoteException(ShopHoliday::addToCartMessage($product));
        }

        // The quote is for one option of a product sold in options: that option must still be offered
        $variant = null;
        if ($product->has_variants || $quote->product_variant_id) {
            $variant = $product->has_variants && $quote->product_variant_id ? ProductVariant::where('product_id', $product->id)->find($quote->product_variant_id) : null;
            if (! $variant || ! $variant->is_active) {
                throw new QuoteException(__('The option in this quote (:option) is no longer offered.', ['option' => $quote->variant_name ?: '—']));
            }
        }

        $available = app(CartService::class)->availableStock($product, $variant);
        if ($available < (int) $quote->quoted_quantity) {
            throw new QuoteException(__('Not enough stock: :shop has :available of ":product" right now and the quote is for :quantity. Ask the shop in the messages below, or try again later.', [
                'shop' => $quote->shopName(),
                'available' => max(0, $available),
                'product' => $quote->displayName(),
                'quantity' => (int) $quote->quoted_quantity,
            ]));
        }
    }

    // ---- The thread ---------------------------------------------------------------------------------

    /**
     * Add a short message to an open request's thread and tell the other side (at most one email
     * every NOTIFY_EVERY_MINUTES per request and person).
     *
     * @param  'customer'|'seller'|'admin'  $role
     *
     * @throws QuoteException when the request is closed
     */
    public function postMessage(QuoteRequest $quote, User $sender, string $role, string $body): QuoteMessage
    {
        if (! $quote->isOpen()) {
            throw new QuoteException(__('This request is closed, so no more messages can be added.'));
        }

        $message = DB::transaction(function () use ($quote, $sender, $role, $body) {
            $message = new QuoteMessage(['body' => Str::limit(trim($body), self::MESSAGE_MAX_LENGTH, '')]);
            $message->forceFill(['quote_request_id' => $quote->id, 'sender_id' => $sender->id, 'sender_role' => $role])->save();

            $bumps = ['last_message_at' => now()];
            if ($role !== 'customer') {
                $bumps['customer_unread'] = DB::raw('customer_unread + 1');
            }
            if ($role !== 'seller') {
                $bumps['seller_unread'] = DB::raw('seller_unread + 1');
            }
            QuoteRequest::whereKey($quote->id)->update($bumps);

            return $message;
        });
        $message->setRelation('quoteRequest', $quote);

        $recipients = match ($role) {
            'customer' => [$quote->seller],
            'seller' => [$quote->customer],
            default => [$quote->customer, $quote->seller],
        };
        foreach (array_filter($recipients) as $recipient) {
            // One email per request and person every few minutes; the page shows the whole thread
            if (Cache::add('quote-message:'.$quote->id.':'.$recipient->id, true, now()->addMinutes(self::NOTIFY_EVERY_MINUTES))) {
                $this->notify($recipient, new QuoteMessageReceived($message));
            }
        }

        return $message;
    }

    /**
     * The customer or the shop opened the request: their unread count goes back to nought.
     */
    public function markRead(QuoteRequest $quote, string $role): void
    {
        $column = match ($role) {
            'customer' => 'customer_unread',
            'seller' => 'seller_unread',
            default => null,
        };
        if ($column !== null && (int) $quote->getAttribute($column) > 0) {
            QuoteRequest::whereKey($quote->id)->update([$column => 0]);
            $quote->setAttribute($column, 0);
            $quote->syncOriginalAttribute($column);
        }
    }

    // ---- The cart and checkout ----------------------------------------------------------------------

    /**
     * Take out of the cart every quoted line that can't be bought any more (its quote expired, was
     * declined or closed, was already ordered, or the line no longer matches it). Returns why, one
     * line each; with $remember the reasons wait in the session for the cart page.
     *
     * @return list<string>
     */
    public function pruneCart(Cart $cart, bool $remember = true): array
    {
        $lines = $cart->items()->whereNotNull('quote_request_id')->with(['quoteRequest.product', 'cart'])->get();
        $reasons = [];
        foreach ($lines as $line) {
            $quote = $line->quoteRequest;
            $reason = $this->lineProblem($line, $quote);
            if ($reason === null) {
                continue;
            }

            $line->delete();
            if ($quote && $quote->hasStatus(QuoteStatus::Quoted, QuoteStatus::Accepted) && $quote->isExpired()) {
                $quote->forceFill(['status' => QuoteStatus::Expired->value, 'expired_at' => now()])->save();
            }
            $reasons[] = $reason;
        }

        if ($reasons !== []) {
            $cart->unsetRelation('items');
            if ($remember) {
                Session::put(self::NOTICES_KEY, array_values(array_merge((array) Session::get(self::NOTICES_KEY, []), $reasons)));
            }
        }

        return $reasons;
    }

    /**
     * Why quoted lines were taken out of the cart since the customer last looked (shown once).
     *
     * @return list<string>
     */
    public function pullNotices(): array
    {
        return array_values(array_filter((array) Session::pull(self::NOTICES_KEY, []), 'is_string'));
    }

    /**
     * Placing an order, before anything is written: quoted lines that can't be bought any more come
     * out of the cart, and each quoted product or option must have stock for everything in the cart.
     * Returns why the order can't be placed, or null.
     */
    public function checkCart(Cart $cart): ?string
    {
        if ($reasons = $this->pruneCart($cart, false)) {
            return $reasons[0];
        }

        $cart->loadMissing('items.product', 'items.variant');
        $quoted = $cart->items->whereNotNull('quote_request_id');
        foreach ($quoted as $line) {
            $sameStock = $cart->items->filter(fn (CartItem $item) => (int) $item->product_id === (int) $line->product_id
                && (int) $item->product_variant_id === (int) $line->product_variant_id);
            $wanted = (int) $sameStock->sum('quantity');
            $available = $line->availableStock();
            if ($available < $wanted) {
                return __('Not enough stock for ":product": :available left, and your cart has :quantity (your quote and any other units). Please change your cart, or ask the shop.', [
                    'product' => $line->product ? (string) $line->product->name : '',
                    'available' => max(0, $available),
                    'quantity' => $wanted,
                ]);
            }
        }

        return null;
    }

    /**
     * What an iruali voucher is worked out on (DiscountService): the goods after the shops' own
     * discounts, less the quoted lines, which get no discount of any kind. In laari.
     */
    public function voucherBase(Cart $cart, float $goodsAfterShopDiscounts): float
    {
        $quoted = $this->quotedGoods($cart);
        if ($quoted === 0) {
            return $goodsAfterShopDiscounts;
        }

        return max(0, ShopDiscountService::toLaari($goodsAfterShopDiscounts) - $quoted) / 100;
    }

    /**
     * A voucher has nothing to work on when everything in the cart is bought on a quote: say so,
     * rather than "applied" with nothing off.
     */
    public function voucherProblem(Cart $cart, ?float $base): ?string
    {
        return $base !== null && $base <= 0 && $this->quotedGoods($cart) > 0
            ? __('iruali vouchers do not apply to items bought on a quote.')
            : null;
    }

    /**
     * The quoted lines' total in laari (price × quantity, like Cart::total counts them).
     */
    public function quotedGoods(Cart $cart): int
    {
        $cart->loadMissing('items.product');
        $laari = 0;
        foreach ($cart->items as $item) {
            if ($item->quote_request_id !== null && $item->product) {
                $laari += ShopDiscountService::toLaari($item->unit_price) * (int) $item->quantity;
            }
        }

        return $laari;
    }

    /**
     * Placing the order (OrderService): the cart's quotes are locked first thing in its transaction,
     * before any plain read, so a quote changed meanwhile (declined, closed, expired) is seen as it
     * is now. With MariaDB's snapshot isolation a locking read taken after the transaction's first
     * plain read fails ("Record has changed since last read") when another process changed the row.
     */
    public function lockQuotes(Cart $cart): void
    {
        $ids = $cart->items->pluck('quote_request_id')->filter()->unique()->values()->all();
        if ($ids !== []) {
            QuoteRequest::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        }
    }

    /**
     * Placing the order (OrderService, inside its transaction, the quotes locked by lockQuotes()):
     * each quote in the cart is checked once more, then marked ordered with this order. When the
     * buyer gave no business details at checkout, the quote's go on the order, so every shop's
     * invoice carries them.
     *
     * @throws QuoteException the order is not placed
     */
    public function placeOrder(Order $order, Cart $cart): void
    {
        $quotedLines = $cart->items->whereNotNull('quote_request_id');
        if ($quotedLines->isEmpty()) {
            return;
        }

        // Already locked by this transaction (lockQuotes), in id order
        $quotes = QuoteRequest::whereIn('id', $quotedLines->pluck('quote_request_id')->unique()->all())
            ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($quotedLines as $line) {
            $line->setRelation('cart', $cart);
            if ($problem = $this->lineProblem($line, $quotes->get($line->quote_request_id))) {
                throw new QuoteException($problem);
            }
        }

        foreach ($quotes as $quote) {
            $quote->forceFill(['status' => QuoteStatus::Ordered->value, 'order_id' => $order->id, 'ordered_at' => now()])->save();
        }

        if (blank($order->buyer_business_name)) {
            $first = $quotes->first();
            app(GstService::class)->recordBuyer($order, [
                'buyer_business_name' => $first->business_name,
                'buyer_tin' => $first->business_tin,
                'buyer_business_address' => $first->business_address,
            ]);
        }
    }

    /**
     * An order that was never paid was cancelled (the customer cancelled it, or nobody paid within
     * a day): its quotes are accepted again, so they can go back in the cart while they hold. A paid
     * order that is cancelled is refunded and its quotes stay used.
     */
    public function orderCancelled(Order $order): void
    {
        if ($order->payment_status === 'paid') {
            return;
        }

        $quotes = QuoteRequest::where('order_id', $order->id)->where('status', QuoteStatus::Ordered->value);
        (clone $quotes)->where('valid_until', '<', today()->toDateString())
            ->update(['status' => QuoteStatus::Expired->value, 'expired_at' => now(), 'order_id' => null, 'ordered_at' => null, 'updated_at' => now()]);
        $quotes->update(['status' => QuoteStatus::Accepted->value, 'order_id' => null, 'ordered_at' => null, 'updated_at' => now()]);
    }

    // ---- Expiry ---------------------------------------------------------------------------------------

    /**
     * Quotes whose last day has passed and were not ordered are marked expired (hourly, and before
     * anything reads them). Their cart lines go the next time the cart is looked at.
     */
    public function expireDue(): int
    {
        return QuoteRequest::whereIn('status', [QuoteStatus::Quoted->value, QuoteStatus::Accepted->value])
            ->where('valid_until', '<', today()->toDateString())
            ->update(['status' => QuoteStatus::Expired->value, 'expired_at' => now(), 'updated_at' => now()]);
    }

    // ---- Helpers --------------------------------------------------------------------------------------

    /**
     * Why a quoted cart line can't be bought (null when it can).
     */
    protected function lineProblem(CartItem $line, ?QuoteRequest $quote): ?string
    {
        if (! $quote) {
            return __('A quoted item was taken out of your cart: its quote no longer exists.');
        }

        $name = ['number' => $quote->id, 'product' => $quote->displayName()];
        if ($quote->hasStatus(QuoteStatus::Expired) || ($quote->hasStatus(QuoteStatus::Quoted, QuoteStatus::Accepted) && $quote->isExpired())) {
            return __('Quote #:number for ":product" expired on :date, so it was taken out of your cart. You can ask the shop for a new quote.', $name + ['date' => $quote->valid_until?->translatedFormat('j M Y') ?? '']);
        }
        if (! $quote->hasStatus(QuoteStatus::Accepted)) {
            return __('Quote #:number for ":product" is :status, so it was taken out of your cart.', $name + ['status' => mb_strtolower($quote->statusLabel())]);
        }

        $cartOwner = $line->cart?->user_id;
        $matches = (int) $cartOwner === (int) $quote->customer_id
            && (int) $line->product_id === (int) $quote->product_id
            && (int) $line->product_variant_id === (int) $quote->product_variant_id
            && (int) $line->quantity === (int) $quote->quoted_quantity
            && ShopDiscountService::toLaari($line->price) === ShopDiscountService::toLaari($quote->unit_price);

        return $matches ? null : __('Quote #:number for ":product" was taken out of your cart because it no longer matches the quote.', $name);
    }

    /**
     * The quoted line, in the customer's active cart. The quote has been checked (and is locked).
     *
     * @throws QuoteException
     */
    protected function putInCart(QuoteRequest $quote, User $customer): CartItem
    {
        $this->assertBuyable($quote);

        $cart = Auth::id() === $customer->id
            ? app(CartService::class)->getOrCreateCart()
            : (Cart::where('user_id', $customer->id)->where('status', 'active')->latest('id')->first()
                ?? Cart::create(['user_id' => $customer->id, 'session_id' => Str::random(40), 'status' => 'active']));

        $line = new CartItem([
            'product_id' => $quote->product_id,
            'product_variant_id' => $quote->product_variant_id,
            'quantity' => (int) $quote->quoted_quantity,
            'price' => round((float) $quote->unit_price, 2),
        ]);
        $line->forceFill(['cart_id' => $cart->id, 'quote_request_id' => $quote->id])->save();
        $cart->touch();

        return $line;
    }

    /**
     * The shop's own price for the product or option now (shown beside the quote).
     */
    protected function listPrice(QuoteRequest $quote): ?float
    {
        $product = $quote->product;
        if (! $product) {
            return null;
        }
        $variant = $quote->product_variant_id ? ProductVariant::where('product_id', $product->id)->find($quote->product_variant_id) : null;

        return round($variant ? $variant->setRelation('product', $product)->effectivePrice() : (float) $product->final_price, 2);
    }

    /**
     * The request's row, locked for the rest of the transaction (taken before any other read).
     */
    protected function lock(QuoteRequest $quote): QuoteRequest
    {
        return QuoteRequest::whereKey($quote->id)->lockForUpdate()->firstOrFail();
    }

    protected function notify(?User $user, Notification $notification): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            report($e); // an email that fails never undoes the step
        }
    }

    /**
     * "MVR 1,250.00 × 40 = MVR 50,000.00" for emails and pages.
     */
    public static function priceLine(QuoteRequest $quote): string
    {
        return __(':price × :quantity = :total', [
            'price' => Money::format($quote->unit_price),
            'quantity' => (int) $quote->quoted_quantity,
            'total' => Money::format($quote->lineTotal()),
        ]);
    }
}
