<?php

namespace Tests\Feature;

use App\Mail\ErrorDigest;
use App\Mail\PaymentErrorAlert;
use App\Models\ErrorEvent;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Services\BmlConnect;
use App\Support\ErrorTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class ErrorEventsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['mail.alerts_to' => 'alerts@iruali.mv']);
    }

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_reported_exceptions_are_counted_by_fingerprint(): void
    {
        $e = new \RuntimeException('Disk full');
        report($e);
        report($e);
        report(new \LogicException('Something else'));

        $this->assertSame(2, ErrorEvent::count());
        $event = ErrorEvent::where('fingerprint', ErrorTracker::fingerprint($e))->first();
        $this->assertSame(2, $event->count);
        $this->assertSame(\RuntimeException::class, $event->exception_class);
        $this->assertSame('Disk full', $event->message);
        $this->assertSame(__FILE__, $event->file);
        $this->assertNotNull($event->first_seen_at);
        Mail::assertNothingOutgoing();
    }

    public function test_404_validation_and_auth_exceptions_are_ignored(): void
    {
        report(new NotFoundHttpException);
        report(ValidationException::withMessages(['email' => 'bad']));
        report(new \Illuminate\Auth\AuthenticationException);
        report(new \Illuminate\Session\TokenMismatchException);
        $this->get('/no-such-page')->assertNotFound();

        $this->assertSame(0, ErrorEvent::count());
    }

    public function test_a_resolved_error_reopens_when_it_happens_again(): void
    {
        $e = new \RuntimeException('Flaky');
        report($e);
        ErrorEvent::first()->forceFill(['resolved_at' => now()])->save();

        report($e);

        $this->assertNull(ErrorEvent::first()->resolved_at);
        $this->assertSame(2, ErrorEvent::first()->count);
    }

    public function test_payment_errors_alert_at_once_but_only_once_an_hour(): void
    {
        config(['services.bml.api_key' => 'test-key', 'services.bml.base_uri' => 'https://bml.test/public']);
        Http::fake(['bml.test/*' => Http::response('boom', 500)]);

        for ($i = 0; $i < 3; $i++) {
            try {
                app(BmlConnect::class)->createTransaction(['amount' => 100]);
            } catch (\RuntimeException $e) {
                report($e);
            }
        }

        $this->assertSame(1, ErrorEvent::count());
        $this->assertSame(3, ErrorEvent::first()->count);
        Mail::assertQueued(PaymentErrorAlert::class, 1);
        Mail::assertQueued(PaymentErrorAlert::class, fn ($mail) => $mail->hasTo('alerts@iruali.mv') && $mail->event->exception_class === \RuntimeException::class);
    }

    public function test_alerts_fall_back_to_the_contact_email_in_settings(): void
    {
        config(['mail.alerts_to' => null]);
        Setting::set(['contact_email' => 'hello@iruali.mv']);

        $this->assertSame('hello@iruali.mv', ErrorTracker::alertsTo());

        Setting::set(['contact_email' => '']);
        $this->assertNull(ErrorTracker::alertsTo());
    }

    public function test_non_payment_errors_do_not_alert(): void
    {
        report(new \RuntimeException('Image resize failed'));

        Mail::assertNotQueued(PaymentErrorAlert::class);
    }

    public function test_digest_is_sent_only_when_there_is_something_to_report(): void
    {
        $this->artisan('errors:digest')->expectsOutputToContain('Nothing to report')->assertExitCode(0);
        Mail::assertNotQueued(ErrorDigest::class);

        report(new \RuntimeException('Nightly thing broke'));
        ErrorEvent::create([
            'fingerprint' => sha1('old'), 'exception_class' => \LogicException::class, 'message' => 'Old and resolved',
            'count' => 50, 'first_seen_at' => now()->subDays(3), 'last_seen_at' => now()->subDays(2), 'resolved_at' => now()->subDay(),
        ]);

        $this->artisan('errors:digest')->expectsOutputToContain('Digest sent to alerts@iruali.mv')->assertExitCode(0);
        Mail::assertQueued(ErrorDigest::class, fn ($mail) => $mail->new->count() === 1 && $mail->top->count() === 1 && $mail->unresolved === 1
            && str_contains($mail->render(), 'Nightly thing broke') && ! str_contains($mail->render(), 'Old and resolved'));
    }

    public function test_digest_is_scheduled_at_seven_maldives_time(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('errors:digest');
    }

    public function test_admin_can_list_view_and_resolve_errors(): void
    {
        report(new \RuntimeException('Visible in admin'));
        $event = ErrorEvent::first();

        $this->actingAs($this->admin())->get('/admin/errors')
            ->assertOk()->assertSee('Visible in admin')->assertSee('Unresolved (1)')->assertSee('All (1)');

        $this->actingAs($this->admin())->get('/admin/errors/'.$event->id)
            ->assertOk()->assertSee('Visible in admin')->assertSee('Mark resolved')->assertSee($event->fingerprint);

        $this->actingAs($this->admin())->post('/admin/errors/'.$event->id.'/resolve')
            ->assertRedirect('/admin/errors');

        $this->assertNotNull($event->fresh()->resolved_at);
        $this->actingAs($this->admin())->get('/admin/errors')->assertOk()->assertDontSee('Visible in admin')->assertSee('Unresolved (0)');
        $this->actingAs($this->admin())->get('/admin/errors?status=all')->assertOk()->assertSee('Visible in admin')->assertSee('Resolved');
    }

    public function test_customers_cannot_open_the_error_pages(): void
    {
        $this->get('/admin/errors')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/errors')->assertForbidden();
    }

    public function test_tracking_never_throws_even_when_the_database_is_unreachable(): void
    {
        $default = \DB::getDefaultConnection();
        config(['database.connections.broken' => ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'nope', 'username' => 'nope', 'password' => '']]);
        \DB::setDefaultConnection('broken');

        try {
            report(new \RuntimeException('No database'));
        } finally {
            \DB::setDefaultConnection($default);
        }

        $this->assertSame(0, ErrorEvent::count(), 'report() returned without an exception and nothing was stored');
    }
}
