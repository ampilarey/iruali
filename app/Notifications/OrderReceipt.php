<?php

namespace App\Notifications;

use App\Models\Order;
use App\Support\ReceiptPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Email me this receipt": the receipt for an order again, with the PDF attached.
 */
class OrderReceipt extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order)
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
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Your receipt for order :number', ['number' => $this->order->order_number]))
            ->line(__('Here is the receipt for order :number, as a PDF.', ['number' => $this->order->order_number]))
            ->action(__('View your order'), $this->order->customerUrl())
            ->attachData(ReceiptPdf::make($this->order), ReceiptPdf::filename($this->order), ['mime' => 'application/pdf']);
    }
}
