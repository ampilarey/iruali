<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\DiscountService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class OrderController extends BaseController
{
    protected $orderService;
    protected $discountService;

    public function __construct(OrderService $orderService, DiscountService $discountService)
    {
        $this->orderService = $orderService;
        $this->discountService = $discountService;
    }

    /**
     * Get user's orders
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
            'status' => 'nullable|in:pending,processing,shipped,delivered,cancelled',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = Auth::user();
        $perPage = $request->per_page ?? 10;

        $query = Order::where('user_id', $user->id)
            ->with(['items.product.mainImage']);

        if ($request->status) {
            $query->where('status', $request->status);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

        $orders->getCollection()->transform(function ($order) {
            return [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'status_badge' => $order->status_badge,
                'total_amount' => $order->total_amount,
                'voucher_code' => $order->voucher_code,
                'voucher_discount' => $order->voucher_discount,
                'loyalty_points_earned' => $order->loyalty_points_earned,
                'points_redeemed' => $order->points_redeemed,
                'points_redeemed_discount' => $order->points_redeemed_discount,
                'shipping_address' => [
                    'address' => $order->shipping_address,
                    'city' => $order->shipping_city,
                    'state' => $order->shipping_state,
                    'zip' => $order->shipping_zip,
                    'country' => $order->shipping_country,
                ],
                'items' => $order->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'subtotal' => $item->quantity * $item->price,
                        'product' => [
                            'id' => $item->product->id,
                            'name' => $item->product->name,
                            'sku' => $item->product->sku,
                            'slug' => $item->product->slug,
                            'main_image' => $item->product->main_image,
                        ],
                    ];
                }),
                'item_count' => $order->items->count(),
                'created_at' => $order->created_at,
                'updated_at' => $order->updated_at,
            ];
        });

        return $this->sendResponse([
            'orders' => $orders->items(),
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
                'from' => $orders->firstItem(),
                'to' => $orders->lastItem(),
            ],
        ], 'Orders retrieved successfully');
    }

    /**
     * Get a specific order
     */
    public function show(Order $order)
    {
        $user = Auth::user();

        if ($order->user_id !== $user->id) {
            return $this->sendForbidden('Unauthorized access to order');
        }

        $order->load(['items.product.mainImage', 'items.product.category']);

        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'status_badge' => $order->status_badge,
            'total_amount' => $order->total_amount,
            'voucher_code' => $order->voucher_code,
            'voucher_discount' => $order->voucher_discount,
            'loyalty_points_earned' => $order->loyalty_points_earned,
            'points_redeemed' => $order->points_redeemed,
            'points_redeemed_discount' => $order->points_redeemed_discount,
            'shipping_address' => [
                'address' => $order->shipping_address,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'zip' => $order->shipping_zip,
                'country' => $order->shipping_country,
            ],
            'billing_address' => $order->billing_address,
            'payment_method' => $order->payment_method,
            'payment_status' => $order->payment_status,
            'tracking_number' => $order->tracking_number,
            'notes' => $order->notes,
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->quantity * $item->price,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'description' => $item->product->description,
                        'sku' => $item->product->sku,
                        'slug' => $item->product->slug,
                        'main_image' => $item->product->main_image,
                        'category' => [
                            'id' => $item->product->category->id,
                            'name' => $item->product->category->name,
                        ],
                    ],
                ];
            }),
            'created_at' => $order->created_at,
            'updated_at' => $order->updated_at,
        ];

        return $this->sendResponse($orderData, 'Order retrieved successfully');
    }

    /**
     * Create a new order from cart
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'shipping_address' => 'required|string|max:500',
            'shipping_city' => 'required|string|max:100',
            'shipping_state' => 'required|string|max:100',
            'shipping_zip' => 'required|string|max:20',
            'shipping_country' => 'required|string|max:100',
            'billing_address' => 'nullable|array',
            'payment_method' => 'required|in:credit_card,paypal,bank_transfer,cash_on_delivery',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = Auth::user();
        
        $shippingData = [
            'shipping_address' => $request->shipping_address,
            'shipping_city' => $request->shipping_city,
            'shipping_state' => $request->shipping_state,
            'shipping_zip' => $request->shipping_zip,
            'shipping_country' => $request->shipping_country,
        ];

        $result = $this->orderService->createOrderFromCart($user, $shippingData);

        if (!$result['success']) {
            return $this->sendError($result['message']);
        }

        $order = $result['order'];
        $order->load(['items.product.mainImage']);

        $orderData = [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'total_amount' => $order->total_amount,
            'shipping_address' => [
                'address' => $order->shipping_address,
                'city' => $order->shipping_city,
                'state' => $order->shipping_state,
                'zip' => $order->shipping_zip,
                'country' => $order->shipping_country,
            ],
            'items' => $order->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'sku' => $item->product->sku,
                        'main_image' => $item->product->main_image,
                    ],
                ];
            }),
            'created_at' => $order->created_at,
        ];

        return $this->sendResponse($orderData, 'Order created successfully', 201);
    }

    /**
     * Track order
     */
    public function track(Order $order)
    {
        $user = Auth::user();

        if ($order->user_id !== $user->id) {
            return $this->sendForbidden('Unauthorized access to order');
        }

        $trackingData = $this->orderService->getOrderTracking($order);

        return $this->sendResponse($trackingData, 'Order tracking information retrieved successfully');
    }

    /**
     * Redeem loyalty points
     */
    public function redeemPoints(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'points' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $user = Auth::user();
        $cart = $this->orderService->getOrCreateCart($user);

        $result = $this->discountService->applyLoyaltyPoints($request->points, $user, $cart);

        if (!$result['valid']) {
            return $this->sendError($result['message']);
        }

        return $this->sendResponse([
            'points_redeemed' => $request->points,
            'discount_amount' => $result['amount'],
            'cart_total' => $result['new_total'],
        ], 'Loyalty points applied successfully');
    }

    /**
     * Remove loyalty points
     */
    public function removePoints()
    {
        $user = Auth::user();
        $cart = $this->orderService->getOrCreateCart($user);

        $result = $this->discountService->removeLoyaltyPoints($cart);

        return $this->sendResponse([
            'cart_total' => $result['new_total'],
        ], 'Loyalty points removed successfully');
    }
}
