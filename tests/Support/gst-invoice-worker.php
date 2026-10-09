<?php

/*
 * Run by GstInvoiceConcurrencyTest, several at once: boots the app, waits for the shared start
 * time, then numbers the invoices of the given (paid) orders, as a payment landing would.
 *
 *   php tests/Support/gst-invoice-worker.php <start unix time> <order id> [<order id> ...]
 */

use App\Models\Order;
use App\Services\GstService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$start = (float) ($argv[1] ?? 0);
while (microtime(true) < $start) {
    usleep(500);
}

foreach (array_slice($argv, 2) as $orderId) {
    app(GstService::class)->assignInvoiceNumbers(Order::findOrFail((int) $orderId));
}

echo 'done';
