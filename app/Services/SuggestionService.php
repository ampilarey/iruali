<?php

namespace App\Services;

use App\Models\Cart;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * "Frequently bought together" and cart suggestions: products that were in the same paid orders,
 * worked out nightly into product_pairs (insights:compute-pairs), with a count of how often the
 * suggestions are shown and added to a cart (Admin → Analytics).
 */
class SuggestionService
{
    public const PLACES = ['product', 'cart'];

    /** Pairs bought together fewer times than this are left out (noise). */
    public const MIN_TOGETHER = 2;

    /**
     * Rebuild the pairs from the last year of paid orders. Returns how many pairs were stored.
     */
    public function computePairs(): int
    {
        $counts = [];
        DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.payment_status', 'paid')->where('orders.status', '!=', 'cancelled')
            ->where('orders.created_at', '>=', now()->subYear())
            ->select('order_items.order_id', 'order_items.product_id')->orderBy('order_items.order_id')
            ->get()->groupBy('order_id')
            ->each(function (Collection $items) use (&$counts) {
                $ids = $items->pluck('product_id')->unique()->values()->take(30)->all();
                foreach ($ids as $a) {
                    foreach ($ids as $b) {
                        if ($a !== $b) {
                            $counts[$a][$b] = ($counts[$a][$b] ?? 0) + 1;
                        }
                    }
                }
            });

        $rows = [];
        foreach ($counts as $a => $paired) {
            arsort($paired);
            foreach (array_slice(array_filter($paired, fn ($n) => $n >= self::MIN_TOGETHER), 0, 12, true) as $b => $n) {
                $rows[] = ['product_id' => $a, 'paired_product_id' => $b, 'together' => $n];
            }
        }

        DB::transaction(function () use ($rows) {
            DB::table('product_pairs')->delete();
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('product_pairs')->insert($chunk);
            }
        });

        return count($rows);
    }

    /**
     * Product ids most often bought with these products, best first (not the products themselves).
     *
     * @param  list<int>  $productIds
     * @return Collection<int, int>
     */
    public function pairedIds(array $productIds, int $limit = 10): Collection
    {
        if ($productIds === []) {
            return collect();
        }

        return DB::table('product_pairs')->whereIn('product_id', $productIds)->whereNotIn('paired_product_id', $productIds)
            ->selectRaw('paired_product_id, sum(together) as score')->groupBy('paired_product_id')
            ->orderByDesc('score')->limit($limit)->pluck('paired_product_id')->map(fn ($id) => (int) $id);
    }

    /**
     * Up to four products to suggest on the cart page: bought with what is in the cart, in stock,
     * from shops taking orders, not in the cart already.
     *
     * @return Collection<int, Product>
     */
    public function forCart(Cart $cart, int $limit = 4): Collection
    {
        $inCart = $cart->items->pluck('product_id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        $ids = $this->pairedIds($inCart, 12);
        if ($ids->isEmpty()) {
            return collect();
        }

        return Product::with(['mainImage', 'seller'])->whereIn('id', $ids)->where('is_active', true)->where('has_variants', false)
            ->where('stock_quantity', '>', 0)->get()
            ->reject(fn (Product $p) => $p->seller?->isOnHoliday() || $p->isComingSoon())
            ->sortBy(fn (Product $p) => $ids->search($p->id))->take($limit)->values();
    }

    public function record(string $place, string $what, int $count = 1): void
    {
        if (! in_array($place, self::PLACES, true) || ! in_array($what, ['shown', 'added'], true) || $count < 1) {
            return;
        }
        try {
            $day = today()->toDateString();
            DB::table('suggestion_stats')->insertOrIgnore(['day' => $day, 'place' => $place, 'shown' => 0, 'added' => 0]);
            DB::table('suggestion_stats')->where('day', $day)->where('place', $place)->increment($what, $count);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Shown, added and the share added, per place, over the last days.
     *
     * @return array<string, array{shown: int, added: int, rate: float}>
     */
    public function report(int $days = 30): array
    {
        $rows = DB::table('suggestion_stats')->where('day', '>=', today()->subDays($days)->toDateString())
            ->selectRaw('place, sum(shown) as shown, sum(added) as added')->groupBy('place')->get()->keyBy('place');

        return collect(self::PLACES)->mapWithKeys(function ($place) use ($rows) {
            $shown = (int) ($rows[$place]->shown ?? 0);
            $added = (int) ($rows[$place]->added ?? 0);

            return [$place => ['shown' => $shown, 'added' => $added, 'rate' => $shown ? round($added / $shown * 100, 1) : 0.0]];
        })->all();
    }
}
