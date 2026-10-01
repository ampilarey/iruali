<?php

namespace App\Notifications;

use App\Models\SellerPayout;
use App\Support\Money;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * iruali has transferred a payout to the shop's bank account.
 */
class PayoutPaid extends Notification implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public SellerPayout $payout) {}

    public function via(object $notifiable): array
    {
        if (method_exists($notifiable, 'wantsNotification') && ! $notifiable->wantsNotification('payout')) {
            return [];
        }

        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $payout = $this->payout;
        $batch = $payout->batch;
        $account = $notifiable->bankAccount ?? null;

        $mail = (new MailMessage)->salutation(__('The iruali team'))
            ->subject(__('Payout of :amount sent to your bank account', ['amount' => Money::format($payout->amount)]))
            ->greeting(__('Hello :name,', ['name' => $notifiable->business_name ?: $notifiable->name]))
            ->line(__('We have transferred :amount to your bank account.', ['amount' => Money::format($payout->amount)]));

        if ($account) {
            $mail->line(__('Account: :bank ending :last4', ['bank' => $account->bankName(), 'last4' => substr($account->account_number, -4)]));
        }
        if ($batch) {
            $mail->line(__('Payout reference: :reference', ['reference' => $batch->reference.'/'.$payout->id]));
            if ($batch->bank_reference) {
                $mail->line(__('Bank reference: :reference', ['reference' => $batch->bank_reference]));
            }
        } elseif ($payout->reference) {
            $mail->line(__('Bank reference: :reference', ['reference' => $payout->reference]));
        }

        return $mail->line(__('Date: :date', ['date' => $payout->paid_at?->translatedFormat('j F Y')]))
            ->line(__('It can take a working day to show in your account, depending on your bank.'))
            ->action(__('View your earnings'), route('seller.earnings'));
    }
}
