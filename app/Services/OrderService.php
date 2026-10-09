<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Voucher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

class OrderService
{
    protected $discountService;

    public function __construct(DiscountService $discountService)
    {
        $this->discountService = $discountService;
    }

    /**
     * Create a new order from cart.
     *
     * A guest order (guest checkout) passes no user: its token-keyed cart comes in $cart and the
     * guest's email and name in $guest. Guests earn no points and can't redeem any.
     *
     * @param  array{email?: string, name?: string}  $guest
     */
    public function createOrderFromCart(?User $user, array $shippingData, ?Cart $cart = null, array $guest = []): array
    {
        $cart ??= $user?->carts()->where('status', 'active')->latest()->first();

        if (! $cart || $cart->items->count() === 0) {
            return ['success' => false, 'message' => 'Your cart is empty.'];
        }

        // Stock check before order creation
        foreach ($cart->items as $cartItem) {
            $product = $cartItem->product;
            if (! $product) {
                return ['success' => false, 'message' => 'A product in your cart no longer exists.'];
            }
            // A shop that was rejected or suspended can't sell (products owned by staff have no seller flag)
            if ($product->seller_id && $product->seller?->is_seller && ! $product->seller->isSeller()) {
                return ['success' => false, 'message' => __('":name" is no longer available: the shop has closed.', ['name' => $product->name['en'] ?? $product->name])];
            }
            // A shop on holiday takes no new orders until it is back; the item stays in the cart
            if ($product->seller?->isOnHoliday()) {
                return ['success' => false, 'message' => \App\Support\ShopHoliday::cartMessage($product)];
            }
            // A product sold in variants must be bought as one of them; stock is then the variant's
            if ($product->has_variants && (! $cartItem->variant || ! $cartItem->variant->is_active)) {
                return ['success' => false, 'message' => __('":name" needs an option (size, colour...) chosen. Please add it to your cart again.', ['name' => $product->name['en'] ?? $product->name])];
            }
            $available = $cartItem->availableStock();
            if ($available < $cartItem->quantity) {
                return [
                    'success' => false,
                    'message' => 'Sorry, not enough stock for "'.($product->name['en'] ?? $product->name).($cartItem->variant ? ' ('.$cartItem->variant->displayName().')' : '').'". Available: '.$available.', Requested: '.$cartItem->quantity,
                ];
            }
        }

        try {
            DB::beginTransaction();

            // Calculate discounts and totals. Redeemed points come from the session; re-check them
            // against the customer's real balance and the cart as it is now.
            $discounts = $this->discountService->calculateTotalDiscount($cart);
            // Shop codes again, for whoever is ordering (a guest by email): one that stopped working refuses the order
            app(ShopDiscountService::class)->assertStillValid($discounts['shop'], $user, $guest['email'] ?? null);
            $redeem = min(
                (int) $discounts['points']['points_redeemed'],
                max(0, (int) $user?->fresh()->loyalty_points),
                (int) floor(max(0, $cart->total - $discounts['shop']['amount'] - $discounts['voucher']['amount']))
            );
            if ($redeem !== (int) $discounts['points']['points_redeemed']) {
                $discounts['points']['points_redeemed'] = $redeem;
                $discounts['points']['amount'] = $redeem;
                $discounts['total_discount'] = $discounts['shop']['amount'] + $discounts['voucher']['amount'] + $redeem;
                $discounts['final_total'] = max(0, $cart->total - $discounts['total_discount']);
            }
            $loyaltyPointsEarned = $user ? $this->discountService->calculateLoyaltyPointsEarned($discounts['final_total']) : 0;

            // Delivery fee by area (points are earned on goods only, not delivery): the area's fee once
            // for the shop parts that are delivered (none when everything is picked up) plus their
            // items' extra charges, which free delivery does not waive; also the Malé time slot
            // (checked again under a lock) and the gift details
            $delivery = app(DeliveryService::class);
            $shippingData['delivery_zone'] = $delivery->zoneFor($shippingData['delivery_zone'] ?? null, $shippingData['shipping_city'] ?? null);
            $shippingData = $delivery->prepareOrder($cart, $shippingData, (float) $discounts['final_total']);

            // Create order
            $order = $this->createOrder($user, $cart, $shippingData, $discounts, $loyaltyPointsEarned, $guest);

            // Process discounts and points
            $this->processDiscounts($order, $discounts);

            // Create order items
            $this->createOrderItems($order, $cart);

            // Shop-funded discounts (multi-buy, shop codes) onto the items, and the codes' uses recorded
            app(ShopDiscountService::class)->applyToOrder($order, $discounts['shop'], $user, $guest['email'] ?? null);

            // One part per shop, with its own fulfilment status and the shop's earnings
            app(FulfilmentService::class)->createParts($order);

            // Deliver or pickup per shop part, and the order's delivery area, time slot and gift details
            $delivery->applyToOrder($order, $shippingData);

            // Take the stock atomically: two checkouts racing for the last unit can't both win.
            // A line with a variant takes it from the variant row (the product total follows).
            foreach ($cart->items as $cartItem) {
                $taken = $this->takeStock($cartItem->product_id, $cartItem->product_variant_id, $cartItem->quantity);
                if ($taken === 0) {
                    throw new \RuntimeException(__('Sorry, ":name" just sold out.', ['name' => ($cartItem->product->name['en'] ?? $cartItem->product->name).($cartItem->variant ? ' ('.$cartItem->variant->displayName().')' : '')]));
                }
            }

            // Redeemed points are taken now; earned points and referral rewards come when the order is paid.
            if ($user && $discounts['points']['points_redeemed'] > 0) {
                app(PointsService::class)->record($user, -(int) $discounts['points']['points_redeemed'], 'redeemed', $order);
            }

            // Store credit pays as much of the total as it can; the rest (if any) goes to the card
            $walletPaid = ! empty($shippingData['use_wallet']) ? app(WalletService::class)->payFromWallet($order, $user) : 0.0;

            // Clear cart
            $this->clearCart($cart);

            DB::commit();

            // The wallet covered everything: the order is paid, no card step
            if ($walletPaid > 0 && $order->fresh()->payment_method === 'wallet') {
                app(PaymentService::class)->confirm($order);
            }

            app(OrderNotifier::class)->orderPlaced($order);

            return [
                'success' => true,
                'order' => $order,
                'message' => 'Order placed successfully!',
            ];

        } catch (\RuntimeException $e) {
            DB::rollBack();

            return ['success' => false, 'message' => $e->getMessage()]; // our own checks, safe to show
        } catch (\Throwable $e) {
            DB::rollBack();
            report($e);

            return ['success' => false, 'message' => __('We could not place your order. Please try again.')];
        }
    }

