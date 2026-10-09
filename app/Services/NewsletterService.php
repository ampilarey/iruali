<?php

namespace App\Services;

use App\Jobs\SendNewsletterBatch;
use App\Mail\NewsletterMail;
use App\Models\Brand;
use App\Models\Campaign;
use App\Models\NewsletterDelivery;
use App\Models\NewsletterIssue;
use App\Models\NewsletterSubscriber;
use App\Models\Product;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * The newsletter sender behind Admin → Moderation → Newsletter.
 *
 * Who gets it: confirmed footer subscribers plus customers who have not opted out of marketing
 * emails, one email per address (an address that is both gets the account's language and opt-out
 * link). Staff accounts, inactive or banned accounts, the smoke-test customer and subscribers
 * whose account opted out are left out.
 *
 * Sending ("Send"): the picked products are frozen on the issue, one delivery row per address is
 * written (unique per issue and address), and the rows are queued in batches. A batch claims each
 * row before sending it, so a retried batch never sends an address the same issue twice. Each
 * email is in the reader's language with the signed one-click unsubscribe link.
 */
class NewsletterService
{
    /** Products listed per section. */
    public const SECTION_PRODUCTS = 6;

    /** Brands listed in the featured brands section. */
    public const SECTION_BRANDS = 6;

    // ---- Who gets it --------------------------------------------------------------------------

