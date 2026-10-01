<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\ReadyChecks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReadyCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bare_install_is_not_ready_and_explains_why(): void
    {
        $this->artisan('iruali:ready --offline')
            ->expectsOutputToContain('NOT READY')
            ->expectsOutputToContain('No heartbeat from the scheduler yet')
            ->assertExitCode(1);

        $checks = collect((new ReadyChecks(offline: true))->run())->keyBy('name');
        $this->assertSame('fail', $checks['Scheduler']['status']);
        $this->assertStringContainsString('schedule:run', $checks['Scheduler']['fix']);
        $this->assertSame('fail', $checks['Cache']['status']);          // CACHE_STORE=array in tests
        $this->assertSame('fail', $checks['Sessions']['status']);       // SESSION_DRIVER=array in tests
        $this->assertSame('warn', $checks['Queue']['status']);          // sync outside production
        $this->assertSame('fail', $checks['BML Connect']['status']);
        $this->assertSame('warn', $checks['SMS']['status']); // SMS_DRIVER=log
        $this->assertSame('pass', $checks['Uploads']['status']);
        $this->assertSame('pass', $checks['App key']['status']);
        $this->assertSame('pass', $checks['Timezone']['status']);
        $this->assertStringNotContainsString('test-key', json_encode($checks));
    }

    public function test_a_configured_install_is_ready(): void
    {
        $this->configureEverything();

        $this->artisan('iruali:ready --offline')
            ->expectsOutputToContain('READY')
            ->doesntExpectOutputToContain('NOT READY')
            ->assertExitCode(0);

        $checks = collect((new ReadyChecks(offline: true))->run())->keyBy('name');
        $this->assertSame(0, ReadyChecks::failures($checks->all()));
        $this->assertSame('pass', $checks['Scheduler']['status']);
        $this->assertSame('pass', $checks['Queue']['status']);
        $this->assertSame('pass', $checks['Database backup']['status']);
        $this->assertSame('pass', $checks['Mail']['status']);
        $this->assertSame('pass', $checks['BML Connect']['status']);
    }

    public function test_a_stale_heartbeat_and_old_backup_fail(): void
    {
        $this->configureEverything();
        Cache::forever('scheduler.heartbeat', now()->subMinutes(20)->toIso8601String());
        Storage::disk('backups')->delete('iruali/iruali-backup-2026-01-01-00-00-00.zip');
        Storage::disk('backups')->put('iruali/iruali-backup-2020-01-01-00-00-00.zip', 'zip');
        touch(Storage::disk('backups')->path('iruali/iruali-backup-2020-01-01-00-00-00.zip'), now()->subDays(5)->getTimestamp());

        $checks = collect((new ReadyChecks(offline: true))->run())->keyBy('name');
        $this->assertSame('fail', $checks['Scheduler']['status']);
        $this->assertSame('fail', $checks['Database backup']['status']);
        $this->assertStringContainsString('backup:run', $checks['Database backup']['fix']);
    }

    public function test_send_test_mail_option_sends_one(): void
    {
        $this->configureEverything();
        Mail::fake();

        $this->artisan('iruali:ready --offline --send-test-mail=owner@iruali.mv')->assertExitCode(0);

        Mail::assertSent(\App\Mail\ReadyCheckTestMail::class, fn ($mail) => $mail->hasTo('owner@iruali.mv'));
    }

    public function test_admin_dashboard_shows_the_system_status_panel_to_admins_only(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->actingAs($admin)->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('System status')
            ->assertSee('Scheduler')
            ->assertSee('data-status="fail"', false);

        $this->assertNotEmpty(Cache::get(ReadyChecks::CACHE_KEY));
    }

    protected function tearDown(): void
    {
        Cache::store('file')->forget('scheduler.heartbeat');
        Cache::store('file')->forget(ReadyChecks::CACHE_KEY);
        parent::tearDown();
    }

    protected function configureEverything(): void
    {
        Cache::forever('scheduler.heartbeat', now()->toIso8601String());
        Storage::fake('backups');
        Storage::disk('backups')->put('iruali/iruali-backup-2026-01-01-00-00-00.zip', 'zip');

        config([
            'cache.default' => 'array',       // the array store round-trips within one process; name check below is relaxed via file
            'session.driver' => 'file',
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.from.address' => 'hello@iruali.mv',
            'mail.alerts_to' => 'alerts@iruali.mv',
            'services.bml.api_key' => 'test-key',
            'services.bml.webhook_secret' => 'whsec',
            'services.bml.environment' => 'sandbox',
            'backup.backup.name' => 'iruali',
            'backup.backup.destination.disks' => ['backups'],
        ]);
        // The cache check wants a persistent store: file works in tests and keeps the heartbeat written above
        Cache::store('file')->forever('scheduler.heartbeat', now()->toIso8601String());
        config(['cache.default' => 'file']);

        require base_path('routes/console.php'); // registers the queue worker now that the queue is not sync
    }
}
