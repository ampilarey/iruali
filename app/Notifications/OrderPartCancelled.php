<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use App\Services\Sms\SmsText;
use App\Support\Money;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Tells the customer that one shop in their order could not supply its items: that part is
 * cancelled and refunded, the rest of the order carries on (FulfilmentService::cancelPart()).
 */
class OrderPartCancelled extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public SellerOrder $part)
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
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Part of order :number is cancelled', ['number' => $number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $this->order->customerName()]))
            ->line(__(':shop could not supply its items in your order, so we have cancelled them. The rest of your order carries on.', ['shop' => $this->part->shopName()]));
        if ((float) $this->part->cancelled_refund > 0) {
            $mail->line(__('We will refund :amount for them and tell you when it is sent.', ['amount' => Money::format($this->part->cancelled_refund)]));
        }
        if ((int) $this->part->points_returned > 0) {
            $mail->line(__('The :points points you used on them are back in your account.', ['points' => (int) $this->part->points_returned]));
        }

        return $mail->action(__('View your order'), $this->order->customerUrl());
    }

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render('order_part_cancelled', ['number' => $this->order->order_number, 'shop' => $this->part->shopName(), 'amount' => Money::format($this->part->cancelled_refund)]);
    }
}
