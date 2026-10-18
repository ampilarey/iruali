<?php

namespace App\Notifications;

use App\Console\Commands\AlertLateOrders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * To a shop: orders it has not started in time, or sent but past their expected delivery date.
 */
class ShopLateOrder extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  'unstarted'|'overdue'  $kind
     * @param  list<string>  $orderNumbers
     */
    public function __construct(public string $kind, public array $orderNumbers)
    {
        $this->afterCommit();
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $orders = implode(', ', $this->orderNumbers);
        $mail = (new MailMessage)->salutation(__('The iruali team'));

        if ($this->kind === 'overdue') {
            return $mail->subject(__('Orders past their delivery date'))
                ->line(__('These orders were sent but are past the expected delivery date: :orders.', ['orders' => $orders]))
                ->line(__('Please check with the courier, update the tracking details or mark them delivered, so customers know where their order is.'))
                ->action(__('Open your orders'), route('seller.orders'));
        }

        return $mail->subject(__('Orders waiting for you to start'))
            ->line(__('These paid orders have been waiting more than :hours hours: :orders.', ['hours' => AlertLateOrders::startHours(), 'orders' => $orders]))
            ->line(__('Please start packing them, or message the customer if something is out of stock.'))
            ->action(__('Open your orders'), route('seller.orders'));
    }
}
