<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\NotificationService;
use App\Services\OrderService;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function index()
    {
        $this->authorize('viewAny', Order::class);

        $orders = $this->orderService->getUserOrders(Auth::user());

        return view('orders.index', compact('orders'));
    }

    public function show(Order $order)
    {
        $this->authorize('view', $order);

        $order->load(['items.product' => fn ($q) => $q->withTrashed()->with(['mainImage', 'seller']), 'sellerOrders.seller', 'sellerOrders.returnRequests.items.orderItem.product']);

        return view('orders.show', compact('order'));
    }

    public function store(StoreOrderRequest $request)
    {
        // $this->authorize('create', Order::class); // Removed as StoreOrderRequest handles authorization

        $user = Auth::user();

        $shippingData = [
            'shipping_address' => $request->shipping_address,
            'shipping_city' => $request->shipping_city,
            'shipping_state' => $request->shipping_state,
            'shipping_zip' => $request->shipping_zip,
            'shipping_country' => $request->shipping_country,
            'shipping_phone' => $request->shipping_phone,
            'delivery_zone' => $request->delivery_zone,
            'payment_method' => $request->payment_method,
            'use_wallet' => $request->boolean('use_wallet'),
        ];

        $result = $this->orderService->createOrderFromCart($user, $shippingData);

        if (! $result['success']) {
            NotificationService::error($result['message']);

            return redirect()->route('cart');
        }

        $order = $result['order'];

        // Card payment: straight to BML's payment page. If BML can't be reached the order
        // stays unpaid and the order page offers "Pay now" to try again.
        // (An order the wallet covered in full is already paid and skips the card step.)
        if ($order->payment_method === 'bml') {
            try {
                return redirect()->away(app(\App\Services\PaymentService::class)->startBmlPayment($order));
            } catch (\Throwable $e) {
                report($e);
                NotificationService::error(__('Your order is saved, but we could not reach the payment page. Please use "Pay now" to try again.'));

                return redirect()->route('orders.show', $order);
            }
        }

        NotificationService::orderPlaced();

        return redirect()->route('orders.show', $order);
    }

    public function cancel(Order $order)
    {
        $this->authorize('cancel', $order);

        if (! $this->orderService->updateOrderStatus($order, 'cancelled')) {
            NotificationService::error(__('This order can no longer be cancelled.'));

            return redirect()->route('orders.show', $order);
        }

        NotificationService::success(__('Your order has been cancelled.'));

        return redirect()->route('orders.show', $order);
    }

    /**
     * Printable receipt / order summary (keep for your records).
     */
    public function receipt(Order $order)
    {
        $user = Auth::user();
        abort_unless($order->user_id === $user->id || $user->isAdmin(), 403);

        $order->load(['user', 'items.product.seller', 'paymentTransactions']);

        return view('orders.receipt', compact('order'));
    }

    /**
     * Put the products from a past order back in the cart (whatever is still available).
     */
    public function buyAgain(Order $order, \App\Services\CartService $cart)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        $added = 0;
        foreach ($order->items()->with('product')->get() as $item) {
            $product = $item->product;
            if ($product && $product->is_active && $product->stock_quantity > 0) {
                $cart->addToCart($product->id, min($item->quantity, $product->stock_quantity));
                $added++;
            }
        }

        $added
            ? \App\Services\NotificationService::success(trans_choice(':count item added to your cart.|:count items added to your cart.', $added, ['count' => $added]))
            : \App\Services\NotificationService::error(__('None of these products are available right now.'));

        return redirect()->route('cart');
    }
}
