<?php

namespace App\Console\Commands;

use App\Mail\RestoreDrillReport;
use App\Support\ErrorTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;
use ZipArchive;

/**
 * Proves the backups can actually be restored: downloads the newest backup, restores its SQL
 * dump into a scratch database, compares table and row counts with the live database, drops
 * the scratch tables and emails a report. Scheduled monthly; safe to run by hand any time.
 */
class BackupRestoreDrill extends Command
{
    protected $signature = 'backup:restore-drill {--no-mail : Print the report only}';

    protected $description = 'Restore the newest backup into DB_DRILL_DATABASE, compare counts with the live database and email a report';

    public const COMPARED_TABLES = ['orders', 'users', 'products'];

    protected const DRILL_CONNECTION = 'restore-drill';

    public function handle(): int
    {
        $report = [
            'ok' => false,
            'disk' => null,
            'backup' => null,
            'backup_date' => null,
            'drill_database' => null,
            'tables' => 0,
            'counts' => [],
            'steps' => [],
            'error' => null,
            'started_at' => now(),
        ];

        $tempDir = storage_path('app/restore-drill-'.Str::random(8));

        try {
            $drillDb = $this->drillDatabaseName();
            $report['drill_database'] = $drillDb;

            [$backup, $diskName] = $this->newestBackup();
            $report['disk'] = $diskName;
            $report['backup'] = $backup->path();
            $report['backup_date'] = $backup->date();
            $report['steps'][] = "Newest backup on '{$diskName}': {$backup->path()} (".$backup->date()->diffForHumans().', '.round($backup->sizeInBytes() / 1024).' KB)';

            File::ensureDirectoryExists($tempDir);
            $zipPath = $this->download($backup, $tempDir);
            $dump = $this->extractDump($zipPath, $tempDir);
            $report['steps'][] = 'Downloaded and unzipped; SQL dump '.basename($dump).' ('.round(filesize($dump) / 1024).' KB)';

            $this->prepareDrillConnection($drillDb);
            $this->dropDrillTables();
            $statements = $this->restore($dump);
            $report['steps'][] = "Restored {$statements} SQL statements into {$drillDb}";

            $tables = $this->drillTables();
            $report['tables'] = count($tables);
            foreach (self::COMPARED_TABLES as $table) {
                $report['counts'][$table] = [
                    'backup' => in_array($table, $tables, true) ? (int) DB::connection(self::DRILL_CONNECTION)->table($table)->count() : null,
                    'live' => (int) DB::table($table)->count(),
                ];
            }

            $problems = [];
            if (count($tables) === 0) {
                $problems[] = 'the dump created no tables';
            }
            foreach ($report['counts'] as $table => $c) {
                if ($c['backup'] === null) {
                    $problems[] = "table {$table} is missing from the backup";
                } elseif ($c['live'] > 0 && $c['backup'] === 0) {
                    $problems[] = "table {$table} is empty in the backup but has {$c['live']} live rows";
                }
            }
            $report['ok'] = $problems === [];
            $report['error'] = $problems ? implode('; ', $problems) : null;
        } catch (Throwable $e) {
            $report['error'] = get_class($e).': '.$e->getMessage();
            report($e);
        } finally {
            try {
                $this->dropDrillTables();
                $report['steps'][] = 'Dropped the drill tables again';
            } catch (Throwable $e) {
                $report['steps'][] = 'Could not drop the drill tables: '.$e->getMessage();
            }
            File::deleteDirectory($tempDir);
        }

        $report['finished_at'] = now();
        $this->printReport($report);

        if (! $this->option('no-mail')) {
            if ($to = ErrorTracker::alertsTo()) {
                Mail::to($to)->send(new RestoreDrillReport($report));
                $this->line("Report sent to {$to}.");
            } else {
                $this->warn('No ALERTS_EMAIL (or contact email in Settings): report not sent.');
            }
        }

        return $report['ok'] ? self::SUCCESS : self::FAILURE;
    }

    protected function drillDatabaseName(): string
    {
        $drill = trim((string) config('backup.restore_drill.database'));
        $live = (string) config('database.connections.'.config('database.default').'.database');

        if ($drill === '') {
            throw new \RuntimeException('DB_DRILL_DATABASE is not set: name a scratch database the drill may overwrite.');
        }
        $sameFile = file_exists($drill) && file_exists($live) && realpath($drill) === realpath($live); // sqlite paths
        if ($drill === $live || $sameFile) {
            throw new \RuntimeException("DB_DRILL_DATABASE must not be the live database ({$live}).");
        }

        return $drill;
    }

    /** @return array{0: Backup, 1: string} */
    protected function newestBackup(): array
    {
        $disks = (array) config('backup.backup.destination.disks', ['local']);
        $diskName = (string) ($disks[0] ?? 'local');
        $destination = BackupDestination::create($diskName, (string) config('backup.backup.name'));

        if (! $destination->isReachable()) {
            throw new \RuntimeException("Backup disk '{$diskName}' is not reachable: ".$destination->connectionError()->getMessage());
        }

        $backup = $destination->newestBackup();
        if (! $backup) {
            throw new \RuntimeException("No backup found on disk '{$diskName}'. Run php artisan backup:run --only-db first.");
        }

        return [$backup, $diskName];
    }

    protected function download(Backup $backup, string $tempDir): string
    {
        $zipPath = $tempDir.'/backup.zip';
        $in = $backup->stream();
        $out = fopen($zipPath, 'wb');
        stream_copy_to_stream($in, $out);
        fclose($out);
        if (is_resource($in)) {
            fclose($in);
        }

        return $zipPath;
    }

