<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Island;
use App\Models\SmsCampaign;
use App\Services\Sms\SmsManager;
use App\Services\Sms\SmsText;
use App\Services\SmsCampaignService;
use App\Support\SmsSegments;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → SMS campaigns: write a message, pick who gets it, see how many people and SMS parts,
 * send yourself a test, then send now or at a set time (sms:send-campaigns). Admins only.
 */
class SmsCampaignController extends Controller
{
    public function index()
    {
        return view('admin.sms.campaigns', [
            'campaigns' => SmsCampaign::latest()->paginate(20),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
            'islands' => Island::where('is_active', true)->orderBy('atoll')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $campaign = SmsCampaign::create($data + ['status' => 'draft', 'created_by' => $request->user()->id]);

        return redirect()->route('admin.sms.campaigns.show', $campaign)->with('success', 'Draft saved. Check the numbers, send yourself a test, then send or schedule it.');
    }

    public function show(SmsCampaign $campaign, SmsCampaignService $campaigns)
    {
        $recipients = $campaign->status === 'sent' ? collect() : SmsSegments::recipients($campaign->segment, $campaign->segment_value);
        $sample = $campaigns->text($campaign, auth()->user());

        return view('admin.sms.campaign', [
            'campaign' => $campaign,
            'recipients' => $recipients->count(),
            'sample' => $sample,
            'parts' => SmsSegments::parts($sample),
            'budgetProblem' => $campaign->isEditable() ? $campaigns->budgetProblem($recipients->count()) : null,
        ]);
    }

    public function test(Request $request, SmsCampaign $campaign, SmsCampaignService $campaigns, SmsManager $sms)
    {
        $user = $request->user();
        if (! $user->phone) {
            return back()->with('error', 'Add a phone number to your account first.');
        }
        $result = $sms->send($user->phone, new SmsText('campaign', $campaigns->text($campaign, $user), true));

        return back()->with($result->status === 'failed' || $result->status === 'invalid' ? 'error' : 'success', 'Test to '.$user->phone.': '.$result->status.'.');
    }

    public function send(Request $request, SmsCampaign $campaign, SmsCampaignService $campaigns)
    {
        abort_unless($campaign->isEditable(), 404);
        $data = $request->validate(['when' => 'required|in:now,later', 'scheduled_at' => 'required_if:when,later|nullable|date|after:now', 'confirm' => 'accepted']);
        $count = SmsSegments::recipients($campaign->segment, $campaign->segment_value)->count();
        if ($count === 0) {
            return back()->with('error', 'Nobody in this group allows offers by SMS.');
        }
        if ($problem = $campaigns->budgetProblem($count)) {
            return back()->with('error', $problem);
        }

        $campaign->forceFill(['status' => 'scheduled', 'scheduled_at' => $data['when'] === 'now' ? now() : $data['scheduled_at']])->save();

        return back()->with('success', $data['when'] === 'now' ? 'Sending now (within a minute).' : 'Scheduled for '.$campaign->scheduled_at->format('j M Y H:i').'.');
    }

    public function cancel(SmsCampaign $campaign)
    {
        abort_unless($campaign->status === 'scheduled', 404);
        $campaign->forceFill(['status' => 'cancelled'])->save();

        return back()->with('success', 'Campaign cancelled.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'message' => 'required|string|max:600',
            'segment' => ['required', Rule::in(array_keys(SmsSegments::SEGMENTS))],
            'segment_value' => [Rule::requiredIf(fn () => (SmsSegments::SEGMENTS[$request->input('segment')][1] ?? null) !== null), 'nullable', 'integer'],
        ]);
        $data['segment_value'] = (SmsSegments::SEGMENTS[$data['segment']][1] ?? null) ? (string) $data['segment_value'] : null;

        return $data;
    }
}
