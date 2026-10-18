<?php

namespace App\Console\Commands;

use App\Support\ErrorTracker;
use App\Support\TeamAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Every ten minutes: queued jobs that failed since the last check, and a queue worker that has
 * stopped (its heartbeat is over 20 minutes old). The team is told, at most once an hour each.
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
        if (config('queue.default') !== 'sync' && (! $beat || now()->timestamp - (int) $beat > self::STALE_MINUTES * 60)) {
            $problems['queue_stopped'] = 'The queue worker has not run a job for '.self::STALE_MINUTES.'+ minutes: emails, SMS and alerts are waiting. Check the queue:work cron.';
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
}
