<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SellerDeliverySetting;
use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\PreorderArrived;
use App\Notifications\PreorderDateChanged;
use App\Support\Audit;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\URL;
use RuntimeException;
use Throwable;

/**
 * Pre-orders: customers pay now for goods coming on a later shipment (stock reaches the islands
 * by ship). The shop switches them on per product (product form → "Pre-order"): the date it
 * expects to send them, the most units it takes (counted across the product's variants) and an
 * optional note.
 *
 * - Shoppers: while the product, or the chosen option, has no stock and units are left, the
 *   product page says "Pre-order: ships around <date>" and the button says "Pre-order"; the
 *   cart and checkout mark the line. Cart quantities may go up to the units left
 *   (CartService::availableStock(), CartItem::availableStock()). A shop on holiday takes none.
 * - Checkout takes no stock for a pre-order line, so stock never goes below zero. OrderService
 *   calls reserveForCheckout() first in its transaction: it locks the cart's stock rows and holds
 *   the units against the limit (two checkouts for the last unit cannot both win), then
 *   markOrder() flags the order lines and their shop parts: "Awaiting stock", which can be
 *   prepared but not sent (FulfilmentService, OrderService::nextStatuses()).
 * - Seller Centre → Pre-orders → "Stock arrived" (receiveStock()) gives the units to the paid
 *   pre-orders, oldest first, emails those customers and puts what is left on sale.
 * - Moving the expected date (dateMoved()) emails the waiting customers with a link to cancel;
 *   cancelling goes through the normal cancellation (OrderService::updateOrderStatus()), which
 *   flags the card refund like any paid order cancelled. A daily check (flagLate()) puts
 *   pre-orders more than a week past their date in Admin → Inbox.
 */
class PreorderService
{
    /** Days past the expected date after which a pre-order still waiting is late (Admin → Inbox). */
    public const LATE_AFTER_DAYS = 7;

    /** The most units a shop can take as pre-orders for one product. */
    public const MAX_LIMIT = 99999;

    /** Session: the cart lines the checkout page showed as pre-orders, with their dates. */
    public const SESSION_SHOWN = 'preorder_checkout_lines';

    // ---- The product form -----------------------------------------------------------------------

