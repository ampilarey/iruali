<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class PaymentUpdated extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        $channels = $notifiable instanceof User ? $notifiable->notificationChannels('order_updates') : ['mail'];
        if (WebPushChannel::configured() && $notifiable instanceof User) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $this->order->customerName()]));

        return $mail->subject(__('Payment received for order :number', ['number' => $number]))
            ->line(__('We have received your payment of :amount. Thank you.', ['amount' => Money::format($this->order->total_amount)]))
            ->action(__('View your order'), $this->order->customerUrl());
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: payment of :amount received for order :number. Thank you.', [
            'amount' => Money::format($this->order->total_amount),
            'number' => $this->order->order_number,
        ]);
    }

    /**
     * Browser notification.
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => __('Payment received for order :number', ['number' => $this->order->order_number]),
            'body' => __('We have received your payment of :amount. Thank you.', ['amount' => Money::format($this->order->total_amount)]),
            'url' => route('orders.show', $this->order),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'order-'.$this->order->id,
        ];
    }
}
