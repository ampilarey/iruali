<?php

namespace App\Console\Commands;

use App\Services\DemoDataService;
use Illuminate\Console\Command;

/**
 * php artisan demo:restore [--force]: the same as Admin → Settings → Sample data → Restore, for the
 * server. Puts back exactly what demo:remove (or the admin page) took off.
 */
class DemoRestoreCommand extends Command
{
    protected $signature = 'demo:restore {--force : Restore without asking first}';

    protected $description = 'Put back the demo shops and sample products that demo:remove took off';

    public function handle(DemoDataService $demo): int
    {
        $status = $demo->status();
        if (! $status['can_restore']) {
            $this->info(__('There is no sample data to restore.'));

            return self::SUCCESS;
        }

        $this->line(trans_choice(':count product can be restored.|:count products can be restored.', $status['restorable_products'], ['count' => $status['restorable_products']]));
        if (! $this->option('force') && ! $this->confirm(__('Put the removed sample shops and products back now?'))) {
            $this->warn(__('Nothing was changed.'));

            return self::FAILURE;
        }

        $restored = $demo->restore();
        $labels = $demo->countLabels();
        $this->table([__('Restored'), __('Count')], collect($restored)->map(fn (int $count, string $key) => [$labels[$key] ?? $key, $count])->values()->all());
        $this->info(__('Sample data restored.'));

        return self::SUCCESS;
    }
}
