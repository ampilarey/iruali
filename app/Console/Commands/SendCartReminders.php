<?php

namespace App\Console\Commands;

use App\Services\CartReminderService;
use Illuminate\Console\Command;

/**
 * Emails customers who left items in their cart (see CartReminderService for the rules).
 * Scheduled hourly in routes/console.php.
 */
class SendCartReminders extends Command
{
    protected $signature = 'marketing:cart-reminders';

    protected $description = 'Email signed-in customers about carts they abandoned (3 h and 48 h nudges)';

    public function handle(CartReminderService $reminders): int
    {
        if (! $reminders->enabled()) {
            $this->info('Abandoned cart emails are switched off in Settings.');

            return self::SUCCESS;
        }

        $sent = $reminders->sendDue();
        $this->info("Sent {$sent[1]} first and {$sent[2]} second reminder(s).");

        return self::SUCCESS;
    }
}
