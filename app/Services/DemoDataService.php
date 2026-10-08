<?php

namespace App\Services;

use App\Models\Campaign;
use App\Models\CampaignProduct;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\SavedItem;
use App\Models\Setting;
use App\Models\StockAlert;
use App\Models\User;
use App\Models\Wishlist;
use App\Support\Audit;
use App\Support\DemoData;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Admin → Settings → Sample data, and php artisan demo:remove / demo:restore: take the sample
 * shops and products (App\Support\DemoData, nothing else) off the site when the owner is ready,
 * and put them back exactly as they were.
 *
 * Remove:
 * - each sample product is switched off and moved to the bin (soft delete);
 * - the demo shops are suspended, as Admin → Sellers → Suspend does: their shop pages go, they
 *   cannot use the Seller Centre and checkout refuses their products. Anything else they list is
 *   switched off with them. The accounts stay;
 * - customers' carts, saved items, wishlists and stock alerts lose the sample products, and the
 *   products' campaign entries are taken out;
 * - the cached sitemap and product feeds are dropped, so they are rebuilt without them.
 * Orders that contain sample products are left alone: order pages, receipts and payouts still
 * show them (order lines read their product from the bin).
 *
 * What Remove changed is kept as JSON in the settings table (SNAPSHOT_KEY), so Restore puts back
 * only that: the products it binned, the status each shop had, the products it switched off and
 * the campaign entries. Both run in one transaction and are safe to repeat.
 *
 * @phpstan-type Snapshot array{
 *     removed_at: ?string,
 *     products: array<int, bool>,
 *     switched_off: list<int>,
 *     shops: array<int, string>,
 *     campaign_entries: list<array<string, mixed>>
 * }
 */
class DemoDataService
{
    /** settings.key holding what the last Remove changed, until Restore puts it back */
    public const SNAPSHOT_KEY = 'demo_data_snapshot';

    /** Cached pages built from the product list: SitemapController and FeedController */
    public const CACHE_KEYS = ['sitemap.xml', 'feeds.google-merchant', 'feeds.facebook-catalog'];

    /**
     * What sample data the site has now, and whether there is anything to remove or restore.
     *
     * @return array{shops: int, shops_open: int, shop_names: list<string>, products_live: int, products_removed: int, other_products_live: int, customer_references: int, restorable_products: int, removed_at: ?string, can_remove: bool, can_restore: bool}
     */
    public function status(): array
    {
        $shops = $this->shops();
        $products = $this->products($shops);
        $live = $products->reject(fn (Product $product) => $product->trashed());
        $shopsOpen = $shops->reject(fn (User $shop) => $shop->status === 'suspended')->count();
        $others = count($this->otherLiveProductIds($shops, $products->modelKeys()));
        $references = array_sum($this->referenceCounts($products->modelKeys()));
        $snapshot = $this->snapshot();

        return [
            'shops' => $shops->count(),
            'shops_open' => $shopsOpen,
            'shop_names' => array_values($shops->map(fn (User $shop) => $shop->shopName())->all()),
            'products_live' => $live->count(),
            'products_removed' => $products->count() - $live->count(),
            'other_products_live' => $others,
            'customer_references' => $references,
            'restorable_products' => $snapshot ? Product::onlyTrashed()->whereKey(array_keys($snapshot['products']))->count() : 0,
            'removed_at' => $snapshot['removed_at'] ?? null,
            'can_remove' => $live->isNotEmpty() || $shopsOpen > 0 || $others > 0 || $references > 0,
            'can_restore' => $snapshot !== null,
        ];
    }

