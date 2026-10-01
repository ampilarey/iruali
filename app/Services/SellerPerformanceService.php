<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SellerOrder;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * How a shop is doing: weekly sales, best sellers, late shipments, return rate, ratings and repeat
 * customers. Shown to the shop (Seller Centre → Performance) and to admins (per shop and as columns
 * on the sellers list).
 *
 * - A part is "late" when it was not shipped within `late_shipment_days` (Settings, default 3) of the
 *   customer's payment: shipped after the deadline, or still unshipped past it.
 * - Late-shipment and return rates cover the last 90 days; sales cover 12 weeks; ratings 12 months.
 */
class SellerPerformanceService
{
    public const WINDOW_DAYS = 90;

    public function lateShipmentDays(): int
    {
        return max(0, (int) Setting::get('late_shipment_days', 3));
    }

    public function report(User $seller): array
    {
        return [
            'weekly' => $this->weeklySales($seller),
            'best_sellers' => $this->bestSellers($seller),
            'late' => $this->lateShipments($seller),
            'return_rate' => $this->returnRate($seller),
            'ratings' => $this->ratingsByMonth($seller),
            'repeat' => $this->repeatCustomers($seller),
            'late_days' => $this->lateShipmentDays(),
            'window_days' => self::WINDOW_DAYS,
        ];
    }

    /**
     * Revenue and order count per week for the last 12 weeks (oldest first).
     *
     * @return array<int, array{start: Carbon, label: string, revenue: float, orders: int}>
     */
    public function weeklySales(User $seller): array
    {
        $from = now()->startOfWeek()->subWeeks(11);
        $rows = $this->ownItems($seller)
            ->where('orders.created_at', '>=', $from)
            ->get(['order_items.order_id', 'order_items.price', 'order_items.quantity', 'orders.created_at']);

        $byWeek = $rows->groupBy(fn ($r) => Carbon::parse($r->created_at)->startOfWeek()->toDateString());

        return collect(range(0, 11))->map(function ($i) use ($from, $byWeek) {
            $start = $from->copy()->addWeeks($i);
            $rows = $byWeek[$start->toDateString()] ?? collect();

            return [
                'start' => $start,
                'label' => $start->format('j M'),
                'revenue' => round((float) $rows->sum(fn ($r) => $r->price * $r->quantity), 2),
                'orders' => $rows->pluck('order_id')->unique()->count(),
            ];
        })->all();
    }

    /**
     * Top 10 products by revenue, all time.
     *
     * @return Collection<int, array{product: ?Product, name: string, units: int, revenue: float}>
     */
    public function bestSellers(User $seller): Collection
    {
        $rows = $this->ownItems($seller)
            ->select('order_items.product_id', DB::raw('SUM(order_items.quantity) as units'), DB::raw('SUM(order_items.quantity * order_items.price) as revenue'))
            ->groupBy('order_items.product_id')
            ->orderByDesc('revenue')
            ->take(10)
            ->get();
        $products = Product::withTrashed()->whereIn('id', $rows->pluck('product_id'))->get()->keyBy('id');

        return $rows->map(fn ($row) => [
            'product' => $product = $products[$row->product_id] ?? null,
            'name' => $product ? ($product->getTranslation('name', app()->getLocale(), false) ?: $product->getTranslation('name', 'en', false)) : __('Deleted product'),
            'units' => (int) $row->units,
            'revenue' => round((float) $row->revenue, 2),
        ]);
    }

    /**
     * Paid parts of the last 90 days and which of them were shipped late (or are late and unshipped).
     *
     * @return array{count: int, total: int, rate: ?float, parts: Collection<int, SellerOrder>}
     */
    public function lateShipments(User $seller): array
    {
        $parts = SellerOrder::query()
            ->where('seller_orders.seller_id', $seller->id)
            ->where('seller_orders.status', '!=', 'cancelled')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->whereNotNull('orders.paid_at')
            ->whereNull('orders.deleted_at')
            ->where('orders.paid_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->orderByDesc('orders.paid_at')
            ->get(['seller_orders.*', 'orders.paid_at as order_paid_at']);

        $late = $parts->filter(fn ($part) => $this->isLate($part, Carbon::parse($part->order_paid_at)))->values();
        $orders = Order::whereIn('id', $late->pluck('order_id'))->get()->keyBy('id');
        $late->each(fn ($part) => $part->setRelation('order', $orders[$part->order_id] ?? null));

        return [
            'count' => $late->count(),
            'total' => $parts->count(),
            'rate' => $parts->isEmpty() ? null : round($late->count() / $parts->count() * 100, 1),
            'parts' => $late,
        ];
    }

    public function isLate(SellerOrder $part, Carbon $paidAt, ?int $days = null): bool
    {
        $deadline = $paidAt->copy()->addDays($days ?? $this->lateShipmentDays());

        if ($part->shipped_at) {
            return $part->shipped_at->gt($deadline);
        }

        return in_array($part->status, ['pending', 'processing'], true) && now()->gt($deadline);
    }

    /**
     * Returned items (approved or refunded requests) over items sold, last 90 days.
     *
     * @return array{returned: int, sold: int, rate: ?float}
     */
    public function returnRate(User $seller): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $sold = (int) $this->ownItems($seller)->where('orders.created_at', '>=', $since)->sum('order_items.quantity');
        $returned = (int) DB::table('return_request_items')
            ->join('return_requests', 'return_requests.id', '=', 'return_request_items.return_request_id')
            ->join('seller_orders', 'seller_orders.id', '=', 'return_requests.seller_order_id')
            ->where('seller_orders.seller_id', $seller->id)
            ->whereIn('return_requests.status', ['approved', 'refunded'])
            ->where('return_requests.created_at', '>=', $since)
            ->sum('return_request_items.quantity');

        return ['returned' => $returned, 'sold' => $sold, 'rate' => $sold > 0 ? round($returned / $sold * 100, 1) : null];
    }

