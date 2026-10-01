<?php

namespace App\Notifications;

use App\Models\SellerOrder;
use App\Models\User;
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
        return $notifiable instanceof User ? $notifiable->notificationChannels('delivery_updates') : ['mail'];
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

    public function toSms(object $notifiable): string
    {
        $text = __('iruali: :shop has sent their part of order :number.', ['shop' => $this->part->shopName(), 'number' => $this->part->order->order_number]);

        if (($details = $this->part->trackingSummary()) !== '') {
            $text .= ' '.$details;
        }

        return $text;
    }
}
