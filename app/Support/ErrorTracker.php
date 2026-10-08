<?php

namespace App\Support;

use App\Mail\PaymentErrorAlert;
use App\Models\ErrorEvent;
use App\Models\Setting;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Error tracking without an external service: every reported exception is counted in
 * error_events (one row per class+file+line), payment errors email an alert at once, and
 * errors:digest mails a daily summary. Registered in bootstrap/app.php; never throws itself.
 *
 * When Sentry is installed and SENTRY_LARAVEL_DSN is set, Sentry does the alerting and the
 * immediate payment alert mail is left to it; the local table is still kept for /admin/errors.
 */
class ErrorTracker
{
    /** Classes whose exceptions count as payment problems and alert immediately. */
    public const PAYMENT_CLASSES = [
        \App\Services\BmlConnect::class,
        \App\Services\PaymentService::class,
        \App\Http\Controllers\Customer\BmlPaymentController::class,
    ];

    public static function record(Throwable $e): void
    {
        try {
            if (self::skip($e)) {
                return;
            }

            $event = self::upsert($e);

            if ($event && self::isPaymentRelated($e) && ! self::sentryActive()) {
                self::alert($event);
            }
        } catch (Throwable) {
            // Tracking must never make a failing request fail harder
        }
    }

    public static function fingerprint(Throwable $e): string
    {
        return sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
    }

    public static function skip(Throwable $e): bool
    {
        if ($e instanceof \Illuminate\Validation\ValidationException
            || $e instanceof AuthenticationException
            || $e instanceof AuthorizationException
            || $e instanceof TokenMismatchException
            || $e instanceof ModelNotFoundException) {
            return true;
        }

        return $e instanceof HttpExceptionInterface && in_array($e->getStatusCode(), [401, 403, 404, 419, 422, 429], true);
    }

    /**
     * Thrown from, or passing through, the payment code (BML client, PaymentService, payment controller).
     */
    public static function isPaymentRelated(Throwable $e): bool
    {
        $classes = array_filter(self::PAYMENT_CLASSES, 'class_exists');

        foreach ([$e, $e->getPrevious()] as $ex) {
            if (! $ex) {
                continue;
            }
            foreach ($classes as $class) {
                $file = (new \ReflectionClass($class))->getFileName();
                if ($file && $ex->getFile() === $file) {
                    return true;
                }
            }
            foreach ($ex->getTrace() as $frame) {
                if (isset($frame['class']) && in_array($frame['class'], $classes, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function sentryActive(): bool
    {
        // sentry/sentry-laravel merges its own config (sentry.dsn reads SENTRY_LARAVEL_DSN), so this also
        // works once the config is cached, which a direct env() call would not
        return class_exists(\Sentry\Laravel\ServiceProvider::class) && filled(config('sentry.dsn'));
    }

    /**
     * Where alerts and the digest go: ALERTS_EMAIL, else the contact email from Admin → Settings.
     */
    public static function alertsTo(): ?string
    {
        $to = trim((string) (config('mail.alerts_to') ?: Setting::get('contact_email')));

        return $to !== '' ? $to : null;
    }

    protected static function upsert(Throwable $e): ?ErrorEvent
    {
        $fingerprint = self::fingerprint($e);
        $request = app()->bound('request') ? app('request') : null;
        $url = $request && ! app()->runningInConsole() ? Str::limit($request->fullUrl(), 990, '') : null;
        $userId = $request?->user()?->id;

        $updated = ErrorEvent::where('fingerprint', $fingerprint)->update([
            'count' => DB::raw('count + 1'),
            'message' => Str::limit($e->getMessage(), 2000),
            'url' => $url,
            'user_id' => $userId,
            'last_seen_at' => now(),
            'resolved_at' => null, // it happened again: back on the unresolved list
            'updated_at' => now(),
        ]);

        if (! $updated) {
            ErrorEvent::create([
                'fingerprint' => $fingerprint,
                'exception_class' => get_class($e),
                'message' => Str::limit($e->getMessage(), 2000),
                'file' => Str::limit($e->getFile(), 500, ''),
                'line' => $e->getLine(),
                'url' => $url,
                'user_id' => $userId,
                'count' => 1,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        }

        return ErrorEvent::where('fingerprint', $fingerprint)->first();
    }

    /** One alert per fingerprint per hour. */
    protected static function alert(ErrorEvent $event): void
    {
        $to = self::alertsTo();
        if (! $to || ! Cache::add('error-alert:'.$event->fingerprint, 1, 3600)) {
            return;
        }

        Mail::to($to)->send(new PaymentErrorAlert($event));
    }
}