    /** Unzips the backup and returns the path of the (decompressed) SQL dump. */
    protected function extractDump(string $zipPath, string $tempDir): string
    {
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            throw new \RuntimeException('The backup is not a readable zip file.');
        }
        if ($password = config('backup.backup.password')) {
            $zip->setPassword($password);
        }

        $dumpEntry = null;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('~(^|/)db-dumps/.+\.sql(\.gz)?$~', $name) || (! $dumpEntry && preg_match('~\.sql(\.gz)?$~', $name))) {
                $dumpEntry = $name;
            }
        }
        if (! $dumpEntry) {
            throw new \RuntimeException('The backup contains no SQL dump (was it made with --only-db or with databases configured?).');
        }

        $zip->extractTo($tempDir.'/unzipped', [$dumpEntry]);
        $zip->close();
        $path = $tempDir.'/unzipped/'.$dumpEntry;

        if (str_ends_with($path, '.gz')) {
            $plain = substr($path, 0, -3);
            $gz = gzopen($path, 'rb');
            $out = fopen($plain, 'wb');
            while (! gzeof($gz)) {
                fwrite($out, gzread($gz, 1024 * 1024));
            }
            gzclose($gz);
            fclose($out);
            $path = $plain;
        }

        return $path;
    }

    /**
     * A connection to the drill database that is separate from the live one (so the live
     * connection, and any transaction on it, is never touched). Creates the database if missing.
     */
    protected function prepareDrillConnection(string $drillDb): void
    {
        $liveName = (string) config('database.default');
        $live = config("database.connections.{$liveName}");
        $driver = $live['driver'] ?? 'mysql';

        if ($driver === 'sqlite') {
            if (! file_exists($drillDb)) {
                touch($drillDb);
            }
        } else {
            // A second server connection without a database selected, just to create the drill database
            Config::set('database.connections.restore-drill-admin', array_merge($live, ['database' => null, 'url' => null]));
            DB::purge('restore-drill-admin');
            DB::connection('restore-drill-admin')->statement('CREATE DATABASE IF NOT EXISTS `'.str_replace('`', '', $drillDb).'`');
            DB::purge('restore-drill-admin');
        }

        Config::set('database.connections.'.self::DRILL_CONNECTION, array_merge($live, ['database' => $drillDb, 'url' => null]));
        DB::purge(self::DRILL_CONNECTION);
    }

    /** @return string[] */
    protected function drillTables(): array
    {
        $db = DB::connection(self::DRILL_CONNECTION);
        $database = $db->getDatabaseName();

        // getTables() lists every schema the user can see on MySQL/MariaDB: keep the drill database only
        return collect($db->getSchemaBuilder()->getTables())
            ->filter(fn ($t) => $db->getDriverName() === 'sqlite' || ! isset($t['schema']) || $t['schema'] === $database)
            ->map(fn ($t) => $t['name'])
            ->values()
            ->all();
    }

    protected function dropDrillTables(): void
    {
        if (! config('database.connections.'.self::DRILL_CONNECTION)) {
            return;
        }
        $db = DB::connection(self::DRILL_CONNECTION);
        $tables = $this->drillTables();
        if ($tables === []) {
            return;
        }

        $db->getSchemaBuilder()->disableForeignKeyConstraints();
        foreach ($tables as $table) {
            $db->getSchemaBuilder()->drop($table);
        }
        $db->getSchemaBuilder()->enableForeignKeyConstraints();
    }

    /**
     * Runs the dump statement by statement. mysqldump (and sqlite .dump) escape newlines inside
     * values, so a line ending in ";" always ends a statement.
     */
    protected function restore(string $dumpPath): int
    {
        $pdo = DB::connection(self::DRILL_CONNECTION)->getPdo();
        $handle = fopen($dumpPath, 'rb');
        $buffer = '';
        $count = 0;

        try {
            DB::connection(self::DRILL_CONNECTION)->getSchemaBuilder()->disableForeignKeyConstraints();
            while (($line = fgets($handle)) !== false) {
                $trimmed = rtrim($line);
                if ($buffer === '' && ($trimmed === '' || str_starts_with($trimmed, '--'))) {
                    continue;
                }
                $buffer .= $line;
                if (str_ends_with($trimmed, ';')) {
                    $statement = trim($buffer);
                    $buffer = '';
                    if ($statement === ';' || $statement === '') {
                        continue;
                    }
                    $pdo->exec($statement);
                    $count++;
                }
            }
            if (trim($buffer) !== '') {
                $pdo->exec(trim($buffer));
                $count++;
            }
        } finally {
            fclose($handle);
            DB::connection(self::DRILL_CONNECTION)->getSchemaBuilder()->enableForeignKeyConstraints();
        }

        return $count;
    }

    protected function printReport(array $report): void
    {
        $this->newLine();
        foreach ($report['steps'] as $step) {
            $this->line('  - '.$step);
        }
        if ($report['counts']) {
            $this->table(['Table', 'Rows in backup', 'Rows live'], collect($report['counts'])->map(fn ($c, $t) => [$t, $c['backup'] ?? 'missing', $c['live']])->values()->all());
        }
        if ($report['error']) {
            $this->error('Restore drill FAILED: '.$report['error']);
        } else {
            $this->info("Restore drill PASSED: {$report['tables']} tables restored from {$report['backup']}.");
        }
    }
}
