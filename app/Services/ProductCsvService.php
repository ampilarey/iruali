<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A shop's catalogue as CSV: one row per variant, or per product when it has none.
 *
 * Rows are matched to the shop's products and variants by SKU. A row with an unknown SKU creates
 * a product (pending approval) or, when it carries variant_attributes, a variant of the product
 * named by product_sku (created from the same row when it is new too).
 */
class ProductCsvService
{
    public const COLUMNS = [
        'sku', 'product_sku', 'product_name_en', 'product_name_dv', 'category_slug', 'brand', 'price', 'sale_price',
        'stock', 'variant_attributes', 'is_active', 'description_en', 'description_dv', 'image_urls',
    ];

    public const MAX_ROWS = 2000;

    public const UPDATABLE = ['name', 'description', 'price', 'sale_price', 'stock', 'is_active', 'brand'];

    // ---- Export ------------------------------------------------------------------------------

    public function export(User $seller): string
    {
        $products = $seller->products()->with(['category', 'variants' => fn ($q) => $q->ordered(), 'images'])->orderBy('id')->get();

        $rows = [];
        foreach ($products as $product) {
            $images = $product->getRelation('images')->sortByDesc('is_main')->pluck('url')->filter()->implode('|');
            $base = [
                'product_sku' => $product->sku,
                'product_name_en' => $product->getTranslation('name', 'en', false),
                'product_name_dv' => $product->getTranslation('name', 'dv', false),
                'category_slug' => $product->category?->slug,
                'brand' => $product->brand,
                'description_en' => $product->getTranslation('description', 'en', false),
                'description_dv' => $product->getTranslation('description', 'dv', false),
            ];

            if ($product->has_variants && $product->variants->isNotEmpty()) {
                foreach ($product->variants as $variant) {
                    $variant->setRelation('product', $product);
                    $rows[] = $base + [
                        'sku' => $variant->sku,
                        'price' => $this->money($variant->effectivePrice()),
                        'sale_price' => '',
                        'stock' => $variant->stock_quantity,
                        'variant_attributes' => $this->attributesToString($variant->attributes_list),
                        'is_active' => $variant->is_active ? '1' : '0',
                        'image_urls' => $variant->image ? \Illuminate\Support\Facades\Storage::url($variant->image) : $images,
                    ];
                }
            } else {
                $rows[] = $base + [
                    'sku' => $product->sku,
                    'price' => $this->money($product->price),
                    'sale_price' => $product->sale_price !== null ? $this->money($product->sale_price) : '',
                    'stock' => $product->stock_quantity,
                    'variant_attributes' => '',
                    'is_active' => $product->is_active ? '1' : '0',
                    'image_urls' => $images,
                ];
            }
        }

        return $this->toCsv($rows);
    }

    public function sample(): string
    {
        return $this->toCsv([
            ['sku' => 'TEE-001', 'product_sku' => '', 'product_name_en' => 'Reef T-shirt', 'product_name_dv' => 'ރީފް ޓީޝާޓު', 'category_slug' => 'clothing', 'brand' => 'Iruali', 'price' => '150.00', 'sale_price' => '', 'stock' => '20', 'variant_attributes' => '', 'is_active' => '1', 'description_en' => 'Soft cotton tee.', 'description_dv' => '', 'image_urls' => ''],
            ['sku' => 'HOOD-001-M', 'product_sku' => 'HOOD-001', 'product_name_en' => 'Island Hoodie', 'product_name_dv' => '', 'category_slug' => 'clothing', 'brand' => 'Iruali', 'price' => '300.00', 'sale_price' => '', 'stock' => '5', 'variant_attributes' => 'Size=M;Colour=Blue', 'is_active' => '1', 'description_en' => 'Warm hoodie.', 'description_dv' => '', 'image_urls' => ''],
            ['sku' => 'HOOD-001-L', 'product_sku' => 'HOOD-001', 'product_name_en' => 'Island Hoodie', 'product_name_dv' => '', 'category_slug' => 'clothing', 'brand' => 'Iruali', 'price' => '320.00', 'sale_price' => '', 'stock' => '3', 'variant_attributes' => 'Size=L;Colour=Blue', 'is_active' => '1', 'description_en' => 'Warm hoodie.', 'description_dv' => '', 'image_urls' => ''],
        ]);
    }

