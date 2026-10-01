<?php

namespace App\Console\Commands;

use App\Support\ReadyChecks;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ReadyCheck extends Command
{
    protected $signature = 'iruali:ready
                            {--offline : Skip checks that need the network (BML API)}
                            {--send-test-mail= : Also send a test email to this address}';

    protected $description = 'Check that this install can run unattended: scheduler, cache, queue, mail, BML, backups, uploads';

    public function handle(): int
    {
        $checks = (new ReadyChecks(
            offline: (bool) $this->option('offline'),
            sendTestMailTo: $this->option('send-test-mail') ?: null,
        ))->run();

        // The dashboard panel shows the same answers until they are five minutes old
        Cache::put(ReadyChecks::CACHE_KEY, $checks, 300);

        $this->table(
            ['Check', 'Status', 'Detail', 'How to fix'],
            array_map(fn ($c) => [$c['name'], strtoupper($c['status']), $c['detail'], $c['fix']], $checks),
        );

        $failures = ReadyChecks::failures($checks);
        if ($failures > 0) {
            $this->error("NOT READY ({$failures} failures)");

            return self::FAILURE;
        }

        $this->info('READY');

        return self::SUCCESS;
    }
}
