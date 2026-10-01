<?php

namespace Tests\Feature;

use App\Mail\RestoreDrillReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestoreDrillTest extends TestCase
{
    use RefreshDatabase;

    protected string $drillDb;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('drill-backups');

        $this->drillDb = config('database.connections.'.config('database.default').'.database').'_drill';
        config([
            'mail.alerts_to' => 'alerts@iruali.mv',
            'backup.backup.name' => 'iruali',
            'backup.backup.destination.disks' => ['drill-backups'],
            'backup.restore_drill.database' => $this->drillDb,
        ]);
    }

    protected function putBackup(string $name, string $sql, bool $gzip = true): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'bk');
        $zip = new \ZipArchive;
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $zip->addFromString('db-dumps/mysql-iruali.sql'.($gzip ? '.gz' : ''), $gzip ? gzencode($sql) : $sql);
        $zip->close();

        Storage::disk('drill-backups')->put('iruali/'.$name, file_get_contents($tmp));
        unlink($tmp);
    }

    /** A mysqldump-style dump of a few tables, with the usual comments and a newline escaped inside a value. */
    protected function dump(): string
    {
        return <<<'SQL'
-- MariaDB dump 10.19
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `users` VALUES (1,'Aishath','a@example.com'),(2,'Mohamed; with semicolon\nand newline','m@example.com');
DROP TABLE IF EXISTS `orders`;
CREATE TABLE `orders` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `user_id` bigint unsigned NOT NULL, `total_amount` decimal(10,2) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
INSERT INTO `orders` VALUES (1,1,120.50),(2,2,99.00),(3,1,10.00);
DROP TABLE IF EXISTS `products`;
CREATE TABLE `products` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `name` varchar(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
INSERT INTO `products` VALUES (1,'Dried tuna');
DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (`id` bigint unsigned NOT NULL AUTO_INCREMENT, `key` varchar(255) NOT NULL, PRIMARY KEY (`id`)) ENGINE=InnoDB;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
SQL;
    }

    protected function drillTables(): array
    {
        config(['database.connections.drill-check' => array_merge(config('database.connections.'.config('database.default')), ['database' => $this->drillDb])]);
        DB::purge('drill-check');

        return array_map(fn ($t) => $t['name'], DB::connection('drill-check')->getSchemaBuilder()->getTables($this->drillDb));
    }

    public function test_the_newest_backup_is_restored_compared_and_reported(): void
    {
        User::factory()->create();
        $this->putBackup('iruali-backup-2026-09-01-02-00-00.zip', 'CREATE TABLE `old` (`id` int);');
        $this->putBackup('iruali-backup-2026-09-30-02-00-00.zip', $this->dump());

        // Spatie orders backups by modification time (the prefixed filename is not parsed as a date)
        touch(Storage::disk('drill-backups')->path('iruali/iruali-backup-2026-09-01-02-00-00.zip'), now()->subMonth()->getTimestamp());

        $this->artisan('backup:restore-drill')
            ->expectsOutputToContain('Restore drill PASSED')
            ->expectsOutputToContain('Report sent to alerts@iruali.mv')
            ->assertExitCode(0);

        Mail::assertQueued(RestoreDrillReport::class, function (RestoreDrillReport $mail) {
            $r = $mail->report;

            return $mail->hasTo('alerts@iruali.mv')
                && $r['ok'] === true
                && $r['backup'] === 'iruali/iruali-backup-2026-09-30-02-00-00.zip'
                && $r['disk'] === 'drill-backups'
                && $r['tables'] === 4
                && $r['counts']['users'] === ['backup' => 2, 'live' => 1]
                && $r['counts']['orders'] === ['backup' => 3, 'live' => 0]
                && $r['counts']['products'] === ['backup' => 1, 'live' => 0]
                && str_contains($mail->render(), 'passed');
        });

        $this->assertSame([], $this->drillTables(), 'the drill tables are dropped afterwards');
        $this->assertSame(1, User::count(), 'the live database is untouched');
    }

    public function test_a_plain_sql_dump_works_too_and_live_tables_are_never_touched(): void
    {
        $this->putBackup('iruali-backup-2026-09-30-02-00-00.zip', $this->dump(), gzip: false);
        $products = \App\Models\Product::factory()->count(2)->create();

        $this->artisan('backup:restore-drill --no-mail')->assertExitCode(0);

        Mail::assertNothingQueued();
        $this->assertCount(2, \App\Models\Product::all());
        $this->assertTrue($products->first()->exists);
    }

    public function test_it_refuses_to_use_the_live_database_as_the_drill_database(): void
    {
        config(['backup.restore_drill.database' => config('database.connections.'.config('database.default').'.database')]);
        $this->putBackup('iruali-backup-2026-09-30-02-00-00.zip', $this->dump());

        $this->artisan('backup:restore-drill')
            ->expectsOutputToContain('must not be the live database')
            ->assertExitCode(1);

        Mail::assertQueued(RestoreDrillReport::class, fn ($mail) => $mail->report['ok'] === false && str_contains($mail->report['error'], 'must not be the live database'));
        $this->assertTrue(\Schema::hasTable('users'));
    }

    public function test_a_missing_backup_is_reported_as_a_failure(): void
    {
        $this->artisan('backup:restore-drill')
            ->expectsOutputToContain('No backup found')
            ->assertExitCode(1);

        Mail::assertQueued(RestoreDrillReport::class, fn ($mail) => $mail->report['ok'] === false && str_contains($mail->render(), 'FAILED'));
    }

    public function test_backup_disks_come_from_the_environment(): void
    {
        $fresh = require config_path('backup.php'); // BACKUP_DISKS is not set in tests
        $this->assertSame(['local'], $fresh['backup']['destination']['disks']);
        $this->assertSame(['local'], $fresh['monitor_backups'][0]['disks']);
        $this->assertArrayHasKey('s3', config('filesystems.disks'));
        $this->assertSame([], config('backup.notifications.notifications')[\Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class]);
        $this->assertSame(['mail'], config('backup.notifications.notifications')[\Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class]);
        $this->artisan('schedule:list')->expectsOutputToContain('backup:restore-drill');
    }
}
