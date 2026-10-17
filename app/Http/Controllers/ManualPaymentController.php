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

    public function slip(Order $order): Response
    {
        abort_unless(ManualPaymentService::slipExists($order), 404);

        return Storage::disk(ManualPaymentService::SLIP_DISK)->response((string) $order->payment_slip_path);
    }

    public function confirm(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate(['payment_reference' => 'nullable|string|max:100']);

        if (! $this->payments->confirm($order, $request->user(), $data['payment_reference'] ?? null)) {
            return back()->with('error', 'This order is not waiting for a bank transfer or cash.');
        }

        return back()->with('success', 'Payment recorded. The order is paid and the customer has been emailed.');
    }
}
