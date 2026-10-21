<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A bank transfer order has been confirmed as paid (ManualPaymentService): the shop can send it now.
 * For a pre-order with a booking fee (BookingFeeService), $part says which payment came: the fee
 * (deposit: order the item now) or the balance (send it now).
 */
class ShopOrderPaid extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?string $part = null)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        if (method_exists($notifiable, 'wantsNotification') && ! $notifiable->wantsNotification('new_order')) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->greeting(__('Hello :name,', ['name' => $notifiable->business_name ?: $notifiable->name]));

        $mail = match ($this->part) {
            'deposit' => $mail->subject(__('Booking fee paid for order :number: you can order the item', ['number' => $number]))
                ->line(__('The customer has paid the booking fee of :amount for pre-order :number. You can order the item now.', ['amount' => \App\Support\Money::format($this->order->deposit_amount), 'number' => $number]))
                ->line(__('When it arrives, record it in the Seller Centre under Pre-orders. We then ask the customer for the balance; send the order once it shows as paid.')),
            'balance' => $mail->subject(__('Order :number is paid: please send it', ['number' => $number]))
                ->line(__('The customer has paid the balance for pre-order :number. You can pack and send it now.', ['number' => $number])),
            default => $mail->subject(__('Order :number is paid: please send it', ['number' => $number]))
                ->line(__('The customer\'s bank transfer for order :number has been confirmed. You can pack and send your part now.', ['number' => $number])),
        };

        return $mail->action(__('Open in Seller Centre'), route('seller.orders.show', $this->order));
    }
}
