<?php

use Illuminate\Support\Facades\Schedule;

// Everything here needs the Laravel scheduler cron on the server:
//   * * * * * cd /path/to/iruali && php artisan schedule:run >> /dev/null 2>&1

// Card orders nobody paid for are cancelled after 24 hours and their stock released
Schedule::command('orders:cancel-unpaid-card')->hourly()->withoutOverlapping(60);
// Bank transfer orders with no transfer slip in time (Admin → Settings → Payment methods)
Schedule::command('orders:cancel-unpaid-transfer')->hourly()->withoutOverlapping(60);

// Database backup every night (config/backup.php says where it goes), old ones cleaned up
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run --only-db')->dailyAt('02:00')->withoutOverlapping(120);

// Once a month, prove the backups restore: newest backup into DB_DRILL_DATABASE, counts compared, report mailed
Schedule::command('backup:restore-drill')->monthlyOn(1, '04:00')->withoutOverlapping(120);

// Expired API tokens
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Abandoned-cart nudges: 3 h and 48 h after a signed-in customer last touched the cart (Settings can switch it off)
Schedule::command('marketing:cart-reminders')->hourly()->withoutOverlapping(60);

// Loyalty points older than Settings → points_expire_months go on the 1st; a warning goes a month ahead
Schedule::command('rewards:expire-points')->monthlyOn(1, '04:00')->withoutOverlapping(120);

// Gift cards past their 12 months are marked expired
Schedule::call(fn () => app(\App\Services\GiftCardService::class)->expireOld())->dailyAt('03:30')->name('expire-gift-cards');

// Bulk quotes past their valid-until day are marked expired (their cart lines go when the cart is next opened)
Schedule::command('quotes:expire')->hourly()->withoutOverlapping(60);

// Paused or hidden shop applications reopen on their reopening day; the waiting list is emailed
Schedule::command('sellers:applications-reopen')->hourly()->withoutOverlapping(60);

// Guest carts nobody has touched for a month (and their lines) are dropped
Schedule::call(function () {
    \App\Models\Cart::whereNull('user_id')->where('updated_at', '<', now()->subDays(30))
        ->each(fn ($cart) => $cart->delete());
})->dailyAt('03:00')->name('prune-guest-carts');

// Queued mail (every notification implements ShouldQueue) is sent by a short-lived worker the
// scheduler starts each minute, so no long-running process is needed on shared hosting.
// With QUEUE_CONNECTION=sync (local, tests) jobs run inline and the worker is not started.
if (config('queue.default') !== 'sync') {
    // A short lock (5 minutes): a worker killed by the host (restart, memory) must not leave the lock
    // behind for Laravel's default 24 hours, which would stop every email and SMS for a day
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping(5)->name('queue-worker');
}
Schedule::command('queue:prune-failed --hours=168')->daily();

// Yesterday's errors (new ones and the most frequent), mailed only when there is something to say
Schedule::command('errors:digest')->dailyAt('07:00')->timezone('Indian/Maldives');

// Loyalty: tiers follow the last 12 months of spending, balances must match the points ledger, and
// birthday vouchers go out in the morning (each only when switched on in Admin → Settings)
Schedule::command('rewards:refresh-tiers')->dailyAt('02:30')->timezone('Indian/Maldives')->withoutOverlapping(120);
Schedule::command('rewards:check-balances')->dailyAt('02:45')->timezone('Indian/Maldives')->withoutOverlapping(120);
Schedule::command('marketing:birthday-offers')->dailyAt('09:30')->timezone('Indian/Maldives')->withoutOverlapping(120);

// Open card payments are checked with BML, in case both the return and the webhook were lost
Schedule::command('payments:reconcile-bml')->everyFiveMinutes()->withoutOverlapping(10)->name('payments-reconcile-bml');

// SMS held back by quiet hours (Admin → SMS) go out when they end; the SMS log keeps 180 days
Schedule::command('sms:release-held')->everyMinute()->withoutOverlapping(5)->name('sms-release-held');
Schedule::command('sms:prune')->dailyAt('03:15')->timezone('Indian/Maldives');

