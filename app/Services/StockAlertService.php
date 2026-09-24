<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockAlert;
use App\Notifications\BackInStock;
use Illuminate\Support\Facades\Notification;
use Throwable;

class StockAlertService
{
    /**
     * Email everyone waiting for this product, once, when it comes back in stock.
     */
    public function productRestocked(Product $product): void
    {
        StockAlert::where('product_id', $product->id)->whereNull('notified_at')->get()
            ->each(function (StockAlert $alert) use ($product) {
                try {
                    Notification::route('mail', $alert->email)->notify((new BackInStock($product))->locale($alert->locale));
                    $alert->update(['notified_at' => now()]);
                } catch (Throwable $e) {
                    report($e);
                }
            });
    }
}
