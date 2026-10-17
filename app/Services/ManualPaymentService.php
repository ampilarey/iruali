<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentSlipUploaded;
use App\Support\Audit;
use App\Traits\SecureFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Bank transfer and cash on delivery: payment methods the admins switch on in Admin → Settings →
 * Payment methods, for example while the card gateway (BML) has a problem.
 *
 * Bank transfer: the customer sees iruali's account and the amount, transfers with the order
 * number as the reference and uploads the slip on the order page (the admins are emailed). An admin
 * checks the bank and confirms the payment ("Confirm payment received"), which is from then on like a
 * paid card order (PaymentService::confirm(): invoices, points, the customer's email). An order with
 * no slip within the hours set is cancelled, which puts its stock back (`orders:cancel-unpaid-transfer`).
 *
 * Cash on delivery: one shop's order at a time (several shops would mean paying several people),
 * no pre-order lines, up to an optional amount, optionally only to Greater Malé. The shop is told to
 * send it and to collect the cash; an admin records "Cash received" once the money is with iruali.
 * Until a payment is confirmed the order is unpaid, so the shops' earnings on it are not payable.
 */
class ManualPaymentService
{
    use SecureFileUpload;

    public const TRANSFER = 'transfer';

    public const COD = 'cod';

    public const METHODS = [self::TRANSFER, self::COD];

    public const SLIP_DISK = 'local';

    /** @var array<string, string> */
    public const SLIP_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];

    public static function cardEnabled(): bool
    {
        return filter_var(Setting::get('payment_card_enabled', 1), FILTER_VALIDATE_BOOLEAN);
    }

    /** Switched on, with an account number to pay into. */
    public static function transferEnabled(): bool
    {
        return filter_var(Setting::get('payment_transfer_enabled'), FILTER_VALIDATE_BOOLEAN)
            && trim((string) Setting::get('transfer_account_number')) !== '';
    }

    public static function codEnabled(): bool
    {
        return filter_var(Setting::get('payment_cod_enabled'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * iruali's account for transfers.
     *
     * @return array{bank: string, name: string, number: string, note: string}
     */
    public static function transferDetails(): array
    {
        return [
            'bank' => trim((string) Setting::get('transfer_bank_name')),
            'name' => trim((string) Setting::get('transfer_account_name')),
            'number' => trim((string) Setting::get('transfer_account_number')),
            'note' => trim((string) Setting::get('transfer_note')),
        ];
    }

    /** Hours the customer has to upload a transfer slip before the order is cancelled. */
    public static function payWithinHours(): int
    {
        return max(1, (int) Setting::get('transfer_pay_within_hours', 24));
    }

    public static function codMaxAmount(): float
    {
        return max(0, (float) Setting::get('cod_max_amount'));
    }

    public static function codGreaterMaleOnly(): bool
    {
        return filter_var(Setting::get('cod_greater_male_only'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Why this cart can't be paid in cash on delivery, or null when it can.
     *
     * @param  ?string  $area  the delivery area (DeliveryService), null when everything is collected
     */
    public function codProblem(Cart $cart, float $total, ?string $area): ?string
    {
        $cart->loadMissing('items.product');
        $shops = $cart->items->filter(fn ($item) => $item->product !== null)->map(fn ($item) => (int) ($item->product->seller_id ?? 0))->unique();
        if ($shops->count() > 1) {
            return __('Cash on delivery is for orders from one shop. Please pay another way, or order from each shop separately.');
        }

        $preorders = app(PreorderService::class);
        if ($cart->items->contains(fn ($item) => $item->product && $preorders->cartLine($item) !== null)) {
            return __('Pre-orders can\'t be paid in cash on delivery. Please choose another way to pay.');
        }

        $max = self::codMaxAmount();
        if ($max > 0 && $total > $max) {
            return __('Cash on delivery is for orders up to :amount. Please choose another way to pay.', ['amount' => \App\Support\Money::format($max)]);
        }

        if (self::codGreaterMaleOnly() && $area !== null && $area !== DeliveryService::AREA_GREATER_MALE) {
            return __('Cash on delivery is only for deliveries in Greater Malé. Please choose another way to pay.');
        }

        return null;
    }

    /**
     * Waiting for a bank transfer or cash: the order is not cancelled and not paid yet.
     */
    public static function awaiting(Order $order): bool
    {
        return in_array($order->payment_method, self::METHODS, true)
            && $order->payment_status !== 'paid'
            && $order->status !== 'cancelled';
    }

    /**
     * Keep the customer's transfer slip (a photo or PDF, private) and tell the admins. False when
     * the file is not one we take, or the order no longer waits for a transfer.
     */
    public function storeSlip(Order $order, UploadedFile $file): bool
    {
        if ($order->payment_method !== self::TRANSFER || ! self::awaiting($order)) {
            return false;
        }

        $path = $this->storePrivateFileSecurely($file, 'payment-slips', self::SLIP_TYPES, 8192, self::SLIP_DISK);
        if ($path === null) {
            return false;
        }

        $old = $order->payment_slip_path;
        $order->forceFill(['payment_slip_path' => $path, 'payment_slip_uploaded_at' => now()])->save();
        if ($old && $old !== $path) {
            $this->deletePrivateFile($old, self::SLIP_DISK);
        }

        \App\Support\TeamAlerts::send('bank_slip', 'iruali: bank transfer slip to check for '.$order->order_number.' ('.\App\Support\Money::format($order->cardAmount()).').', route('admin.orders.show', $order));

        try {
            if ($email = trim((string) Setting::get('contact_email'))) {
                Notification::route('mail', $email)->notify(new PaymentSlipUploaded($order));
            }
        } catch (Throwable $e) {
            report($e);
        }

        return true;
    }

    /**
     * What a shop needs to know about how its order is paid: wait for a bank transfer to be confirmed,
     * or collect the cash. Null for a card (or already paid) order.
     */
    public static function shopNote(Order $order): ?string
    {
        if (! self::awaiting($order)) {
            return null;
        }

        return $order->payment_method === self::TRANSFER
            ? __('The customer is paying by bank transfer. Please wait until the order shows as paid before you send it; we email you when the payment is confirmed.')
            : __('Cash on delivery: collect :amount from the customer when you hand over the order, and pass it to iruali.', ['amount' => \App\Support\Money::format($order->cardAmount())]);
    }

    public static function slipExists(Order $order): bool
    {
        return $order->payment_slip_path !== null && Storage::disk(self::SLIP_DISK)->exists($order->payment_slip_path);
    }

    /**
     * An admin saw the transfer in the bank, or has the cash: the order is paid from now on (as
     * a paid card order is). False when it isn't waiting for a transfer or cash.
     */
    public function confirm(Order $order, User $admin, ?string $reference): bool
    {
        if (! self::awaiting($order)) {
            return false;
        }

        $reference = trim((string) $reference);
        $order->forceFill(['payment_reference' => $reference !== '' ? $reference : null, 'payment_confirmed_by' => $admin->id])->save();
        app(PaymentService::class)->confirm($order);
        Audit::record('payment.confirmed_manually', $order, ['method' => $order->payment_method, 'reference' => $reference, 'order_number' => $order->order_number]);

        // The shops were told to wait for a transfer: tell them they can send it now
        if ($order->payment_method === self::TRANSFER) {
            $order->loadMissing('sellerOrders.seller');
            foreach ($order->sellerOrders as $part) {
                if ($part->seller && $part->status !== 'cancelled') {
                    try {
                        $part->seller->notify(new \App\Notifications\ShopOrderPaid($order));
                    } catch (Throwable $e) {
                        report($e);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Bank transfer orders with no slip after the hours allowed are cancelled (their stock goes back).
     * Orders with a slip wait for an admin. Returns how many were cancelled.
     */
    public function cancelUnpaidTransfers(): int
    {
        $cancelled = 0;
        Order::where('payment_method', self::TRANSFER)
            ->where('payment_status', '!=', 'paid')
            ->where('status', 'pending')
            ->whereNull('payment_slip_uploaded_at')
            ->where('created_at', '<', now()->subHours(self::payWithinHours()))
            ->each(function (Order $order) use (&$cancelled) {
                if (app(OrderService::class)->updateOrderStatus($order, 'cancelled')) {
                    $cancelled++;
                }
            });

        return $cancelled;
    }
}
