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

    /** Statuses that count as delivered to the gateway (or held for the morning). */
    public const OK = ['sent', 'logged', 'held'];

    /**
     * Send the campaign to everyone in its segment who hasn't had it yet. Safe to run again: a run
     * that stopped half way (the host ended it, a deploy) carries on where it stopped
     * (sms_campaign_recipients), and the next minute's run picks up a campaign left "sending".
     */
    public function send(SmsCampaign $campaign): void
    {
        $campaign->forceFill(['status' => 'sending'])->save();
        $people = SmsSegments::recipients($campaign->segment, $campaign->segment_value);
        $done = DB::table('sms_campaign_recipients')->where('sms_campaign_id', $campaign->id)->pluck('user_id')->flip();
        $sms = app(SmsManager::class);

        $count = 0;
        foreach ($people as $user) {
            if (isset($done[$user->id])) {
                continue;
            }
            // Claimed before it is sent (the row is unique per person): a second run going at the same time
            // (the first one still sending when its lock ran out) skips everyone the first has taken
            if (DB::table('sms_campaign_recipients')->insertOrIgnore(['sms_campaign_id' => $campaign->id, 'user_id' => $user->id, 'status' => 'sending', 'created_at' => now()]) === 0) {
                continue;
            }
            $result = $sms->send($user->phone, new SmsText('campaign', $this->text($campaign, $user)));
            DB::table('sms_campaign_recipients')->where('sms_campaign_id', $campaign->id)->where('user_id', $user->id)->update(['status' => $result->status]);
            if (++$count % 50 === 0) {
                $campaign->touch(); // still going: the stuck-campaign check looks at this
            }
        }

        // Counted from what was recorded, so a run that carried on from an earlier one counts everyone
        $statuses = DB::table('sms_campaign_recipients')->where('sms_campaign_id', $campaign->id)->pluck('status');
        $sent = $statuses->filter(fn ($status) => in_array($status, self::OK, true))->count();
        $failed = $statuses->count() - $sent;
        $campaign->forceFill([
            'status' => 'sent', 'sent_at' => now(), 'recipients_count' => $sent + $failed, 'sent_count' => $sent, 'failed_count' => $failed,
        ])->save();
        \App\Support\Audit::record('sms.campaign_sent', $campaign, ['sent' => $sent, 'failed' => $failed]);
    }
}
