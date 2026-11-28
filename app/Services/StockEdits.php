<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

/**
 * Stock numbers a shop typed into a page (the stock page, the product form), saved so that nothing
 * that happened while the page was open is written over.
 *
 * The page sends each number as it was when the page opened (original) beside the one typed. Only
 * rows the shop changed are saved, and as the difference, under a row lock: a sale (or a cancelled
 * order's units coming back) while the page was open stays counted. More units for a product with
 * pre-orders waiting go to those orders first (PreorderService::receiveStock()). A row with no
 * original is saved as typed (an older page, or an API client that sends only the new number).
 */
class StockEdits
{
    public function __construct(protected PreorderService $preorders) {}

    /**
     * @param  array<int|string, int|string>  $products  product id => stock typed (products without variants)
     * @param  array<int|string, int|string>  $variants  variant id => stock typed
     * @param  array{products?: array<int|string, mixed>, variants?: array<int|string, mixed>}  $original  the same keys, as the page showed them
     * @return int rows changed
     */
    public function apply(array $products, array $variants, array $original, string $source): int
    {
        $changed = 0;
        $arrivals = []; // product id => [variant id, or 0 => units]
        DB::transaction(function () use ($products, $variants, $original, &$changed, &$arrivals) {
            foreach ($products as $id => $stock) {
                $locked = Product::whereKey((int) $id)->lockForUpdate()->first();
                if (! $locked || $locked->has_variants) {
                    continue;
                }
                $delta = (int) $stock - $this->originalOf($original['products'] ?? [], $id, (int) $locked->stock_quantity);
                if ($delta === 0) {
                    continue;
                }
                if ($delta > 0 && $this->preorders->waitingUnits($locked->id) > 0) {
                    $arrivals[$locked->id][0] = $delta;
                } else {
                    $locked->update(['stock_quantity' => max(0, (int) $locked->stock_quantity + $delta)]);
                }
                $changed++;
            }
            foreach ($variants as $id => $stock) {
                $locked = ProductVariant::whereKey((int) $id)->lockForUpdate()->first();
                if (! $locked) {
                    continue;
                }
                $delta = (int) $stock - $this->originalOf($original['variants'] ?? [], $id, (int) $locked->stock_quantity);
                if ($delta === 0) {
                    continue;
                }
                if ($delta > 0 && $this->preorders->waitingUnits((int) $locked->product_id) > 0) {
                    $arrivals[(int) $locked->product_id][(int) $locked->id] = $delta;
                } else {
                    $locked->update(['stock_quantity' => max(0, (int) $locked->stock_quantity + $delta)]);
                }
                $changed++;
            }
        });

        foreach ($arrivals as $productId => $units) {
            if ($product = Product::find($productId)) {
                $this->preorders->receiveStock($product, $units, $source);
            }
        }
        foreach (array_unique(array_merge(array_keys($arrivals), ProductVariant::whereIn('id', array_map('intval', array_keys($variants)))->pluck('product_id')->all())) as $productId) {
            Product::find($productId)?->syncStockFromVariants();
        }

        return $changed;
    }

    /**
     * The product form: save its stock numbers for the product and its existing variants as above,
     * then put today's numbers back into the form, so saving the rest of the form writes no stale
     * stock (ProductEditService::apply(), ProductReviewService::submitForm()).
     *
     * @param  array<string, mixed>  $form  ProductEditService::formFromRequest()
     * @param  array<string, mixed>  $original  the form's original[...] fields
     * @return array<string, mixed>
     */
    public function fromProductForm(Product $product, array $form, array $original, bool $apply = true): array
    {
        $products = [];
        if (! $product->has_variants && empty($form['product']['has_variants']) && array_key_exists('stock_quantity', $form['product'])) {
            $products[$product->id] = (int) $form['product']['stock_quantity'];
        }
        $own = $product->variants()->pluck('id')->map(fn ($id) => (int) $id)->all();
        $variants = [];
        foreach ($form['variants'] as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id && in_array($id, $own, true) && empty($row['remove']) && array_key_exists('stock_quantity', $row)) {
                $variants[$id] = (int) $row['stock_quantity'];
            }
        }
        if ($products === [] && $variants === []) {
            return $form;
        }

        // $apply false: this form's stock change was already saved (the same form sent again); only
        // today's numbers go back into the form
        if ($apply) {
            $this->apply($products, $variants, $original, 'product_form');
        }

        if ($products !== []) {
            $form['product']['stock_quantity'] = (int) Product::whereKey($product->id)->value('stock_quantity');
        }
        $now = ProductVariant::whereIn('id', array_keys($variants))->pluck('stock_quantity', 'id');
        foreach ($form['variants'] as $i => $row) {
            $id = (int) ($row['id'] ?? 0);
            if (isset($variants[$id], $now[$id])) {
                $form['variants'][$i]['stock_quantity'] = (int) $now[$id];
            }
        }

        return $form;
    }

    /** @param  array<int|string, mixed>  $originals */
    protected function originalOf(array $originals, int|string $id, int $live): int
    {
        $value = $originals[$id] ?? null;

        return is_numeric($value) ? (int) $value : $live;
    }
}
