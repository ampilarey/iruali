<?php

namespace App\Notifications;

use App\Models\Dispute;
use App\Models\User;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Tells the shop (and iruali's contact address) that a customer has opened a dispute.
 */
class DisputeOpened extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Dispute $dispute) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $dispute = $this->dispute;
        $order = $dispute->order;
        $isShop = $notifiable instanceof User && $dispute->seller_id && $notifiable->id === $dispute->seller_id;

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Dispute opened on order :number', ['number' => $order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? 'iruali']))
            ->line(__(':customer has opened a dispute (:type) on order :number, claiming :amount.', [
                'customer' => $dispute->customer->name ?? __('A customer'),
                'type' => $dispute->typeLabel(),
                'number' => $order->order_number,
                'amount' => Money::format($dispute->amount_claimed),
            ]));

        if ($dispute->details) {
            $mail->line('> '.Str::limit($dispute->details, 500));
        }

        if ($isShop) {
            return $mail->line(__('Please reply in the order conversation within 2 business days so iruali can decide fairly.'))
                ->action(__('Reply to the customer'), route('seller.orders.show', $order));
        }

        return $mail->action(__('Review the dispute'), route('admin.disputes.show', $dispute));
    }
}
