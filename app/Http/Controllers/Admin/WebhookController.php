<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Admin → Webhooks: addresses other systems (accounting, a warehouse, Zapier) give to be told
 * about orders, each with its own signing secret, a test send and the last result. Admins only.
 */
class WebhookController extends Controller
{
    public function index()
    {
        return view('admin.webhooks.index', ['endpoints' => WebhookEndpoint::latest()->get()]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'url' => ['required', 'url:https', 'max:255'],
            'events' => 'required|array|min:1',
            'events.*' => [Rule::in(array_keys(Webhooks::EVENTS))],
        ]);
        WebhookEndpoint::create($data + ['secret' => Str::random(40), 'is_active' => true]);
        \App\Support\Audit::record('webhook.added', null, ['url' => $data['url']]);

        return back()->with('success', 'Webhook added. Give its secret to the other system to check the signature.');
    }

    public function toggle(WebhookEndpoint $endpoint)
    {
        $endpoint->forceFill(['is_active' => ! $endpoint->is_active])->save();

        return back();
    }

    public function destroy(WebhookEndpoint $endpoint)
    {
        $endpoint->delete();
        \App\Support\Audit::record('webhook.removed', null, ['url' => $endpoint->url]);

        return back()->with('success', 'Webhook removed.');
    }

    /**
     * Send a sample order.created now (not queued) and show the answer.
     */
    public function test(WebhookEndpoint $endpoint)
    {
        $order = Order::latest('id')->first() ?? new Order(['order_number' => 'ORD-TEST', 'status' => 'pending', 'payment_status' => 'pending', 'total_amount' => 100]);
        $payload = Webhooks::payload('order.created', $order) + ['test' => true];
        $body = (string) json_encode($payload);
        try {
            $response = Http::timeout(10)->withBody($body, 'application/json')->withHeaders(['X-Iruali-Event' => 'order.created', 'X-Iruali-Signature' => Webhooks::sign($body, $endpoint->secret)])->post($endpoint->url);
            $endpoint->forceFill(['last_status' => $response->status(), 'last_error' => $response->successful() ? null : 'HTTP '.$response->status(), 'last_sent_at' => now()])->save();

            return back()->with($response->successful() ? 'success' : 'error', 'Test sent: HTTP '.$response->status().'.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Test failed: '.mb_substr($e->getMessage(), 0, 200));
        }
    }
}