    /**
     * Rules for the product form's "Pre-order" fieldset. The date, the limit and the note are only
     * read while pre-orders are switched on; a form without the fieldset changes nothing.
     *
     * @return array<string, mixed>
     */
    public static function formRules(): array
    {
        $on = 'exclude_unless:preorder_enabled,1';

        return [
            'preorder_form' => 'nullable|boolean',
            'preorder_enabled' => 'nullable|boolean',
            'preorder_ship_date' => [$on, 'required', 'date', 'after:today', 'before_or_equal:'.today()->addYear()->toDateString()],
            'preorder_limit' => [$on, 'required', 'integer', 'min:1', 'max:'.self::MAX_LIMIT],
            'preorder_note' => [$on, 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function formMessages(): array
    {
        return [
            'preorder_ship_date.required' => __('Give the date you expect to send the pre-orders.'),
            'preorder_ship_date.date' => __('Give the date you expect to send the pre-orders.'),
            'preorder_ship_date.after' => __('The expected ship date must be after today.'),
            'preorder_ship_date.before_or_equal' => __('Choose an expected ship date within the next year.'),
            'preorder_limit.required' => __('Give the most units you will take as pre-orders.'),
            'preorder_limit.min' => __('Take at least 1 unit as a pre-order.'),
        ];
    }

    /**
     * The product's pre-order columns from the validated form (nothing when the form had no
     * "Pre-order" fieldset). Switching pre-orders off keeps the date, limit and note for next time.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function attributesFromForm(array $data): array
    {
        if (! array_key_exists('preorder_form', $data)) {
            return [];
        }
        if (empty($data['preorder_enabled'])) {
            return ['preorder_enabled' => false];
        }

        $note = trim((string) ($data['preorder_note'] ?? ''));

        return [
            'preorder_enabled' => true,
            'preorder_ship_date' => $data['preorder_ship_date'],
            'preorder_limit' => (int) $data['preorder_limit'],
            'preorder_note' => $note !== '' ? $note : null,
        ];
    }

    // ---- Shoppers -------------------------------------------------------------------------------

    /**
     * The product takes pre-orders now, whatever its stock: switched on, an expected date from
     * today on, a limit, and on sale. A shop on holiday is refused where every order is
     * (CartService::addToCart(), OrderService) and productOffer() shows nothing for it.
     */
    public function accepting(Product $product): bool
    {
        $date = $product->preorder_ship_date;

        return (bool) $product->preorder_enabled
            && (bool) $product->is_active
            && (int) $product->preorder_limit > 0
            && $date !== null
            && ! $date->isBefore(today());
    }

    /**
     * Units bought as pre-orders that are still waiting for their stock, across all the product's
     * variants (orders that are not cancelled).
     */
    public function waitingUnits(int $productId): int
    {
        return (int) DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.product_id', $productId)
            ->where('order_items.is_preorder', true)
            ->whereNull('order_items.preorder_allocated_at')
            ->where('orders.status', '!=', 'cancelled')
            ->whereNull('orders.deleted_at')
            ->sum(DB::raw('order_items.quantity - order_items.preorder_allocated_quantity'));
    }

    /**
     * Pre-order units the shop still takes for this product (shared by all its variants), or 0
     * when it takes none (or the option is switched off).
     */
    public function unitsLeft(Product $product, ?ProductVariant $variant = null): int
    {
        if (! $this->accepting($product) || ($variant !== null && ! $variant->is_active)) {
            return 0;
        }

        return max(0, (int) $product->preorder_limit - $this->waitingUnits((int) $product->id));
    }

    /**
     * The product page's pre-order offer, or null: the shop takes pre-orders for it now (not on
     * holiday, units left) and the product, or at least one of its options, is out of stock.
     * "whole" is true when the product itself is out of stock (else only some options are).
     *
     * @return array{date: CarbonInterface, note: ?string, left: int, whole: bool}|null
     */
    public function productOffer(Product $product): ?array
    {
        $date = $product->preorder_ship_date;
        if ($date === null || ! $this->accepting($product) || $product->seller?->isOnHoliday()) {
            return null;
        }

        $whole = $product->effectiveStock() <= 0;
        if (! $whole && ! ($product->has_variants && $this->activeVariants($product)->contains(fn (ProductVariant $variant) => (int) $variant->stock_quantity <= 0))) {
            return null;
        }

        $left = $this->unitsLeft($product);

        return $left > 0 ? ['date' => $date, 'note' => $product->preorder_note, 'left' => $left, 'whole' => $whole] : null;
    }

    /**
     * Is this product (or this option of it) out of stock and open for pre-orders right now? The
     * feeds and the structured data ask; a shop on holiday shows as out of stock instead.
     */
    public function isPreorderable(Product $product, ?ProductVariant $variant = null): bool
    {
        $stock = $variant ? ($variant->is_active ? (int) $variant->stock_quantity : 0) : $product->effectiveStock();

        return $stock <= 0 && $this->accepting($product) && ! $product->seller?->isOnHoliday() && $this->unitsLeft($product, $variant) > 0;
    }

    /**
     * "Arrives around 5–8 Nov": the expected ship date (today at the earliest), then the shop's
     * dispatch days and the transit days to the area (DeliveryService::arrivalWindow()).
     */
    public function arrivalText(int $dispatchDays, string $area, CarbonInterface $shipDate): string
    {
        $from = $shipDate->isBefore(today()) ? today() : $shipDate;
        [$first, $last] = app(DeliveryService::class)->arrivalWindow($dispatchDays, $area, $from);

        return __('Arrives around :dates', ['dates' => DeliveryService::dateRange($first, $last)]);
    }

    /**
     * The product page's delivery box (DeliveryService::productBox()) for a product on pre-order:
     * the estimate counts from the expected ship date; with only some options on pre-order it
     * gives both estimates.
     *
     * @param  array<string, mixed>  $box
     * @return array<string, mixed>
     */
    public function deliveryBox(array $box, Product $product): array
    {
        $offer = $this->productOffer($product);
        if ($offer === null) {
            return $box;
        }

        [$first, $last] = app(DeliveryService::class)->arrivalWindow((int) $box['shipsWithinDays'], (string) $box['destination']['area'], $offer['date']->isBefore(today()) ? today() : $offer['date']);
        $box['arrival'] = $offer['whole']
            ? $this->arrivalText((int) $box['shipsWithinDays'], (string) $box['destination']['area'], $offer['date'])
            : $box['arrival'].' · '.__('Pre-order options arrive around :dates', ['dates' => DeliveryService::dateRange($first, $last)]);
        $box['preorder'] = true;

        return $box;
    }

    /**
     * A cart line that would be ordered as a pre-order now (no stock for it, the shop takes
     * pre-orders, units left), or null: its expected ship date and the shop's note.
     *
     * @return array{date: CarbonInterface, note: ?string}|null
     */
    public function cartLine(CartItem $item): ?array
    {
        $product = $item->product;
        $date = $product?->preorder_ship_date;
        if (! $product || $date === null || ! $this->accepting($product) || ($product->has_variants && ! $item->product_variant_id)) {
            return null;
        }

        $variant = $item->product_variant_id ? $item->variant : null;
        if ($item->product_variant_id && (! $variant || ! $variant->is_active)) {
            return null;
        }
        $stock = $variant ? (int) $variant->stock_quantity : (int) $product->stock_quantity;

        return $stock <= 0 && $this->unitsLeft($product, $variant) > 0 ? ['date' => $date, 'note' => $product->preorder_note] : null;
    }

    /**
     * The checkout page's pre-order lines, keyed by cart item id. They are remembered in the
     * session, so that placing the order can tell a line the customer saw as a pre-order from one
     * that sold out in the meantime (reserveForCheckout()).
     *
     * @return array<int, array{date: CarbonInterface, note: ?string}>
     */
    public function checkoutLines(Cart $cart): array
    {
        $lines = [];
        foreach ($cart->items as $item) {
            if ($line = $this->cartLine($item)) {
                $lines[(int) $item->id] = $line;
            }
        }

        Session::put(self::SESSION_SHOWN, array_map(fn (array $line) => $line['date']->toDateString(), $lines));

        return $lines;
    }

    /**
     * The checkout's per-shop delivery estimates (DeliveryService::checkout()): a shop with
     * pre-order lines sends its part when their stock is in, so its estimates count from the
     * latest expected date, and so does its pickup.
     *
     * @param  Collection<int, array<string, mixed>>  $shops
     * @param  array<string, string>  $areas
     * @param  array<int, array{date: CarbonInterface, note: ?string}>|null  $lines  checkoutLines() when already worked out
     * @return Collection<int, array<string, mixed>>
     */
    public function withCheckoutEstimates(Collection $shops, Cart $cart, array $areas, ?array $lines = null): Collection
    {
        $dates = [];
        foreach ($cart->items as $item) {
            $line = $lines !== null ? ($lines[(int) $item->id] ?? null) : $this->cartLine($item);
            if ($line !== null) {
                $key = (string) (int) ($item->product->seller_id ?? 0);
                $dates[$key] = isset($dates[$key]) && $dates[$key]->greaterThan($line['date']) ? $dates[$key] : $line['date'];
            }
        }
        if ($dates === []) {
            return $shops;
        }

        return $shops->map(function (array $shop) use ($dates, $areas) {
            $date = $dates[(string) $shop['key']] ?? null;
            if ($date === null) {
                return $shop;
            }

            $days = SellerDeliverySetting::for((int) $shop['key'] ?: null)->shipsWithinDays();
            $shop['estimates'] = collect($areas)->map(fn ($label, $area) => $this->arrivalText($days, (string) $area, $date))->all();
            if (is_array($shop['pickup'] ?? null)) {
                $shop['pickup']['ready'] = __('Ready to collect once the pre-order stock is in, around :date', ['date' => $date->translatedFormat('j M')]);
            }
            $shop['preorder_date'] = $date;

            return $shop;
        });
    }

    // ---- Checkout (OrderService::createOrderFromCart()) -----------------------------------------

    /**
     * Run first in the order's transaction. Locks the cart's variant and product rows before any
     * plain read (with MariaDB's snapshot isolation a lock taken after a plain read fails when
     * another checkout changed the row in between), then picks the pre-order lines: no stock for
     * them and the shop takes pre-orders. Their units are checked against the limit with those
     * already waiting, under the product's lock, so two checkouts for the last unit cannot both
     * get it. Lines with stock are left to OrderService::takeStock().
     *
     * @return array<int, array{product_id: int, variant_id: ?int, seller_id: int, date: CarbonInterface}> keyed by cart item id
     *
     * @throws RuntimeException a message for the customer
     */
    public function reserveForCheckout(Cart $cart, ?User $user): array
    {
        $items = $cart->items->filter(fn (CartItem $item) => $item->product_id !== null);
        if ($items->isEmpty()) {
            return [];
        }

        // Variants first, then products, each in id order: the order takeStock() and the stock page use
        $variantIds = $items->pluck('product_variant_id')->filter()->unique()->sort()->values();
        $variants = $variantIds->isEmpty() ? collect() : ProductVariant::whereIn('id', $variantIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $products = Product::whereIn('id', $items->pluck('product_id')->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        $lines = [];
        $wanted = [];
        foreach ($items as $item) {
            $product = $products[$item->product_id] ?? null;
            $variant = $item->product_variant_id ? ($variants[$item->product_variant_id] ?? null) : null;
            $date = $product?->preorder_ship_date;
            if (! $product || $date === null || ($item->product_variant_id && ! $variant) || ($product->has_variants && ! $variant)) {
                continue; // OrderService refuses these lines itself
            }

            $stock = $variant ? ($variant->is_active ? (int) $variant->stock_quantity : 0) : (int) $product->stock_quantity;
            // The smoke test only ever orders what is in stock; a shop on holiday takes no pre-orders
            if ($stock > 0 || ! $this->accepting($product) || ($variant && ! $variant->is_active) || $user?->isSmokeTest() || $product->seller?->isOnHoliday()) {
                continue;
            }

            $lines[(int) $item->id] = ['product_id' => (int) $product->id, 'variant_id' => $variant ? (int) $variant->id : null, 'seller_id' => (int) ($product->seller_id ?? 0), 'date' => $date];
            $wanted[(int) $product->id] = ($wanted[(int) $product->id] ?? 0) + (int) $item->quantity;
        }

        foreach ($wanted as $productId => $units) {
            $product = $products[$productId];
            $left = max(0, (int) $product->preorder_limit - $this->waitingUnits($productId));
            if ($units > $left) {
                throw new RuntimeException($left > 0
                    ? trans_choice('Sorry, only :count pre-order unit of ":name" is left.|Sorry, only :count pre-order units of ":name" are left.', $left, ['count' => $left, 'name' => $product->name])
                    : __('Sorry, ":name" can\'t be pre-ordered any more: the shop has taken all the pre-orders it can.', ['name' => $product->name]));
            }
        }

        // The checkout page said which lines were pre-orders: one that sold out since, or whose date
        // moved later, is not bought as a pre-order without the customer seeing it first
        $shown = Session::get(self::SESSION_SHOWN);
        if (is_array($shown)) {
            foreach ($lines as $id => $line) {
                $seen = $shown[$id] ?? null;
                if (! is_string($seen) || $line['date']->toDateString() > $seen) {
                    throw new RuntimeException(__('":name" is now a pre-order that ships around :date. Please check your cart and place the order again.', ['name' => $products[$line['product_id']]->name, 'date' => $line['date']->translatedFormat('j M')]));
                }
            }
        }

        return $lines;
    }

    /**
     * Flag the new order's pre-order lines (with their expected date) and their shop parts:
     * "Awaiting stock" until the stock comes. No stock is taken for them.
     *
     * @param  array<int, array{product_id: int, variant_id: ?int, seller_id: int, date: CarbonInterface}>  $lines  from reserveForCheckout()
     */
    public function markOrder(Order $order, array $lines): void
    {
        if ($lines === []) {
            return;
        }

        $byLine = collect($lines)->keyBy(fn (array $line) => $line['product_id'].':'.($line['variant_id'] ?? 0));
        $dates = [];
        foreach ($order->items()->get() as $item) {
            $line = $byLine[$item->product_id.':'.($item->product_variant_id ?? 0)] ?? null;
            if ($line === null) {
                continue;
            }

            $item->forceFill(['is_preorder' => true, 'preorder_ship_date' => $line['date']])->save();
            $seller = (int) $line['seller_id'];
            $dates[$seller] = isset($dates[$seller]) && $dates[$seller]->greaterThan($line['date']) ? $dates[$seller] : $line['date'];
        }

        foreach ($order->sellerOrders()->get() as $part) {
            if ($date = $dates[(int) ($part->seller_id ?? 0)] ?? null) {
                $part->forceFill(['awaiting_stock' => true, 'preorder_ship_date' => $date])->save();
            }
        }

        $order->unsetRelation('items'); // read again with the flags
    }

    // ---- Orders ---------------------------------------------------------------------------------

    /** Any shop part of the order still awaiting pre-order stock. */
    public function orderAwaitingStock(Order $order): bool
    {
        return $order->sellerOrders()->where('awaiting_stock', true)->where('status', '!=', 'cancelled')->exists();
    }

    public function hasPreorders(Order $order): bool
    {
        return $order->items()->where('is_preorder', true)->exists();
    }

    /**
     * When a part's days to ship start: the payment; for pre-order items, the day their stock
     * arrived, or while they still wait, the date they are expected. Used for late shipments
     * (SellerPerformanceService) and for when "not delivered" can be reported (DisputeService).
     */
    public function dispatchClockStart(SellerOrder $part, CarbonInterface $paidAt): Carbon
    {
        $start = Carbon::instance($paidAt);

        $from = $part->awaiting_stock ? $part->preorder_ship_date?->copy()->startOfDay() : $part->stock_arrived_at;

        return $from !== null && $from->greaterThan($start) ? Carbon::instance($from) : $start;
    }

    /**
     * Lines for the customer's "order received" email about its pre-order items.
     *
     * @return list<string>
     */
    public function customerNotes(Order $order): array
    {
        $notes = [];
        foreach ($order->items()->where('is_preorder', true)->with('product')->get() as $item) {
            if ($item->preorder_ship_date) {
                $notes[] = __('Pre-order: :item ships around :date. We will email you when its stock arrives.', ['item' => $item->displayName(), 'date' => $item->preorder_ship_date->translatedFormat('j M')]);
            }
        }
        if ($notes !== []) {
            $notes[] = __('Rather not wait? You can cancel the order for a full refund until it is sent.');
        }

        return $notes;
    }

    /**
     * Lines for a shop's "new order" email about its pre-order items.
     *
     * @return list<string>
     */
    public function shopNotes(Order $order, ?int $sellerId): array
    {
        $items = $order->items()->where('is_preorder', true)->with('product')->get()
            ->filter(fn (OrderItem $item) => (int) ($item->product->seller_id ?? 0) === (int) $sellerId);

        $notes = $items->map(fn (OrderItem $item) => __('Pre-order: :item × :quantity, waiting for stock (expected :date).', [
            'item' => $item->displayName(), 'quantity' => $item->quantity, 'date' => $item->preorder_ship_date?->translatedFormat('j M') ?? '',
        ]))->values()->all();
        if ($notes !== []) {
            $notes[] = __('When the stock arrives, record it in the Seller Centre under Pre-orders: you can send this order once its stock is in.');
        }

        return $notes;
    }

    // ---- Stock arrived (Seller Centre → Pre-orders) ---------------------------------------------

    /**
     * Stock has arrived for a product: the units received (per variant id, or under 0 for a
     * product without variants) and any already on the shelf go to the paid pre-orders still
     * waiting, oldest first; what is left goes on sale. A line gets what there is, so a partial
     * arrival covers the oldest orders and the next arrival carries on. A shop part with nothing
     * left waiting follows the normal flow again, and customers whose items are covered are emailed.
     *
     * @param  array<int, int>  $received
     * @param  string  $source  stock_arrived (the shop recorded a delivery) or order_cancelled (units a cancelled order gave back)
     * @return array{received: int, allocated: int, to_stock: int, orders: int}
     */
    public function receiveStock(Product $product, array $received, string $source = 'stock_arrived'): array
    {
        $completed = collect();

        $summary = DB::transaction(function () use ($product, $received, $source, &$completed) {
            // Every row this changes is locked first, before any plain read (snapshot isolation): the
            // variants, the product, the waiting pre-order lines oldest first, their orders and shop parts
            $variants = $product->has_variants ? ProductVariant::where('product_id', $product->id)->orderBy('id')->lockForUpdate()->get()->keyBy('id') : collect();
            $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
            $waiting = OrderItem::where('product_id', $locked->id)->where('is_preorder', true)->whereNull('preorder_allocated_at')->orderBy('id')->lockForUpdate()->get();
            $orders = $waiting->isEmpty() ? collect() : Order::whereIn('id', $waiting->pluck('order_id')->unique()->sort()->values())->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $parts = $orders->isEmpty() ? collect() : SellerOrder::whereIn('order_id', $orders->keys())->where('seller_id', $locked->seller_id)->orderBy('id')->lockForUpdate()->get()->keyBy('order_id');

            // What can be handed out: what is on the shelf plus what came, per variant (0 without variants)
            $pool = [];
            if ($locked->has_variants) {
                foreach ($variants as $id => $variant) {
                    $pool[(int) $id] = max(0, (int) $variant->stock_quantity) + max(0, (int) ($received[(int) $id] ?? 0));
                }
            } else {
                $pool[0] = max(0, (int) $locked->stock_quantity) + max(0, (int) ($received[0] ?? 0));
            }
            $total = array_sum(array_map(fn ($units) => max(0, (int) $units), $received));

            $allocated = 0;
            $touchedOrders = [];
            foreach ($waiting as $item) {
                $order = $orders[$item->order_id] ?? null;
                // Units go to pre-orders that are paid for and still on, in the order they were placed
                if (! $order || $order->status === 'cancelled' || $order->payment_status !== 'paid') {
                    continue;
                }
                $key = $locked->has_variants ? (int) $item->product_variant_id : 0;
                $need = (int) $item->quantity - (int) $item->preorder_allocated_quantity;
                $give = min($need, $pool[$key] ?? 0);
                if ($give <= 0) {
                    continue;
                }

                $pool[$key] -= $give;
                $allocated += $give;
                $item->preorder_allocated_quantity = (int) $item->preorder_allocated_quantity + $give;
                if ($item->preorder_allocated_quantity >= (int) $item->quantity) {
                    $item->preorder_allocated_at = now();
                    $item->preorder_late_at = null;
                    $completed->push($item);
                    $touchedOrders[(int) $item->order_id] = true;
                }
                $item->save();
            }

            // A part with nothing left waiting follows the normal flow (it can be sent now)
            foreach (array_keys($touchedOrders) as $orderId) {
                $part = $parts[$orderId] ?? null;
                if (! $part || ! $part->awaiting_stock) {
                    continue;
                }
                $stillWaiting = $this->waitingLinesOfPart($part);
                $part->forceFill($stillWaiting->isEmpty()
                    ? ['awaiting_stock' => false, 'stock_arrived_at' => now()]
                    : ['preorder_ship_date' => $stillWaiting->max('preorder_ship_date')])->save();
            }

            // What is left goes on sale (saved as a model, so back-in-stock alerts go out for it)
            if ($locked->has_variants) {
                foreach ($variants as $id => $variant) {
                    if ($pool[(int) $id] !== (int) $variant->stock_quantity) {
                        $variant->setRelation('product', $locked)->update(['stock_quantity' => $pool[(int) $id]]);
                    }
                }
            } elseif ($pool[0] !== (int) $locked->stock_quantity) {
                $locked->update(['stock_quantity' => $pool[0]]);
            }

            $summary = ['received' => $total, 'allocated' => $allocated, 'to_stock' => array_sum($pool), 'orders' => count($touchedOrders)];
            if ($total > 0 || $allocated > 0) {
                Audit::record('preorder.stock_arrived', $locked, $summary + ['source' => $source, 'product' => $locked->getTranslation('name', 'en', false) ?: $locked->name]);
            }

            return $summary;
        });

        $this->notifyArrived($completed);

        return $summary;
    }

    /**
     * The pre-order lines of a shop part still waiting for stock (the part's own products only).
     *
     * @return Collection<int, OrderItem>
     */
    protected function waitingLinesOfPart(SellerOrder $part): Collection
    {
        return OrderItem::where('order_id', $part->order_id)
            ->where('is_preorder', true)
            ->whereNull('preorder_allocated_at')
            ->whereHas('product', fn ($q) => $q->withTrashed()->where('seller_id', $part->seller_id))
            ->get();
    }

    /**
     * After an order is cancelled, units it gave back go first to the paid pre-orders still waiting
     * for the same products (in their own transaction, once the cancellation is committed).
     */
    public function afterOrderReversed(Order $order): void
    {
        $productIds = $order->items()->pluck('product_id')->unique()->values();

        DB::afterCommit(function () use ($productIds) {
            foreach ($productIds as $productId) {
                try {
                    $product = Product::find($productId);
                    if ($product && $product->effectiveStock() > 0 && $this->waitingUnits((int) $product->id) > 0) {
                        $this->receiveStock($product, [], 'order_cancelled');
                    }
                } catch (Throwable $e) {
                    report($e); // the shop can still hand them out under Pre-orders
                }
            }
        });
    }

    /**
     * @param  Collection<int, OrderItem>  $items  pre-order lines whose stock is now all there
     */
    protected function notifyArrived(Collection $items): void
    {
        foreach ($items->groupBy('order_id') as $orderId => $lines) {
            $order = Order::find($orderId);
            if ($order && $order->status !== 'cancelled') {
                app(OrderNotifier::class)->sendToCustomer($order, new PreorderArrived($order, $lines->pluck('id')->all()));
            }
        }
    }

    // ---- The expected date moved ----------------------------------------------------------------

    /**
     * The shop moved the product's expected ship date: the pre-orders still waiting take the new
     * date (no longer late), and their customers are emailed it with a link to cancel. Runs when
     * the product is saved with a new date (PreorderServiceProvider).
     */
    public function dateMoved(Product $product, ?CarbonInterface $from): void
    {
        $to = $product->preorder_ship_date;
        if ($to === null) {
            return;
        }

        $changed = DB::transaction(function () use ($product, $to) {
            // Locks first: the product (checkouts hold it while they add pre-orders), its waiting lines and their parts
            Product::whereKey($product->id)->lockForUpdate()->first();
            $items = OrderItem::where('product_id', $product->id)->where('is_preorder', true)->whereNull('preorder_allocated_at')->orderBy('id')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                return collect();
            }
            $parts = SellerOrder::whereIn('order_id', $items->pluck('order_id')->unique()->sort()->values())->where('seller_id', $product->seller_id)->orderBy('id')->lockForUpdate()->get();

            $live = Order::whereIn('id', $items->pluck('order_id')->unique())->where('status', '!=', 'cancelled')->pluck('id')->flip();
            $changed = $items->filter(fn (OrderItem $item) => isset($live[$item->order_id]) && ! $item->preorder_ship_date?->isSameDay($to));
            foreach ($changed as $item) {
                $item->forceFill(['preorder_ship_date' => $to->toDateString(), 'preorder_late_at' => null])->save();
            }

            foreach ($parts->whereIn('order_id', $changed->pluck('order_id')->unique()) as $part) {
                if ($part->awaiting_stock) {
                    $part->forceFill(['preorder_ship_date' => $this->waitingLinesOfPart($part)->max('preorder_ship_date')])->save();
                }
            }

            return $changed;
        });

        if ($changed->isEmpty()) {
            return;
        }

        Audit::record('preorder.date_moved', $product, [
            'from' => $from?->toDateString(), 'to' => $to->toDateString(),
            'orders' => $changed->pluck('order_id')->unique()->count(), 'product' => $product->getTranslation('name', 'en', false) ?: $product->name,
        ]);

        foreach ($changed->groupBy('order_id') as $orderId => $lines) {
            if ($order = Order::find($orderId)) {
                app(OrderNotifier::class)->sendToCustomer($order, new PreorderDateChanged($order, $lines->pluck('id')->all(), $to->toDateString(), $from?->toDateString()));
            }
        }
    }

    // ---- Cancelling -----------------------------------------------------------------------------

    /**
     * Can the customer cancel this order because of its pre-order (until anything in it is sent)?
     * Cancelling is the normal cancellation of the whole order.
     */
    public function canCancel(Order $order): bool
    {
        return $order->status !== 'cancelled' && $this->hasPreorders($order)
            && app(OrderService::class)->canTransition($order, 'cancelled');
    }

    /**
     * Cancel the order through the normal cancellation (OrderService::updateOrderStatus()): stock that
     * had arrived for it goes back, points, voucher and wallet are returned, and a card payment is
     * flagged for refund (PaymentService::flagRefund()) like any paid order cancelled.
     */
    public function cancel(Order $order): bool
    {
        if (! $this->canCancel($order) || ! app(OrderService::class)->updateOrderStatus($order, 'cancelled')) {
            return false;
        }

        Audit::record('preorder.cancelled', $order, ['order_number' => $order->order_number, 'refund_due' => (float) $order->fresh()?->refund_amount]);

        return true;
    }

    /**
     * Where the customer cancels: My Orders for an account, a signed link for a guest order.
     */
    public function cancelUrl(Order $order): string
    {
        return $order->isGuest() && $order->guest_token
            ? URL::signedRoute('guest.orders.preorder.cancel', ['order' => $order->getKey(), 'token' => $order->guest_token])
            : route('orders.preorder.cancel', $order);
    }

    // ---- Late pre-orders (daily, Admin → Inbox) -------------------------------------------------

    /**
     * Flag pre-orders still waiting more than LATE_AFTER_DAYS days after their expected date (each
     * once; moving the date clears it). Returns how many lines were flagged.
     */
    public function flagLate(): int
    {
        $ids = OrderItem::query()
            ->where('is_preorder', true)
            ->whereNull('preorder_allocated_at')
            ->whereNull('preorder_late_at')
            ->whereNotNull('preorder_ship_date')
            ->whereDate('preorder_ship_date', '<', today()->subDays(self::LATE_AFTER_DAYS)->toDateString())
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled'))
            ->pluck('id');

        // By id, so the update only locks the lines it flags; checked again in case their stock just came
        return $ids->isEmpty() ? 0 : OrderItem::whereIn('id', $ids)->whereNull('preorder_allocated_at')->whereNull('preorder_late_at')->update(['preorder_late_at' => now()]);
    }

    /** Orders with a pre-order flagged late and still waiting (the Admin → Inbox row). */
    public function lateOrderCount(): int
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('order_items.preorder_late_at')
            ->where('order_items.is_preorder', true)
            ->whereNull('order_items.preorder_allocated_at')
            ->where('orders.status', '!=', 'cancelled')
            ->whereNull('orders.deleted_at')
            ->distinct()
            ->count('order_items.order_id');
    }

    // ---- Helpers --------------------------------------------------------------------------------

    /**
     * @return Collection<int, ProductVariant>
     */
    protected function activeVariants(Product $product): Collection
    {
        return $product->relationLoaded('variants') ? $product->variants->where('is_active', true)->values() : $product->activeVariants()->get();
    }
}
