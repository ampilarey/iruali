<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A shop's order (or its part of one) was cancelled after the shop was told about it: do not pack or
 * send it. Always sent, whatever the shop's notification settings: it stops goods going out unpaid.
 */
class ShopOrderCancelled extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @param  bool  $partOnly  only this shop's part was cancelled; the rest of the order goes ahead */
    public function __construct(public Order $order, public bool $partOnly = false)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;

        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject($this->partOnly ? __('Your part of order :number is cancelled', ['number' => $number]) : __('Order :number is cancelled', ['number' => $number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->business_name ?: $notifiable->name]))
            ->line($this->partOnly
                ? __('iruali has cancelled your part of order :number. Please do not pack or send its items. The other shops in the order carry on.', ['number' => $number])
                : __('Order :number has been cancelled. Please do not pack or send it. If it has already left your shop, tell iruali straight away.', ['number' => $number]))
            ->action(__('View the order'), route('seller.orders.show', $this->order));
    }
}
