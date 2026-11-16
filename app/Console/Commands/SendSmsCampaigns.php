<?php

namespace App\Console\Commands;

use App\Models\SmsCampaign;
use App\Services\SmsCampaignService;
use Illuminate\Console\Command;

/**
 * Every minute: SMS campaigns whose time has come go out (Admin → SMS campaigns). A campaign left
 * "sending" (its run was stopped half way) carries on; one with no progress for an hour is reported.
 */
class SendSmsCampaigns extends Command
{
    protected $signature = 'sms:send-campaigns';

    protected $description = 'Send SMS campaigns that are due';

    public function handle(SmsCampaignService $campaigns): int
    {
        // A run that stopped half way left its campaign "sending": tell the team once if it has not moved for an hour
        SmsCampaign::where('status', 'sending')->where('updated_at', '<', now()->subHour())->get()
            ->each(function (SmsCampaign $campaign) {
                if (\Illuminate\Support\Facades\Cache::add('sms-campaign-stuck:'.$campaign->id, true, now()->addDay())) {
                    \App\Support\TeamAlerts::send('ops_problem', 'iruali: SMS campaign "'.$campaign->name.'" has been sending for over an hour without progress. It carries on by itself; check Admin → SMS if it does not finish.', route('admin.sms.campaigns'));
                }
            });

        SmsCampaign::where(fn ($q) => $q->where('status', 'scheduled')->where('scheduled_at', '<=', now())->orWhere('status', 'sending'))
            ->orderBy('scheduled_at')->get()
            ->each(function (SmsCampaign $campaign) use ($campaigns) {
                $campaigns->send($campaign);
                $this->info('Sent "'.$campaign->name.'" to '.$campaign->sent_count.' people.');
            });

        return self::SUCCESS;
    }
}
