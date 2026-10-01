<?php

namespace App\Support;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Backup\BackupDestination\BackupDestination;
use Throwable;

/**
 * Is this install ready to run unattended? Used by `php artisan iruali:ready` and the admin
 * dashboard's "System status" panel. Every check answers pass / warn / fail with a one-line fix.
 *
 * @phpstan-type Check array{name: string, status: 'pass'|'warn'|'fail'|'skip', detail: string, fix: string}
 */
class ReadyChecks
{
    public const CACHE_KEY = 'ready.checks';

    public function __construct(
        protected bool $offline = false,
        protected ?string $sendTestMailTo = null,
    ) {}

    /**
     * The checks as the admin dashboard shows them: offline (no network) and cached for 5 minutes.
     *
     * @return array<int, Check>
     */
    public static function cached(): array
    {
        try {
            return Cache::remember(self::CACHE_KEY, 300, fn () => (new static(offline: true))->run());
        } catch (Throwable) {
            return (new static(offline: true))->run();
        }
    }

    /** @return array<int, Check> */
    public function run(): array
    {
        $checks = [];
        foreach ($this->checkMethods() as $name => $method) {
            try {
                $checks[] = ['name' => $name] + $this->$method();
            } catch (Throwable $e) {
                $checks[] = ['name' => $name, 'status' => 'fail', 'detail' => Str::limit(get_class($e).': '.$e->getMessage(), 160), 'fix' => 'Fix the error above, then run the check again.'];
            }
        }

        return $checks;
    }

    /** @param  array<int, Check>  $checks */
    public static function failures(array $checks): int
    {
        return count(array_filter($checks, fn ($c) => $c['status'] === 'fail'));
    }

    /** @return array<string, string> check label => method */
    protected function checkMethods(): array
    {
        return [
            'App key' => 'checkAppKey',
            'Database' => 'checkDatabase',
            'Environment' => 'checkEnvironment',
            'Timezone' => 'checkTimezone',
            'Scheduler' => 'checkScheduler',
            'Cache' => 'checkCache',
            'Sessions' => 'checkSession',
            'Queue' => 'checkQueue',
            'Mail' => 'checkMail',
            'BML Connect' => 'checkBml',
            'Database backup' => 'checkBackup',
            'Uploads' => 'checkUploads',
            'Error alerts' => 'checkAlerts',
            'SMS' => 'checkSms',
        ];
    }

    protected function pass(string $detail, string $fix = ''): array
    {
        return ['status' => 'pass', 'detail' => $detail, 'fix' => $fix];
    }

    protected function warn(string $detail, string $fix): array
    {
        return ['status' => 'warn', 'detail' => $detail, 'fix' => $fix];
    }

    protected function fail(string $detail, string $fix): array
    {
        return ['status' => 'fail', 'detail' => $detail, 'fix' => $fix];
    }

    protected function skip(string $detail): array
    {
        return ['status' => 'skip', 'detail' => $detail, 'fix' => ''];
    }

    protected function production(): bool
    {
        return app()->environment('production');
    }

    protected function checkAppKey(): array
    {
        return filled(config('app.key'))
            ? $this->pass('APP_KEY is set')
            : $this->fail('APP_KEY is empty', 'Run php artisan key:generate (sessions and 2FA secrets depend on it).');
    }

    protected function checkDatabase(): array
    {
        $connection = (string) config('database.default');
        try {
            \Illuminate\Support\Facades\DB::connection()->getPdo();
            $migrated = \Illuminate\Support\Facades\Schema::hasTable('migrations');
        } catch (Throwable $e) {
            return $this->fail("Cannot connect to the {$connection} database: ".Str::limit($e->getMessage(), 120), 'Check DB_CONNECTION, DB_DATABASE, DB_USERNAME and DB_PASSWORD in .env.');
        }

        return $migrated
            ? $this->pass("Connected to {$connection}")
            : $this->fail("Connected to {$connection} but it has no tables", 'Run php artisan migrate --force.');
    }

    protected function checkEnvironment(): array
    {
        $env = (string) config('app.env');
        if (! $this->production()) {
            return $this->warn("APP_ENV={$env}, APP_DEBUG=".var_export((bool) config('app.debug'), true), 'Fine for development. On the live site set APP_ENV=production and APP_DEBUG=false in .env.');
        }

        $problems = [];
        if (config('app.debug')) {
            $problems[] = 'APP_DEBUG is true (stack traces would be shown to visitors)';
        }
        if (! str_starts_with((string) config('app.url'), 'https://')) {
            $problems[] = 'APP_URL is not https';
        }

        return $problems
            ? $this->fail(implode('; ', $problems), 'In .env set APP_DEBUG=false and APP_URL=https://iruali.mv, then php artisan config:cache.')
            : $this->pass('APP_ENV=production, APP_DEBUG=false, APP_URL='.config('app.url'));
    }

