<?php

namespace App\Notifications;

use App\Models\SellerOrder;
use App\Notifications\Channels\WebPushChannel;
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
        return WebPushChannel::configured() ? ['mail', WebPushChannel::class] : ['mail'];
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
            $mail->line('• '.$item->displayName().' × '.$item->quantity);
        }

        if ($this->part->tracking_note) {
            $mail->line(__('Tracking: :note', ['note' => $this->part->tracking_note]));
        }

        return $mail->line(__('The rest of your order is still being prepared by the other shops.'))
            ->action(__('View your order'), route('orders.show', $order));
    }

    /**
     * Browser notification.
     */
    public function toWebPush(object $notifiable): array
    {
        $order = $this->part->order;

        return [
            'title' => __('Items from :shop are on their way (order :number)', ['shop' => $this->part->shopName(), 'number' => $order->order_number]),
            'body' => $this->part->tracking_note ? __('Tracking: :note', ['note' => $this->part->tracking_note]) : __('Tap to see your order.'),
            'url' => route('orders.show', $order),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'order-'.$order->id,
        ];
    }
}
