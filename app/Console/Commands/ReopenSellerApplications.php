<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Services\SellerWaitlistService;
use App\Support\Audit;
use App\Support\SellerApplications;
use Illuminate\Console\Command;

/**
 * Hourly: when the reopening day of paused (or hidden) shop applications has come, store "open"
 * again and email everyone who asked to be told. The site already treats that day as open (see
 * SellerApplications::status()), so a late run only delays the emails.
 */
class ReopenSellerApplications extends Command
{
    protected $signature = 'sellers:applications-reopen';

    protected $description = 'Reopen new shop applications on their reopening day and email the waiting list';

    public function handle(SellerWaitlistService $waitlist): int
    {
        if (SellerApplications::stored() !== SellerApplications::OPEN && SellerApplications::open()) {
            Setting::set(['seller_applications' => SellerApplications::OPEN, 'seller_applications_until' => '']);
            Audit::record('seller_applications.reopened', null, ['by' => 'schedule']);
            $this->info('New shop applications are open again.');
        }

        if (! SellerApplications::open()) {
            $this->line('New shop applications are '.SellerApplications::status().'; nobody was emailed.');

            return self::SUCCESS;
        }

        $told = $waitlist->notifyAll();
        $this->info("Emailed {$told} ".($told === 1 ? 'person' : 'people').' on the waiting list.');

        return self::SUCCESS;
    }
}