    /**
     * Create order record
     */
    protected function createOrder(?User $user, Cart $cart, array $shippingData, array $discounts, int $loyaltyPointsEarned, array $guest = []): Order
    {
        $voucher = $discounts['voucher']['voucher'];
        $pointsRedeemed = $discounts['points']['points_redeemed'];

        return Order::create([
            'user_id' => $user?->id,
            // Guest checkout: who to email, and a token that signed order links carry
            'guest_email' => $user ? null : ($guest['email'] ?? null),
            'guest_name' => $user ? null : ($guest['name'] ?? null),
            'guest_token' => $user ? null : \Illuminate\Support\Str::random(40),
            'order_number' => $this->generateOrderNumber(),
            'status' => 'pending',
            'total_amount' => $discounts['final_total'] + $shippingData['shipping_amount'],
            'shipping_amount' => $shippingData['shipping_amount'],
            'delivery_zone' => $shippingData['delivery_zone'],
            'payment_method' => $shippingData['payment_method'] ?? 'bml',
            'voucher_code' => $voucher ? $voucher->code : null,
            'voucher_discount' => $discounts['voucher']['amount'],
            'loyalty_points_earned' => $loyaltyPointsEarned,
            'points_redeemed' => $pointsRedeemed,
            'points_redeemed_discount' => $discounts['points']['amount'],
            'shipping_address' => $shippingData['shipping_address'],
            'shipping_city' => $shippingData['shipping_city'],
            'shipping_state' => $shippingData['shipping_state'],
            'shipping_zip' => $shippingData['shipping_zip'],
            'shipping_country' => $shippingData['shipping_country'],
            'shipping_phone' => $shippingData['shipping_phone'] ?? null,
        ]);
    }

