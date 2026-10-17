<?php

namespace App\Jobs;

use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsText;
use App\Services\Telegram;
use App\Support\TeamAlerts;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * One team alert (App\Support\TeamAlerts): a text to each staff member who chose the event, and a
 * post to the Telegram group when the event is picked there.
 */
class SendTeamAlert implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public function __construct(public string $event, public string $text, public ?string $url = null) {}

    public function handle(SmsManager $sms, Telegram $telegram): void
    {
        $urgent = in_array($this->event, ['payment_error', 'backup_failed'], true);
        foreach (TeamAlerts::smsRecipients($this->event) as $staff) {
            try {
                $sms->send($staff->phone, new SmsText('team_alert', $this->text, $urgent));
            } catch (Throwable $e) {
                report($e);
            }
        }

        if (($chat = TeamAlerts::telegramChatId()) && in_array($this->event, TeamAlerts::telegramEvents(), true)) {
            $telegram->send($chat, $this->text.($this->url ? "\n".$this->url : ''));
        }
    }
}
