<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\SellerOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Payments for the same shop landing at the same moment (separate PHP processes, real
 * transactions) never share or skip an invoice number.
 */
class GstInvoiceConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    /** No wrapping transaction: the worker processes must see these rows, so the test removes them itself. */
    protected array $connectionsToTransact = [];

    public function test_payments_landing_together_never_share_or_skip_a_shop_invoice_number(): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true) || ! function_exists('proc_open')) {
            $this->markTestSkipped('Needs MySQL/MariaDB row locks and proc_open.');
        }

        $customer = User::factory()->create();
        $shop = User::factory()->create(['is_seller' => true, 'seller_approved' => true, 'business_name' => 'Race Shop']);
        $orderIds = [];

        try {
            foreach (range(1, 12) as $i) {
                $order = Order::factory()->create(['user_id' => $customer->id, 'status' => 'processing', 'payment_status' => 'paid', 'paid_at' => now(), 'total_amount' => 100, 'shipping_amount' => 0]);
                SellerOrder::create(['order_id' => $order->id, 'seller_id' => $shop->id, 'status' => 'processing', 'subtotal' => 100, 'commission_rate' => 10, 'commission_amount' => 10, 'seller_earnings' => 90]);
                $orderIds[] = $order->id;
            }

            // Four processes, three orders each, all starting at the same instant
            $start = microtime(true) + 3;
            $workers = [];
            foreach (array_chunk($orderIds, 3) as $chunk) {
                $command = [PHP_BINARY, base_path('tests/Support/gst-invoice-worker.php'), (string) $start, ...array_map('strval', $chunk)];
                $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $this->workerEnvironment());
                $this->assertIsResource($process);
                $workers[] = [$process, $pipes];
            }
            foreach ($workers as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $output);
                $this->assertSame('done', trim($output));
            }

            $parts = SellerOrder::whereIn('order_id', $orderIds)->get();
            $this->assertSame(range(1, 12), $parts->pluck('invoice_sequence')->sort()->values()->all());
            $this->assertCount(12, $parts->pluck('invoice_number')->filter()->unique());
            $this->assertSame(12, (int) DB::table('invoice_sequences')->where('series', 'shop:'.$shop->id)->value('last_number'));
        } finally {
            SellerOrder::whereIn('order_id', $orderIds)->delete();
            Order::withTrashed()->whereIn('id', $orderIds)->forceDelete();
            DB::table('invoice_sequences')->where('series', 'shop:'.$shop->id)->delete();
            User::withTrashed()->whereIn('id', [$customer->id, $shop->id])->forceDelete();
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
