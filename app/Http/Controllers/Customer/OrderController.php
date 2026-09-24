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

        $order = $this->orderService->getOrderWithDetails($order);

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
        ];

        $result = $this->orderService->createOrderFromCart($user, $shippingData);

        if (! $result['success']) {
            NotificationService::error($result['message']);

            return redirect()->route('cart');
        }

        NotificationService::orderPlaced();

        return redirect()->route('orders.show', $result['order']);
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
}
