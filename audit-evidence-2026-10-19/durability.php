<?php

/**
 * Local durability drills against the disposable audit database. Not part of the app.
 * Usage: php durability.php <points|stock|confirm|return>
 * The parent (php durability.php all) spawns two children at once.
 */

use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\ManualPaymentService;
use App\Services\OrderService;
use App\Services\PointsService;
use App\Services\ReturnService;
use Illuminate\Support\Facades\DB;

$mode = $argv[1] ?? 'all';

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
DB::connection()->getPdo()->exec('PRAGMA busy_timeout = 8000');

function barrier(string $name): void
{
    $flag = "/tmp/audit-barrier-{$name}";
    $wait = "/tmp/audit-ready-{$name}-".getmypid();
    touch($wait);
    $deadline = microtime(true) + 15;
    while (microtime(true) < $deadline && ! file_exists($flag)) {
        usleep(20000);
    }
}

function out(string $line): void
{
    fwrite(STDOUT, $line.PHP_EOL);
}

if ($mode === 'points-child') {
    barrier('points');
    $user = User::find((int) $argv[2]);
    try {
        app(PointsService::class)->record($user, -100, 'redeemed', null, 'audit race');
        out('ok '.getmypid());
    } catch (Throwable $e) {
        out('fail '.getmypid().' '.$e->getMessage());
    }
    exit(0);
}

if ($mode === 'guard-child') {
    barrier('guard');
    try {
        $svc = app(OrderService::class);
        $method = new ReflectionMethod($svc, 'takeStock');
        $taken = $method->invoke($svc, 3, null, 1);
        out('taken '.$taken.' '.getmypid());
    } catch (Throwable $e) {
        out('fail '.getmypid().' '.$e->getMessage());
    }
    exit(0);
}

if ($mode === 'stock-child') {
    barrier('stock');
    $user = User::find((int) $argv[2]);
    $cart = Cart::find((int) $argv[3]);
    try {
        $result = app(OrderService::class)->createOrderFromCart($user, [
            'shipping_address' => 'Race house',
            'shipping_city' => 'Feridhoo',
            'shipping_state' => 'Alif Alif',
            'shipping_country' => 'Maldives',
            'shipping_zip' => null,
            'shipping_phone' => '7911400',
            'payment_method' => 'bml',
            'delivery_zone' => 'islands',
            'fulfilment' => [2 => 'deliver'],
        ], $cart);
        out(($result['success'] ? 'ok' : 'fail').' '.getmypid().' '.($result['message'] ?? ($result['order']->order_number ?? '')));
    } catch (Throwable $e) {
        out('fail '.getmypid().' '.$e->getMessage());
    }
    exit(0);
}

if ($mode === 'confirm-child') {
    barrier('confirm');
    $order = Order::find((int) $argv[2]);
    $admin = User::find(1);
    try {
        $ok = app(ManualPaymentService::class)->confirm($order, $admin, 'race');
        out(($ok ? 'ok' : 'fail').' '.getmypid());
    } catch (Throwable $e) {
        out('fail '.getmypid().' '.$e->getMessage());
    }
    exit(0);
}

if ($mode === 'return-child') {
    $return = ReturnRequest::find((int) $argv[2]);
    barrier('return');
    try {
        $ok = app(ReturnService::class)->approve($return, 10, true, 'audit race', User::find(1));
        out(($ok ? 'ok' : 'fail').' '.getmypid());
    } catch (Throwable $e) {
        out('fail '.getmypid().' '.$e->getMessage());
    }
    exit(0);
}