    protected function toCsv(array $rows): string
    {
        // UTF-8 BOM so Excel opens Dhivehi correctly; every value quoted so the file is predictable to edit
        $quote = fn ($value) => '"'.str_replace('"', '""', (string) $value).'"';
        $csv = "\xEF\xBB\xBF".implode(',', self::COLUMNS)."\n";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(fn ($column) => $quote($row[$column] ?? ''), self::COLUMNS))."\n";
        }

        return $csv;
    }

    protected function money(float|int|string|null $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    public function attributesToString(array $attributes): string
    {
        return implode(';', array_map(fn ($k, $v) => $k.'='.$v, array_keys($attributes), $attributes));
    }

    /**
     * "Size=M;Colour=Blue" or a JSON object -> ['Size' => 'M', 'Colour' => 'Blue']; null when malformed.
     */
    public function parseAttributes(string $text): ?array
    {
        $text = trim($text);
        if ($text === '') {
            return [];
        }

        if (str_starts_with($text, '{')) {
            $decoded = json_decode($text, true);

            return is_array($decoded) ? app(VariantService::class)->cleanAttributes($decoded) : null;
        }

        $attributes = [];
        foreach (explode(';', $text) as $pair) {
            if (trim($pair) === '') {
                continue;
            }
            if (! str_contains($pair, '=')) {
                return null;
            }
            [$key, $value] = array_map('trim', explode('=', $pair, 2));
            if ($key === '' || $value === '') {
                return null;
            }
            $attributes[$key] = $value;
        }

        return count($attributes) > 3 ? null : $attributes;
    }

    // ---- Parse ------------------------------------------------------------------------------

    /**
     * Read a CSV into rows keyed by column name, each with its line number.
     *
     * @return array{rows: array<int, array>, errors: array<int, string>}
     */
    public function parse(string $contents): array
    {
        $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle, 0, ',', '"', '');
        if (! $header) {
            return ['rows' => [], 'errors' => [__('The file is empty.')]];
        }
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        $missing = array_diff(['sku'], $header);
        if ($missing) {
            return ['rows' => [], 'errors' => [__('The file needs a "sku" column. Download the sample CSV to see the format.')]];
        }

        $rows = [];
        $line = 1;
        while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $line++;
            if ($values === [null] || implode('', array_map('trim', array_map('strval', $values))) === '') {
                continue; // blank line
            }
            if (count($rows) >= self::MAX_ROWS) {
                fclose($handle);

                return ['rows' => [], 'errors' => [__('The file has more than :max rows. Split it into smaller files.', ['max' => self::MAX_ROWS])]];
            }
            $row = ['line' => $line];
            foreach (self::COLUMNS as $column) {
                $index = array_search($column, $header, true);
                $row[$column] = $index === false ? null : trim((string) ($values[$index] ?? ''));
            }
            $rows[] = $row;
        }
        fclose($handle);

        return ['rows' => $rows, 'errors' => []];
    }

    // ---- Dry run ----------------------------------------------------------------------------

    /**
     * What applying these rows would do, row by row: create / update / error, with the changes.
     *
     * @return array{rows: array<int, array>, counts: array{create: int, update: int, error: int}}
     */
    public function preview(User $seller, array $rows): array
    {
        $products = $seller->products()->with('variants')->get();
        $bySku = $products->keyBy(fn ($p) => mb_strtolower($p->sku));
        $variantsBySku = $products->flatMap(fn ($p) => $p->variants->each->setRelation('product', $p))->keyBy(fn ($v) => mb_strtolower($v->sku));
        $categories = Category::pluck('id', 'slug');
        $ownImages = $products->isEmpty() ? collect() : ProductImage::whereIn('product_id', $products->pluck('id'))->pluck('url')->unique()->flip();

        $seen = [];          // sku => line, within the file
        $newProducts = [];   // product_sku => line, products this file creates
        $out = [];
        $counts = ['create' => 0, 'update' => 0, 'error' => 0];

        foreach ($rows as $row) {
            $result = ['line' => $row['line'], 'sku' => $row['sku'], 'name' => $row['product_name_en'], 'action' => 'error', 'changes' => [], 'errors' => []];
            $sku = (string) $row['sku'];
            $key = mb_strtolower($sku);

            if ($sku === '') {
                $result['errors'][] = __('SKU is missing.');
            } elseif (mb_strlen($sku) > 100) {
                $result['errors'][] = __('SKU is longer than 100 characters.');
            } elseif (isset($seen[$key])) {
                $result['errors'][] = __('SKU repeats line :line.', ['line' => $seen[$key]]);
            }
            $seen[$key] = $row['line'];

            $attributes = $this->parseAttributes((string) $row['variant_attributes']);
            if ($attributes === null) {
                $result['errors'][] = __('variant_attributes must look like "Size=M;Colour=Blue" (up to 3 options).');
            }
            $price = $this->number($row['price'], 0.01, 999999.99);
            $salePrice = $this->number($row['sale_price'], 0.01, 999999.99);
            $stock = $this->integer($row['stock'], 0, 999999);
            $active = $this->flag($row['is_active']);
            foreach ([['price', $row['price'], $price], ['sale_price', $row['sale_price'], $salePrice]] as [$column, $raw, $parsed]) {
                if ($raw !== null && $raw !== '' && $parsed === null) {
                    $result['errors'][] = __(':column must be a number between 0.01 and 999999.99.', ['column' => $column]);
                }
            }
            if ($row['stock'] !== null && $row['stock'] !== '' && $stock === null) {
                $result['errors'][] = __('stock must be a whole number from 0 to 999999.');
            }
            if ($row['is_active'] !== null && $row['is_active'] !== '' && $active === null) {
                $result['errors'][] = __('is_active must be 1 or 0.');
            }
            if ($result['errors']) {
                $counts['error']++;
                $out[] = $result;

                continue;
            }

            $product = $bySku->get($key);
            $variant = $variantsBySku->get($key);

            if ($product) {
                $result['action'] = 'update';
                $result['name'] = $product->getTranslation('name', 'en', false);
                $result['changes'] = $this->productChanges($product, $row, $price, $salePrice, $stock, $active);
            } elseif ($variant) {
                $result['action'] = 'update';
                $result['name'] = $variant->product->getTranslation('name', 'en', false).' – '.$variant->displayName();
                $result['changes'] = $this->variantChanges($variant, $price, $stock, $active);
            } elseif ($this->skuTakenElsewhere($sku, $seller)) {
                $result['errors'][] = __('This SKU belongs to another shop.');
            } elseif ($attributes !== []) {
                // A new variant: of an existing product, or of one created earlier in this file
                $parentKey = mb_strtolower((string) $row['product_sku']);
                $parent = $parentKey !== '' ? $bySku->get($parentKey) : null;
                if ($parentKey === '') {
                    $result['errors'][] = __('A variant row needs product_sku (the SKU of the product it belongs to).');
                } elseif (! $parent && ! isset($newProducts[$parentKey])) {
                    $errors = $this->creationErrors($row, $categories, $price);
                    if ($this->skuTakenElsewhere($row['product_sku'], $seller)) {
                        $errors[] = __('product_sku ":sku" belongs to another shop.', ['sku' => $row['product_sku']]);
                    }
                    if ($errors) {
                        $result['errors'] = $errors;
                    } else {
                        $newProducts[$parentKey] = $row['line'];
                        $result['action'] = 'create';
                        $result['changes'] = [__('New product ":name" with variant :variant', ['name' => $row['product_name_en'], 'variant' => $this->attributesToString($attributes)])];
                    }
                } elseif ($parent && ! $parent->has_variants) {
                    $result['errors'][] = __('Product ":sku" is not sold in variants. Turn variants on in its product form first.', ['sku' => $parent->sku]);
                } else {
                    if ($price === null) {
                        $result['errors'][] = __('A new variant needs a price.');
                    } else {
                        $result['action'] = 'create';
                        $result['name'] = ($parent ? $parent->getTranslation('name', 'en', false) : $row['product_name_en']).' – '.implode(' / ', array_values($attributes));
                        $result['changes'] = [__('New variant :variant, price :price, stock :stock', ['variant' => $this->attributesToString($attributes), 'price' => $this->money($price), 'stock' => $stock ?? 0])];
                    }
                }
            } else {
                $errors = $this->creationErrors($row, $categories, $price);
                if ($errors) {
                    $result['errors'] = $errors;
                } else {
                    $result['action'] = 'create';
                    $images = $this->ownImageUrls($row['image_urls'], $ownImages);
                    $result['changes'] = [__('New product, price :price, stock :stock (pending approval)', ['price' => $this->money($price), 'stock' => $stock ?? 0])];
                    if ($images) {
                        $result['changes'][] = trans_choice(':count image reused|:count images reused', count($images), ['count' => count($images)]);
                    }
                }
            }

            if ($result['errors']) {
                $result['action'] = 'error';
            }
            $counts[$result['action']]++;
            $out[] = $result;
        }

        return ['rows' => $out, 'counts' => $counts];
    }

    // ---- Apply ------------------------------------------------------------------------------

    /**
     * Apply the rows that the dry run found valid. Returns the same counts as preview().
     */
    public function apply(User $seller, array $rows): array
    {
        $preview = $this->preview($seller, $rows);
        $valid = collect($preview['rows'])->where('action', '!=', 'error')->pluck('line')->flip();
        $rows = array_values(array_filter($rows, fn ($row) => isset($valid[$row['line']])));

        DB::transaction(function () use ($seller, $rows) {
            $products = $seller->products()->with('variants')->get();
            $bySku = $products->keyBy(fn ($p) => mb_strtolower($p->sku));
            $variantsBySku = $products->flatMap(fn ($p) => $p->variants->each->setRelation('product', $p))->keyBy(fn ($v) => mb_strtolower($v->sku));
            $categories = Category::pluck('id', 'slug');
            $ownImages = $products->isEmpty() ? collect() : ProductImage::whereIn('product_id', $products->pluck('id'))->pluck('url')->unique()->flip();

            foreach ($rows as $row) {
                $key = mb_strtolower((string) $row['sku']);
                $attributes = $this->parseAttributes((string) $row['variant_attributes']) ?? [];
                $price = $this->number($row['price'], 0.01, 999999.99);
                $salePrice = $this->number($row['sale_price'], 0.01, 999999.99);
                $stock = $this->integer($row['stock'], 0, 999999);
                $active = $this->flag($row['is_active']);

                if ($product = $bySku->get($key)) {
                    $this->updateProduct($product, $row, $price, $salePrice, $stock, $active);
                } elseif ($variant = $variantsBySku->get($key)) {
                    $this->updateVariant($variant, $price, $stock, $active);
                } elseif ($attributes !== []) {
                    $parentKey = mb_strtolower((string) $row['product_sku']);
                    $parent = $bySku->get($parentKey);
                    if (! $parent) {
                        $parent = $this->createProduct($seller, $row, $categories, $price, $salePrice, 0, $this->ownImageUrls($row['image_urls'], $ownImages), hasVariants: true, sku: $row['product_sku']);
                        $bySku->put($parentKey, $parent);
                    }
                    $variant = $parent->variants()->create([
                        'attributes' => $attributes,
                        'type' => implode('/', array_keys($attributes)),
                        'name' => ['en' => implode(' / ', array_values($attributes))],
                        'sku' => $row['sku'],
                        'price' => $price,
                        'stock_quantity' => $stock ?? 0,
                        'is_active' => $active ?? true,
                        'sort_order' => $parent->variants()->count(),
                    ]);
                    $variantsBySku->put($key, $variant->setRelation('product', $parent));
                } else {
                    $product = $this->createProduct($seller, $row, $categories, $price, $salePrice, $stock ?? 0, $this->ownImageUrls($row['image_urls'], $ownImages), hasVariants: false, sku: $row['sku']);
                    $bySku->put($key, $product);
                }
            }
        });

        return $preview['counts'];
    }

    // ---- Helpers ----------------------------------------------------------------------------

    protected function productChanges(Product $product, array $row, ?float $price, ?float $salePrice, ?int $stock, ?bool $active): array
    {
        $changes = [];
        if ($row['product_name_en'] !== null && $row['product_name_en'] !== '' && $row['product_name_en'] !== $product->getTranslation('name', 'en', false)) {
            $changes[] = __('name: :from → :to', ['from' => $product->getTranslation('name', 'en', false), 'to' => $row['product_name_en']]);
        }
        if ($row['product_name_dv'] !== null && $row['product_name_dv'] !== '' && $row['product_name_dv'] !== $product->getTranslation('name', 'dv', false)) {
            $changes[] = __('Dhivehi name updated');
        }
        foreach (['description_en' => 'en', 'description_dv' => 'dv'] as $column => $locale) {
            if ($row[$column] !== null && $row[$column] !== '' && $row[$column] !== $product->getTranslation('description', $locale, false)) {
                $changes[] = __('description (:locale) updated', ['locale' => $locale]);
            }
        }
        // Brands match like the brand list does: "SAMSUNG" is the same brand as "Samsung"
        if ($row['brand'] !== null && $row['brand'] !== '' && \App\Models\Brand::keyFor($row['brand']) !== \App\Models\Brand::keyFor($product->brand)) {
            $changes[] = __('brand: :from → :to', ['from' => $product->brand ?: '—', 'to' => $row['brand']]);
        }
        if ($price !== null && abs($price - (float) $product->price) >= 0.005) {
            $changes[] = __('price: :from → :to', ['from' => $this->money($product->price), 'to' => $this->money($price)]);
        }
        if ($salePrice !== null && abs($salePrice - (float) $product->sale_price) >= 0.005) {
            $changes[] = __('sale price: :from → :to', ['from' => $product->sale_price !== null ? $this->money($product->sale_price) : '—', 'to' => $this->money($salePrice)]);
        }
        if ($stock !== null && ! $product->has_variants && $stock !== (int) $product->stock_quantity) {
            $changes[] = __('stock: :from → :to', ['from' => $product->stock_quantity, 'to' => $stock]);
        }
        if ($active !== null && $active !== (bool) $product->is_active) {
            $changes[] = $active && ! $product->isApproved()
                ? __('stays pending until an admin approves it')
                : ($active ? __('activated') : __('deactivated'));
        }

        return $changes ?: [__('No changes')];
    }

    protected function variantChanges(ProductVariant $variant, ?float $price, ?int $stock, ?bool $active): array
    {
        $changes = [];
        if ($price !== null && abs($price - $variant->effectivePrice()) >= 0.005) {
            $changes[] = __('price: :from → :to', ['from' => $this->money($variant->effectivePrice()), 'to' => $this->money($price)]);
        }
        if ($stock !== null && $stock !== (int) $variant->stock_quantity) {
            $changes[] = __('stock: :from → :to', ['from' => $variant->stock_quantity, 'to' => $stock]);
        }
        if ($active !== null && $active !== (bool) $variant->is_active) {
            $changes[] = $active ? __('activated') : __('deactivated');
        }

        return $changes ?: [__('No changes')];
    }

    protected function updateProduct(Product $product, array $row, ?float $price, ?float $salePrice, ?int $stock, ?bool $active): void
    {
        foreach (['product_name_en' => 'en', 'product_name_dv' => 'dv'] as $column => $locale) {
            if ($row[$column] !== null && $row[$column] !== '') {
                $product->setTranslation('name', $locale, $row[$column]);
            }
        }
        foreach (['description_en' => 'en', 'description_dv' => 'dv'] as $column => $locale) {
            if ($row[$column] !== null && $row[$column] !== '') {
                $product->setTranslation('description', $locale, $row[$column]);
            }
        }
        if ($row['brand'] !== null && $row['brand'] !== '') {
            $product->brand = $row['brand'];
        }
        if ($price !== null) {
            $product->price = $price;
        }
        if ($salePrice !== null) {
            $product->sale_price = $salePrice;
        }
        if ($stock !== null && ! $product->has_variants) {
            $product->stock_quantity = $stock;
        }
        if ($active !== null) {
            // Never past approval: a product that was never approved stays pending
            $product->is_active = $active ? $product->isApproved() : false;
        }
        $product->save();
    }

    protected function updateVariant(ProductVariant $variant, ?float $price, ?int $stock, ?bool $active): void
    {
        if ($price !== null) {
            $variant->price = $price;
        }
        if ($stock !== null) {
            $variant->stock_quantity = $stock;
        }
        if ($active !== null) {
            $variant->is_active = $active;
        }
        $variant->save();
    }

    protected function createProduct(User $seller, array $row, Collection $categories, ?float $price, ?float $salePrice, int $stock, array $images, bool $hasVariants, string $sku): Product
    {
        $product = new Product([
            'name' => array_filter(['en' => $row['product_name_en'], 'dv' => $row['product_name_dv'] ?: null]),
            'description' => array_filter(['en' => $row['description_en'] ?: null, 'dv' => $row['description_dv'] ?: null]),
            'sku' => $sku,
            'category_id' => $categories[$row['category_slug']],
            'price' => $price,
            'sale_price' => $salePrice,
            'stock_quantity' => $stock,
            'has_variants' => $hasVariants,
            'reorder_point' => 5,
            'brand' => $row['brand'] ?: null,
            'is_active' => false, // new listings wait for admin approval
        ]);
        $product->seller_id = $seller->id;
        $product->save();

        foreach ($images as $i => $url) {
            $product->images()->create(['url' => $url, 'alt_text' => $row['product_name_en'], 'is_main' => $i === 0, 'sort_order' => $i]);
            if ($i === 0) {
                $product->forceFill(['main_image' => Str::after($url, '/storage/')])->save();
            }
        }

        return $product;
    }

    protected function creationErrors(array $row, Collection $categories, ?float $price): array
    {
        $errors = [];
        if ($row['product_name_en'] === null || $row['product_name_en'] === '') {
            $errors[] = __('A new product needs product_name_en.');
        }
        if ($row['category_slug'] === null || $row['category_slug'] === '' || ! isset($categories[$row['category_slug']])) {
            $errors[] = __('A new product needs a valid category_slug.');
        }
        if ($price === null) {
            $errors[] = __('A new product needs a price.');
        }

        return $errors;
    }

    /**
     * Only image URLs that are already this shop's uploads are kept; nothing is fetched from the web.
     */
    protected function ownImageUrls(?string $list, Collection $ownImages): array
    {
        if ($list === null || $list === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode('|', $list)), fn ($url) => $url !== '' && isset($ownImages[$url])));
    }

    protected function skuTakenElsewhere(string $sku, User $seller): bool
    {
        return Product::withTrashed()->where('sku', $sku)->where('seller_id', '!=', $seller->id)->exists()
            || ProductVariant::where('sku', $sku)->whereHas('product', fn ($q) => $q->withTrashed()->where('seller_id', '!=', $seller->id))->exists();
    }

    protected function number(?string $raw, float $min, float $max): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $raw = str_replace(',', '', $raw);
        if (! is_numeric($raw)) {
            return null;
        }
        $value = round((float) $raw, 2);

        return $value >= $min && $value <= $max ? $value : null;
    }

    protected function integer(?string $raw, int $min, int $max): ?int
    {
        if ($raw === null || $raw === '' || ! preg_match('/^\d+$/', $raw)) {
            return null;
        }
        $value = (int) $raw;

        return $value >= $min && $value <= $max ? $value : null;
    }

    protected function flag(?string $raw): ?bool
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        return match (mb_strtolower($raw)) {
            '1', 'yes', 'true', 'y' => true,
            '0', 'no', 'false', 'n' => false,
            default => null,
        };
    }
}
