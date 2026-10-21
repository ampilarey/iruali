<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\ManualPaymentService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bank transfer and cash on delivery (ManualPaymentService): the customer uploads the transfer slip
 * (signed-in, or a guest through the order's signed link); admins see it and confirm the payment.
 */
class ManualPaymentController extends Controller
{
    public function __construct(protected ManualPaymentService $payments) {}

    public function upload(Request $request, Order $order): RedirectResponse
    {
        $this->authorize('view', $order);

        return $this->store($request, $order);
    }

    public function guestUpload(Request $request, Order $order, string $token): RedirectResponse
    {
        abort_unless($order->guest_token && hash_equals($order->guest_token, $token), 403);

        return $this->store($request, $order);
    }

    protected function store(Request $request, Order $order): RedirectResponse
    {
        $request->validate(['slip' => 'required|file|max:8192|mimes:jpg,jpeg,png,webp,pdf'], [
            'slip.required' => __('Please choose the photo or PDF of your transfer slip.'),
            'slip.mimes' => __('The slip must be a photo (JPG, PNG, WebP) or a PDF.'),
            'slip.max' => __('The slip must be smaller than 8 MB.'),
        ]);

        if (! $this->payments->storeSlip($order, $request->file('slip'))) {
            NotificationService::error(__('We could not take that file. Please upload a photo or PDF of the slip.'));

            return back();
        }

        NotificationService::success(__('Thank you. We will check your transfer and confirm your order soon.'));

        return back();
    }

    /**
     * The transfer slip; ?part=deposit for the booking fee's slip on a booking-fee pre-order.
     */
    public function slip(Request $request, Order $order): Response
    {
        $path = $request->query('part') === 'deposit' ? $order->deposit_slip_path : $order->payment_slip_path;
        abort_unless($path && Storage::disk(ManualPaymentService::SLIP_DISK)->exists($path), 404);

        return Storage::disk(ManualPaymentService::SLIP_DISK)->response((string) $path);
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['payment_reference' => 'nullable|string|max:100', 'part' => 'nullable|in:deposit,balance,full']);
        $part = \App\Services\BookingFeeService::nextPart($order);

        if (! $this->payments->confirm($order, $request->user(), $data['payment_reference'] ?? null, $data['part'] ?? null)) {
            return back()->with('error', 'This order is not waiting for that bank transfer or cash payment. Reload the page to see where it is now.');
        }

        return back()->with('success', match ($part) {
            'deposit' => 'Booking fee recorded. The customer and the shop have been told; the balance is asked for when the stock arrives.',
            'balance' => 'Balance recorded. The order is paid, and the customer and the shop have been told.',
            default => 'Payment recorded. The order is paid and the customer has been emailed.',
        });
    }
}
