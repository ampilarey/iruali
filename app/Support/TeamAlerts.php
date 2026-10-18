<?php

namespace App\Support;

use App\Jobs\SendTeamAlert;
use App\Models\Setting;
use App\Models\User;
use App\Services\Telegram;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Alerts to the iruali team (Admin → Team alerts) when something needs a person: by SMS to the staff
 * who chose that event, and to a Telegram group for the events picked there. The business inbox
 * (contact email) keeps getting its emails as before. Sent from the queue, after the database
 * commit, so an alert never slows a customer down or goes out for something that was rolled back.
 */
class TeamAlerts
{
    /** @var array<string, array{0: string, 1: string}> event => [label, when] */
    public const EVENTS = [
        'new_order' => ['New order', 'Any order is placed (card orders before payment, too).'],
        'bank_slip' => ['Bank slip to check', 'A customer uploads a bank-transfer slip.'],
        'ops_problem' => ['Site problem', 'Queued jobs fail or the queue worker stops (checked every 10 minutes).'],
        'gst_due' => ['GST return due', 'Last month\'s GST return is not marked filed yet (on the reminder day).'],
        'late_order' => ['Late orders', 'Paid orders a shop has not started in time, or sent but past their delivery date (hourly check).'],
        'refund_due' => ['Refund due', 'A refund needs to be sent (cancelled paid order, approved return).'],
        'complaint' => ['Complaint or low rating', 'Someone sends the complaint form, or rates an order 1 or 2 out of 5.'],
        'dispute_opened' => ['Dispute opened', 'A customer opens a dispute about a shop.'],
        'return_requested' => ['Return requested', 'A customer asks to return items.'],
        'shop_application' => ['New shop application', 'Someone applies to sell on iruali.'],
        'product_review' => ['Product to review', 'A shop sends a new product or a change for approval.'],
        'payment_error' => ['Payment error', 'Something in the card payment code fails.'],
        'backup_failed' => ['Backup failed', 'The nightly backup did not complete.'],
        'sms_limit' => ['SMS limit reached', 'The monthly SMS limit stops order updates.'],
        'staff_locked' => ['Staff account locked', 'A staff account is locked after repeated wrong passwords or codes.'],
    ];

    /**
     * Tell the team. $url is opened from the Telegram message (and added to SMS when short).
     */
    public static function send(string $event, string $text, ?string $url = null): void
    {
        if (! isset(self::EVENTS[$event])) {
            return;
        }

        try {
            $byTelegram = self::telegramChatId() !== null && in_array($event, self::telegramEvents(), true);
            $bySms = self::smsRecipients($event)->isNotEmpty();
            if (! $byTelegram && ! $bySms) {
                return;
            }

            SendTeamAlert::dispatch($event, $text, $url)->afterCommit();
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Tell the team now, without the queue (for when the queue itself is the problem).
     */
    public static function sendNow(string $event, string $text, ?string $url = null): void
    {
        if (! isset(self::EVENTS[$event])) {
            return;
        }
        try {
            SendTeamAlert::dispatchSync($event, $text, $url);
        } catch (Throwable $e) {
            report($e);
        }
    }

    public static function telegramChatId(): ?string
    {
        $id = trim((string) Setting::get('telegram_chat_id'));

        return Telegram::configured() && $id !== '' ? $id : null;
    }

    /**
     * @return list<string>
     */
    public static function telegramEvents(): array
    {
        return array_values(array_intersect(array_keys(self::EVENTS), array_filter(array_map('trim', explode(',', (string) Setting::get('telegram_events'))))));
    }

    /**
     * Staff who want a text about this event and have a phone.
     *
     * @return Collection<int, User>
     */
    public static function smsRecipients(string $event): Collection
    {
        return self::staff()->filter(fn (User $u) => filled($u->phone) && in_array($event, (array) ($u->team_alerts ?? []), true))->values();
    }

    /**
     * Everyone with a staff role.
     *
     * @return Collection<int, User>
     */
    public static function staff(): Collection
    {
        return User::whereHas('roles', fn ($q) => $q->whereIn('name', (array) config('staff.roles', [])))->orderBy('name')->get();
    }
}
