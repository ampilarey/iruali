<?php

namespace App\Notifications;

use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * A shop has the customer's items ready to collect: where, when it is open, and the 6-digit
 * pickup code to show at the counter. Emailed to the customer (the account, or the guest's
 * address); FulfilmentService also texts it to the order's phone when an SMS gateway is set up,
 * as an on-demand "sms" route.
 */
class PickupReady extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SellerOrder $part)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        if ($notifiable instanceof AnonymousNotifiable) {
            return array_values(array_intersect(['mail', 'sms'], array_keys($notifiable->routes)));
        }

        // The code is needed to collect, so it is always emailed (the text is sent separately)
        $channels = ['mail'];
        if ($notifiable instanceof User && WebPushChannel::configured()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->part->order;
        $shop = $this->part->shopName();

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Ready for pickup from :shop (order :number)', ['shop' => $shop, 'number' => $order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $order->customerName()]))
            ->line(__(':shop has your items ready to collect:', ['shop' => $shop]));

        foreach ($this->part->items()->with('product')->get() as $item) {
            $mail->line('• '.$item->displayName().' × '.$item->quantity);
        }

        $mail->line(__('Your pickup code: :code', ['code' => $this->part->pickup_code]))
            ->line(__('Show this code at the shop. They need it to hand over your order, so do not share it with anyone else.'));

        if ($this->part->pickup_address || $this->part->pickup_island) {
            $mail->line(__('Pick up from: :address', ['address' => collect([$this->part->pickup_address, $this->part->pickup_island])->filter()->join(', ')]));
        }
        if ($this->part->pickup_hours) {
            $mail->line(__('Opening hours: :hours', ['hours' => $this->part->pickup_hours]));
        }

        return $mail->action(__('View your order'), $order->customerUrl());
    }

    public function toSms(object $notifiable): string
    {
        return __('iruali: order :number is ready for pickup at :shop, :address. Your pickup code is :code.', [
            'number' => $this->part->order->order_number,
            'shop' => $this->part->shopName(),
            'address' => collect([$this->part->pickup_address, $this->part->pickup_island])->filter()->join(', '),
            'code' => $this->part->pickup_code,
        ]);
    }

    /**
     * Browser notification (without the code).
     */
    public function toWebPush(object $notifiable): array
    {
        $order = $this->part->order;

        return [
            'title' => __('Ready for pickup from :shop (order :number)', ['shop' => $this->part->shopName(), 'number' => $order->order_number]),
            'body' => __('Tap to see your pickup code.'),
            'url' => route('orders.show', $order),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'order-'.$order->id,
        ];
    }
}
