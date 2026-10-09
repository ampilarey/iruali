<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreGuestOrderRequest;
use App\Models\Order;
use App\Models\Setting;
use App\Services\CartService;
use App\Services\DeliveryService;
use App\Services\DiscountService;
use App\Services\NotificationService;
use App\Services\OrderService;
use App\Services\PaymentService;
use App\Support\GuestCheckout;
use Illuminate\Http\Request;
use Throwable;

/**
 * Checkout without an account (when the owner has turned it on). The guest gives an email, a name
 * and an address; the order is theirs through signed links that carry the order's guest token.
 */
class GuestCheckoutController extends Controller
{
    public function __construct(protected CartService $cartService, protected DiscountService $discountService, protected OrderService $orderService) {}

    public function index(Request $request)
    {
        if ($request->user()) {
            return redirect()->route('checkout');
        }
        if (! GuestCheckout::enabled()) {
            return redirect()->guest(route('login'));
        }

        $cart = $this->cartService->currentCart();
        if (! $cart || $this->cartService->isCartEmpty($cart)) {
            return redirect()->route('cart')->with('error', __('Your cart is empty.'));
        }
        $cart->load(['items.product.mainImage', 'items.variant']);

        $discounts = $this->discountService->calculateTotalDiscount($cart);
        $voucherDiscount = (float) $discounts['voucher']['amount'];
        $voucherCode = $discounts['voucher']['voucher']?->code;
        $goodsTotal = (float) $discounts['final_total'];
        $deliveryZones = DeliveryService::zones();
        $deliveryQuotes = app(DeliveryService::class)->quotes($goodsTotal);
        $freeDeliveryOver = (float) Setting::get('free_delivery_over');
        $islandsByAtoll = app(DeliveryService::class)->islandsByAtoll();

        return view('checkout.index', [
            'cart' => $cart,
            'isGuest' => true,
            'points_redeemed' => 0,
            'points_redeemed_discount' => 0,
            'voucherDiscount' => $voucherDiscount,
            'voucherCode' => $voucherCode,
            'goodsTotal' => $goodsTotal,
            'deliveryZones' => $deliveryZones,
            'deliveryQuotes' => $deliveryQuotes,
            'freeDeliveryOver' => $freeDeliveryOver,
            'addresses' => collect(),
            'selectedAddressId' => '',
            'islandsByAtoll' => $islandsByAtoll,
        ]);
    }

    public function store(StoreGuestOrderRequest $request)
    {
        $cart = $this->cartService->currentCart();

        $shippingData = [
            'shipping_address' => $request->shipping_address,
            'shipping_city' => $request->shipping_city,
            'shipping_state' => $request->shipping_state,
            'shipping_zip' => $request->shipping_zip,
            'shipping_country' => $request->shipping_country,
            'shipping_phone' => $request->shipping_phone,
            'delivery_zone' => $request->delivery_zone,
            'payment_method' => $request->payment_method,
        ];
        // Deliver or pick up per shop, the Malé time slot and the gift details
        $shippingData += $request->deliveryChoices();

        $result = $this->orderService->createOrderFromCart(null, $shippingData, $cart, [
            'email' => $request->guest_email,
            'name' => $request->guest_name,
        ]);

        if (! $result['success']) {
            NotificationService::error($result['message']);

            return redirect()->route('cart');
        }

        $order = $result['order'];
        // Remember this browser placed the order, so the confirmation page can be reopened from the cart
        $request->session()->push('guest_orders', $order->id);

        if ($order->payment_method === 'bml') {
            try {
                return redirect()->away(app(PaymentService::class)->startBmlPayment($order));
            } catch (Throwable $e) {
                report($e);
                NotificationService::error(__('Your order is saved, but we could not reach the payment page. Please use "Pay now" to try again.'));
            }
        }

        return redirect()->to($order->guestUrl());
    }

    /**
     * The guest's order page (confirmation and tracking). The link is signed and carries the order's token.
     */
    public function show(Order $order, string $token)
    {
        $this->guard($order, $token);

        $order->load(['items.product' => fn ($q) => $q->withTrashed()->with(['mainImage', 'seller']), 'sellerOrders.seller', 'paymentTransactions']);

        return view('orders.guest', compact('order'));
    }

    public function receipt(Order $order, string $token)
    {
        $this->guard($order, $token);

        $order->load(['items.product.seller', 'paymentTransactions']);

        return view('orders.receipt', compact('order'));
    }

    public function pay(Order $order, string $token, PaymentService $payments)
    {
        $this->guard($order, $token);

        if (! $payments->canPayOnline($order)) {
            return redirect()->to($order->guestUrl());
        }

        try {
            return redirect()->away($payments->startBmlPayment($order));
        } catch (Throwable $e) {
            report($e);
            NotificationService::error(__('We could not reach the payment page. Please try again in a moment.'));

            return redirect()->to($order->guestUrl());
        }
    }

    protected function guard(Order $order, string $token): void
    {
        // The link keeps working after the guest creates an account and the order is attached to it.
        abort_unless($order->guest_token && hash_equals($order->guest_token, $token), 403);
    }
}
