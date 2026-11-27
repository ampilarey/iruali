<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Sms\SmsText;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

class OrderStatusChanged extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** The status this message is about, as it was when it was queued (the order is read again when sent). */
    public string $status;

    public function __construct(public Order $order, ?string $status = null)
    {
        // Two quick steps (processing, then shipped) queue two messages: each says its own step, not
        // twice whatever the order says by the time the worker sends them
        $this->status = $status ?? (string) $order->status;
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        if (! $notifiable instanceof User) {
            return ['mail'];
        }

        // Shipping and delivery news follow the delivery preference; everything else the order one
        $type = in_array($this->status, ['shipped', 'out_for_delivery', 'delivered'], true) ? 'delivery_updates' : 'order_updates';

        $channels = $notifiable->notificationChannels($type);
        if (WebPushChannel::configured()) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $number = $this->order->order_number;

        [$subject, $line] = match ($this->status) {
            'processing' => [__('Order :number is being prepared', ['number' => $number]), __('The shop is packing your order now.')],
            'shipped' => [__('Order :number is on its way', ['number' => $number]), __('Your order has left the shop and is on its way to your island.')],
            'out_for_delivery' => [__('Order :number is out for delivery', ['number' => $number]), __('Your order is out for delivery today. Please keep your phone close.')],
            'delivered' => [__('Order :number has been delivered', ['number' => $number]), __('Your order has been delivered. We hope you enjoy it.')],
            'cancelled' => $this->order->availability_declined_at
                // A pre-order paid once the shop confirms, and the shop can't get it (PreorderConfirmService)
                ? [__('Order :number: the shop can\'t get your pre-order', ['number' => $number]), __('Sorry, the shop can\'t get your pre-order, so the order has been cancelled. You have not been charged, and any loyalty points you used have been returned.')]
                : [__('Order :number has been cancelled', ['number' => $number]), __('Your order has been cancelled. Any loyalty points you used have been returned.')],
            default => [__('Update on order :number', ['number' => $number]), __('Your order status is now :status.', ['status' => $this->status])],
        };

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject($subject)
            ->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $this->order->customerName()]))
            ->line($line);

        // Delivery details each shop gave when it sent its part
        if (in_array($this->status, ['shipped', 'out_for_delivery'], true)) {
            foreach ($this->trackedParts() as $part) {
                $mail->line(__('Delivery from :shop: :details', ['shop' => $part->shopName(), 'details' => $part->trackingSummary()]));
            }
        }

        if ($this->status === 'delivered') {
            $mail->line(new \Illuminate\Support\HtmlString(e(__('How did it go?')).' <a href="'.e($this->order->feedbackUrl()).'">'.e(__('Rate your order')).'</a>'));
        }

        return $mail->action(__('View your order'), $this->order->customerUrl());
    }

    public function toSms(object $notifiable): SmsText
    {
        $number = $this->order->order_number;
        $type = match ($this->status) {
            'processing' => 'order_processing',
            'shipped' => 'order_shipped',
            'out_for_delivery' => 'order_out_for_delivery',
            'delivered' => 'order_delivered',
            'cancelled' => $this->order->availability_declined_at ? 'preorder_unavailable' : 'order_cancelled',
            default => 'order_status',
        };

        $details = in_array($this->status, ['shipped', 'out_for_delivery'], true)
            ? $this->trackedParts()->map(fn ($p) => $p->trackingSummary())->filter()->join('; ')
            : '';

        return SmsTypes::render($type, ['number' => $number, 'status' => $this->status, 'details' => $details]);
    }

    /**
     * Parts with something to say about their delivery.
     */
    protected function trackedParts(): \Illuminate\Support\Collection
    {
        return $this->order->sellerOrders()->with('seller')->get()->filter(fn ($p) => $p->trackingSummary() !== '')->values();
    }

    /**
     * Browser notification (same wording as the email subject).
     */
    public function toWebPush(object $notifiable): array
    {
        $number = $this->order->order_number;

        return [
            'title' => match ($this->status) {
                'processing' => __('Order :number is being prepared', ['number' => $number]),
                'shipped' => __('Order :number is on its way', ['number' => $number]),
                'delivered' => __('Order :number has been delivered', ['number' => $number]),
                'cancelled' => $this->order->availability_declined_at
                    ? __('Order :number: the shop can\'t get your pre-order', ['number' => $number])
                    : __('Order :number has been cancelled', ['number' => $number]),
                default => __('Update on order :number', ['number' => $number]),
            },
            'body' => __('Tap to see your order.'),
            'url' => route('orders.show', $this->order),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'order-'.$this->order->id,
        ];
    }
}
