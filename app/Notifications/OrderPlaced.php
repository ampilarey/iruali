<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderPlaced extends Notification
{
    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items.product');
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Order :number received', ['number' => $order->order_number]))
            ->greeting(__('Thank you, :name.', ['name' => $notifiable->name]))
            ->line(__('We have your order :number. The shop will prepare it and we will email you when it ships.', ['number' => $order->order_number]));

        foreach ($order->items as $item) {
            $mail->line('• '.($item->product->name ?? __('Product')).' × '.$item->quantity.' — '.Money::format($item->price * $item->quantity));
        }

        $mail->line(__('Delivery').': '.Money::format($order->shipping_amount))
            ->line('**'.__('Total').': '.Money::format($order->total_amount).'**');

        if ($order->payment_method === 'bank_transfer') {
            $mail->line(__('Please transfer the total and upload your slip on the order page.'));
        }

        return $mail->action(__('View your order'), route('orders.show', $order))
            ->line(__('Please keep this email as a record of your purchase, together with our Terms & Conditions and Returns, Refunds & Cancellations policy: :url', ['url' => route('policies.terms')]));
    }
}
