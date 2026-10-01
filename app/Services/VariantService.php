<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Traits\SecureFileUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a product's variants in step with the rows a shop submitted on the product form.
 */
class VariantService
{
    use SecureFileUpload;

    /**
     * @param  array<int, array>  $rows  validated variants[] rows (see Seller\ProductRequest)
     */
    public function sync(Product $product, array $rows): void
    {
        DB::transaction(function () use ($product, $rows) {
            $existing = $product->variants()->get()->keyBy('id');
            $kept = [];

            foreach (array_values($rows) as $index => $row) {
                $id = (int) ($row['id'] ?? 0);
                $variant = $id ? $existing->get($id) : null;

                if (! empty($row['remove'])) {
                    continue; // handled as a removal below
                }

                $attributes = $this->cleanAttributes(json_decode((string) $row['attributes'], true) ?: []);
                $data = [
                    'attributes' => $attributes,
                    'type' => implode('/', array_keys($attributes)),
                    'name' => ['en' => implode(' / ', array_values($attributes))],
                    'sku' => trim((string) $row['sku']),
                    'price' => isset($row['price']) && $row['price'] !== '' ? round((float) $row['price'], 2) : null,
                    'price_adjustment' => 0,
                    'stock_quantity' => (int) $row['stock_quantity'],
                    'low_stock_threshold' => isset($row['low_stock_threshold']) && $row['low_stock_threshold'] !== '' ? (int) $row['low_stock_threshold'] : null,
                    'is_active' => ! empty($row['is_active']),
                    'sort_order' => $index,
                ];

                if ($variant) {
                    $variant->fill($data);
                } else {
                    $variant = new ProductVariant($data);
                    $variant->product_id = $product->id;
                }

                if (($row['image'] ?? null) instanceof UploadedFile) {
                    $path = $this->storeFileSecurely($row['image'], 'products/variants');
                    if ($path) {
                        if ($variant->image) {
                            $this->deleteFile($variant->image);
                        }
                        $variant->image = $path;
                    }
                }

                $variant->save();
                $kept[$variant->id] = true;
            }

            // Rows the shop removed: keep the ones that were ever ordered (deactivated), drop the rest
            foreach ($existing as $variant) {
                if (! isset($kept[$variant->id])) {
                    $this->remove($variant);
                }
            }

            $product->refresh()->syncStockFromVariants();
        });
    }

    /**
     * Deactivate when there is order history (orders keep their line), delete otherwise.
     */
    public function remove(ProductVariant $variant): void
    {
        if ($variant->hasOrderHistory()) {
            $variant->update(['is_active' => false]);
        } else {
            if ($variant->image) {
                $this->deleteFile($variant->image);
            }
            $variant->delete();
        }
    }

    /**
     * Trim option names and values, drop blanks.
     */
    public function cleanAttributes(array $attributes): array
    {
        $clean = [];
        foreach ($attributes as $key => $value) {
            $key = trim((string) $key);
            $value = is_scalar($value) ? trim((string) $value) : '';
            if ($key !== '' && $value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }

    /**
     * The option types and their values across a product's variants, e.g. ['Size' => ['S','M'], 'Colour' => ['Blue']].
     *
     * @return array<string, array<int, string>>
     */
    public function optionsOf(iterable $variants): array
    {
        $options = [];
        foreach ($variants as $variant) {
            foreach ($variant->attributes_list as $key => $value) {
                $options[$key] ??= [];
                if (! in_array($value, $options[$key], true)) {
                    $options[$key][] = $value;
                }
            }
        }

        return $options;
    }
}