    /**
     * Take the sample data off the site. A second run finds nothing left to do.
     *
     * @return array{products: int, shops: int, other_products: int, campaign_entries: int, cart_items: int, saved_items: int, wishlist_items: int, stock_alerts: int}
     */
    public function remove(): array
    {
        $this->ensureSnapshotRow();

        $counts = DB::transaction(function () {
            $snapshot = $this->snapshot(lock: true) ?? $this->emptySnapshot();
            $shops = $this->shops();
            $products = $this->products($shops);
            $ids = $products->modelKeys();

            // Customers' carts, saved items, wishlists and stock alerts lose every sample product
            $counts = ['products' => 0, 'shops' => 0, 'other_products' => 0, 'campaign_entries' => 0] + $this->deleteReferences($ids);

            // Campaign entries come out too; Restore puts them back
            $entries = CampaignProduct::whereIn('product_id', $ids)->get();
            foreach ($entries as $entry) {
                $snapshot['campaign_entries'][] = [
                    'campaign_id' => $entry->campaign_id,
                    'product_id' => $entry->product_id,
                    'seller_id' => $entry->seller_id,
                    'discount_percent' => $entry->discount_percent,
                    'approved_at' => $entry->approved_at?->toDateTimeString(),
                    'created_at' => $entry->created_at?->toDateTimeString(),
                    'updated_at' => $entry->updated_at?->toDateTimeString(),
                ];
            }
            $counts['campaign_entries'] = CampaignProduct::whereKey($entries->modelKeys())->delete();

            // Sample products: switched off, then into the bin, remembering which were on sale.
            // Switched off as well so nothing that reads products from the bin (an old order's
            // "Buy again") offers them.
            $live = $products->reject(fn (Product $product) => $product->trashed());
            foreach ($live as $product) {
                $snapshot['products'][$product->id] = $product->is_active;
            }
            Product::whereKey($live->modelKeys())->update(['is_active' => false]);
            $live->each(fn (Product $product) => $product->delete());
            $counts['products'] = $live->count();

            // Whatever else the demo shops list goes off sale with the shop
            $others = $this->otherLiveProductIds($shops, $ids);
            Product::whereKey($others)->update(['is_active' => false]);
            $snapshot['switched_off'] = array_values(array_unique(array_merge($snapshot['switched_off'], $others)));
            $counts['other_products'] = count($others);

            // The shops are suspended, remembering the status each had (a shop that was already
            // suspended is left out, so Restore leaves it suspended)
            foreach ($shops as $shop) {
                if ($shop->status === 'suspended') {
                    continue;
                }
                $snapshot['shops'][$shop->id] ??= $shop->status;
                $shop->forceFill(['status' => 'suspended'])->save(); // status is not mass-assignable
                $counts['shops']++;
            }

            if ($counts['products'] + $counts['shops'] + $counts['other_products'] + $counts['campaign_entries'] > 0) {
                $snapshot['removed_at'] ??= now()->toIso8601String();
                $this->saveSnapshot($snapshot);
            }

            return $counts;
        });

        $this->forgetCachedListings();
        if (array_sum($counts) > 0) {
            Audit::record('demo.removed', null, $counts);
        }

        return $counts;
    }

    /**
     * Put back exactly what remove() took off: the products it binned (on sale again if they were),
     * the status each shop had, the products it switched off and the campaign entries. Customers'
     * carts, saved items, wishlists and stock alerts are not refilled. A second run does nothing.
     *
     * @return array{products: int, shops: int, other_products: int, campaign_entries: int}
     */
    public function restore(): array
    {
        $this->ensureSnapshotRow();

        $counts = DB::transaction(function () {
            $counts = ['products' => 0, 'shops' => 0, 'other_products' => 0, 'campaign_entries' => 0];
            $snapshot = $this->snapshot(lock: true);
            if ($snapshot === null) {
                return $counts;
            }

            // Out of the bin: only the products remove() put there
            $products = Product::onlyTrashed()->whereKey(array_keys($snapshot['products']))->get();
            $products->each(fn (Product $product) => $product->restore());
            $wereOnSale = array_keys(array_filter($snapshot['products']));
            Product::whereKey(array_values(array_intersect($products->modelKeys(), $wereOnSale)))->update(['is_active' => true]);
            $counts['products'] = $products->count();

            // Products switched off with the shops come back on (not ones deleted since)
            $counts['other_products'] = Product::whereKey($snapshot['switched_off'])->where('is_active', false)->update(['is_active' => true]);

            foreach ($snapshot['shops'] as $id => $status) {
                $shop = User::withTrashed()->find($id);
                if ($shop && $shop->status !== $status) {
                    $shop->forceFill(['status' => $status])->save();
                    $counts['shops']++;
                }
            }

            foreach ($snapshot['campaign_entries'] as $row) {
                $counts['campaign_entries'] += $this->restoreCampaignEntry($row) ? 1 : 0;
            }

            $this->saveSnapshot(null);

            return $counts;
        });

        $this->forgetCachedListings();
        if (array_sum($counts) > 0) {
            Audit::record('demo.restored', null, $counts);
        }

        return $counts;
    }