    /**
     * Process discounts and update related records
     */
    protected function processDiscounts(Order $order, array $discounts): void
    {
        $voucher = $discounts['voucher']['voucher'];

        // Use up the voucher, with the row locked so the last use can't be taken twice
        if ($voucher) {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->first();
            if (! $locked || ! $locked->is_active || ($locked->max_uses && $locked->used_count >= $locked->max_uses)) {
                throw new \RuntimeException(__('Voucher usage limit reached.'));
            }
            $locked->increment('used_count');
            Session::forget('voucher_code');
        }

        // Clear points from session
        if ($discounts['points']['points_redeemed'] > 0) {
            Session::forget('points_redeemed');
        }
    }

    /**
     * Create order items from cart items (with the variant's name and SKU as they are now)
     */
    protected function createOrderItems(Order $order, Cart $cart): void
    {
        foreach ($cart->items as $cartItem) {
            $variant = $cartItem->variant;
            $order->items()->create([
                'product_id' => $cartItem->product_id,
                'product_variant_id' => $variant?->id,
                'variant_name' => $variant?->displayName(),
                'variant_sku' => $variant?->sku,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->unit_price,
            ]);
        }
    }

    /**
     * Conditionally take units from a variant row, or the product row when the line has no variant.
     * Returns the number of rows updated (0 when there was not enough stock).
     */
    protected function takeStock(int $productId, ?int $variantId, int $quantity): int
    {
        if ($variantId) {
            $taken = ProductVariant::whereKey($variantId)->where('product_id', $productId)
                ->where('stock_quantity', '>=', $quantity)
                ->decrement('stock_quantity', $quantity);
            if ($taken > 0) {
                Product::find($productId)?->syncStockFromVariants();
            }

            return $taken;
        }

        return Product::whereKey($productId)
            ->where('stock_quantity', '>=', $quantity)
            ->decrement('stock_quantity', $quantity);
    }

    /**
     * Put units back on the row they were taken from (a cancelled order, an approved return).
     */
    public function restock(Product $product, ?int $variantId, int $quantity): void
    {
        if ($variantId && $product->variants()->whereKey($variantId)->exists()) {
            ProductVariant::whereKey($variantId)->increment('stock_quantity', $quantity);
            $product->syncStockFromVariants();

            return;
        }

        $product->increment('stock_quantity', $quantity);
    }

    /**
     * Give the customer the points this order earns, and the referral reward if this is their first
     * paid order. Runs when payment is confirmed; safe to call again.
     */
    public function awardRewards(Order $order): void
    {
        $user = $order->user;
        if (! $user || $user->isSmokeTest() || $order->payment_status !== 'paid' || $order->status === 'cancelled') {
            return;
        }

        if (! $order->loyalty_points_awarded_at) {
            if ($order->loyalty_points_earned > 0) {
                app(PointsService::class)->record($user, (int) $order->loyalty_points_earned, 'earned', $order);
            }
            $order->forceFill(['loyalty_points_awarded_at' => now()])->save();
        }

        $this->discountService->processReferralRewards($user, $order);
    }

