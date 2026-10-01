<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockAlert;
use App\Notifications\BackInStock;
use Illuminate\Support\Facades\Notification;
use Throwable;

class StockAlertService
{
    /**
     * Email everyone waiting for this product (not a particular variant), once, when it comes back in stock.
     */
    public function productRestocked(Product $product): void
    {
        $this->notify(StockAlert::where('product_id', $product->id)->whereNull('product_variant_id')->whereNull('notified_at')->get(), $product, null);
    }

    /**
     * Email everyone waiting for this exact variant (size, colour...) when it is restocked.
     */
    public function variantRestocked(ProductVariant $variant): void
    {
        $product = $variant->product;
        if (! $product || ! $product->is_active) {
            return;
        }

        $this->notify(StockAlert::where('product_variant_id', $variant->id)->whereNull('notified_at')->get(), $product, $variant);
    }

    protected function notify($alerts, Product $product, ?ProductVariant $variant): void
    {
        $alerts->each(function (StockAlert $alert) use ($product, $variant) {
            try {
                Notification::route('mail', $alert->email)->notify((new BackInStock($product, $variant))->locale($alert->locale));
                $alert->update(['notified_at' => now()]);
            } catch (Throwable $e) {
                report($e);
            }
        });
    }
}