    /**
     * What each count means, for the admin page and the artisan commands.
     *
     * @return array<string, string>
     */
    public function countLabels(): array
    {
        return [
            'products' => __('Sample products'),
            'shops' => __('Demo shops'),
            'other_products' => __('Other products of the demo shops'),
            'campaign_entries' => __('Campaign entries'),
            'cart_items' => __('Cart lines'),
            'saved_items' => __('Saved-for-later items'),
            'wishlist_items' => __('Wishlist items'),
            'stock_alerts' => __('Stock alerts'),
        ];
    }

    /**
     * The demo shop accounts (exact emails only), deleted accounts included.
     *
     * @return Collection<int, User>
     */
    protected function shops(): Collection
    {
        $emails = DemoData::shopEmails();

        // Emails compare without regard to case in MySQL; only the exact demo addresses count
        return User::withTrashed()->whereIn('email', $emails)->orderBy('id')->get()
            ->filter(fn (User $user) => in_array($user->email, $emails, true))
            ->values();
    }

    /**
     * Every sample product, in the bin or not: each demo shop's own SKUs under that shop's account,
     * and the generic demo products (exact SKU or exact English name) under admin@example.com.
     *
     * @param  Collection<int, User>  $shops
     * @return Collection<int, Product>
     */
    protected function products(Collection $shops): Collection
    {
        $skus = DemoData::shopSkus();
        $skusBySeller = [];
        foreach ($shops as $shop) {
            $skusBySeller[$shop->id] = $skus[$shop->email] ?? [];
        }

        $admin = User::withTrashed()->where('email', DemoData::ADMIN_EMAIL)->get()
            ->first(fn (User $user) => $user->email === DemoData::ADMIN_EMAIL);
        $sellerIds = array_keys($skusBySeller);
        if ($admin) {
            $sellerIds[] = $admin->id;
        }
        if ($sellerIds === []) {
            return new Collection;
        }

        // SKUs and names are compared here, exactly, rather than by the database's collation
        return Product::withTrashed()->whereIn('seller_id', $sellerIds)->orderBy('id')->get()
            ->filter(function (Product $product) use ($skusBySeller, $admin) {
                if ($admin && $product->seller_id === $admin->id) {
                    return in_array($product->sku, DemoData::GENERIC_SKUS, true)
                        || in_array($product->getTranslation('name', 'en', false), DemoData::GENERIC_NAMES, true);
                }

                return in_array($product->sku, $skusBySeller[$product->seller_id] ?? [], true);
            })
            ->values();
    }

    /**
     * Products on sale that the demo shops list besides their sample products.
     *
     * @param  Collection<int, User>  $shops
     * @param  array<int, int>  $sampleIds
     * @return list<int>
     */
    protected function otherLiveProductIds(Collection $shops, array $sampleIds): array
    {
        if ($shops->isEmpty()) {
            return [];
        }

        return array_values(Product::whereIn('seller_id', $shops->modelKeys())
            ->whereNotIn('id', $sampleIds)
            ->where('is_active', true)
            ->orderBy('id')
            ->pluck('id')
            ->all());
    }

    /**
     * Customers' rows that point at sample products.
     *
     * @param  array<int, int>  $ids
     * @return array{cart_items: int, saved_items: int, wishlist_items: int, stock_alerts: int}
     */
    protected function referenceCounts(array $ids): array
    {
        return [
            'cart_items' => CartItem::whereIn('product_id', $ids)->count(),
            'saved_items' => SavedItem::whereIn('product_id', $ids)->count(),
            'wishlist_items' => Wishlist::whereIn('product_id', $ids)->count(),
            'stock_alerts' => StockAlert::whereIn('product_id', $ids)->count(),
        ];
    }

    /**
     * @param  array<int, int>  $ids
     * @return array{cart_items: int, saved_items: int, wishlist_items: int, stock_alerts: int}
     */
    protected function deleteReferences(array $ids): array
    {
        return [
            'cart_items' => CartItem::whereIn('product_id', $ids)->delete(),
            'saved_items' => SavedItem::whereIn('product_id', $ids)->delete(),
            'wishlist_items' => Wishlist::whereIn('product_id', $ids)->delete(),
            'stock_alerts' => StockAlert::whereIn('product_id', $ids)->delete(),
        ];
    }

