<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class NewSellerOrder extends Notification
{
    /**
     * @param  Collection  $items  the order items that belong to this seller
     */
    public function __construct(public Order $order, public Collection $items) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('New order :number', ['number' => $this->order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->business_name ?: $notifiable->name]))
            ->line(__('You have a new order. Please pack these items:'));

        foreach ($this->items as $item) {
            $mail->line('• '.($item->product->name ?? __('Product')).' × '.$item->quantity.' — '.Money::format($item->price * $item->quantity));
        }

        return $mail->line(__('Deliver to: :place', ['place' => trim($this->order->shipping_city.', '.$this->order->shipping_state, ', ')]))
            ->action(__('Open in Seller Centre'), route('seller.orders.show', $this->order));
    }
}
