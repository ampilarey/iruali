<?php

namespace App\Notifications;

use App\Models\SellerOrder;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Sms\SmsText;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * Part of a multi-shop order is on its way (the rest is still being prepared).
 */
class SellerOrderShipped extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SellerOrder $part)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        $channels = $notifiable instanceof User ? $notifiable->notificationChannels('delivery_updates') : ['mail'];
        if (WebPushChannel::configured() && $notifiable instanceof User) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->part->order;
        $shop = $this->part->shopName();

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Items from :shop are on their way (order :number)', ['shop' => $shop, 'number' => $order->order_number]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $order->customerName()]))
            ->line(__(':shop has sent their part of your order:', ['shop' => $shop]));

        foreach ($this->part->items()->with('product')->get() as $item) {
            $mail->line('• '.$item->displayName().' × '.$item->quantity);
        }

        if ($this->part->order->isByBoat()) {
            $mail->line(__('It is on the boat: :boat. Boat phone: :phone. Collect it from the boat on your island and pay the boat\'s freight charge there.', [
                'boat' => \App\Support\BoatDelivery::summary($this->part->order->boat), 'phone' => $this->part->order->boat['phone'] ?? '',
            ]));
        }
        if ($this->part->courier || $this->part->tracking_number) {
            $mail->line(__('Courier: :courier', ['courier' => trim($this->part->courier.' '.$this->part->tracking_number)]));
        }
        if ($this->part->tracking_url) {
            $mail->line(__('Track the parcel: :url', ['url' => $this->part->tracking_url]));
        }
        if ($this->part->vessel_or_flight) {
            $mail->line(__('Boat or flight: :vessel', ['vessel' => $this->part->vessel_or_flight]));
        }
        if ($this->part->expected_delivery_date) {
            $mail->line(__('Expected delivery: :date', ['date' => $this->part->expected_delivery_date->translatedFormat('l j F')]));
        }
        if ($this->part->tracking_note) {
            $mail->line(__('Tracking: :note', ['note' => $this->part->tracking_note]));
        }

        return $mail->line(__('The rest of your order is still being prepared by the other shops.'))
            ->action(__('View your order'), $order->customerUrl());
    }

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render('part_shipped', [
            'shop' => $this->part->shopName(),
            'number' => $this->part->order->order_number,
            // Sent with a boat: which boat, from where, when, and its phone, so the customer can meet it
            'details' => $this->part->order->isByBoat()
                ? __('On the boat :boat, phone :phone.', ['boat' => \App\Support\BoatDelivery::summary($this->part->order->boat), 'phone' => $this->part->order->boat['phone'] ?? ''])
                : $this->part->trackingSummary(),
        ]);
    }

    /**
     * Browser notification.
     */
    public function toWebPush(object $notifiable): array
    {
        $order = $this->part->order;

        return [
            'title' => __('Items from :shop are on their way (order :number)', ['shop' => $this->part->shopName(), 'number' => $order->order_number]),
            'body' => $this->part->tracking_note ? __('Tracking: :note', ['note' => $this->part->tracking_note]) : __('Tap to see your order.'),
            'url' => route('orders.show', $order),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'order-'.$order->id,
        ];
    }
}
