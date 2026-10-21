<?php

namespace App\Providers;

use App\Models\Product;
use App\Services\BookingFeeService;
use App\Services\PreorderConfirmService;
use App\Services\PreorderService;
use App\Support\AdminInbox;
use Carbon\CarbonInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Pre-orders (App\Services\PreorderService): the Admin → Inbox rows for late pre-orders, for
 * booking-fee balances overdue (BookingFeeService) and for pre-orders waiting for their shop to
 * confirm (PreorderConfirmService), and the emails to waiting customers when a shop moves a product's
 * expected date. Routes are in routes/web/preorders.php, the daily late check and payment reminders
 * in routes/console.php.
 */
class PreorderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Admin inbox: orders with a pre-order more than a week past its date (flagged daily by preorders:flag-late)
        AdminInbox::register('late_preorders', fn () => [
            'label' => 'Late pre-orders',
            'count' => app(PreorderService::class)->lateOrderCount(),
            'route' => 'admin.preorders',
            'severity' => 'warn',
        ]);

        // Pre-orders with a booking fee whose balance is past its due date (BookingFeeService)
        AdminInbox::register('booking_balances_overdue', fn () => [
            'label' => 'Pre-order balances overdue',
            'count' => app(BookingFeeService::class)->overdueCount(),
            'route' => 'admin.preorders',
            'severity' => 'danger',
        ]);

        // Pre-orders paid once the shop confirms, waiting for the shop to say it can get the item (PreorderConfirmService)
        AdminInbox::register('preorders_to_confirm', fn () => [
            'label' => 'Pre-orders to confirm',
            'count' => app(PreorderConfirmService::class)->waitingCount(),
            'route' => 'admin.preorders',
            'severity' => 'warn',
        ]);

        // The shop moved a product's expected date (product form or Seller Centre → Pre-orders):
        // the pre-orders still waiting take it, and their customers are emailed with a link to cancel.
        // Not when the product ships some days after each order, or switches to or from that.
        Product::updated(function (Product $product) {
            if ($product->wasChanged('preorder_ship_date') && $product->preorder_ship_date !== null
                && ! $product->preorder_lead_days && ! $product->getOriginal('preorder_lead_days')) {
                $from = $product->getOriginal('preorder_ship_date');
                app(PreorderService::class)->dateMoved($product, $from instanceof CarbonInterface ? $from : null);
            }
        });
    }
}
