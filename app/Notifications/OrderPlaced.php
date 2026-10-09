<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class OrderPlaced extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? $notifiable->notificationChannels('order_updates') : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->loadMissing('items.product');
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Order :number received', ['number' => $order->order_number]))
            ->greeting(__('Thank you, :name.', ['name' => $notifiable->name ?? $order->customerName()]))
            ->line(__('We have your order :number. The shop will prepare it and we will email you when it ships.', ['number' => $order->order_number]));

        foreach ($order->items as $item) {
            $mail->line('• '.$item->displayName().' × '.$item->quantity.' — '.Money::format($item->price * $item->quantity));
        }
        foreach (app(\App\Services\PreorderService::class)->customerNotes($order) as $note) {
            $mail->line($note); // pre-order items: when they should ship
        }
        if ((float) $order->shop_discount > 0) {
            $mail->line(__('Shop discounts (multi-buy offers and shop codes)').': −'.Money::format($order->shop_discount));
        }

        $mail->line(__('Delivery').': '.Money::format($order->shipping_amount))
            ->line('**'.__('Total').': '.Money::format($order->total_amount).'**');

        $mail->action(__('View your order'), $order->customerUrl());
        if ($order->isGuest()) {
            $mail->line(__('You ordered as a guest: keep this email, its link is the way to see and track your order. You can also create an account with this email to find it under My Orders.'));
        }

        return $mail->line(__('Please keep this email as a record of your purchase, together with our Terms & Conditions and Returns, Refunds & Cancellations policy: :url', ['url' => route('policies.terms')]))
            ->line(__('Printable receipt: :url', ['url' => $order->customerReceiptUrl()]));
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: we have your order :number (:amount). We will text you when it is on its way.', [
            'number' => $this->order->order_number,
            'amount' => Money::format($this->order->total_amount),
        ]);
    }
}
