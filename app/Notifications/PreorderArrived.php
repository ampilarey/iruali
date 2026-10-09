<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * The stock for some of the customer's pre-order items has arrived and is theirs (Seller Centre →
 * Pre-orders → Stock arrived): the shop can now send them. Follows the order-updates preference.
 */
class PreorderArrived extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * @param  list<int>  $itemIds  the order lines whose stock is all there now
     */
    public function __construct(public Order $order, public array $itemIds)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        $channels = $notifiable->notificationChannels('order_updates');
        if (WebPushChannel::configured()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;
        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Your pre-order is in stock (order :number)', ['number' => $number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $this->order->customerName()]))
            ->line(__('Good news: the stock for your pre-order has arrived and is set aside for you:'));

        foreach ($this->items() as $item) {
            $mail->line('• '.$item->displayName().' × '.$item->quantity);
        }

        return $mail->line(__('The shop will get your order ready and send it to you soon. We will let you know when it is on its way.'))
            ->action(__('View your order'), $this->order->customerUrl());
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: the stock for your pre-order (order :number) has arrived. The shop will send it soon.', ['number' => $this->order->order_number]);
    }

    /**
     * Browser notification.
     */
    public function toWebPush(object $notifiable): array
    {
        return [
            'title' => __('Your pre-order is in stock (order :number)', ['number' => $this->order->order_number]),
            'body' => __('Tap to see your order.'),
            'url' => route('orders.show', $this->order),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'order-'.$this->order->id,
        ];
    }

    /**
     * @return Collection<int, OrderItem>
     */
    protected function items(): Collection
    {
        return OrderItem::whereIn('id', $this->itemIds)->with('product')->orderBy('id')->get();
    }
}
