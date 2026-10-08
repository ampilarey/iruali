<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Changes a shop applies to many products at once, and product duplication.
 */
class BulkProductService
{
    public const ACTIONS = ['price_percent', 'stock_set', 'activate', 'deactivate'];

    /**
     * Price change by a percentage: rounded to 2 dp, never below 0.01.
     */
    public function adjustedPrice(float $price, float $percent): float
    {
        return max(0.01, round($price * (1 + $percent / 100), 2));
    }

    /**
     * What the action will do to each product (shown on the confirm page).
     *
     * @return array<int, array{product: Product, note: string}>
     */
    public function describe(Collection $products, string $action, ?float $value): array
    {
        return $products->map(function (Product $product) use ($action, $value) {
            $note = match ($action) {
                'price_percent' => $product->has_variants
                    ? __(':from → :to (and each variant with its own price)', ['from' => number_format((float) $product->price, 2), 'to' => number_format($this->adjustedPrice((float) $product->price, $value), 2)])
                    : __(':from → :to', ['from' => number_format((float) $product->price, 2), 'to' => number_format($this->adjustedPrice((float) $product->price, $value), 2)]),
                'stock_set' => $product->has_variants
                    ? __('each active variant set to :n', ['n' => (int) $value])
                    : __(':from → :to', ['from' => $product->stock_quantity, 'to' => (int) $value]),
                'activate' => $product->isApproved() ? __('activated') : __('stays pending until an admin approves it'),
                'deactivate' => __('deactivated'),
                default => throw new \InvalidArgumentException('Unknown bulk action: '.$action),
            };

            return ['product' => $product, 'note' => $note];
        })->all();
    }

    /**
     * Apply the action to the products (and their variants where it applies). Returns how many products changed.
     */
    public function apply(Collection $products, string $action, ?float $value): int
    {
        return DB::transaction(function () use ($products, $action, $value) {
            $count = 0;
            foreach ($products as $product) {
                match ($action) {
                    'price_percent' => $this->changePrice($product, (float) $value),
                    'stock_set' => $this->setStock($product, (int) $value),
                    'activate' => $product->update(['is_active' => $product->isApproved()]),
                    'deactivate' => $product->update(['is_active' => false]),
                    default => throw new \InvalidArgumentException('Unknown bulk action: '.$action),
                };
                $count++;
            }

            return $count;
        });
    }

    protected function changePrice(Product $product, float $percent): void
    {
        $product->update(['price' => $this->adjustedPrice((float) $product->price, $percent)]);

        // Variants with their own price move by the same percentage; the others follow the product's price
        if ($product->has_variants) {
            foreach ($product->variants()->whereNotNull('price')->get() as $variant) {
                $variant->update(['price' => $this->adjustedPrice((float) $variant->price, $percent)]);
            }
        }
    }

    protected function setStock(Product $product, int $stock): void
    {
        if ($product->has_variants) {
            foreach ($product->variants()->where('is_active', true)->get() as $variant) {
                $variant->update(['stock_quantity' => $stock]);
            }
            $product->syncStockFromVariants();
        } else {
            $product->update(['stock_quantity' => $stock]);
        }
    }

    /**
     * A copy of the product: "(copy)" on the names, a fresh slug and SKU, the same image files,
     * variants with stock 0, inactive until an admin approves it.
     */
    public function duplicate(Product $product): Product
    {
        return DB::transaction(function () use ($product) {
            $copy = $product->replicate(['slug', 'search_text', 'approved_at', 'sponsored_until', 'is_sponsored', 'is_featured']);
            foreach ($product->getTranslations('name') as $locale => $name) {
                $copy->setTranslation('name', $locale, $name.' '.__('(copy)'));
            }
            $copy->sku = $this->freeSku($product->sku); // the slug was left out above, so a new one is made on create
            $copy->is_active = false;
            $copy->approved_at = null;
            $copy->save();

            foreach ($product->images()->orderBy('sort_order')->get() as $image) {
                $copy->images()->create($image->only(['url', 'alt_text', 'is_main', 'sort_order']));
            }

            foreach ($product->variants()->ordered()->get() as $variant) {
                $clone = $variant->replicate();
                $clone->product_id = $copy->id;
                $clone->sku = $this->freeSku($variant->sku);
                $clone->stock_quantity = 0;
                $clone->save();
            }

            $copy->syncStockFromVariants();

            return $copy;
        });
    }

    /**
     * "<sku>-COPY", or "-COPY2", "-COPY3"... until it is free of both products and variants.
     */
    protected function freeSku(string $sku): string
    {
        $base = mb_substr($sku, 0, 90).'-COPY';
        $candidate = $base;
        $n = 1;
        while (Product::withTrashed()->where('sku', $candidate)->exists() || ProductVariant::where('sku', $candidate)->exists()) {
            $candidate = $base.(++$n);
        }

        return $candidate;
    }
}