    /**
     * Average approved rating per month for the last 12 months (oldest first), plus the overall average.
     *
     * @return array{months: array<int, array{month: string, label: string, avg: ?float, count: int}>, overall: ?float, count: int}
     */
    public function ratingsByMonth(User $seller): array
    {
        $from = now()->startOfMonth()->subMonths(11);
        $rows = $this->reviews($seller)
            ->where('product_reviews.created_at', '>=', $from)
            ->selectRaw("DATE_FORMAT(product_reviews.created_at, '%Y-%m') as ym, AVG(product_reviews.rating) as avg_rating, COUNT(*) as n")
            ->groupBy('ym')->get()->keyBy('ym');

        $months = collect(range(0, 11))->map(function ($i) use ($from, $rows) {
            $month = $from->copy()->addMonths($i);
            $row = $rows[$month->format('Y-m')] ?? null;

            return ['month' => $month->format('Y-m'), 'label' => $month->format('M'), 'avg' => $row ? round((float) $row->avg_rating, 2) : null, 'count' => (int) ($row->n ?? 0)];
        })->all();

        $overall = $this->reviews($seller)->selectRaw('AVG(product_reviews.rating) as avg_rating, COUNT(*) as n')->first();

        return ['months' => $months, 'overall' => $overall && $overall->n > 0 ? round((float) $overall->avg_rating, 2) : null, 'count' => (int) ($overall->n ?? 0)];
    }

    /**
     * Customers with two or more (non-cancelled) orders from this shop, as a share of all its customers.
     *
     * @return array{customers: int, repeat: int, rate: ?float}
     */
    public function repeatCustomers(User $seller): array
    {
        $perCustomer = $this->ownItems($seller)
            ->whereNotNull('orders.user_id')
            ->groupBy('orders.user_id')
            ->selectRaw('orders.user_id, COUNT(DISTINCT orders.id) as orders_count')
            ->pluck('orders_count', 'orders.user_id');

        $customers = $perCustomer->count();
        $repeat = $perCustomer->filter(fn ($n) => (int) $n >= 2)->count();

        return ['customers' => $customers, 'repeat' => $repeat, 'rate' => $customers > 0 ? round($repeat / $customers * 100, 1) : null];
    }

    /**
     * Late-shipment rate (last 90 days) and average rating for several shops at once, for the admin list.
     *
     * @param  iterable<int>  $sellerIds
     * @return array<int, array{late_count: int, shipped_total: int, late_rate: ?float, rating: ?float, reviews: int}>
     */
    public function summaryFor(iterable $sellerIds): array
    {
        $ids = collect($sellerIds)->map(fn ($id) => (int) $id)->all();
        $days = $this->lateShipmentDays();

        $parts = SellerOrder::query()
            ->whereIn('seller_orders.seller_id', $ids)
            ->where('seller_orders.status', '!=', 'cancelled')
            ->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->whereNotNull('orders.paid_at')
            ->whereNull('orders.deleted_at')
            ->where('orders.paid_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->get(['seller_orders.seller_id', 'seller_orders.status', 'seller_orders.shipped_at', 'orders.paid_at as order_paid_at'])
            ->groupBy('seller_id');

        $ratings = ProductReview::query()
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->whereIn('products.seller_id', $ids)
            ->where('product_reviews.is_approved', true)
            ->groupBy('products.seller_id')
            ->selectRaw('products.seller_id, AVG(product_reviews.rating) as avg_rating, COUNT(*) as n')
            ->get()->keyBy('seller_id');

        $out = [];
        foreach ($ids as $id) {
            $shopParts = $parts[$id] ?? collect();
            $late = $shopParts->filter(fn ($p) => $this->isLate($p, Carbon::parse($p->order_paid_at), $days))->count();
            $out[$id] = [
                'late_count' => $late,
                'shipped_total' => $shopParts->count(),
                'late_rate' => $shopParts->isEmpty() ? null : round($late / $shopParts->count() * 100, 1),
                'rating' => isset($ratings[$id]) ? round((float) $ratings[$id]->avg_rating, 2) : null,
                'reviews' => (int) ($ratings[$id]->n ?? 0),
            ];
        }

        return $out;
    }

    /**
     * Order items for the shop's products on non-cancelled orders.
     */
    protected function ownItems(User $seller)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->where('products.seller_id', $seller->id)
            ->where('orders.status', '!=', 'cancelled')
            ->whereNull('orders.deleted_at');
    }

    protected function reviews(User $seller)
    {
        return ProductReview::query()
            ->join('products', 'products.id', '=', 'product_reviews.product_id')
            ->where('products.seller_id', $seller->id)
            ->where('product_reviews.is_approved', true);
    }
}
