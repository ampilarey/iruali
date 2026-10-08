<?php

namespace App\Console\Commands;

use App\Services\BrandService;
use Illuminate\Console\Command;

/**
 * Link any product whose brand name has no brand yet, for example after rows were imported
 * straight into the database. Products saved through the app are linked as they are saved.
 */
class SyncBrands extends Command
{
    protected $signature = 'brands:sync';

    protected $description = 'Link products to the shared brand list (safe to run any time)';

    public function handle(BrandService $brands): int
    {
        $result = $brands->linkProducts();
        $this->info("Brands created: {$result['brands']}. Products linked: {$result['products']}.");

        return self::SUCCESS;
    }
}
