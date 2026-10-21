<?php

namespace App\Notifications;

use App\Models\Order;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Services\Sms\SmsText;
use App\Support\Money;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * The customer's payment is confirmed. For a pre-order with a booking fee: $part "deposit" when the
 * booking fee is paid (the balance comes when the stock arrives), and the balance once it is paid.
 */
class PaymentUpdated extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Order $order, public ?string $part = null)
    {
        // Sent only once the surrounding database transaction has committed
        $this->afterCommit();
    }

    /** deposit, balance or null (the whole order) */
    protected function part(): ?string
    {
        return $this->part ?? ($this->order->hasBookingFee() ? 'balance' : null);
    }

    /**
     * @return array{0: string, 1: string} the subject and the line
     */
    protected function wording(): array
    {
        $number = $this->order->order_number;

        return match ($this->part()) {
            'deposit' => [
                __('Booking fee received for order :number', ['number' => $number]),
                __('We have received your booking fee of :amount. The shop is getting your item now; when it arrives we will ask you for the balance of :balance before it is delivered.', ['amount' => Money::format($this->order->deposit_amount), 'balance' => Money::format($this->order->balanceAmount())]),
            ],
            'balance' => [
                __('Payment received for order :number', ['number' => $number]),
                __('We have received the balance of :amount. Your order is fully paid and the shop will send it soon.', ['amount' => Money::format($this->order->balanceAmount())]),
            ],
            default => [
                __('Payment received for order :number', ['number' => $number]),
                __('We have received your payment of :amount. Thank you.', ['amount' => Money::format($this->order->total_amount)]),
            ],
        };
    }

    public function via(object $notifiable): array
    {
        $channels = $notifiable instanceof User ? $notifiable->notificationChannels('order_updates') : ['mail'];
        if (WebPushChannel::configured() && $notifiable instanceof User) {
            $channels[] = WebPushChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        [$subject, $line] = $this->wording();
        $mail = (new MailMessage)->salutation(__('The iruali team'))->greeting(__('Hello :name,', ['name' => $notifiable->name ?? $this->order->customerName()]));

        $mail->subject($subject)->line($line);
        if ($this->part() === 'deposit') {
            $mail->line(__('The invoice for the booking fee is on your order page.'));
        }

        return $mail->action(__('View your order'), $this->order->customerUrl());
    }

    public function toSms(object $notifiable): SmsText
    {
        return match ($this->part()) {
            'deposit' => SmsTypes::render('booking_fee_received', ['amount' => Money::format($this->order->deposit_amount), 'number' => $this->order->order_number]),
            'balance' => SmsTypes::render('payment_received', ['amount' => Money::format($this->order->balanceAmount()), 'number' => $this->order->order_number]),
            default => SmsTypes::render('payment_received', ['amount' => Money::format($this->order->total_amount), 'number' => $this->order->order_number]),
        };
    }

    /**
     * Browser notification.
     */
    public function toWebPush(object $notifiable): array
    {
        [$subject, $line] = $this->wording();

        return [
            'title' => $subject,
            'body' => $line,
            'url' => route('orders.show', $this->order),
            'icon' => asset('images/icons/icon-192.png'),
            'tag' => 'order-'.$this->order->id,
        ];
    }
}