    /**
     * @deprecated Points are awarded by awardRewards() once the order is paid.
     */
    protected function processLoyaltyPoints(User $user, int $pointsEarned, int $pointsRedeemed): void
    {
        // Deduct redeemed points
        if ($pointsRedeemed > 0) {
            $user->decrement('loyalty_points', $pointsRedeemed);
        }

        // Award loyalty points
        if ($pointsEarned > 0) {
            $user->increment('loyalty_points', $pointsEarned);
        }
    }

    /**
     * Clear cart after order creation
     */
    protected function clearCart(Cart $cart): void
    {
        $cart->items()->delete();
        $cart->status = 'ordered';
        $cart->voucher_code = null;
        $cart->save();
    }

    /**
     * Generate unique order number
     */
    protected function generateOrderNumber(): string
    {
        // Random rather than time-based (uniqid), so order numbers can't be guessed from each other
        do {
            $number = 'ORD-'.strtoupper(\Illuminate\Support\Str::random(10));
        } while (Order::where('order_number', $number)->exists());

        return $number;
    }

    /**
     * Get user orders with pagination
     */
    public function getUserOrders(User $user, int $perPage = 10): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        return Order::where('user_id', $user->id)
            ->with('items.product')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Get order with related data
     */
    public function getOrderWithDetails(Order $order): Order
    {
        return $order->load('items.product');
    }

