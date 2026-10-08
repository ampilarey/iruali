<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\BrandAlias;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Support\ImageVariants;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The shared brand list: turns what shops type into one brand per real brand, keeps products'
 * brand names in step with it, and does the admin's renames, merges and deletes.
 */
class BrandService
{
    /**
     * The brand a typed name stands for: the brand with that name (ignoring case, spaces and
     * punctuation), the brand an old name was merged into, or else a new brand. Null when the
     * text means "no brand" ("N/A", "Generic", nothing).
     */
    public function resolve(?string $name, ?int $createdBy = null): ?Brand
    {
        $name = Brand::cleanName($name);
        $key = Brand::keyFor($name);
        if (Brand::isNoBrand($key)) {
            return null;
        }

        if ($brand = $this->findByKey($key)) {
            return $brand;
        }

        try {
            return Brand::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
                'key' => $key,
                'created_by' => $createdBy,
            ]);
        } catch (UniqueConstraintViolationException $e) {
            // Two saves raced to create the same brand: use the one that was stored first
            return $this->findByKey($key) ?? throw $e;
        }
    }

    public function findByKey(string $key): ?Brand
    {
        if ($key === '') {
            return null;
        }

        return Brand::where('key', $key)->first()
            ?? BrandAlias::where('key', $key)->first()?->brand;
    }

    /**
     * The brand a /brands/... address points at: its slug, an old slug kept after a rename or
     * merge, or (for links made before brands had slugs) the brand's name.
     */
    public function findForUrl(string $value): ?Brand
    {
        return Brand::where('slug', $value)->first()
            ?? BrandAlias::where('slug', $value)->first()?->brand
            ?? $this->findByKey(Brand::keyFor($value));
    }

    /**
     * Called by the Product model before every save: point the product at its brand and store the
     * brand's own spelling of the name.
     */
    public function syncProduct(Product $product): void
    {
        if ($product->isDirty('brand_id') && $product->brand_id) {
            if ($brand = Brand::find($product->brand_id)) {
                $product->brand = $brand->name;

                return;
            }
            $product->brand_id = null;
        }

        $name = Brand::cleanName($product->brand);
        $unlinked = $name !== '' && ! $product->brand_id;
        $stale = $name === '' && $product->brand_id;
        if (! $product->isDirty('brand') && ! $unlinked && ! $stale) {
            return;
        }

        $brand = $this->resolve($name, $product->seller_id ?: auth()->id());
        $product->brand_id = $brand?->id;
        $product->brand = $brand?->name;
    }

    /**
     * Link every product whose brand name has no brand yet. The most common spelling of a name
     * becomes the brand's name. Safe to run again: returns how many brands and products changed.
     *
     * @return array{brands: int, products: int}
     */
    public function linkProducts(): array
    {
        $brandsBefore = Brand::count();
        $linked = 0;

        // Grouped here rather than in SQL: MySQL compares case-insensitively and would pick one
        // spelling of "Samsung" / "SAMSUNG" at random.
        $groups = [];
        Product::withTrashed()->whereNull('brand_id')->whereNotNull('brand')->where('brand', '!=', '')
            ->pluck('brand')
            ->each(function ($raw) use (&$groups) {
                $clean = Brand::cleanName($raw);
                $key = Brand::keyFor($clean);
                $groups[$key]['spellings'][$clean] = ($groups[$key]['spellings'][$clean] ?? 0) + 1;
                $groups[$key]['raw'][(string) $raw] = true;
            });

        // Most used names first, so they get the plain web addresses
        uasort($groups, fn ($a, $b) => array_sum($b['spellings']) <=> array_sum($a['spellings']));

        foreach ($groups as $group) {
            $brand = $this->resolve($this->preferredSpelling($group['spellings']));
            Product::withTrashed()->whereNull('brand_id')->whereIn('brand', array_keys($group['raw']))->lazyById(200)
                ->each(function (Product $product) use ($brand, &$linked) {
                    $this->storeBrandQuietly($product, $brand);
                    $linked++;
                });
        }

        return ['brands' => Brand::count() - $brandsBefore, 'products' => $linked];
    }

    /**
     * The spelling used most; on a tie, normal capitalisation ("Samsung") beats shouting
     * ("SAMSUNG") or all lower case, while short all-capital names ("LG", "ASUS") are fine.
     *
     * @param  array<string, int>  $spellings  spelling => how many products use it
     */
    protected function preferredSpelling(array $spellings): string
    {
        $style = function (string $name): int {
            $upper = preg_match('/\p{Lu}/u', $name) === 1;
            $lower = preg_match('/\p{Ll}/u', $name) === 1;

            return match (true) {
                $upper && $lower => 2,
                $upper && mb_strlen(Brand::keyFor($name)) <= 4 => 1,
                default => 0,
            };
        };

        $names = array_keys($spellings);
        usort($names, fn ($a, $b) => [$spellings[$b], $style($b), $a] <=> [$spellings[$a], $style($a), $b]);

        return (string) $names[0];
    }

    /**
     * Save the admin's changes. A new name or address is stored as an alias, so old links and
     * shops typing the old name still reach this brand; products take the new name.
     *
     * @param  array{name: string, slug: string, description?: array<string, string>, logo?: ?string, reviewed_at?: mixed}  $data
     */
    public function update(Brand $brand, array $data): Brand
    {
        DB::transaction(function () use ($brand, $data) {
            $name = Brand::cleanName($data['name']);
            $key = Brand::keyFor($name);
            $slug = $data['slug'];
            [$oldKey, $oldSlug, $oldName] = [$brand->key, $brand->slug, $brand->name];

            // Taking back an old name or address: it stops being an alias
            $this->releaseAliases($brand, $key, $slug);

            $brand->fill(['name' => $name, 'key' => $key, 'slug' => $slug]);
            foreach (['logo', 'reviewed_at'] as $field) {
                if (array_key_exists($field, $data)) {
                    $brand->{$field} = $data[$field];
                }
            }
            if (array_key_exists('description', $data)) {
                $brand->replaceTranslations('description', array_filter($data['description'] ?? [], fn ($text) => filled($text)));
            }
            $brand->save();

            if ($oldKey !== $key) {
                BrandAlias::updateOrCreate(['key' => $oldKey], ['brand_id' => $brand->id]);
            }
            if ($oldSlug !== $slug) {
                BrandAlias::updateOrCreate(['slug' => $oldSlug], ['brand_id' => $brand->id]);
            }
            if ($oldName !== $name) {
                Product::withTrashed()->where('brand_id', $brand->id)->lazyById(200)
                    ->each(fn (Product $product) => $this->storeBrandQuietly($product, $brand));
            }
        });

        return $brand->refresh();
    }

    /**
     * Fold one brand into another: its products move over, its name and address become aliases
     * of the other brand (old links redirect), and the other brand keeps its own logo and
     * description, borrowing these only if it has none. Returns how many products moved.
     */
    public function merge(Brand $source, Brand $target): int
    {
        if ($source->is($target)) {
            throw new InvalidArgumentException('A brand cannot be merged into itself.');
        }

        $moved = 0;
        $orphanLogo = null;

        DB::transaction(function () use ($source, $target, &$moved, &$orphanLogo) {
            Product::withTrashed()->where('brand_id', $source->id)->lazyById(200)
                ->each(function (Product $product) use ($target, &$moved) {
                    $this->storeBrandQuietly($product, $target);
                    $moved++;
                });

            BrandAlias::where('brand_id', $source->id)->update(['brand_id' => $target->id]);

            if (! $target->logo && $source->logo) {
                $target->logo = $source->logo;
            } else {
                $orphanLogo = $source->logo;
            }
            if (! filled(array_filter($target->getTranslations('description'))) && filled(array_filter($source->getTranslations('description')))) {
                $target->replaceTranslations('description', array_filter($source->getTranslations('description')));
            }
            if (! $target->reviewed_at && $source->reviewed_at) {
                $target->reviewed_at = $source->reviewed_at;
            }
            $target->save();

            [$key, $slug] = [$source->key, $source->slug];
            $source->delete();
            BrandAlias::create(['brand_id' => $target->id, 'key' => $key, 'slug' => $slug]);
        });

        $this->deleteLogo($orphanLogo);

        return $moved;
    }

    /**
     * Remove a brand nobody sells. Refused (false) while products still use it; merge instead.
     */
    public function delete(Brand $brand): bool
    {
        if (Product::where('brand_id', $brand->id)->exists()) {
            return false;
        }

        DB::transaction(function () use ($brand) {
            // Products in the bin forget the brand; they get one again if they come back
            Product::onlyTrashed()->where('brand_id', $brand->id)->update(['brand_id' => null]);
            $brand->delete();
        });
        $this->deleteLogo($brand->logo);

        return true;
    }

    /** Another brand already using this match key, directly or as an old name. */
    public function keyOwner(string $key, ?Brand $except = null): ?Brand
    {
        $brand = Brand::where('key', $key)->when($except, fn ($q) => $q->where('id', '!=', $except->id))->first();

        return $brand ?? BrandAlias::where('key', $key)->when($except, fn ($q) => $q->where('brand_id', '!=', $except->id))->first()?->brand;
    }

    /** Is this web address used by another brand, now or as an old address? */
    public function slugTaken(string $slug, ?Brand $except = null): bool
    {
        return Brand::where('slug', $slug)->when($except, fn ($q) => $q->where('id', '!=', $except->id))->exists()
            || BrandAlias::where('slug', $slug)->when($except, fn ($q) => $q->where('brand_id', '!=', $except->id))->exists();
    }

    public function uniqueSlug(string $name, ?Brand $except = null): string
    {
        $base = trim(Str::limit(Str::slug($name), 100, ''), '-') ?: 'brand';
        $slug = $base;
        for ($n = 2; $this->slugTaken($slug, $except); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }

    /**
     * Brands whose names look like this one ("Samsung" and "Samsung Electronics"): candidates
     * for a merge, shown first on the admin's merge list.
     */
    public function similar(Brand $brand, int $limit = 10): Collection
    {
        $key = $brand->key;

        return Brand::where('id', '!=', $brand->id)->orderBy('name')->get(['id', 'name', 'key'])
            ->filter(function (Brand $other) use ($key) {
                $shorter = min(strlen($key), strlen($other->key));
                if ($shorter >= 3 && (str_starts_with($other->key, $key) || str_starts_with($key, $other->key))) {
                    return true;
                }

                return $shorter >= 4 && levenshtein($key, $other->key) <= max(1, intdiv($shorter, 5));
            })
            ->take($limit)
            ->values();
    }

    /**
     * Every brand with something on sale, A to Z, with how many of its products are on sale.
     */
    public function directory(): Collection
    {
        return Brand::query()->listed()->withActiveProductCount()->orderBy('name')->get();
    }

    /**
     * The brands people buy most (units sold in paid orders over the last 90 days), then the ones
     * with the most products on sale.
     */
    public function popular(int $limit = 12): Collection
    {
        $sold = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('orders.payment_status', 'paid')
            ->where('orders.created_at', '>=', now()->subDays(90))
            ->whereNotNull('products.brand_id')
            ->groupBy('products.brand_id')
            ->selectRaw('products.brand_id, sum(order_items.quantity) as units')
            ->orderByDesc('units')
            ->limit($limit * 2)
            ->pluck('units', 'products.brand_id');

        $bestSellers = Brand::query()->listed()->withActiveProductCount()->whereIn('id', $sold->keys())->get()
            ->sortBy([
                fn (Brand $a, Brand $b) => (int) $sold[$b->id] <=> (int) $sold[$a->id],
                fn (Brand $a, Brand $b) => $b->active_products_count <=> $a->active_products_count,
                fn (Brand $a, Brand $b) => strcasecmp($a->name, $b->name),
            ])
            ->take($limit)
            ->values();
        if ($bestSellers->count() >= $limit) {
            return $bestSellers;
        }

        // Not enough sales yet: fill up with the brands that have the most products on sale
        $rest = Brand::query()->listed()->withActiveProductCount()->whereNotIn('id', $bestSellers->pluck('id'))
            ->orderByDesc('active_products_count')->orderBy('name')
            ->limit($limit - $bestSellers->count())
            ->get();

        return $bestSellers->concat($rest)->values();
    }

    /**
     * The shops selling this brand, most products first, each with brand_product_count set.
     *
     * @return array{shops: Collection, total: int}
     */
    public function shops(Brand $brand, int $limit = 8): array
    {
        $counts = Product::query()->active()->where('brand_id', $brand->id)->whereNotNull('seller_id')
            ->selectRaw('seller_id, count(*) as aggregate')->groupBy('seller_id')
            ->pluck('aggregate', 'seller_id');

        $shops = User::whereIn('id', $counts->keys())->get(['id', 'name', 'business_name'])
            ->each(fn (User $shop) => $shop->brand_product_count = (int) $counts[$shop->id])
            ->sortBy([['brand_product_count', 'desc'], fn ($a, $b) => strcasecmp($a->business_name ?: $a->name, $b->business_name ?: $b->name)])
            ->take($limit)
            ->values();

        return ['shops' => $shops, 'total' => $counts->count()];
    }

    /**
     * The departments this brand's products sit in, most products first.
     */
    public function departments(Brand $brand, int $limit = 8): Collection
    {
        $counts = Product::query()->active()->where('brand_id', $brand->id)->whereNotNull('category_id')
            ->selectRaw('category_id, count(*) as aggregate')->groupBy('category_id')
            ->pluck('aggregate', 'category_id');

        return Category::active()->whereIn('id', $counts->keys())->get()
            ->each(fn (Category $category) => $category->brand_product_count = (int) $counts[$category->id])
            ->sortBy([['brand_product_count', 'desc'], fn ($a, $b) => strcasecmp((string) $a->localized_name, (string) $b->localized_name)])
            ->take($limit)
            ->values();
    }

    /** Point a product at a brand (or none) without firing model events, refreshing its search text. */
    protected function storeBrandQuietly(Product $product, ?Brand $brand): void
    {
        $product->brand_id = $brand?->id;
        $product->brand = $brand?->name;
        if ($product->isDirty('brand') || $product->search_text === null) {
            $product->search_text = $product->buildSearchText();
        }
        if ($product->isDirty()) {
            $product->timestamps = false;
            $product->saveQuietly();
        }
    }

    protected function releaseAliases(Brand $brand, string $key, string $slug): void
    {
        BrandAlias::where('brand_id', $brand->id)->where(fn ($q) => $q->where('key', $key)->orWhere('slug', $slug))->get()
            ->each(function (BrandAlias $alias) use ($key, $slug) {
                if ($alias->key === $key) {
                    $alias->key = null;
                }
                if ($alias->slug === $slug) {
                    $alias->slug = null;
                }
                $alias->key || $alias->slug ? $alias->save() : $alias->delete();
            });
    }

    protected function deleteLogo(?string $path): void
    {
        if ($path) {
            ImageVariants::delete($path);
            Storage::disk('public')->delete($path);
        }
    }
}
