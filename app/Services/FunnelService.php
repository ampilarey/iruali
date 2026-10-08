<?php

namespace App\Services;

use App\Models\FunnelEvent;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * The first-party shopping funnel: view product → add to cart → begin checkout → order paid.
 *
 * Visitors are a sha256 of the session id and a salt that changes daily, so nobody can be followed
 * across days or identified from the table. Recording never breaks the page that calls it.
 */
class FunnelService
{
    public const RETENTION_DAYS = 90;

    /**
     * Record a step for the current visitor.
     */
    public static function record(string $event, ?int $productId = null, ?int $orderId = null): void
    {
        try {
            FunnelEvent::create([
                'session_hash' => self::sessionHash(),
                'user_id' => auth()->id(),
                'event' => $event,
                'product_id' => $productId,
                'order_id' => $orderId,
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * An order was paid: one row per product in it, so product conversion can be measured. The
     * payment confirmation may come from a BML webhook with no session, so the visitor key is the
     * order itself.
     */
    public static function orderPaid(Order $order): void
    {
        try {
            $hash = self::hash('order:'.$order->id);
            $now = now();
            $rows = $order->items()->whereNotNull('product_id')->pluck('product_id')->unique()
                ->map(fn ($productId) => [
                    'session_hash' => $hash,
                    'user_id' => $order->user_id,
                    'event' => 'order_paid',
                    'product_id' => $productId,
                    'order_id' => $order->id,
                    'created_at' => $now,
                ])->values()->all();

            if ($rows === []) {
                $rows[] = ['session_hash' => $hash, 'user_id' => $order->user_id, 'event' => 'order_paid', 'product_id' => null, 'order_id' => $order->id, 'created_at' => $now];
            }

            FunnelEvent::insert($rows);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * Visitors per step in the last N days, in funnel order, with the conversion from the step
     * before: ['view_product' => ['count' => 120, 'rate' => null], 'add_to_cart' => ['count' => 30, 'rate' => 25.0], ...]
     * One grouped query.
     */
    public static function funnel(int $days): array
    {
        $counts = FunnelEvent::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->select('event', DB::raw('COUNT(DISTINCT session_hash) as visitors'))
            ->groupBy('event')
            ->pluck('visitors', 'event');

        $out = [];
        $previous = null;
        foreach (FunnelEvent::EVENTS as $event) {
            $count = (int) ($counts[$event] ?? 0);
            $out[$event] = [
                'count' => $count,
                'rate' => $previous === null ? null : ($previous > 0 ? round(min($count, $previous) / $previous * 100, 1) : 0.0),
            ];
            $previous = $count;
        }

        return $out;
    }

    /**
     * Products ranked by how often an add-to-cart turned into a paid order (last N days).
     * One grouped query; product names are loaded afterwards.
     */
    public static function topProducts(int $days, int $limit = 10): Collection
    {
        $carts = "COUNT(DISTINCT CASE WHEN event = 'add_to_cart' THEN session_hash END)";
        $paid = "COUNT(DISTINCT CASE WHEN event = 'order_paid' THEN order_id END)";

        $rows = FunnelEvent::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->whereNotNull('product_id')
            ->whereIn('event', ['add_to_cart', 'order_paid'])
            ->select('product_id', DB::raw("{$carts} as carts"), DB::raw("{$paid} as paid"))
            ->groupBy('product_id')
            ->havingRaw("{$carts} > 0")
            ->toBase() // plain rows: these are per-product totals, not funnel events
            ->get()
            ->map(function ($row) {
                $row->carts = (int) $row->carts;
                $row->paid = (int) $row->paid;
                $row->rate = round(min($row->paid, $row->carts) / $row->carts * 100, 1);

                return $row;
            })
            ->sortBy([['rate', 'desc'], ['paid', 'desc'], ['carts', 'desc']])
            ->take($limit)
            ->values();

        $names = Product::withTrashed()->whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');
        $rows->each(fn ($row) => $row->product = $names[$row->product_id] ?? null);

        return $rows;
    }

    /**
     * Drop rows older than the retention window.
     */
    public static function prune(): int
    {
        return FunnelEvent::where('created_at', '<', now()->subDays(self::RETENTION_DAYS))->delete();
    }

    public static function sessionHash(): string
    {
        $request = request();
        $seed = $request->hasSession() ? $request->session()->getId() : (string) $request->ip();

        return self::hash($seed);
    }

    protected static function hash(string $seed): string
    {
        $salt = hash_hmac('sha256', now()->toDateString(), (string) config('app.key'));

        return hash('sha256', $seed.$salt);
    }
}
