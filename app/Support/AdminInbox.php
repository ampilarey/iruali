<?php

namespace App\Support;

use App\Models\ErrorEvent;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * "What needs a human today": one row per thing waiting for staff, with a count and the page
 * that deals with it. Shown at /admin/inbox and as a badge in the admin nav.
 *
 * Other features add rows from a service provider:
 *
 *   AdminInbox::register('disputes', fn () => [
 *       'label' => 'Open disputes', 'count' => Dispute::open()->count(),
 *       'route' => 'admin.disputes', 'severity' => 'warn',
 *   ]);
 *
 * A row's "route" is also what decides whether a staff member sees it (config/staff.php);
 * "url" may be given instead of letting it default to route($route).
 *
 * @phpstan-type Item array{key: string, label: string, count: int, url: string, route: string, severity: 'info'|'warn'|'danger'}
 */
class AdminInbox
{
    /** @var array<string, Closure> */
    protected static array $providers = [];

    protected static bool $builtInRegistered = false;

    /** @var array<int, Item>|null memo for the current request */
    protected static ?array $memo = null;

    public static function register(string $key, Closure $provider): void
    {
        static::$providers[$key] = $provider;
        static::$memo = null;
    }

    public static function forget(string $key): void
    {
        unset(static::$providers[$key]);
        static::$memo = null;
    }

    /**
     * Every row, evaluated once per request. A provider that throws is reported and skipped.
     *
     * @return array<int, Item>
     */
    public static function all(): array
    {
        static::registerBuiltIn();

        if (static::$memo !== null) {
            return static::$memo;
        }

        $items = [];
        foreach (static::$providers as $key => $provider) {
            try {
                $row = $provider();
                $route = (string) ($row['route'] ?? '');
                $items[] = [
                    'key' => $key,
                    'label' => (string) ($row['label'] ?? $key),
                    'count' => max(0, (int) ($row['count'] ?? 0)),
                    'route' => $route,
                    'url' => (string) ($row['url'] ?? ($route && \Illuminate\Support\Facades\Route::has($route) ? route($route) : '#')),
                    'severity' => in_array($row['severity'] ?? null, ['info', 'warn', 'danger'], true) ? $row['severity'] : 'info',
                ];
            } catch (Throwable $e) {
                report($e);
            }
        }

        return static::$memo = $items;
    }

    /**
     * The rows this staff member may open.
     *
     * @return array<int, Item>
     */
    public static function items(?User $user = null): array
    {
        $user ??= auth()->user();

        return array_values(array_filter(static::all(), fn ($item) => $item['route'] === '' || StaffAccess::can($item['route'], $user)));
    }

    /** Total count across the rows this staff member may open (the nav badge). */
    public static function total(?User $user = null): int
    {
        return array_sum(array_column(static::items($user), 'count'));
    }

    /** @internal tests */
    public static function reset(): void
    {
        static::$providers = [];
        static::$builtInRegistered = false;
        static::$memo = null;
    }

    protected static function registerBuiltIn(): void
    {
        if (static::$builtInRegistered) {
            return;
        }
        static::$builtInRegistered = true;

        $builtIn = [
            'sellers_pending' => fn () => [
                'label' => 'Shops awaiting approval',
                'count' => User::whereHas('roles', fn ($q) => $q->where('name', 'seller'))->where('seller_approved', false)->count(),
                'route' => 'admin.sellers', 'severity' => 'warn',
            ],
            'products_pending' => fn () => [
                'label' => 'Products pending review',
                'count' => Product::where('is_active', false)->count(),
                'route' => 'admin.products', 'severity' => 'info',
            ],
            'refunds_due' => fn () => [
                'label' => 'Refunds due',
                'count' => Order::where('refund_status', 'due')->count(),
                'route' => 'admin.returns', 'severity' => 'danger',
            ],
            'returns_open' => fn () => [
                'label' => 'Open return requests',
                'count' => ReturnRequest::whereIn('status', ['requested', 'approved'])->count(),
                'route' => 'admin.returns', 'severity' => 'warn',
            ],
            'bml_failed' => fn () => [
                'label' => 'BML payments failed (24 h)',
                'count' => PaymentTransaction::whereIn('state', PaymentTransaction::FINAL_FAILED)->where('updated_at', '>=', now()->subDay())->count(),
                'route' => 'admin.orders', 'severity' => 'warn',
            ],
            'low_stock_best_sellers' => fn () => [
                'label' => 'Best sellers almost out of stock',
                'count' => static::lowStockBestSellers(),
                'route' => 'admin.products', 'severity' => 'warn',
            ],
            'errors_unresolved' => fn () => [
                'label' => 'Unresolved errors',
                'count' => ErrorEvent::unresolved()->count(),
                'route' => 'admin.errors', 'severity' => 'danger',
            ],
        ];

        // Built-in rows go first; rows registered earlier by other providers keep their place after them
        static::$providers = $builtIn + static::$providers;
    }

    /** Of the 20 best-selling products of the last 30 days, how many have 3 or fewer left. */
    public static function lowStockBestSellers(int $top = 20, int $threshold = 3): int
    {
        $bestSellers = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.created_at', '>=', now()->subDays(30))
            ->where('orders.status', '!=', 'cancelled')
            ->whereNull('orders.deleted_at')
            ->groupBy('order_items.product_id')
            ->orderByRaw('SUM(order_items.quantity) DESC')
            ->limit($top)
            ->pluck('order_items.product_id');

        if ($bestSellers->isEmpty()) {
            return 0;
        }

        return Product::whereIn('id', $bestSellers)->where('stock_quantity', '<=', $threshold)->count();
    }
}
