<?php

namespace App\Services\Sms;

use App\Mail\SmsLimitReached;
use App\Models\Setting;
use App\Models\SmsMessage;
use App\Support\ErrorTracker;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Sends text messages through the configured driver and keeps a record of every one.
 *
 * Admin → SMS controls (Settings): one switch that stops every SMS; a monthly limit on messages
 * sent (urgent ones such as verification codes still go); quiet hours, during which non-urgent
 * messages are held and sent when they end (sms:release-held, every minute).
 */
class SmsManager
{
    protected ?SmsDriver $driver = null;

    public function driverName(): string
    {
        return config('sms.driver', 'log') === 'http' ? 'http' : 'log';
    }

    /**
     * True when messages really leave the server (a gateway is configured).
     */
    public function isLive(): bool
    {
        return $this->driverName() !== 'log';
    }

    /**
     * The admin switch for all SMS.
     */
    public static function enabled(): bool
    {
        return filter_var(Setting::get('sms_enabled', 1), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Messages a month before non-urgent ones stop (0: no limit).
     */
    public static function monthlyLimit(): int
    {
        return max(0, (int) Setting::get('sms_monthly_limit', 0));
    }

    /**
     * Messages the gateway accepted this month.
     */
    public static function sentThisMonth(): int
    {
        return SmsMessage::where('status', 'sent')->where('created_at', '>=', now()->startOfMonth())->count();
    }

    public static function limitReached(): bool
    {
        $limit = self::monthlyLimit();

        return $limit > 0 && self::sentThisMonth() >= $limit;
    }

    /**
     * When quiet hours are on now, the time they end; otherwise null.
     */
    public static function quietUntil(?Carbon $at = null): ?Carbon
    {
        $from = (string) Setting::get('sms_quiet_from');
        $until = (string) Setting::get('sms_quiet_until');
        if (! preg_match('/^\d{2}:\d{2}$/', $from) || ! preg_match('/^\d{2}:\d{2}$/', $until) || $from === $until) {
            return null;
        }

        $at = ($at ?? now())->copy();
        $time = $at->format('H:i');
        $overnight = $from > $until;
        $quiet = $overnight ? ($time >= $from || $time < $until) : ($time >= $from && $time < $until);
        if (! $quiet) {
            return null;
        }

        $end = $at->copy()->setTimeFromTimeString($until);

        return $end->lte($at) ? $end->addDay() : $end;
    }

    /**
     * @param  string|SmsText  $message  an SmsText carries its type and urgency
     */
    public function send(?string $to, string|SmsText $message, ?string $type = null, bool $urgent = false): SmsResult
    {
        if ($message instanceof SmsText) {
            $type ??= $message->type;
            $urgent = $urgent || $message->urgent;
            $message = $message->text;
        }
        $message = trim($message);
        $number = PhoneNumber::normalize($to);

        if (! $number) {
            return $this->record((string) $to, $message, SmsResult::failed('Not a Maldivian mobile number', 'invalid'), $type);
        }
        if ($message === '') {
            return $this->record($number, $message, SmsResult::failed('Empty message', 'invalid'), $type);
        }
        if (! self::enabled()) {
            return $this->record($number, $message, SmsResult::blocked('All SMS are switched off (Admin → SMS)'), $type);
        }
        if (! $urgent && self::limitReached()) {
            $this->alertLimitReached();

            return $this->record($number, $message, SmsResult::blocked('Monthly limit of '.self::monthlyLimit().' reached (Admin → SMS)'), $type);
        }
        if (! $urgent && ($until = self::quietUntil())) {
            return $this->record($number, $message, SmsResult::held(), $type, $until);
        }

        return $this->record($number, $message, $this->deliver($number, $message), $type);
    }

    /**
     * Send the messages held by quiet hours whose time has come. Returns how many were tried.
     */
    public function releaseHeld(): int
    {
        $count = 0;
        SmsMessage::where('status', 'held')->where('send_after', '<=', now())->orderBy('id')->limit(500)->get()
            ->each(function (SmsMessage $held) use (&$count) {
                $count++;
                $result = match (true) {
                    ! self::enabled() => SmsResult::blocked('All SMS are switched off (Admin → SMS)'),
                    self::limitReached() => SmsResult::blocked('Monthly limit of '.self::monthlyLimit().' reached (Admin → SMS)'),
                    default => $this->deliver($held->to, $held->message),
                };
                $held->forceFill(['status' => $result->status, 'provider_response' => $result->providerResponse, 'cost' => $result->cost])->save();
            });

        return $count;
    }

    /**
     * Try a failed or held message again, now (Admin → SMS → log).
     */
    public function resend(SmsMessage $message): SmsResult
    {
        return $this->send($message->to, new SmsText((string) $message->type, $message->message, true));
    }

    public function driver(): SmsDriver
    {
        return $this->driver ??= match ($this->driverName()) {
            'http' => new HttpSms((array) config('sms.http', [])),
            default => new LogSms,
        };
    }

    /**
     * Use another driver (tests, or a one-off command).
     */
    public function using(?SmsDriver $driver): static
    {
        $this->driver = $driver;

        return $this;
    }

    protected function deliver(string $number, string $message): SmsResult
    {
        try {
            return $this->driver()->send($number, $message, (string) config('sms.sender_id', 'iruali'));
        } catch (Throwable $e) {
            report($e);

            return SmsResult::failed(mb_substr($e->getMessage(), 0, 1000));
        }
    }

    /**
     * Tell the team once a month when the limit stops messages.
     */
    protected function alertLimitReached(): void
    {
        $to = ErrorTracker::alertsTo();
        if (! $to || ! Cache::add('sms-limit-alert:'.now()->format('Y-m'), true, now()->addDays(40))) {
            return;
        }

        try {
            Mail::to($to)->send(new SmsLimitReached(self::sentThisMonth(), self::monthlyLimit()));
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function record(string $to, string $message, SmsResult $result, ?string $type = null, ?Carbon $sendAfter = null): SmsResult
    {
        try {
            SmsMessage::create([
                'type' => $type !== null ? mb_substr($type, 0, 40) : null,
                'to' => mb_substr($to, 0, 30),
                'message' => $message,
                'status' => $result->status,
                'provider_response' => $result->providerResponse,
                'cost' => $result->cost,
                'send_after' => $sendAfter,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }

        return $result;
    }
}
