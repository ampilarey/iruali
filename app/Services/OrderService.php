<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Cart;
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
     * Create a new order from cart
     */
    public function createOrderFromCart(User $user, array $shippingData): array
    {
        $cart = $user->carts()->where('status', 'active')->latest()->first();

        if (!$cart || $cart->items->count() === 0) {
            return ['success' => false, 'message' => 'Your cart is empty.'];
        }

        try {
            DB::beginTransaction();

            // Calculate discounts and totals
            $discounts = $this->discountService->calculateTotalDiscount($cart);
            $loyaltyPointsEarned = $this->discountService->calculateLoyaltyPointsEarned($discounts['final_total']);

            // Create order
            $order = $this->createOrder($user, $cart, $shippingData, $discounts, $loyaltyPointsEarned);

            // Process discounts and points
            $this->processDiscounts($order, $discounts);

            // Create order items
            $this->createOrderItems($order, $cart);

            // Process loyalty points and referral rewards
            $this->processLoyaltyPoints($user, $loyaltyPointsEarned, $discounts['points']['points_redeemed']);
            $this->discountService->processReferralRewards($user);

            // Clear cart
            $this->clearCart($cart);

            DB::commit();

            return [
                'success' => true,
                'order' => $order,
                'message' => 'Order placed successfully!'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to create order: ' . $e->getMessage()];
        }
    }

    /**
     * Create order record
     */
    protected function createOrder(User $user, Cart $cart, array $shippingData, array $discounts, int $loyaltyPointsEarned): Order
    {
        $voucher = $discounts['voucher']['voucher'];
        $pointsRedeemed = $discounts['points']['points_redeemed'];

        return Order::create([
            'user_id' => $user->id,
            'order_number' => $this->generateOrderNumber(),
            'status' => 'pending',
            'total_amount' => $discounts['final_total'],
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
        ]);
    }

    /**
     * Process discounts and update related records
     */
    protected function processDiscounts(Order $order, array $discounts): void
    {
        $voucher = $discounts['voucher']['voucher'];
        
        // Increment voucher usage
        if ($voucher) {
            $voucher->increment('used_count');
            Session::forget('voucher_code');
        }

        // Clear points from session
        if ($discounts['points']['points_redeemed'] > 0) {
            Session::forget('points_redeemed');
        }
    }

    /**
     * Create order items from cart items
     */
    protected function createOrderItems(Order $order, Cart $cart): void
    {
        foreach ($cart->items as $cartItem) {
            $order->items()->create([
                'product_id' => $cartItem->product_id,
                'quantity' => $cartItem->quantity,
                'price' => $cartItem->product->final_price,
            ]);
        }
    }

    /**
     * Process loyalty points
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
        $cart->save();
    }

    /**
     * Generate unique order number
     */
    protected function generateOrderNumber(): string
    {
        return 'ORD-' . strtoupper(uniqid());
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
     * Update order status
     */
    public function updateOrderStatus(Order $order, string $status): bool
    {
        $order->update(['status' => $status]);
        return true;
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
            ]
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

        if (!$cart) {
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
            'delivered' => ['status' => 'Delivered', 'description' => 'Your order has been delivered'],
            'cancelled' => ['status' => 'Cancelled', 'description' => 'Your order has been cancelled']
        ];

        return [
            'order_number' => $order->order_number,
            'current_status' => $order->status,
            'status_info' => $statusTimeline[$order->status] ?? ['status' => 'Unknown', 'description' => 'Status not available'],
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at
        ];
    }
} 