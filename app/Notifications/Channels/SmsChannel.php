<?php

namespace App\Notifications\Channels;

use App\Services\Sms\SmsManager;
use Illuminate\Notifications\Notification;

/**
 * The "sms" notification channel: a notification implements toSms($notifiable), returning an
 * SmsText (App\Support\SmsTypes::render, which carries the type) or a plain string, and
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

        $message = $notification->toSms($notifiable);
        if (! $to || trim((string) $message) === '') {
            return; // no number, or the type is switched off in Admin → SMS
        }

        $this->sms->send($to, $message instanceof \App\Services\Sms\SmsText ? $message : (string) $message);
    }
}