function spawn(string $mode, array $args, string $barrier): array
{
    @unlink("/tmp/audit-barrier-{$barrier}");
    foreach (glob("/tmp/audit-ready-{$barrier}-*") ?: [] as $f) {
        @unlink($f);
    }
    $procs = [];
    foreach ($args as $i => $arg) {
        $cmd = ['/tmp/iruali-audit-env.sh', 'php', __FILE__, $mode, ...array_map('strval', (array) $arg)];
        $procs[$i] = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, '/workspace');
        $procs[$i.'_pipes'] = $pipes;
    }
    $deadline = microtime(true) + 15;
    $expected = count($args);
    while (microtime(true) < $deadline && count(glob("/tmp/audit-ready-{$barrier}-*") ?: []) < $expected) {
        usleep(20000);
    }
    touch("/tmp/audit-barrier-{$barrier}");
    $lines = [];
    foreach ($args as $i => $_) {
        $pipes = $procs[$i.'_pipes'];
        $lines[] = trim(stream_get_contents($pipes[1]).' '.stream_get_contents($pipes[2]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($procs[$i]);
    }

    return $lines;
}

$report = [];

if ($mode === 'all' || $mode === 'points') {
    $user = User::firstOrCreate(['email' => 'points-audit@example.com'], [
        'name' => 'Points Audit', 'password' => bcrypt('password'), 'phone' => '+9607911401',
        'has_password' => true, 'phone_verified_at' => now(), 'status' => 'active', 'is_active' => true,
        'referral_code' => 'POINTSAUD1',
    ]);
    $user->forceFill(['loyalty_points' => 100])->save();
    $before = (int) $user->fresh()->loyalty_points;
    $lines = spawn('points-child', [[$user->id], [$user->id]], 'points');
    $after = (int) $user->fresh()->loyalty_points;
    $report['points'] = ['before' => $before, 'after' => $after, 'children' => $lines, 'non_negative' => $after >= 0, 'one_failed' => count(array_filter($lines, fn ($l) => str_starts_with($l, 'fail'))) >= 1];
}

if ($mode === 'all' || $mode === 'stock') {
    $product = Product::find(3);
    $original = (int) $product->stock_quantity;
    $product->forceFill(['stock_quantity' => 1])->save();
    $buyers = [];
    foreach ([2, 3] as $n) {
        $buyer = User::firstOrCreate(['email' => "stock-audit-{$n}@example.com"], [
            'name' => "Stock Audit {$n}", 'password' => bcrypt('password'), 'phone' => '+960791140'.$n,
            'has_password' => true, 'phone_verified_at' => now(), 'status' => 'active', 'is_active' => true,
            'referral_code' => 'STOCKAUD'.$n,
        ]);
        $cart = Cart::create(['user_id' => $buyer->id, 'session_id' => 'audit-stock-'.$n, 'status' => 'active']);
        $cart->items()->create(['product_id' => 3, 'quantity' => 1, 'price' => 85]);
        $buyers[] = [$buyer->id, $cart->id];
    }
    $lines = spawn('stock-child', $buyers, 'stock');
    $left = (int) $product->fresh()->stock_quantity;
    $report['stock'] = ['set_to' => 1, 'original' => $original, 'left' => $left, 'children' => $lines, 'not_negative' => $left >= 0, 'one_failed' => count(array_filter($lines, fn ($l) => str_starts_with($l, 'fail'))) >= 1];
    $product->forceFill(['stock_quantity' => max(0, $original - (1 - $left))])->save();
    $report['stock']['restored_to'] = (int) $product->fresh()->stock_quantity;
}

if ($mode === 'guard') {
    $product = Product::find(3);
    $before = (int) $product->stock_quantity;
    $product->forceFill(['stock_quantity' => 1])->save();
    $lines = spawn('guard-child', [[], []], 'guard');
    $left = (int) $product->fresh()->stock_quantity;
    $product->forceFill(['stock_quantity' => $before - (1 - $left)])->save();
    $report['stock_guard'] = ['before_set' => $before, 'left_after_race' => $left, 'restored_to' => (int) $product->fresh()->stock_quantity, 'children' => $lines];
}

if ($mode === 'all' || $mode === 'confirm') {
    $order = Order::find(1);
    $earnedBefore = (int) DB::table('points_transactions')->where('order_id', 1)->where('type', 'earned')->count();
    $lines = spawn('confirm-child', [[1], [1]], 'confirm');
    $fresh = $order->fresh();
    $earnedAfter = (int) DB::table('points_transactions')->where('order_id', 1)->where('type', 'earned')->count();
    $report['confirm'] = [
        'children' => $lines,
        'payment_status' => $fresh->payment_status,
        'earned_rows_before' => $earnedBefore,
        'earned_rows_after' => $earnedAfter,
        'points_balance' => (int) User::find($fresh->user_id)->loyalty_points,
    ];
}

if ($mode === 'all' || $mode === 'return') {
    $product = Product::find(14);
    $stockBefore = (int) $product->stock_quantity;
    $return = ReturnRequest::create([
        'order_id' => 1, 'seller_order_id' => 1, 'user_id' => 8, 'status' => 'requested',
        'reason' => 'changed_mind', 'items_value' => 10, 'details' => 'audit race',
    ]);
    $return->items()->create(['order_item_id' => 1, 'quantity' => 1, 'refund_value' => 10]);
    $lines = spawn('return-child', [[$return->id], [$return->id]], 'return');
    $report['return'] = [
        'children' => $lines,
        'status' => $return->fresh()->status,
        'stock_before' => $stockBefore,
        'stock_after' => (int) $product->fresh()->stock_quantity,
        'restocked_once' => (int) $product->fresh()->stock_quantity === $stockBefore + 1,
    ];
}

echo json_encode($report, JSON_PRETTY_PRINT), PHP_EOL;