    /**
     * Customers who get newsletters: not opted out of marketing, active, not banned, not staff and
     * not the smoke-test customer.
     *
     * @return Builder<User>
     */
    public function customers(): Builder
    {
        return User::query()
            ->whereNull('marketing_opt_out_at')
            ->whereNotNull('email')->where('email', '!=', '')
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('banned_until')->orWhere('banned_until', '<=', now()))
            ->where('is_smoke_test', false)
            ->whereDoesntHave('roles', fn (Builder $q) => $q->whereIn('name', (array) config('staff.roles', ['admin'])));
    }

    /**
     * Confirmed footer subscribers, except addresses whose account opted out of marketing emails.
     *
     * @return Builder<NewsletterSubscriber>
     */
    public function subscribers(): Builder
    {
        return NewsletterSubscriber::query()->confirmed()
            ->whereNotIn('email', User::query()->whereNotNull('marketing_opt_out_at')->whereNotNull('email')->select('email'));
    }

    /**
     * How many addresses a send would reach now.
     *
     * @return array{customers: int, subscribers: int, total: int}
     */
    public function audience(): array
    {
        $customers = $this->customers()->count();
        $subscribers = $this->subscribers()->whereNotIn('email', $this->customers()->select('email'))->count();

        return ['customers' => $customers, 'subscribers' => $subscribers, 'total' => $customers + $subscribers];
    }

    // ---- What is in it ------------------------------------------------------------------------

    /**
     * The sections' contents as ids: frozen on the issue once it is sent, picked now for a draft.
     *
     * @return array{new_arrivals?: array{days: int, products: list<int>}, deals?: list<int>, campaign?: array{id: int, products: list<int>}, brands?: list<int>}
     */
    public function content(NewsletterIssue $issue): array
    {
        return $issue->isDraft() || ! is_array($issue->content) ? $this->pick($issue) : $issue->content;
    }

    /**
     * Pick each ticked section's products (on show and in stock) and brands. A product is listed in
     * the first section it fits, so it never shows twice.
     *
     * @return array{new_arrivals?: array{days: int, products: list<int>}, deals?: list<int>, campaign?: array{id: int, products: list<int>}, brands?: list<int>}
     */
    public function pick(NewsletterIssue $issue): array
    {
        $content = [];
        $used = [];
        $cards = fn (array $used) => Product::query()->active()->inStock()->whereNotIn('id', $used)->limit(self::SECTION_PRODUCTS);

        if ($days = $issue->newArrivalDays()) {
            $ids = $cards($used)->whereRaw('COALESCE(approved_at, created_at) >= ?', [now()->subDays($days)])
                ->orderByRaw('COALESCE(approved_at, created_at) DESC')->orderByDesc('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $content['new_arrivals'] = ['days' => $days, 'products' => $ids];
            $used = array_merge($used, $ids);
        }

        if ($issue->wantsDeals()) {
            $ids = $cards($used)->onSale()->orderByRaw('(compare_price - price) / compare_price DESC')->orderByDesc('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $content['deals'] = $ids;
            $used = array_merge($used, $ids);
        }

        $campaign = $issue->campaignId() ? Campaign::query()->live()->find($issue->campaignId()) : null;
        if ($campaign) {
            $ids = $cards($used)->whereHas('campaignParticipations', fn (Builder $q) => $q->where('campaign_id', $campaign->id)->whereNotNull('approved_at'))
                ->latest('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
            $content['campaign'] = ['id' => (int) $campaign->id, 'products' => $ids];
        }

        if ($issue->wantsBrands()) {
            $content['brands'] = app(BrandService::class)->popular(self::SECTION_BRANDS)->pluck('id')->map(fn ($id) => (int) $id)->all();
        }

        return $content;
    }

    /**
     * The records behind the content, loaded once for a whole batch (names and prices are read in
     * each reader's language when an email is rendered). Products gone off sale since are left out.
     *
     * @param  array{new_arrivals?: array{days: int, products: list<int>}, deals?: list<int>, campaign?: array{id: int, products: list<int>}, brands?: list<int>}  $content
     * @return array{new_arrivals: Collection<int, Product>|null, new_arrival_days: int|null, deals: Collection<int, Product>|null, campaign: Campaign|null, campaign_products: Collection<int, Product>, brands: Collection<int, Brand>|null}
     */
    public function sections(array $content): array
    {
        $ids = array_merge($content['new_arrivals']['products'] ?? [], $content['deals'] ?? [], $content['campaign']['products'] ?? []);
        $products = Product::query()->active()->with('mainImage')->whereIn('id', $ids)->get()->keyBy('id');
        $inOrder = fn (array $list) => collect($list)->map(fn (int $id) => $products->get($id))->filter()->values();
        $brands = isset($content['brands']) ? Brand::query()->whereIn('id', $content['brands'])->get()->keyBy('id') : null;

        return [
            'new_arrivals' => isset($content['new_arrivals']) ? $inOrder($content['new_arrivals']['products']) : null,
            'new_arrival_days' => $content['new_arrivals']['days'] ?? null,
            'deals' => isset($content['deals']) ? $inOrder($content['deals']) : null,
            'campaign' => isset($content['campaign']) ? Campaign::query()->find($content['campaign']['id']) : null,
            'campaign_products' => $inOrder($content['campaign']['products'] ?? []),
            'brands' => $brands ? collect($content['brands'])->map(fn (int $id) => $brands->get($id))->filter()->values() : null,
        ];
    }

    /**
     * The email for one reader. $unsubscribe is the signed route that opts them out (generated in
     * the reader's language when the email is rendered); null shows a placeholder (previews).
     *
     * @param  array{route: string, params: array<string, int>}|null  $unsubscribe
     */
    public function mail(NewsletterIssue $issue, ?string $name, ?array $unsubscribe, bool $test = false): NewsletterMail
    {
        return new NewsletterMail($issue, $this->sections($this->content($issue)), $name, $unsubscribe, $test);
    }

    /**
     * "Send me a test": the issue to the admin's own address, in the language asked for.
     */
    public function sendTest(NewsletterIssue $issue, User $to, string $locale): void
    {
        Mail::to($to->email)->locale($locale)->send($this->mail($issue, $to->name, ['route' => 'marketing.unsubscribe', 'params' => ['user' => (int) $to->id]], true));
        $issue->forceFill(['test_sent_at' => now()])->save();
    }

    // ---- Sending ------------------------------------------------------------------------------

    /**
     * "Send": freeze the content, list every address once and queue the batches. Returns how many
     * addresses it goes to, or null when the issue was not a draft (it was sent already).
     */
    public function queue(NewsletterIssue $issue, User $by): ?int
    {
        // Only one "Send" wins, however often it is pressed
        $claimed = NewsletterIssue::query()->whereKey($issue->id)->where('status', 'draft')
            ->update(['status' => 'sending', 'sent_by' => $by->id, 'queued_at' => now(), 'updated_at' => now()]);
        if ($claimed !== 1) {
            return null;
        }

        $issue->refresh();
        try {
            $issue->forceFill(['content' => $this->pick($issue)])->save();
            $recipients = $this->listRecipients($issue);
            $issue->forceFill(['recipients_count' => $recipients])->save();
        } catch (Throwable $e) {
            // Nothing was queued yet: back to a draft that can be sent again
            $issue->deliveries()->delete();
            $issue->forceFill(['status' => 'draft', 'sent_by' => null, 'queued_at' => null, 'content' => null, 'recipients_count' => 0])->save();

            throw $e;
        }

        Audit::record('newsletter.sent', $issue, ['subject' => $issue->subject_en, 'recipients' => $recipients]);

        $this->dispatchBatches($issue);
        $this->finishIfDone($issue);

        return $recipients;
    }

    /**
     * One pending delivery row per address: customers first, so an address that is both gets the
     * account's language and its opt-out link; the unique index drops the second.
     */
    protected function listRecipients(NewsletterIssue $issue): int
    {
        $now = now();
        $row = fn (string $email, string $locale, ?int $userId, ?int $subscriberId) => [
            'newsletter_issue_id' => $issue->id,
            'email' => mb_strtolower(trim($email)),
            'locale' => $locale === 'dv' ? 'dv' : 'en',
            'user_id' => $userId,
            'newsletter_subscriber_id' => $subscriberId,
            'status' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $this->customers()->select(['id', 'email', 'preferred_language'])->chunkById(500, function (Collection $users) use ($row) {
            NewsletterDelivery::query()->insertOrIgnore($users->map(fn (User $user) => $row((string) $user->email, (string) $user->preferred_language, (int) $user->id, null))->all());
        });

        $this->subscribers()->select(['id', 'email', 'locale'])->chunkById(500, function (Collection $subscribers) use ($row) {
            NewsletterDelivery::query()->insertOrIgnore($subscribers->map(fn (NewsletterSubscriber $subscriber) => $row((string) $subscriber->email, (string) $subscriber->locale, null, (int) $subscriber->id))->all());
        });

        return $issue->deliveries()->count();
    }

    /**
     * Queue the pending rows in batches, spaced out (config/newsletter.php): shared hosting limits
     * how many emails go out an hour.
     */
    public function dispatchBatches(NewsletterIssue $issue): int
    {
        $size = max(1, (int) config('newsletter.batch_size', 40));
        $gap = max(0, (int) config('newsletter.minutes_between_batches', 5));
        $batches = 0;

        $issue->deliveries()->where('status', 'pending')->orderBy('id')->pluck('id')
            ->chunk($size)
            ->each(function (Collection $ids) use ($issue, $gap, &$batches) {
                SendNewsletterBatch::dispatch((int) $issue->id, $ids->map(fn ($id) => (int) $id)->values()->all())
                    ->delay(now()->addMinutes($gap * $batches));
                $batches++;
            });

        return $batches;
    }

    /**
     * Send one batch. A row is claimed (pending → sending) before its email goes out; a row that
     * was claimed before (an earlier try of this batch) is left alone, so nobody gets it twice.
     *
     * @param  list<int>  $deliveryIds
     */
    public function sendBatch(NewsletterIssue $issue, array $deliveryIds): void
    {
        $deliveries = $issue->deliveries()->whereIn('id', $deliveryIds)->where('status', 'pending')->orderBy('id')
            ->with(['user', 'subscriber'])->get();
        $sections = $deliveries->isEmpty() ? [] : $this->sections($this->content($issue));

        foreach ($deliveries as $delivery) {
            $claimed = NewsletterDelivery::query()->whereKey($delivery->id)->where('status', 'pending')->update(['status' => 'sending', 'updated_at' => now()]);
            if ($claimed !== 1) {
                continue;
            }

            [$name, $unsubscribe] = $this->reader($delivery);
            if ($unsubscribe === null) {
                $delivery->forceFill(['status' => 'skipped'])->save(); // unsubscribed after the send started

                continue;
            }

            try {
                Mail::to($delivery->email)->locale($delivery->locale)->send(new NewsletterMail($issue, $sections, $name, $unsubscribe));
                $delivery->forceFill(['status' => 'sent', 'sent_at' => now(), 'error' => null])->save();
            } catch (Throwable $e) {
                report($e);
                $delivery->forceFill(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 250, '')])->save();
            }
        }

        $this->finishIfDone($issue);
    }

    /**
     * Who a row is for, checked again at sending time: [name, unsubscribe link], or null for the
     * link when they opted out (or were removed) since the send started.
     *
     * @return array{0: string|null, 1: array{route: string, params: array<string, int>}|null}
     */
    protected function reader(NewsletterDelivery $delivery): array
    {
        if ($delivery->user_id) {
            $user = $delivery->user;
            $still = $user && $user->marketing_opt_out_at === null && $user->isActive();

            return [$user?->name, $still ? ['route' => 'marketing.unsubscribe', 'params' => ['user' => (int) $user->id]] : null];
        }

        $subscriber = $delivery->subscriber;
        $still = $subscriber && $subscriber->isConfirmed();

        return [null, $still ? ['route' => 'newsletter.unsubscribe', 'params' => ['subscriber' => (int) $subscriber->id]] : null];
    }

    /**
     * Update the counts, and mark the issue sent once no row is waiting.
     */
    public function finishIfDone(NewsletterIssue $issue): void
    {
        $counts = $this->counts($issue);
        $done = $counts['pending'] === 0;

        $issue->forceFill([
            'sent_count' => $counts['sent'],
            // Rows still claimed when none wait were cut off mid-send (a crashed try): never sent again
            'failed_count' => $counts['failed'] + ($done ? $counts['sending'] : 0),
        ]);
        if ($done && $issue->status === 'sending') {
            $issue->forceFill(['status' => 'sent', 'sent_at' => now()]);
        }

        $issue->save();
    }

    /**
     * Rows per status, for the issue page.
     *
     * @return array{pending: int, sending: int, sent: int, failed: int, skipped: int}
     */
    public function counts(NewsletterIssue $issue): array
    {
        $byStatus = $issue->deliveries()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return [
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'sending' => (int) ($byStatus['sending'] ?? 0),
            'sent' => (int) ($byStatus['sent'] ?? 0),
            'failed' => (int) ($byStatus['failed'] ?? 0),
            'skipped' => (int) ($byStatus['skipped'] ?? 0),
        ];
    }

    /**
     * "Try the failed ones again" (after the mail server refused some, e.g. an hourly limit):
     * only rows whose sending failed go back in the queue. Returns how many.
     */
    public function retryFailed(NewsletterIssue $issue): int
    {
        if ($issue->isDraft()) {
            return 0;
        }

        $count = $issue->deliveries()->where('status', 'failed')->update(['status' => 'pending', 'error' => null, 'updated_at' => now()]);
        if ($count > 0) {
            $issue->forceFill(['status' => 'sending', 'sent_at' => null])->save();
            $this->dispatchBatches($issue);
            $this->finishIfDone($issue);
        }

        return $count;
    }
}
