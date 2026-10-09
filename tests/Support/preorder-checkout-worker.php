<?php

/*
 * Run by PreorderConcurrencyTest, several at once: boots the app, waits for the shared start time,
 * then places the customer's order from the given cart, as a checkout would, and prints the result.
 *
 *   php tests/Support/preorder-checkout-worker.php <start unix time> <customer id> <cart id>
 */

use App\Models\Cart;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$start = (float) ($argv[1] ?? 0);
$customer = User::findOrFail((int) ($argv[2] ?? 0));
$cart = Cart::with('items.product.seller', 'items.variant')->findOrFail((int) ($argv[3] ?? 0));

while (microtime(true) < $start) {
    usleep(200);
}

$result = app(OrderService::class)->createOrderFromCart($customer, [
    'shipping_address' => 'M. Blue House', 'shipping_city' => 'Hithadhoo', 'shipping_state' => 'Addu',
    'shipping_zip' => '19020', 'shipping_country' => 'Maldives', 'shipping_phone' => '7771234',
    'delivery_zone' => 'islands', 'payment_method' => 'bml',
], $cart);

echo json_encode(['success' => $result['success'], 'message' => $result['message']]);
