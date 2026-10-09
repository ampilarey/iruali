<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\NewsletterIssue;
use App\Services\NewsletterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Admin → Moderation → Newsletter: write a newsletter (English and Dhivehi), see a preview, send
 * yourself a test, then send it to everyone (NewsletterService). Admins only: the support role
 * sees the subscriber list but cannot send (config/staff.php).
 */
class NewsletterIssueController extends Controller
{
    public function __construct(protected NewsletterService $newsletters) {}

    public function create()
    {
        return view('admin.newsletter.form', [
            'issue' => new NewsletterIssue(['sections' => ['new_arrivals_days' => NewsletterIssue::DEFAULT_NEW_ARRIVAL_DAYS, 'deals' => true, 'brands' => false]]),
            'campaigns' => $this->campaigns(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $issue = new NewsletterIssue($this->validated($request));
        $issue->created_by = $request->user()->id;
        $issue->save();

        return redirect()->route('admin.newsletter-issues.show', $issue)->with('success', 'Draft saved. Check the preview, send yourself a test, then send it.');
    }

    public function show(NewsletterIssue $issue)
    {
        return view('admin.newsletter.show', [
            'issue' => $issue,
            'audience' => $issue->isDraft() ? $this->newsletters->audience() : null,
            'counts' => $this->newsletters->counts($issue),
            'campaign' => $issue->campaignId() ? Campaign::find($issue->campaignId()) : null,
        ]);
    }

    public function edit(NewsletterIssue $issue)
    {
        abort_unless($issue->isDraft(), 404);

        return view('admin.newsletter.form', ['issue' => $issue, 'campaigns' => $this->campaigns()]);
    }

    public function update(Request $request, NewsletterIssue $issue): RedirectResponse
    {
        abort_unless($issue->isDraft(), 404);

        $issue->update($this->validated($request));

        return redirect()->route('admin.newsletter-issues.show', $issue)->with('success', 'Draft saved.');
    }

    /**
     * The email as a reader gets it, in English or Dhivehi (shown in a frame on the issue page).
     */
    public function preview(Request $request, NewsletterIssue $issue): Response
    {
        $locale = $request->query('lang') === 'dv' ? 'dv' : 'en';
        $html = $this->newsletters->mail($issue, $request->user()->name, null)->locale($locale)->render();

        return response($html)->header('Content-Type', 'text/html; charset=UTF-8');
    }

    /**
     * "Send me a test": to the admin's own address.
     */
    public function test(Request $request, NewsletterIssue $issue): RedirectResponse
    {
        $data = $request->validate(['lang' => ['required', Rule::in(['en', 'dv'])]]);
        $admin = $request->user();

        try {
            $this->newsletters->sendTest($issue, $admin, $data['lang']);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['test' => 'The test could not be sent: '.$e->getMessage()]);
        }

        return back()->with('success', 'Test sent to '.$admin->email.' ('.($data['lang'] === 'dv' ? 'Dhivehi' : 'English').').');
    }

    /**
     * "Send": queue it to every confirmed subscriber and customer who has not opted out.
     */
    public function send(Request $request, NewsletterIssue $issue): RedirectResponse
    {
        if (! $issue->isDraft()) {
            return back()->withErrors(['send' => 'This newsletter has been sent already.']);
        }
        if (! $issue->hasDhivehi()) {
            return back()->withErrors(['send' => 'Add the Dhivehi subject and text first: readers who chose Dhivehi get it in Dhivehi.']);
        }
        if ($this->newsletters->audience()['total'] === 0) {
            return back()->withErrors(['send' => 'Nobody would get it yet: there are no confirmed subscribers or customers who accept marketing emails.']);
        }

        $recipients = $this->newsletters->queue($issue, $request->user());
        if ($recipients === null) {
            return back()->withErrors(['send' => 'This newsletter has been sent already.']);
        }

        return redirect()->route('admin.newsletter-issues.show', $issue)->with('success', "Sending to {$recipients} addresses. The emails go out in batches; this page shows how far it got.");
    }

    /**
     * After the mail server refused some emails (for example an hourly limit): queue just those again.
     */
    public function retry(NewsletterIssue $issue): RedirectResponse
    {
        $count = $this->newsletters->retryFailed($issue);

        return back()->with('success', $count ? "{$count} failed emails are queued again." : 'Nothing to send again.');
    }

    public function destroy(NewsletterIssue $issue): RedirectResponse
    {
        abort_unless($issue->isDraft(), 404);
        $issue->delete();

        return redirect()->route('admin.newsletter')->with('success', 'Draft deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'subject_en' => 'required|string|max:150',
            'subject_dv' => 'nullable|string|max:150',
            'intro_en' => 'required|string|max:5000',
            'intro_dv' => 'nullable|string|max:5000',
            'new_arrivals' => 'nullable|boolean',
            'new_arrivals_days' => 'nullable|required_if:new_arrivals,1|integer|min:1|max:90',
            'deals' => 'nullable|boolean',
            'campaign_id' => ['nullable', 'integer', Rule::exists('campaigns', 'id')],
            'brands' => 'nullable|boolean',
        ], [], [
            'subject_en' => 'English subject',
            'subject_dv' => 'Dhivehi subject',
            'intro_en' => 'English text',
            'intro_dv' => 'Dhivehi text',
            'new_arrivals_days' => 'number of days',
        ]);

        return [
            'subject_en' => $data['subject_en'],
            'subject_dv' => $data['subject_dv'] ?? null,
            'intro_en' => $data['intro_en'],
            'intro_dv' => $data['intro_dv'] ?? null,
            'sections' => [
                'new_arrivals_days' => ! empty($data['new_arrivals']) ? (int) $data['new_arrivals_days'] : null,
                'deals' => ! empty($data['deals']),
                'campaign_id' => isset($data['campaign_id']) ? (int) $data['campaign_id'] : null,
                'brands' => ! empty($data['brands']),
            ],
        ];
    }

    /**
     * Campaigns running now (the only ones a newsletter can feature).
     */
    protected function campaigns()
    {
        return Campaign::query()->live()->ordered()->get();
    }
}