    /**
     * Allowed next statuses for each order status.
     */
    public const TRANSITIONS = [
        'pending' => ['processing', 'cancelled'],
        'processing' => ['shipped', 'cancelled'],
        'shipped' => ['out_for_delivery', 'delivered'],
        'out_for_delivery' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    /**
     * Statuses the order can move to next.
     */
    public function nextStatuses(Order $order): array
    {
        $next = self::TRANSITIONS[$order->status] ?? [];

        if (in_array('cancelled', $next, true) && app(FulfilmentService::class)->anyPartSent($order)) {
            $next = array_values(array_diff($next, ['cancelled']));
        }

        return $next;
    }

    public function canTransition(Order $order, string $status): bool
    {
        // Once a shop has sent its part, the order can't be cancelled as a whole (use a return instead).
        if ($status === 'cancelled' && app(FulfilmentService::class)->anyPartSent($order)) {
            return false;
        }

        return in_array($status, $this->nextStatuses($order), true);
    }

    /**
     * Move an order to a new status, enforcing the allowed transitions.
     * Cancelling also restocks the items and reverses loyalty points and voucher use.
     */
    public function updateOrderStatus(Order $order, string $status): bool
    {
        if (! $this->canTransition($order, $status)) {
            return false;
        }

        $before = $order->status;
        DB::transaction(function () use ($order, $status) {
            if ($status === 'cancelled') {
                $this->reverseOrder($order);
            }

            $order->update(['status' => $status]);

            // The wallet's share went straight back in reverseOrder(); only the card part needs a manual refund
            if ($status === 'cancelled' && $order->payment_status === 'paid' && $order->cardAmount() > 0) {
                app(PaymentService::class)->flagRefund($order, $order->cardAmount(), 'Order cancelled after payment');
            }

            // Bring every shop's part along with the order
            app(FulfilmentService::class)->cascadeFromOrder($order, $status);
        });

        \App\Support\Audit::record($status === 'cancelled' ? 'order.cancelled' : 'order.status', $order, ['from' => $before, 'to' => $status, 'order_number' => $order->order_number]);
        app(OrderNotifier::class)->statusChanged($order->fresh());

        return true;
    }

    /**
     * Move the order forward to $target through the normal steps (used when shops update their parts),
     * emailing the customer once about where it ended up. Never moves backwards or cancels.
     */
    public function advanceTo(Order $order, string $target): bool
    {
        $path = ['pending', 'processing', 'shipped', 'out_for_delivery', 'delivered'];
        $from = array_search($order->status, $path, true);
        $to = array_search($target, $path, true);

        if ($from === false || $to === false || $to <= $from) {
            return false;
        }

        $order->update(['status' => $target]);
        app(OrderNotifier::class)->statusChanged($order->fresh());

        return true;
    }

    /**
     * Undo the side effects of placing an order: stock, loyalty points and voucher use.
     */
    protected function reverseOrder(Order $order): void
    {
        foreach ($order->items()->with('product')->get() as $item) {
            if ($item->product) {
                $this->restock($item->product, $item->product_variant_id, $item->quantity);
            }
        }

        $user = $order->user;
        if ($user) {
            if ($order->points_redeemed > 0) {
                app(PointsService::class)->record($user, (int) $order->points_redeemed, 'refund', $order, __('Redeemed points returned'));
            }
            // Earned points are only taken back if they were actually given (the order was paid).
            // The balance can go below zero if they were already spent; nothing can be redeemed until it recovers.
            if ($order->loyalty_points_earned > 0 && $order->loyalty_points_awarded_at) {
                app(PointsService::class)->record($user, -(int) $order->loyalty_points_earned, 'refund', $order, __('Earned points taken back'));
                $order->forceFill(['loyalty_points_awarded_at' => null])->save();
            }
        }

        if ($order->voucher_code) {
            Voucher::where('code', $order->voucher_code)->where('used_count', '>', 0)->decrement('used_count');
        }

        // Store credit the order took goes back to the wallet; a gift card that was never paid for is dropped
        app(WalletService::class)->reverseOrderPayment($order);
        app(GiftCardService::class)->orderCancelled($order);
    }

    /**
     * Get order summary for display
     */
    public function getOrderSummary(Order $order): array
    {
        return [
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'voucher_discount' => $order->voucher_discount,
            'points_redeemed_discount' => $order->points_redeemed_discount,
            'loyalty_points_earned' => $order->loyalty_points_earned,
            'points_redeemed' => $order->points_redeemed,
            'item_count' => $order->items->count(),
            'created_at' => $order->created_at,
            'shipping_address' => [
                'address' => $order->shipping_address,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'zip' => $order->shipping_zip,
                'country' => $order->shipping_country,
                'phone' => $order->shipping_phone,
            ],
        ];
    }

    /**
     * Calculate order statistics for user
     */
    public function getUserOrderStats(User $user): array
    {
        $orders = $user->orders();

        return [
            'total_orders' => $orders->count(),
            'total_spent' => $orders->sum('total_amount'),
            'pending_orders' => $orders->where('status', 'pending')->count(),
            'completed_orders' => $orders->where('status', 'delivered')->count(),
            'loyalty_points_earned' => $orders->sum('loyalty_points_earned'),
        ];
    }

    /**
     * Validate order creation prerequisites
     */
    public function validateOrderCreation(User $user): array
    {
        $cart = $user->carts()->where('status', 'active')->latest()->first();

        if (! $cart) {
            return ['valid' => false, 'message' => 'No active cart found.'];
        }

        if ($cart->items->count() === 0) {
            return ['valid' => false, 'message' => 'Your cart is empty.'];
        }

        return ['valid' => true, 'cart' => $cart];
    }

    /**
     * Get order tracking information
     */
    public function getOrderTracking(Order $order): array
    {
        $statusTimeline = [
            'pending' => ['status' => 'Order Placed', 'description' => 'Your order has been placed and is being processed'],
            'processing' => ['status' => 'Processing', 'description' => 'Your order is being prepared for shipment'],
            'shipped' => ['status' => 'Shipped', 'description' => 'Your order has been shipped'],
            'out_for_delivery' => ['status' => 'Out for delivery', 'description' => 'Your order is out for delivery'],
            'delivered' => ['status' => 'Delivered', 'description' => 'Your order has been delivered'],
            'cancelled' => ['status' => 'Cancelled', 'description' => 'Your order has been cancelled'],
        ];

        return [
            'order_number' => $order->order_number,
            'current_status' => $order->status,
            'status_info' => $statusTimeline[$order->status] ?? ['status' => 'Unknown', 'description' => 'Status not available'],
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];
    }
}
