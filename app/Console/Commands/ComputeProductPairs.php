<?php

namespace App\Console\Commands;

use App\Services\SuggestionService;
use Illuminate\Console\Command;

/**
 * Nightly: which products are bought together (product pages, cart suggestions).
 */
class ComputeProductPairs extends Command
{
    protected $signature = 'insights:compute-pairs';

    protected $description = 'Work out which products are frequently bought together';

    public function handle(SuggestionService $suggestions): int
    {
        $this->info('Stored '.$suggestions->computePairs().' product pairs.');

        return self::SUCCESS;
    }
}
