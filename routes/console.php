<?php

use Illuminate\Support\Facades\Schedule;

// Everything here needs the Laravel scheduler cron on the server:
//   * * * * * cd /path/to/iruali && php artisan schedule:run >> /dev/null 2>&1

// Card orders nobody paid for are cancelled after 24 hours and their stock released
Schedule::command('orders:cancel-unpaid-card')->hourly()->withoutOverlapping();

// Database backup every night (config/backup.php says where it goes), old ones cleaned up
Schedule::command('backup:clean')->dailyAt('01:30');
Schedule::command('backup:run --only-db')->dailyAt('02:00')->withoutOverlapping();

// Expired API tokens
Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Guest carts nobody has touched for a month (and their lines) are dropped
Schedule::call(function () {
    \App\Models\Cart::whereNull('user_id')->where('updated_at', '<', now()->subDays(30))
        ->each(fn ($cart) => $cart->delete());
})->dailyAt('03:00')->name('prune-guest-carts');

// Shopping funnel rows (Admin → Analytics) are kept for 90 days
Schedule::call(fn () => \App\Services\FunnelService::prune())->dailyAt('03:30')->name('prune-funnel-events');
