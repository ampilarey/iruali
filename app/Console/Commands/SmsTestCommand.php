<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsManager;
use Illuminate\Console\Command;

/**
 * Sends one text message through the configured SMS gateway and shows the gateway's reply,
 * to check SMS_* in .env (it is kept in Admin → SMS like any other message).
 */
class SmsTestCommand extends Command
{
    protected $signature = 'sms:test {number : Maldivian mobile number, e.g. 7771234}
                            {--message= : Text to send (default: a short test message)}';

    protected $description = 'Send a test SMS through the configured gateway and show the reply';

    public function handle(SmsManager $sms): int
    {
        $message = (string) ($this->option('message') ?: 'iruali SMS test: if you can read this, text messages work.');

        $this->line('Driver: '.$sms->driverName().($sms->isLive() ? ' ('.config('sms.http.url').')' : ' (nothing leaves the server; set SMS_DRIVER=http)'));
        $result = $sms->send((string) $this->argument('number'), $message, 'test', urgent: true);

        $this->line('Status: '.$result->status);
        if (filled($result->providerResponse)) {
            $this->line('Gateway reply: '.$result->providerResponse);
        }

        if (! $result->success) {
            $this->error('SMS not sent.');

            return self::FAILURE;
        }

        $this->info($sms->isLive() ? 'SMS sent.' : 'SMS logged, not sent.');

        return self::SUCCESS;
    }
}
