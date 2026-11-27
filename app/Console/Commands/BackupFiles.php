<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Spatie\Backup\BackupDestination\BackupDestinationFactory;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Backup\BackupJobFactory;
use Spatie\Backup\Tasks\Cleanup\CleanupJob;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Throwable;

/**
 * The weekly backup of the uploaded files (photos, documents, slips: storage/app), kept under a name
 * of its own (config('backup.backup.name') plus "-files", its own folder on the backup disk) and
 * cleaned up on its own. Kept in the database backups' folder, the Sunday files zip took the
 * weekly, monthly and yearly places meant for a database dump.
 */
class BackupFiles extends Command
{
    public const SUFFIX = '-files';

    protected $signature = 'backup:files {--clean : remove old files backups (the same keep rules as the database backups)}';

    protected $description = 'Back up the uploaded files (or clean old files backups) under their own backup name';

    public static function backupName(): string
    {
        return config('backup.backup.name').self::SUFFIX;
    }

    public static function config(): Config
    {
        $settings = (array) config('backup');
        $settings['backup']['name'] = self::backupName();

        return Config::fromArray($settings);
    }

    public function handle(): int
    {
        try {
            if ($this->option('clean')) {
                $destinations = BackupDestinationFactory::createFromArray(self::config());
                (new CleanupJob($destinations, app(DefaultStrategy::class), true))->run();
                $this->info('Old files backups cleaned up.');

                return self::SUCCESS;
            }

            $job = BackupJobFactory::createFromConfig(self::config())->dontBackupDatabases()->disableSignals();
            $job->setFilename('files-'.now()->format('Y-m-d-His').'.zip'); // on disk: iruali-backup-files-….zip
            $job->run();
            $this->info('Files backed up.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('Files backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
