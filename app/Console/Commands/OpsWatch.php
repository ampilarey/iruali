<?php

namespace App\Console\Commands;

use App\Support\ErrorTracker;
use App\Support\TeamAlerts;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Every ten minutes: queued jobs that failed since the last check, and a queue worker that has
 * stopped (its heartbeat is over 20 minutes old). The team is told, at most once an hour each, and
 * once more when the worker runs again. A stopped worker's schedule lock is released here, so a
 * worker killed by the host (which leaves its lock behind) starts again on the next minute.
 */
class OpsWatch extends Command
{
    public const STALE_MINUTES = 20;

    protected $signature = 'ops:watch';

    protected $description = 'Alert when queued jobs fail or the queue worker stops';

    public function handle(): int
    {
        $problems = [];

        $lastId = (int) Cache::get('ops.failed_jobs_seen', 0);
        $failed = DB::table('failed_jobs')->where('id', '>', $lastId)->orderBy('id')->get(['id', 'payload', 'exception']);
        if ($failed->isNotEmpty()) {
            Cache::forever('ops.failed_jobs_seen', (int) $failed->max('id'));
            $names = $failed->map(fn ($job) => class_basename((string) (json_decode((string) $job->payload, true)['displayName'] ?? 'job')))->countBy()
                ->map(fn ($count, $name) => $name.($count > 1 ? ' ×'.$count : ''))->implode(', ');
            $problems['failed_jobs'] = $failed->count().' queued job(s) failed: '.$names.'. See failed_jobs / php artisan queue:failed.';
        }

        $beat = Cache::get('queue.heartbeat');
        $stopped = config('queue.default') !== 'sync' && (! $beat || now()->timestamp - (int) $beat > self::STALE_MINUTES * 60);
        if ($stopped) {
            $released = $this->releaseWorkerLock();
            $waiting = $this->waiting();
            $problems['queue_stopped'] = 'The queue worker has not run a job for '.self::STALE_MINUTES.'+ minutes'
                .($waiting ? ' ('.$waiting.')' : '').': emails, SMS and alerts are waiting.'
                .($released ? ' Its schedule lock was left behind and has been released, so it should start again within a minute.' : '')
                .' If this repeats, check that the cron runs php artisan schedule:run every minute.';
            Cache::forever('ops.queue_down', true);
        } elseif (Cache::pull('ops.queue_down')) {
            // It was reported stopped and runs again: say so once
            Cache::forget('ops.alerted.queue_stopped');
            if ($to = ErrorTracker::alertsTo()) {
                Mail::raw('iruali: the queue worker is running again. Waiting emails and SMS are going out.', fn ($m) => $m->to($to)->subject('[iruali] Queue worker running again'));
            }
            $this->info('Queue worker running again.');
        }

        foreach ($problems as $key => $text) {
            if (! Cache::add('ops.alerted.'.$key, true, now()->addHour())) {
                continue;
            }
            if ($to = ErrorTracker::alertsTo()) {
                Mail::raw('iruali: '.$text, fn ($m) => $m->to($to)->subject('[iruali] '.($key === 'queue_stopped' ? 'Queue worker stopped' : 'Queued jobs failed')));
            }
            // Through Telegram straight away: SMS alerts need the queue, which may be what stopped
            TeamAlerts::sendNow('ops_problem', 'iruali: '.$text, route('admin.dashboard'));
        }
        $this->info($problems === [] ? 'All good.' : implode("\n", $problems));

        return self::SUCCESS;
    }

    /**
     * Release the queue-worker's overlap lock when no worker is running it (the heartbeat is stale),
     * which happens when the host kills a worker mid-run. True when there was a lock.
     */
    protected function releaseWorkerLock(): bool
    {
        foreach (app(Schedule::class)->events() as $event) {
            if ($event->description === 'queue-worker' && $event->withoutOverlapping && $event->mutex->exists($event)) {
                $event->mutex->forget($event);

                return true;
            }
        }

        return false;
    }

    /** "12 jobs waiting, the oldest 35 minutes" (the database queue), or null. */
    protected function waiting(): ?string
    {
        if (config('queue.default') !== 'database') {
            return null;
        }
        $table = DB::table((string) config('queue.connections.database.table', 'jobs'));
        $count = (clone $table)->count();
        if ($count === 0) {
            return null;
        }
        $oldest = (int) (clone $table)->min('created_at');

        return $count.' job(s) waiting, the oldest '.max(1, intdiv(now()->timestamp - $oldest, 60)).' minutes';
    }
}
