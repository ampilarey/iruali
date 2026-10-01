<?php

namespace App\Notifications;

use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Part of a multi-shop order is on its way (the rest is still being prepared).
 */
class SellerOrderShipped extends Notification
{
    public function __construct(public SellerOrder $part) {}

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $notifiable->notificationChannels('delivery_updates') : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->part->order;
        $shop = $this->part->shopName();

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Items from :shop are on their way (order :number)', ['shop' => $shop, 'number' => $order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line(__(':shop has sent their part of your order:', ['shop' => $shop]));

        foreach ($this->part->items()->with('product')->get() as $item) {
            $mail->line('• '.($item->product->name ?? __('Product')).' × '.$item->quantity);
        }

        if ($this->part->tracking_note) {
            $mail->line(__('Tracking: :note', ['note' => $this->part->tracking_note]));
        }

        return $mail->line(__('The rest of your order is still being prepared by the other shops.'))
            ->action(__('View your order'), route('orders.show', $order));
    }

    public function toSms(object $notifiable): string
    {
        $text = __('iruali: :shop has sent their part of order :number.', ['shop' => $this->part->shopName(), 'number' => $this->part->order->order_number]);

        if ($this->part->tracking_note) {
            $text .= ' '.__('Tracking: :note', ['note' => $this->part->tracking_note]);
        }

        return $text;
    }
}
