<?php

namespace App\Notifications;

use App\Models\Voucher;
use App\Services\Sms\SmsText;
use App\Support\SmsTypes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Happy birthday, with a single-use voucher (marketing:birthday-offers). By email, and by SMS too when
 * the customer chose SMS for offers.
 */
class BirthdayOffer extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Voucher $voucher)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return method_exists($notifiable, 'notificationChannels') ? $notifiable->notificationChannels('marketing') : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Happy birthday from iruali!'))
            ->greeting(__('Happy birthday, :name!', ['name' => $notifiable->name]))
            ->line(__('Here is :percent% off your next order, as a small gift from us.', ['percent' => (float) $this->voucher->amount]))
            ->line(__('Your code: :code (until :date)', ['code' => $this->voucher->code, 'date' => $this->voucher->valid_until?->translatedFormat('j M Y')]))
            ->action(__('Start shopping'), route('home'));
    }

    public function toSms(object $notifiable): SmsText
    {
        return SmsTypes::render('birthday_offer', [
            'percent' => (string) (float) $this->voucher->amount,
            'code' => $this->voucher->code,
            'date' => $this->voucher->valid_until?->translatedFormat('j M'),
        ]);
    }
}
