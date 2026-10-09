<?php

namespace App\Providers;

use App\Models\Order;
use App\Models\QuoteRequest;
use App\Services\QuoteService;
use App\Support\AdminInbox;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Bulk quotes for businesses (QuoteService; routes in routes/web/quotes.php).
 */
class QuoteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Asking for quotes: 10 an hour per customer; messages in a request's thread: 20 a minute
        RateLimiter::for('quote-requests', fn (Request $request) => Limit::perHour(10)->by('quote-requests:'.($request->user()?->id ?: $request->ip())));
        RateLimiter::for('quote-messages', fn (Request $request) => Limit::perMinute(20)->by('quote-messages:'.($request->user()?->id ?: $request->ip())));

        // Admin inbox: requests no shop has answered for more than two days
        AdminInbox::register('quotes_waiting', fn () => [
            'label' => 'Quote requests waiting more than '.QuoteService::WAITING_DAYS.' days for a shop',
            'count' => QuoteRequest::query()->waitingForShop()->count(),
            'route' => 'admin.quotes',
            'url' => route('admin.quotes', ['status' => 'waiting']),
            'severity' => 'warn',
        ]);

        // An order that was never paid and is cancelled gives its quotes back to the customer
        Order::updated(function (Order $order) {
            if ($order->wasChanged('status') && $order->status === 'cancelled') {
                app(QuoteService::class)->orderCancelled($order);
            }
        });
    }
}