    /**
     * Put one campaign entry back, if its campaign and product are still there and the product has
     * not joined the campaign again since.
     *
     * @param  array<string, mixed>  $row
     */
    protected function restoreCampaignEntry(array $row): bool
    {
        $campaignId = filter_var($row['campaign_id'] ?? null, FILTER_VALIDATE_INT);
        $productId = filter_var($row['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($campaignId === false || $productId === false
            || ! Campaign::whereKey($campaignId)->exists()
            || ! Product::whereKey($productId)->exists()
            || CampaignProduct::where('campaign_id', $campaignId)->where('product_id', $productId)->exists()) {
            return false;
        }

        $entry = new CampaignProduct;
        $entry->timestamps = false; // keep when it joined
        $entry->forceFill([
            'campaign_id' => $campaignId,
            'product_id' => $productId,
            'seller_id' => $row['seller_id'] ?? null,
            'discount_percent' => $row['discount_percent'] ?? null,
            'approved_at' => $row['approved_at'] ?? null,
            'created_at' => $row['created_at'] ?? now(),
            'updated_at' => $row['updated_at'] ?? now(),
        ])->save();

        return true;
    }

    /**
     * What the last remove() changed, or null when there is nothing to restore.
     *
     * @return Snapshot|null
     */
    protected function snapshot(bool $lock = false): ?array
    {
        $query = Setting::query()->where('key', self::SNAPSHOT_KEY);
        if ($lock) {
            $query->lockForUpdate();
        }
        $value = $query->value('value');
        $data = is_string($value) && $value !== '' ? json_decode($value, true) : null;
        if (! is_array($data) || ! is_string($data['removed_at'] ?? null)) {
            return null;
        }

        $snapshot = $this->emptySnapshot();
        $snapshot['removed_at'] = $data['removed_at'];
        foreach ((array) ($data['products'] ?? []) as $row) {
            if (is_array($row) && is_int($row['id'] ?? null)) {
                $snapshot['products'][$row['id']] = (bool) ($row['on_sale'] ?? false);
            }
        }
        foreach ((array) ($data['shops'] ?? []) as $row) {
            if (is_array($row) && is_int($row['id'] ?? null) && is_string($row['status'] ?? null)) {
                $snapshot['shops'][$row['id']] = $row['status'];
            }
        }
        $snapshot['switched_off'] = array_values(array_filter((array) ($data['switched_off'] ?? []), 'is_int'));
        $snapshot['campaign_entries'] = array_values(array_filter((array) ($data['campaign_entries'] ?? []), 'is_array'));

        return $snapshot;
    }

    /**
     * Store the snapshot (lists of id/value pairs, so ids never turn into list positions), or
     * clear it with null.
     *
     * @param  Snapshot|null  $snapshot
     */
    protected function saveSnapshot(?array $snapshot): void
    {
        $value = '';
        if ($snapshot !== null) {
            $value = json_encode([
                'removed_at' => $snapshot['removed_at'],
                'products' => array_map(fn (int $id, bool $onSale) => ['id' => $id, 'on_sale' => $onSale], array_keys($snapshot['products']), $snapshot['products']),
                'shops' => array_map(fn (int $id, string $status) => ['id' => $id, 'status' => $status], array_keys($snapshot['shops']), $snapshot['shops']),
                'switched_off' => $snapshot['switched_off'],
                'campaign_entries' => $snapshot['campaign_entries'],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        Setting::set([self::SNAPSHOT_KEY => $value]);
    }

    /** @return Snapshot */
    protected function emptySnapshot(): array
    {
        return ['removed_at' => null, 'products' => [], 'switched_off' => [], 'shops' => [], 'campaign_entries' => []];
    }

    /**
     * The settings row exists before the transactions lock it, so two clicks at once queue up
     * instead of both inserting it.
     */
    protected function ensureSnapshotRow(): void
    {
        Setting::query()->insertOrIgnore(['key' => self::SNAPSHOT_KEY, 'value' => '', 'created_at' => now(), 'updated_at' => now()]);
    }

    /** The sitemap and product feeds are cached for an hour; rebuild them on the next request. */
    protected function forgetCachedListings(): void
    {
        foreach (self::CACHE_KEYS as $key) {
            Cache::forget($key);
        }
        Campaign::forgetDiscounts();
    }
}
