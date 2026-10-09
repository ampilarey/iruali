<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\PreorderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Checkouts racing for the last pre-order units (separate PHP processes, real transactions): never
 * more than the shop's limit is taken, stock never goes below zero, and the ones that lose get the
 * pre-order message rather than a database error.
 */
class PreorderConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /** No wrapping transaction: the worker processes must see these rows, so the test removes them itself. */
    protected array $connectionsToTransact = [];

    public function test_two_checkouts_for_the_last_pre_order_unit_cannot_both_get_it(): void
    {
        $this->race(customers: 2, limit: 1, expectedWinners: 1);
    }

    public function test_four_checkouts_with_in_stock_items_for_the_last_two_pre_order_units(): void
    {
        $this->race(customers: 4, limit: 2, expectedWinners: 2, withStockLine: true);
    }

    protected function race(int $customers, int $limit, int $expectedWinners, bool $withStockLine = false): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) || ! function_exists('proc_open')) {
            $this->markTestSkipped('Needs MySQL/MariaDB row locks and proc_open.');
        }

        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Race Shop']);
        $category = Category::factory()->create(['status' => 'active']);
        $product = Product::factory()->create(['seller_id' => $shop->id, 'category_id' => $category->id, 'brand' => null, 'price' => 100, 'stock_quantity' => 0, 'is_active' => true]);
        $product->forceFill(['preorder_enabled' => true, 'preorder_ship_date' => now()->addWeeks(2)->toDateString(), 'preorder_limit' => $limit])->save();
        $stocked = Product::factory()->create(['seller_id' => $shop->id, 'category_id' => $category->id, 'brand' => null, 'price' => 40, 'stock_quantity' => 20, 'is_active' => true]);

        $buyers = [];
        $carts = [];
        try {
            foreach (range(1, $customers) as $i) {
                $buyer = User::factory()->create();
                $cart = Cart::factory()->create(['user_id' => $buyer->id, 'status' => 'active']);
                CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 100]);
                if ($withStockLine) {
                    CartItem::factory()->create(['cart_id' => $cart->id, 'product_id' => $stocked->id, 'quantity' => 1, 'price' => 40]);
                }
                $buyers[] = $buyer;
                $carts[] = $cart;
            }

            // Every checkout starts at the same instant, each in its own process
            $start = microtime(true) + 3;
            $workers = [];
            foreach ($buyers as $i => $buyer) {
                $command = [PHP_BINARY, base_path('tests/Support/preorder-checkout-worker.php'), (string) $start, (string) $buyer->id, (string) $carts[$i]->id];
                $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->workerEnvironment());
                $this->assertIsResource($process);
                $workers[] = [$process, $pipes];
            }

            $results = [];
            foreach ($workers as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $output);
                $result = json_decode(trim($output), true);
                $this->assertIsArray($result, $output);
                $results[] = $result;
            }

            $winners = array_filter($results, fn ($r) => $r['success']);
            $this->assertCount($expectedWinners, $winners, json_encode($results));
            foreach (array_filter($results, fn ($r) => ! $r['success']) as $lost) {
                // The pre-order message (under the lock), or the cart's own check when the winner was already in
                $this->assertMatchesRegularExpression('/can\'t be pre-ordered any more|pre-order units? of|Available: 0/', $lost['message'], 'lost to a database error instead: '.$lost['message']);
            }

            $this->assertSame($expectedWinners, Order::whereIn('user_id', array_map(fn ($b) => $b->id, $buyers))->count());
            $this->assertSame($limit, app(PreorderService::class)->waitingUnits($product->id));
            $this->assertSame(0, (int) $product->fresh()->stock_quantity);
            if ($withStockLine) {
                $this->assertSame(20 - $expectedWinners, (int) $stocked->fresh()->stock_quantity, 'only the winners took the in-stock item');
            }
        } finally {
            $orderIds = Order::withTrashed()->whereIn('user_id', array_map(fn ($b) => $b->id, $buyers))->pluck('id');
            DB::table('seller_orders')->whereIn('order_id', $orderIds)->delete();
            OrderItem::whereIn('order_id', $orderIds)->delete();
            Order::withTrashed()->whereIn('id', $orderIds)->forceDelete();
            CartItem::whereIn('cart_id', array_map(fn ($c) => $c->id, $carts))->delete();
            Cart::whereIn('id', array_map(fn ($c) => $c->id, $carts))->delete();
            Product::withTrashed()->whereIn('id', [$product->id, $stocked->id])->forceDelete();
            $category->delete();
            User::withTrashed()->whereIn('id', array_merge([$shop->id], array_map(fn ($b) => $b->id, $buyers)))->forceDelete();
        }
    }

    /**
     * The test's own database settings, so the workers use the same database.
     *
     * @return array<string, string>
     */
    protected function workerEnvironment(): array
    {
        $name = config('database.default');
        $connection = config("database.connections.{$name}");

        return array_merge(getenv(), [
            'APP_ENV' => 'testing',
            'DB_CONNECTION' => (string) $name,
            'DB_HOST' => (string) ($connection['host'] ?? '127.0.0.1'),
            'DB_PORT' => (string) ($connection['port'] ?? '3306'),
            'DB_DATABASE' => (string) $connection['database'],
            'DB_USERNAME' => (string) ($connection['username'] ?? 'root'),
            'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
            'CACHE_STORE' => 'array',
            'SESSION_DRIVER' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'MAIL_MAILER' => 'array',
        ]);
    }
}
