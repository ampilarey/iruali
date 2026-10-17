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
 */
class ShopOrderPaid extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
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
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Order :number is paid: please send it', ['number' => $this->order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->business_name ?: $notifiable->name]))
            ->line(__('The customer\'s bank transfer for order :number has been confirmed. You can pack and send your part now.', ['number' => $this->order->order_number]))
            ->action(__('Open in Seller Centre'), route('seller.orders.show', $this->order));
    }
}