    protected function checkTimezone(): array
    {
        $tz = (string) config('app.timezone');

        return $tz === 'Indian/Maldives'
            ? $this->pass('Indian/Maldives')
            : $this->warn("APP_TIMEZONE={$tz}", 'Set APP_TIMEZONE=Indian/Maldives so order times and the daily schedule use Maldives time.');
    }

    protected function checkScheduler(): array
    {
        $seen = Cache::get('scheduler.heartbeat');
        $fix = 'Add the cron on the server: * * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1';

        if (! $seen) {
            return $this->fail('No heartbeat from the scheduler yet', $fix);
        }

        $at = Carbon::parse($seen);
        if ($at->lt(now()->subMinutes(5))) {
            return $this->fail('Last heartbeat '.$at->diffForHumans(), $fix.' (it stopped running)');
        }

        return $this->pass('Last heartbeat '.$at->diffForHumans());
    }

    protected function checkCache(): array
    {
        $store = (string) config('cache.default');
        if (in_array($store, ['array', 'null'], true)) {
            return $this->fail("CACHE_STORE={$store} forgets everything between requests", 'Set CACHE_STORE=database (the cache table exists) or file in .env.');
        }

        $probe = Str::random(12);
        Cache::put('ready.probe', $probe, 60);
        $back = Cache::get('ready.probe');
        Cache::forget('ready.probe');

        return $back === $probe
            ? $this->pass("CACHE_STORE={$store}, values round-trip")
            : $this->fail("CACHE_STORE={$store} did not return the value just written", 'Check the cache table / storage/framework/cache permissions, or switch CACHE_STORE.');
    }

    protected function checkSession(): array
    {
        $driver = (string) config('session.driver');
        if (in_array($driver, ['array', 'null'], true)) {
            return $this->fail("SESSION_DRIVER={$driver}: nobody could stay signed in", 'Set SESSION_DRIVER=database (or file) in .env.');
        }

        return $this->pass("SESSION_DRIVER={$driver}");
    }

    protected function checkQueue(): array
    {
        $connection = (string) config('queue.default');
        if ($connection === 'sync') {
            return $this->production()
                ? $this->fail('QUEUE_CONNECTION=sync: emails are sent during the request and a mail outage breaks checkout', 'Set QUEUE_CONNECTION=database in .env; the scheduler then runs the worker every minute.')
                : $this->warn('QUEUE_CONNECTION=sync (jobs run inline)', 'Use QUEUE_CONNECTION=database on the live site.');
        }

        return $this->workerScheduled()
            ? $this->pass("QUEUE_CONNECTION={$connection}, queue:work scheduled every minute")
            : $this->fail("QUEUE_CONNECTION={$connection} but no queue worker is scheduled", 'routes/console.php must schedule queue:work --stop-when-empty (it is skipped only for the sync queue).');
    }

    protected function workerScheduled(): bool
    {
        try {
            app(ConsoleKernel::class)->bootstrap(); // loads routes/console.php when called from a web request
            foreach (app(Schedule::class)->events() as $event) {
                if (str_contains((string) $event->command, 'queue:work')) {
                    return true;
                }
            }
        } catch (Throwable) {
            return false;
        }

        return false;
    }

    protected function checkMail(): array
    {
        $mailer = (string) config('mail.default');
        $from = (string) config('mail.from.address');
        $fix = 'Set MAIL_MAILER=smtp with the host, port, username, password and MAIL_FROM_ADDRESS in .env, then run php artisan iruali:ready --send-test-mail=you@iruali.mv.';

        if (in_array($mailer, ['log', 'array'], true)) {
            return $this->production()
                ? $this->fail("MAIL_MAILER={$mailer}: no email leaves the server", $fix)
                : $this->warn("MAIL_MAILER={$mailer} (emails go to the log)", $fix);
        }
        if ($from === '' || str_contains($from, 'example.com')) {
            return $this->fail("MAIL_FROM_ADDRESS is '{$from}'", 'Set MAIL_FROM_ADDRESS to a real mailbox on your domain (e.g. hello@iruali.mv).');
        }

        $detail = "MAIL_MAILER={$mailer}, from {$from}";
        if ($this->sendTestMailTo) {
            try {
                Mail::to($this->sendTestMailTo)->send(new \App\Mail\ReadyCheckTestMail);
                $detail .= '; test mail sent to '.$this->sendTestMailTo;
            } catch (Throwable $e) {
                return $this->fail($detail.'; sending failed: '.Str::limit($e->getMessage(), 120), $fix);
            }
        }

        return $this->pass($detail);
    }

