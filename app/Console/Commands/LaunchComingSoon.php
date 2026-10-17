<?php

namespace App\Console\Commands;

use App\Services\ComingSoonService;
use Illuminate\Console\Command;

/**
 * Daily, just after midnight Maldives time: "coming soon" products whose launch day has come go on
 * sale, and the shoppers who asked are emailed (ComingSoonService::launchDue()).
 */
class LaunchComingSoon extends Command
{
    protected $signature = 'products:launch';

    protected $description = 'Put "coming soon" products on sale on their launch day and email those who asked';

    public function handle(ComingSoonService $comingSoon): int
    {
        $count = $comingSoon->launchDue();
        $this->info("{$count} product(s) went on sale.");

        return self::SUCCESS;
    }
}
