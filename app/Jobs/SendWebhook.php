<?php

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use App\Support\Webhooks;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * POSTs one webhook to one endpoint, retried a few times with growing waits when it fails.
 */
class SendWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300, 900, 3600];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public int $endpointId, public array $payload) {}

    public function handle(): void
    {
        $endpoint = WebhookEndpoint::find($this->endpointId);
        if (! $endpoint || ! $endpoint->is_active) {
            return;
        }
        $body = (string) json_encode($this->payload);
        try {
            $response = Http::timeout(10)->withBody($body, 'application/json')->withHeaders([
                'X-Iruali-Event' => (string) ($this->payload['event'] ?? ''),
                'X-Iruali-Signature' => Webhooks::sign($body, $endpoint->secret),
            ])->post($endpoint->url);
            $endpoint->forceFill(['last_status' => $response->status(), 'last_error' => $response->successful() ? null : 'HTTP '.$response->status(), 'last_sent_at' => now()])->save();
            if (! $response->successful()) {
                $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
            }
        } catch (\Throwable $e) {
            $endpoint->forceFill(['last_status' => null, 'last_error' => mb_substr($e->getMessage(), 0, 250), 'last_sent_at' => now()])->save();
            $this->release($this->backoff()[min($this->attempts() - 1, 3)]);
        }
    }
}
