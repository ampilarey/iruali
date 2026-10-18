<?php

namespace App\Services;

use App\Models\SmsCampaign;
use App\Models\User;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsText;
use App\Support\SmsOptOut;
use App\Support\SmsSegments;
use Illuminate\Support\Facades\DB;

/**
 * Sends SMS campaigns: the message with {name} filled in and a stop link added, to each person in
 * the segment once, through SmsManager (so the all-SMS switch, the monthly limit and quiet hours
 * apply: at night the messages wait until morning).
 */
class SmsCampaignService
{
    public function text(SmsCampaign $campaign, User $user): string
    {
        $first = trim(strtok((string) $user->name, ' ') ?: '');

        return trim(str_replace('{name}', $first, $campaign->message)).' Stop offers: '.SmsOptOut::url($user);
    }

    /**
     * Why the campaign can't go out to this many people now, or null (the monthly limit).
     */
    public function budgetProblem(int $recipients): ?string
    {
        $limit = SmsManager::monthlyLimit();
        if ($limit > 0 && SmsManager::sentThisMonth() + $recipients > $limit) {
            return 'This would go over the monthly SMS limit ('.SmsManager::sentThisMonth().' of '.$limit.' used, '.$recipients.' recipients). Raise the limit under Admin → SMS or choose a smaller group.';
        }

        return null;
    }

    public function send(SmsCampaign $campaign): void
    {
        $campaign->forceFill(['status' => 'sending'])->save();
        $people = SmsSegments::recipients($campaign->segment, $campaign->segment_value);
        $done = DB::table('sms_campaign_recipients')->where('sms_campaign_id', $campaign->id)->pluck('user_id')->flip();
        $sms = app(SmsManager::class);
        [$sent, $failed] = [(int) $campaign->sent_count, (int) $campaign->failed_count];

        foreach ($people as $user) {
            if (isset($done[$user->id])) {
                continue;
            }
            $result = $sms->send($user->phone, new SmsText('campaign', $this->text($campaign, $user)));
            DB::table('sms_campaign_recipients')->insertOrIgnore(['sms_campaign_id' => $campaign->id, 'user_id' => $user->id, 'status' => $result->status, 'created_at' => now()]);
            in_array($result->status, ['sent', 'logged', 'held'], true) ? $sent++ : $failed++;
        }

        $campaign->forceFill([
            'status' => 'sent', 'sent_at' => now(), 'recipients_count' => $sent + $failed, 'sent_count' => $sent, 'failed_count' => $failed,
        ])->save();
        \App\Support\Audit::record('sms.campaign_sent', $campaign, ['sent' => $sent, 'failed' => $failed]);
    }
}
