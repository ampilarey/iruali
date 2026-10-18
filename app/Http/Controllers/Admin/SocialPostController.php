<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\SocialPost;
use App\Services\SocialPublisher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin → Social posts: drafts to approve (new products are drafted automatically when that is
 * switched on), a new post form, what was posted, and the products customers share most.
 */
class SocialPostController extends Controller
{
    public function index()
    {
        return view('admin.social.index', [
            'pending' => SocialPost::with('product')->where('status', 'pending')->latest()->get(),
            'recent' => SocialPost::with('product')->whereIn('status', ['approved', 'posted', 'failed', 'rejected'])->latest()->take(30)->get(),
            'channels' => SocialPublisher::configuredChannels(),
            'autoDrafts' => filter_var(Setting::get('social_auto_drafts', 0), FILTER_VALIDATE_BOOL),
            'telegramChannel' => (string) Setting::get('telegram_channel_id', ''),
            'shared' => DB::table('share_events')->join('products', 'products.id', '=', 'share_events.product_id')
                ->where('share_events.created_at', '>=', now()->subDays(30))
                ->selectRaw('share_events.product_id, count(*) as shares')->groupBy('share_events.product_id')
                ->orderByDesc('shares')->limit(10)->get(),
            'byNetwork' => DB::table('share_events')->where('created_at', '>=', now()->subDays(30))->selectRaw('network, count(*) as shares')->groupBy('network')->pluck('shares', 'network'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        SocialPost::create($data + ['kind' => 'custom', 'status' => 'pending', 'created_by' => $request->user()->id]);

        return back()->with('success', 'Draft saved. Approve it to post.');
    }

    public function update(Request $request, SocialPost $post)
    {
        abort_unless($post->status === 'pending', 404);
        $post->update($this->validated($request));

        return back()->with('success', 'Draft updated.');
    }

    public function approve(Request $request, SocialPost $post)
    {
        abort_unless($post->status === 'pending', 404);
        $data = $request->validate(['scheduled_at' => 'nullable|date|after:now']);
        if (empty($post->channels)) {
            return back()->with('error', 'Choose at least one channel first.');
        }
        $post->forceFill(['status' => 'approved', 'approved_by' => $request->user()->id, 'scheduled_at' => $data['scheduled_at'] ?? now()])->save();
        \App\Support\Audit::record('social.approved', $post, ['channels' => $post->channels]);

        return back()->with('success', ($data['scheduled_at'] ?? null) ? 'Approved; it posts at the time set.' : 'Approved; it posts within 5 minutes.');
    }

    public function reject(SocialPost $post)
    {
        abort_unless($post->status === 'pending', 404);
        $post->forceFill(['status' => 'rejected'])->save();

        return back()->with('success', 'Draft dropped.');
    }

    public function settings(Request $request)
    {
        $data = $request->validate(['social_auto_drafts' => 'required|boolean', 'telegram_channel_id' => ['nullable', 'string', 'max:60', 'regex:/^(@[A-Za-z0-9_]{5,}|-?\d+)$/']]);
        Setting::set(['social_auto_drafts' => (int) $data['social_auto_drafts'], 'telegram_channel_id' => (string) ($data['telegram_channel_id'] ?? '')]);

        return back()->with('success', 'Social settings saved.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'message' => 'required|string|max:2000',
            'link' => 'nullable|url|max:255',
            'image_url' => 'nullable|url|max:255',
            'channels' => 'nullable|array',
            'channels.*' => [Rule::in(array_keys(SocialPost::CHANNELS))],
        ]) + ['channels' => []];
    }
}
