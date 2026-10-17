<?php

namespace App\Console\Commands;

use App\Mail\ComplaintsDigest;
use App\Models\Complaint;
use App\Models\OrderFeedback;
use App\Support\ErrorTracker;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Mondays: last week's complaints and order ratings, to the alerts inbox.
 */
class ComplaintsWeeklySummary extends Command
{
    protected $signature = 'complaints:weekly-summary';

    protected $description = 'Email the weekly complaints and ratings summary';

    public function handle(): int
    {
        $to = ErrorTracker::alertsTo();
        if (! $to) {
            $this->warn('No alerts inbox (MAIL_ALERTS_TO or the contact email in Settings).');

            return self::SUCCESS;
        }

        $since = now()->subDays(7);
        $new = Complaint::where('created_at', '>=', $since)->get();
        $ratings = OrderFeedback::where('created_at', '>=', $since)->get();

        Mail::to($to)->queue(new ComplaintsDigest('weekly', [
            'new' => $new->count(),
            'resolved' => Complaint::where('resolved_at', '>=', $since)->count(),
            'open' => Complaint::open()->count(),
            'categories' => $new->groupBy(fn (Complaint $c) => $c->categoryLabel())->map->count()->all(),
            'ratings' => $ratings->count(),
            'average' => round((float) $ratings->avg('rating'), 1),
            'low' => $ratings->where('rating', '<=', 2)->count(),
        ]));
        $this->info('Summary sent.');

        return self::SUCCESS;
    }
}
