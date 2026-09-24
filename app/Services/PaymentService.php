<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Payment methods and bank-transfer slip handling.
 *
 * Statuses: unpaid → submitted (slip uploaded) → paid, or → rejected (customer uploads again).
 * Slips are stored on the private "local" disk and only streamed to the customer and admins.
 */
class PaymentService
{
    public const DISK = 'local';

    /**
     * Payment methods offered at checkout. Bank transfer appears once bank details are set.
     */
    public function methods(): array
    {
        $methods = ['cod' => __('Cash on delivery')];

        if ($this->bankTransferEnabled()) {
            $methods['bank_transfer'] = __('Bank transfer');
        }

        return $methods;
    }

    public function bankTransferEnabled(): bool
    {
        return trim((string) Setting::get('bank_account_number')) !== '';
    }

    public function bankDetails(): array
    {
        return [
            'bank' => Setting::get('bank_name'),
            'name' => Setting::get('bank_account_name'),
            'number' => Setting::get('bank_account_number'),
        ];
    }

    public function canUploadSlip(Order $order): bool
    {
        return $order->payment_method === 'bank_transfer'
            && in_array($order->payment_status, ['unpaid', 'rejected', 'submitted'], true)
            && $order->status !== 'cancelled';
    }

    public function submitSlip(Order $order, UploadedFile $file): void
    {
        if ($order->payment_slip) {
            Storage::disk(self::DISK)->delete($order->payment_slip);
        }

        $path = $file->storeAs(
            'payment-slips',
            $order->order_number.'-'.Str::random(8).'.'.$file->extension(),
            self::DISK
        );

        $order->update(['payment_slip' => $path, 'payment_status' => 'submitted']);
    }

    public function confirm(Order $order): void
    {
        $order->update(['payment_status' => 'paid', 'paid_at' => now()]);
    }

    public function reject(Order $order): void
    {
        $order->update(['payment_status' => 'rejected', 'paid_at' => null]);
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid' => __('Paid'),
            'submitted' => __('Slip submitted'),
            'rejected' => __('Slip rejected'),
            default => __('Unpaid'),
        };
    }

    public static function statusBadge(?string $status): string
    {
        return match ($status) {
            'paid' => 'bg-green-100 text-green-800',
            'submitted' => 'bg-sun-soft text-sun-ink',
            'rejected' => 'bg-red-100 text-red-800',
            default => 'bg-gray-100 text-gray-700',
        };
    }
}
