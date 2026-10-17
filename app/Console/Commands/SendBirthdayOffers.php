<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\BirthdayOffer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Daily: customers whose birthday is today (and who have not turned off offers) get a single-use
 * voucher, once a year (Admin → Settings → Loyalty & referrals → Birthday offer).
 */
class SendBirthdayOffers extends Command
{
    protected $signature = 'marketing:birthday-offers';

    protected $description = 'Send birthday vouchers to customers whose birthday is today';

    public function handle(): int
    {
        if (! filter_var(Setting::get('birthday_offer_enabled'), FILTER_VALIDATE_BOOLEAN)) {
            $this->line('Birthday offers are switched off.');

            return self::SUCCESS;
        }

        $today = now();
        $percent = min(100, max(1, (float) Setting::get('birthday_offer_percent', 10)));
        $days = max(1, (int) Setting::get('birthday_offer_days', 7));
        $sent = 0;

        User::whereNotNull('birth_date')
            ->whereNull('marketing_opt_out_at')
            ->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('birthday_offer_year')->orWhere('birthday_offer_year', '<', $today->year))
            ->orderBy('id')
            ->each(function (User $user) use ($today, $percent, $days, &$sent) {
                if (! $user->birth_date || $user->birth_date->format('m-d') !== $today->format('m-d') || $user->isStaff() || $user->isSmokeTest()) {
                    return;
                }
                try {
                    $voucher = Voucher::create([
                        'code' => 'BDAY-'.strtoupper(Str::random(6)),
                        'type' => 'percent',
                        'amount' => $percent,
                        'min_order' => 0,
                        'max_uses' => 1,
                        'used_count' => 0,
                        'valid_from' => now(),
                        'valid_until' => now()->addDays($days)->endOfDay(),
                        'is_active' => true,
                        'user_id' => $user->id,
                    ]);
                    $user->forceFill(['birthday_offer_year' => $today->year])->save();
                    $user->notify(new BirthdayOffer($voucher));
                    $sent++;
                } catch (Throwable $e) {
                    report($e);
                }
            });

        $this->info("Sent {$sent} birthday offer(s).");

        return self::SUCCESS;
    }
}
