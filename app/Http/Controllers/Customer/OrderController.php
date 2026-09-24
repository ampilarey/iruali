<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

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
            'delivery_zone' => $request->delivery_zone,
            'payment_method' => $request->payment_method,
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

    public function uploadPaymentSlip(Request $request, Order $order, PaymentService $payments)
    {
        abort_unless($order->user_id === Auth::id(), 403);

        if (! $payments->canUploadSlip($order)) {
            NotificationService::error(__('A payment slip can\'t be uploaded for this order.'));

            return redirect()->route('orders.show', $order);
        }

        $request->validate([
            'payment_slip' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
        ], [
            'payment_slip.mimes' => __('Upload a photo (JPG, PNG) or PDF of your transfer slip.'),
            'payment_slip.max' => __('The slip must be 5 MB or smaller.'),
        ]);

        $payments->submitSlip($order, $request->file('payment_slip'));

        NotificationService::success(__('Thanks. We\'ll confirm your payment shortly.'));

        return redirect()->route('orders.show', $order);
    }

    public function showPaymentSlip(Order $order)
    {
        $user = Auth::user();
        abort_unless($order->user_id === $user->id || $user->isAdmin(), 403);
        abort_unless($order->payment_slip && Storage::disk(PaymentService::DISK)->exists($order->payment_slip), 404);

        return Storage::disk(PaymentService::DISK)->response($order->payment_slip);
    }
}
