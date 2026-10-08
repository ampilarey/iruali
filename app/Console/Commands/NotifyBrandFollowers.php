<?php

namespace App\Console\Commands;

use App\Services\BrandDigestService;
use Illuminate\Console\Command;

/**
 * The daily email to brand followers: what the brands they follow put on sale, or into a running
 * sale, since the last email (see BrandDigestService). Scheduled once a day in routes/console.php.
 */
class NotifyBrandFollowers extends Command
{
    protected $signature = 'brands:notify-followers';

    protected $description = 'Email each customer what the brands they follow put on sale since the last email';

    public function handle(BrandDigestService $digests): int
    {
        $sent = $digests->sendAll();
        $this->info("Brand updates emailed to {$sent} customer(s).");

        return self::SUCCESS;
    }
}
