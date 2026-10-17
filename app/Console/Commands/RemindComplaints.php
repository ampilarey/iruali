<?php

namespace App\Console\Commands;

use App\Mail\ComplaintsDigest;
use App\Models\Complaint;
use App\Support\ErrorTracker;
use App\Support\TeamAlerts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Daily: open complaints nobody has updated for a few days go to the alerts inbox and the team
 * alert, at most once every few days each.
 */
class RemindComplaints extends Command
{
    public const DAYS = 3;

    protected $signature = 'complaints:remind';

    protected $description = 'Remind the team of complaints left open without an update';

    public function handle(): int
    {
        $stale = Complaint::open()->where('updated_at', '<=', now()->subDays(self::DAYS))
            ->where(fn ($q) => $q->whereNull('reminded_at')->orWhere('reminded_at', '<=', now()->subDays(self::DAYS)))
            ->orderBy('created_at')->get();
        if ($stale->isEmpty()) {
            $this->info('No complaints waiting.');

            return self::SUCCESS;
        }

        if ($to = ErrorTracker::alertsTo()) {
            Mail::to($to)->queue(new ComplaintsDigest('stale', ['complaints' => $stale->map(fn (Complaint $c) => [
                'reference' => $c->reference, 'category' => $c->categoryLabel(), 'name' => $c->name,
                'sent' => $c->created_at->format('j M'), 'url' => route('admin.complaints.show', $c),
            ])->all()]));
        }
        TeamAlerts::send('complaint', 'iruali: '.$stale->count().' complaint(s) open for '.self::DAYS.'+ days without an update.', route('admin.complaints'));

        // Not touching updated_at, which is what "no update" is measured by
        Complaint::whereIn('id', $stale->pluck('id'))->toBase()->update(['reminded_at' => now()]);
        $this->info('Reminded about '.$stale->count().' complaint(s).');

        return self::SUCCESS;
    }
}