// The ready check (php artisan iruali:ready, admin dashboard) uses this to prove the cron is running
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever('scheduler.heartbeat', now()->toIso8601String()))->everyMinute()->name('heartbeat');

// Shops get one low-stock email a day, in the morning Maldives time (only when something is low)
Schedule::command('seller:low-stock-digest')->dailyAt('08:00')->timezone('Indian/Maldives')->withoutOverlapping(120);

// Shopping funnel rows (Admin → Analytics) are kept for 90 days
Schedule::call(fn () => \App\Services\FunnelService::prune())->dailyAt('03:30')->name('prune-funnel-events');

// Brand followers get one email a day with what those brands put on sale (only when there is news)
Schedule::command('brands:notify-followers')->dailyAt('09:00')->timezone('Indian/Maldives')->withoutOverlapping(120);

// Wishlist price-drop alerts: one email (and push) a day per customer, only when something got cheaper
Schedule::command('wishlist:price-drops')->dailyAt('10:00')->timezone('Indian/Maldives')->withoutOverlapping(120);

// Pre-orders more than a week past their expected date go to Admin → Inbox ("Late pre-orders")
Schedule::command('preorders:flag-late')->dailyAt('06:45')->timezone('Indian/Maldives')->withoutOverlapping(120);

// Pre-orders with a booking fee: a reminder before the balance is due, and overdue balances to the team
Schedule::command('preorders:balance-reminders')->dailyAt('09:40')->timezone('Indian/Maldives')->withoutOverlapping(120);

// "Coming soon" products whose launch day has come go on sale, and those who asked are emailed
Schedule::command('products:launch')->dailyAt('00:05')->timezone('Indian/Maldives')->withoutOverlapping(120);

// Complaints left open without an update go to the team daily; a complaints and ratings summary on Mondays
Schedule::command('complaints:remind')->dailyAt('09:15')->timezone('Indian/Maldives')->withoutOverlapping(120);
Schedule::command('complaints:weekly-summary')->weeklyOn(1, '08:15')->timezone('Indian/Maldives');

// Ordering paused (Admin → Settings → Ordering): those who asked are emailed once it is back
Schedule::command('ordering:notify-back')->everyFiveMinutes()->withoutOverlapping(120)->name('ordering-notify-back');

// Shop parts not started in time after payment, or past their delivery date: the shop and the team are told once
Schedule::command('orders:alert-late')->hourly()->withoutOverlapping(60);

// Yesterday's refunds and those still due, to the alerts inbox every morning
Schedule::command('refunds:daily-summary')->dailyAt('07:45')->timezone('Indian/Maldives');

// Pay later: reminders before an invoice is due, and overdue ones marked and alerted
Schedule::command('credit:reminders')->dailyAt('09:05')->timezone('Indian/Maldives')->withoutOverlapping(120);

// "Frequently bought together" and cart suggestions, from the last year of paid orders
Schedule::command('insights:compute-pairs')->dailyAt('03:45')->timezone('Indian/Maldives')->withoutOverlapping(120);

// SMS campaigns whose time has come (Admin → SMS campaigns)
Schedule::command('sms:send-campaigns')->everyMinute()->withoutOverlapping(120)->name('sms-send-campaigns');

// Approved social posts go out (Admin → Social posts)
Schedule::command('social:publish')->everyFiveMinutes()->withoutOverlapping(120)->name('social-publish');

// Monthly expenses copied into the new month (Admin → Finance)
Schedule::command('finance:recurring-expenses')->monthlyOn(1, '00:30')->timezone('Indian/Maldives');

// GST return reminder (Settings: gst_reminder_day) until last month is locked as filed
Schedule::command('gst:filing-reminder')->dailyAt('09:20')->timezone('Indian/Maldives');

// Every withoutOverlapping() above has its own lock time: a run killed by the host leaves its lock
// behind, and Laravel's default (24 hours) would skip that job for a day.
// The queue worker proves it is alive every 5 minutes; ops:watch alerts on failed jobs or a stopped worker
Schedule::job(new \App\Jobs\QueueHeartbeat)->everyFiveMinutes()->name('queue-heartbeat');
Schedule::command('ops:watch')->everyTenMinutes()->withoutOverlapping(10)->name('ops-watch');
