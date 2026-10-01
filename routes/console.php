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

// Abandoned-cart nudges: 3 h and 48 h after a signed-in customer last touched the cart (Settings can switch it off)
Schedule::command('marketing:cart-reminders')->hourly()->withoutOverlapping();

// Guest carts nobody has touched for a month (and their lines) are dropped
Schedule::call(function () {
    \App\Models\Cart::whereNull('user_id')->where('updated_at', '<', now()->subDays(30))
        ->each(fn ($cart) => $cart->delete());
})->dailyAt('03:00')->name('prune-guest-carts');
