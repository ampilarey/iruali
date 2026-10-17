<?php

namespace App\Console\Commands;

use App\Models\SmsMessage;
use Illuminate\Console\Command;

/**
 * Daily: delete SMS log rows older than the days kept (default 180), apart from held ones.
 */
class SmsPruneCommand extends Command
{
    protected $signature = 'sms:prune {--days=180 : Keep this many days of the SMS log}';

    protected $description = 'Delete old rows from the SMS log';

    public function handle(): int
    {
        $days = max(30, (int) $this->option('days'));
        $deleted = SmsMessage::where('created_at', '<', now()->subDays($days))->where('status', '!=', 'held')->delete();
        $this->info("Deleted {$deleted} SMS log rows older than {$days} days.");

        return self::SUCCESS;
    }
}
