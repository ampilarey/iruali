<?php

namespace App\Console\Commands;

use App\Services\DemoDataService;
use Illuminate\Console\Command;

/**
 * php artisan demo:remove [--force]: the same as Admin → Settings → Sample data → Remove, for the
 * server. Shows what there is, asks first (unless --force), then takes the sample data off.
 */
class DemoRemoveCommand extends Command
{
    protected $signature = 'demo:remove {--force : Remove without asking first}';

    protected $description = 'Take the demo shops and sample products off the site (Restore with demo:restore)';

    public function handle(DemoDataService $demo): int
    {
        $status = $demo->status();
        $this->table([__('Sample data on this site'), __('Count')], [
            [__('Demo shops'), $status['shops']],
            [__('Sample products still on the site'), $status['products_live']],
            [__('Sample products removed'), $status['products_removed']],
        ]);

        if (! $status['can_remove']) {
            $this->info(__('There is no sample data to remove.'));

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm(__('Remove the sample shops and products now?'))) {
            $this->warn(__('Nothing was changed.'));

            return self::FAILURE;
        }

        $removed = $demo->remove();
        $labels = $demo->countLabels();
        $this->table([__('Removed'), __('Count')], collect($removed)->map(fn (int $count, string $key) => [$labels[$key] ?? $key, $count])->values()->all());
        $this->info(__('Sample data removed. Bring it back with php artisan demo:restore or from Admin → Settings → Sample data.'));

        return self::SUCCESS;
    }
}
