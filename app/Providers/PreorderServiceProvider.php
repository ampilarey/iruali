<?php

namespace App\Providers;

use App\Models\Product;
use App\Services\PreorderService;
use App\Support\AdminInbox;
use Carbon\CarbonInterface;
use Illuminate\Support\ServiceProvider;

/**
 * Pre-orders (App\Services\PreorderService): the Admin → Inbox row for late pre-orders, and the
 * emails to waiting customers when a shop moves a product's expected date. Routes are in
 * routes/web/preorders.php, the daily late check in routes/console.php.
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

        // The shop moved a product's expected date (product form or Seller Centre → Pre-orders):
        // the pre-orders still waiting take it, and their customers are emailed with a link to cancel
        Product::updated(function (Product $product) {
            if ($product->wasChanged('preorder_ship_date') && $product->preorder_ship_date !== null) {
                $from = $product->getOriginal('preorder_ship_date');
                app(PreorderService::class)->dateMoved($product, $from instanceof CarbonInterface ? $from : null);
            }
        });
    }
}
