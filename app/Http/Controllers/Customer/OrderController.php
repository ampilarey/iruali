<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Cart;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\StoreOrderRequest;
use App\Services\NotificationService;

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

        if (!$result['success']) {
            NotificationService::error($result['message']);
            return redirect()->route('cart.index');
        }

        NotificationService::orderPlaced();

        return redirect()->route('orders.show', $result['order']);
    }
}
