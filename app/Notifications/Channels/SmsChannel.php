<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsManager;
use Illuminate\Notifications\Notification;

/**
 * The "sms" notification channel: a notification implements toSms($notifiable): string and
 * the notifiable gives its number (routeNotificationForSms() or a phone attribute).
 */
class SmsChannel
{
    public function __construct(protected SmsManager $sms) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $to = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('sms', $notification)
            : null;
        $to = $to ?: ($notifiable->phone ?? null);

        $message = (string) $notification->toSms($notifiable);
        if (! $to || trim($message) === '') {
            return;
        }

        $this->sms->send($to, $message);
    }
}
