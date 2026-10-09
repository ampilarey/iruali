<?php

use Illuminate\Support\Facades\Schedule;

// Everything here needs the Laravel scheduler cron on the server:
//   * * * * * cd /path/to/iruali && php artisan schedule:run >> /dev/null 2>&1

// Card orders nobody paid for are cancelled after 24 hours and their stock released
Schedule::command('orders:cancel-unpaid-card')->hourly()->withoutOverlapping();

// Database backup every night (config/backup.php says where it goes), old ones cleaned up
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run --only-db')->dailyAt('02:00')->withoutOverlapping();

// Once a month, prove the backups restore: newest backup into DB_DRILL_DATABASE, counts compared, report mailed
Schedule::command('backup:restore-drill')->monthlyOn(1, '04:00')->withoutOverlapping();

// Expired API tokens
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Abandoned-cart nudges: 3 h and 48 h after a signed-in customer last touched the cart (Settings can switch it off)
Schedule::command('marketing:cart-reminders')->hourly()->withoutOverlapping();

// Loyalty points older than Settings → points_expire_months go on the 1st; a warning goes a month ahead
Schedule::command('rewards:expire-points')->monthlyOn(1, '04:00')->withoutOverlapping();

// Gift cards past their 12 months are marked expired
Schedule::call(fn () => app(\App\Services\GiftCardService::class)->expireOld())->dailyAt('03:30')->name('expire-gift-cards');

// Bulk quotes past their valid-until day are marked expired (their cart lines go when the cart is next opened)
Schedule::command('quotes:expire')->hourly()->withoutOverlapping();

// Guest carts nobody has touched for a month (and their lines) are dropped
Schedule::call(function () {
    \App\Models\Cart::whereNull('user_id')->where('updated_at', '<', now()->subDays(30))
        ->each(fn ($cart) => $cart->delete());
})->dailyAt('03:00')->name('prune-guest-carts');

// Queued mail (every notification implements ShouldQueue) is sent by a short-lived worker the
// scheduler starts each minute, so no long-running process is needed on shared hosting.
// With QUEUE_CONNECTION=sync (local, tests) jobs run inline and the worker is not started.
if (config('queue.default') !== 'sync') {
    Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')->everyMinute()->withoutOverlapping()->name('queue-worker');
}
Schedule::command('queue:prune-failed --hours=168')->daily();

// Yesterday's errors (new ones and the most frequent), mailed only when there is something to say
Schedule::command('errors:digest')->dailyAt('07:00')->timezone('Indian/Maldives');

// The ready check (php artisan iruali:ready, admin dashboard) uses this to prove the cron is running
Schedule::call(fn () => \Illuminate\Support\Facades\Cache::forever('scheduler.heartbeat', now()->toIso8601String()))->everyMinute()->name('heartbeat');

// Shops get one low-stock email a day, in the morning Maldives time (only when something is low)
Schedule::command('seller:low-stock-digest')->dailyAt('08:00')->timezone('Indian/Maldives')->withoutOverlapping();

// Shopping funnel rows (Admin → Analytics) are kept for 90 days
Schedule::call(fn () => \App\Services\FunnelService::prune())->dailyAt('03:30')->name('prune-funnel-events');

// Brand followers get one email a day with what those brands put on sale (only when there is news)
Schedule::command('brands:notify-followers')->dailyAt('09:00')->timezone('Indian/Maldives')->withoutOverlapping();

// Wishlist price-drop alerts: one email (and push) a day per customer, only when something got cheaper
Schedule::command('wishlist:price-drops')->dailyAt('10:00')->timezone('Indian/Maldives')->withoutOverlapping();
