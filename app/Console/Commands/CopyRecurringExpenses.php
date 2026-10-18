<?php

namespace App\Console\Commands;

use App\Services\FinanceService;
use Illuminate\Console\Command;

/**
 * On the 1st: monthly expenses (hosting, SMS plan...) are copied into the new month.
 */
class CopyRecurringExpenses extends Command
{
    protected $signature = 'finance:recurring-expenses';

    protected $description = 'Copy last month\'s recurring expenses into this month';

    public function handle(FinanceService $finance): int
    {
        $this->info('Copied '.$finance->copyRecurring().' recurring expense(s).');

        return self::SUCCESS;
    }
}
