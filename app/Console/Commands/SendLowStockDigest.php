<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\LowStockDigest;
use Illuminate\Console\Command;
use Throwable;

/**
 * Daily low-stock email per shop: products and variants at or below their threshold (the product's
 * reorder point, or 3 when it has none). Shops with nothing low get no email.
 */
class SendLowStockDigest extends Command
{
    protected $signature = 'seller:low-stock-digest {--seller= : Only this shop (user id)}';

    protected $description = 'Email each shop the products and variants that are running low';

    public function handle(): int
    {
        $sellers = User::where('is_seller', true)->whereNotNull('email')
            ->when($this->option('seller'), fn ($q, $id) => $q->whereKey($id))
            ->get();

        $sent = 0;
        foreach ($sellers as $seller) {
            $lines = $this->linesFor($seller);
            if ($lines === []) {
                continue;
            }

            try {
                $seller->notify(new LowStockDigest($lines));
                $sent++;
            } catch (Throwable $e) {
                report($e);
            }
        }

        $this->info("Low-stock digest sent to {$sent} shop(s).");

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, sku: ?string, stock: int, threshold: int, url: string}>
     */
    public function linesFor(User $seller): array
    {
        $lines = [];

        $products = Product::where('seller_id', $seller->id)->where('is_active', true)->with('variants')->get();
        foreach ($products as $product) {
            $threshold = (int) $product->reorder_point > 0 ? (int) $product->reorder_point : LowStockDigest::DEFAULT_THRESHOLD;
            $name = $product->getTranslation('name', app()->getLocale(), false) ?: $product->getTranslation('name', 'en', false);

            if ($product->variants->isEmpty()) {
                if ((int) $product->stock_quantity <= $threshold) {
                    $lines[] = ['name' => $name, 'sku' => $product->sku, 'stock' => (int) $product->stock_quantity, 'threshold' => $threshold, 'url' => route('seller.products.edit', $product)];
                }

                continue;
            }

            foreach ($product->variants->where('is_active', true) as $variant) {
                /** @var ProductVariant $variant */
                if ((int) $variant->stock_quantity <= $threshold) {
                    $lines[] = ['name' => $name.' – '.$variant->localized_name, 'sku' => $variant->sku, 'stock' => (int) $variant->stock_quantity, 'threshold' => $threshold, 'url' => route('seller.products.edit', $product)];
                }
            }
        }

        return $lines;
    }
}