    protected function checkBml(): array
    {
        $missing = [];
        if (blank(config('services.bml.api_key'))) {
            $missing[] = 'BML_API_KEY';
        }
        if (blank(config('services.bml.webhook_secret'))) {
            $missing[] = 'BML_WEBHOOK_SECRET';
        }
        $environment = (string) config('services.bml.environment');
        if (! in_array($environment, ['sandbox', 'production'], true)) {
            $missing[] = 'BML_ENVIRONMENT (sandbox or production)';
        }
        if ($missing) {
            return $this->fail('Missing: '.implode(', ', $missing), 'Set them in .env from the BML merchant portal (docs/PAYMENTS_BML.md). Card payment is hidden until BML_API_KEY is set.');
        }

        $detail = "environment={$environment}, API key and webhook secret set";
        if ($this->production() && $environment !== 'production') {
            return $this->warn($detail, 'The live site is still on the BML sandbox: set BML_ENVIRONMENT=production and the live key once BML approves go-live.');
        }
        if ($this->offline) {
            return $this->pass($detail.' (network check skipped)');
        }

        $base = rtrim((string) config('services.bml.base_uri'), '/');
        try {
            $status = Http::timeout(10)->get($base)->status();
        } catch (Throwable $e) {
            return $this->fail($detail.'; '.$base.' unreachable: '.Str::limit($e->getMessage(), 100), 'Allow outbound HTTPS from the server to the BML API host (firewall / cPanel outbound rules).');
        }

        return $status < 500
            ? $this->pass($detail.", {$base} answered HTTP {$status}")
            : $this->fail($detail.", {$base} answered HTTP {$status}", 'BML is returning server errors; try again shortly and check status with BML.');
    }

    protected function checkBackup(): array
    {
        $disks = (array) config('backup.backup.destination.disks', ['local']);
        $disk = (string) ($disks[0] ?? 'local');
        $fix = 'Run php artisan backup:run --only-db once by hand, and make sure the scheduler cron runs (it backs up nightly). Set BACKUP_DISKS=s3 to keep copies off the server.';

        $destination = BackupDestination::create($disk, (string) config('backup.backup.name'));
        if (! $destination->isReachable()) {
            return $this->fail("Backup disk '{$disk}' is not reachable", 'Check the disk in config/filesystems.php and the AWS_* keys in .env.');
        }

        $newest = $destination->newestBackup();
        if (! $newest) {
            return $this->fail("No backup on disk '{$disk}' yet", $fix);
        }

        $age = $newest->date();
        if ($age->lt(now()->subHours(48))) {
            return $this->fail("Newest backup on '{$disk}' is from ".$age->diffForHumans(), $fix);
        }

        return $this->pass("Newest backup on '{$disk}' ".$age->diffForHumans().', '.count($disks).' disk(s): '.implode(', ', $disks));
    }

    protected function checkUploads(): array
    {
        if (! Route::has('storage.file')) {
            return $this->fail('The /storage/{path} route is missing', 'Restore the storage.file route in routes/web.php (StorageController).');
        }

        $disk = Storage::disk('public');
        $name = 'ready-check-'.Str::random(8).'.txt';
        $probe = Str::random(16);
        if (! $disk->put($name, $probe) || $disk->get($name) !== $probe) {
            return $this->fail('storage/app/public is not writable', 'mkdir -p storage/app/public and give the web server write access (chmod -R 775 storage).');
        }

        try {
            // From the command line (not inside a web request) also fetch it the way a browser would
            if (app()->runningInConsole() && app('request')->route() === null) {
                $original = app('request');
                try {
                    $response = app(HttpKernel::class)->handle(Request::create('/storage/'.$name, 'GET'));
                } finally {
                    app()->instance('request', $original);
                }
                if ($response->getStatusCode() !== 200) {
                    return $this->fail('GET /storage/'.$name.' answered HTTP '.$response->getStatusCode(), 'StorageController must serve files from the public disk; check .htaccess rewrites reach index.php.');
                }
            }
        } finally {
            $disk->delete($name);
        }

        return $this->pass('storage/app/public is writable and served at /storage/…');
    }

    protected function checkAlerts(): array
    {
        $to = config('mail.alerts_to') ?: \App\Models\Setting::get('contact_email');

        return filled($to)
            ? $this->pass('Error alerts and the daily digest go to '.$to)
            : $this->warn('No address for error alerts', 'Set ALERTS_EMAIL in .env (or a contact email in Admin → Settings).');
    }

    protected function checkSms(): array
    {
        if (! is_array(config('sms'))) {
            return $this->skip('No SMS configuration in this install');
        }

        $driver = (string) config('sms.driver', 'log');

        return $driver === 'log'
            ? $this->warn('SMS driver is log (codes are written to the log, not sent)', 'Set the SMS driver and credentials in config/sms.php / .env.')
            : $this->pass("SMS driver {$driver}");
    }
}
