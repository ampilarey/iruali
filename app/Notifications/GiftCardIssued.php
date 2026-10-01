<?php

namespace App\Notifications;

use App\Models\GiftCard;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The gift card itself: code, amount, the buyer's message and how to redeem it.
 */
class GiftCardIssued extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public GiftCard $card) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $card = $this->card->loadMissing('purchaser');
        $from = $card->purchaser?->name ?? 'iruali';

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__(':from sent you an iruali gift card', ['from' => $from]))
            ->greeting(__('Hello :name,', ['name' => $card->recipient_name ?: $card->recipient_email]))
            ->line(__(':from has sent you a gift card worth :amount to spend on iruali.', ['from' => $from, 'amount' => Money::format($card->amount)]));

        if ($card->message) {
            $mail->line('"'.$card->message.'"');
        }

        return $mail->line('**'.__('Your code').': '.$card->code.'**')
            ->line(__('Sign in (or create an account) and enter the code under My Account → Wallet. The amount goes into your wallet and can pay for any order.'))
            ->action(__('Redeem your gift card'), route('account.wallet'))
            ->line(__('Valid until :date.', ['date' => $card->expires_at?->translatedFormat('j F Y')]));
    }
}
