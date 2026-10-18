<?php

namespace App\Support;

use App\Jobs\SendWebhook;
use App\Models\Order;
use App\Models\WebhookEndpoint;
use Throwable;

/**
 * Webhooks out: order.created, order.paid, order.delivered and order.cancelled are POSTed as JSON
 * to each active endpoint that wants them, signed with its secret (X-Iruali-Signature:
 * sha256=HMAC of the body), from the queue with retries. No customer contact details are sent.
 */
class Webhooks
{
    public const EVENTS = [
        'order.created' => 'Order placed',
        'order.paid' => 'Order paid',
        'order.delivered' => 'Order delivered',
        'order.cancelled' => 'Order cancelled',
    ];

    public static function order(string $event, Order $order): void
    {
        try {
            $endpoints = WebhookEndpoint::where('is_active', true)->get()->filter(fn ($e) => in_array($event, (array) $e->events, true));
            if ($endpoints->isEmpty()) {
                return;
            }
            $payload = self::payload($event, $order);
            foreach ($endpoints as $endpoint) {
                SendWebhook::dispatch($endpoint->id, $payload)->afterCommit();
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function payload(string $event, Order $order): array
    {
        $order->loadMissing('items');

        return [
            'event' => $event,
            'sent_at' => now()->toIso8601String(),
            'order' => [
                'id' => $order->id,
                'number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'payment_method' => $order->payment_method,
                'total' => (float) $order->total_amount,
                'delivery' => (float) $order->shipping_amount,
                'currency' => 'MVR',
                'island' => $order->shipping_city,
                'atoll' => $order->shipping_state,
                'created_at' => $order->created_at?->toIso8601String(),
                'items' => $order->items->map(fn ($item) => [
                    'product_id' => $item->product_id, 'variant_sku' => $item->variant_sku, 'quantity' => (int) $item->quantity, 'price' => (float) $item->price,
                ])->values()->all(),
            ],
        ];
    }

    public static function sign(string $body, string $secret): string
    {
        return 'sha256='.hash_hmac('sha256', $body, $secret);
    }
}
