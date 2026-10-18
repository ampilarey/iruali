<?php

namespace App\Console\Commands;

use App\Models\SmsCampaign;
use App\Services\SmsCampaignService;
use Illuminate\Console\Command;

/**
 * Every minute: SMS campaigns whose time has come go out (Admin → SMS campaigns).
 */
class SendSmsCampaigns extends Command
{
    protected $signature = 'sms:send-campaigns';

    protected $description = 'Send SMS campaigns that are due';

    public function handle(SmsCampaignService $campaigns): int
    {
        SmsCampaign::where('status', 'scheduled')->where('scheduled_at', '<=', now())->orderBy('scheduled_at')->get()
            ->each(function (SmsCampaign $campaign) use ($campaigns) {
                $campaigns->send($campaign);
                $this->info('Sent "'.$campaign->name.'" to '.$campaign->sent_count.' people.');
            });

        return self::SUCCESS;
    }
}
