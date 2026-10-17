<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsManager;
use Illuminate\Console\Command;

/**
 * Every minute: send the text messages held back by quiet hours (Admin → SMS) once they end.
 */
class SmsReleaseHeldCommand extends Command
{
    protected $signature = 'sms:release-held';

    protected $description = 'Send SMS held back by quiet hours whose time has come';

    public function handle(SmsManager $sms): int
    {
        $count = $sms->releaseHeld();
        if ($count > 0) {
            $this->info("Released {$count} held SMS.");
        }

        return self::SUCCESS;
    }
}
