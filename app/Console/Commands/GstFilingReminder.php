<?php

namespace App\Console\Commands;

use App\Models\GstPeriod;
use App\Models\Setting;
use App\Support\ErrorTracker;
use App\Support\TeamAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily: on the reminder day (Settings: gst_reminder_day, default the 21st) while last month is not
 * locked as filed, the team is reminded to file the GST return (MIRA, due by the 28th).
 */
class GstFilingReminder extends Command
{
    protected $signature = 'gst:filing-reminder {--force : send whatever the date}';

    protected $description = 'Remind the team to file last month\'s GST return';

    public function handle(): int
    {
        $day = max(1, min(28, (int) Setting::get('gst_reminder_day', 21)));
        $month = now()->subMonthNoOverflow()->format('Y-m');
        if ((! $this->option('force') && now()->day !== $day) || GstPeriod::where('month', $month)->exists()) {
            return self::SUCCESS;
        }

        $url = route('admin.tax.report', ['month' => $month]);
        $text = 'iruali: the GST return for '.now()->subMonthNoOverflow()->format('F Y').' is due by the 28th. Report: '.$url.' (lock the month once filed).';
        if ($to = ErrorTracker::alertsTo()) {
            Mail::raw($text, fn ($m) => $m->to($to)->subject('[iruali] GST return for '.$month.' is due'));
        }
        TeamAlerts::send('gst_due', $text, $url);
        $this->info('Reminder sent for '.$month.'.');

        return self::SUCCESS;
    }
}
