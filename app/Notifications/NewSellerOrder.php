<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * A shop has a new order to pack (queued; shops can turn it off under Settings → Notifications).
 */
class NewSellerOrder extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection  $items  the order items that belong to this seller
     */
    public function __construct(public Order $order, public Collection $items)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        if (method_exists($notifiable, 'wantsNotification') && ! $notifiable->wantsNotification('new_order')) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('New order :number', ['number' => $this->order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->business_name ?: $notifiable->name]))
            ->line(__('You have a new order. Please pack these items:'));

        foreach ($this->items as $item) {
            $mail->line('• '.$item->displayName().' × '.$item->quantity.' — '.Money::format($item->price * $item->quantity));
        }

        // Picked up from the shop, a Malé time slot, a gift
        foreach (app(\App\Services\DeliveryService::class)->newOrderNotes($this->order, $notifiable->id ?? null) as $note) {
            $mail->line($note);
        }

        return $mail->line(__('Deliver to: :place', ['place' => trim($this->order->shipping_city.', '.$this->order->shipping_state, ', ')]))
            ->action(__('Open in Seller Centre'), route('seller.orders.show', $this->order));
    }
}
