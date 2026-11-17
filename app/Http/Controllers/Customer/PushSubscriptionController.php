<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\Request;

/**
 * "Get order updates on this device": the browser's push subscription, saved for the signed-in user.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url', 'starts_with:https://'],
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'content_encoding' => 'nullable|string|in:aesgcm,aes128gcm',
        ]);

        // An endpoint belongs to one browser; if another account used it on this device, it moves over
        $subscription = PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id' => $request->user()->id,
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? 'aes128gcm',
            ]
        );

        return response()->json(['ok' => true, 'id' => $subscription->id, 'message' => __('Order updates will be sent to this device.')], 201);
    }

    /**
     * The browser replaced this device's subscription (the service worker's pushsubscriptionchange):
     * the saved one moves to the new address. There is no session here (a service worker has no page
     * or form token), so the proof is the old address itself: a long random URL only that browser and
     * iruali hold. Nothing is created for an address we don't know.
     */
    public function renew(Request $request)
    {
        $data = $request->validate([
            'old_endpoint' => 'required|string|max:500',
            'endpoint' => ['required', 'string', 'max:500', 'url', 'starts_with:https://'],
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'content_encoding' => 'nullable|string|in:aesgcm,aes128gcm',
        ]);

        $subscription = PushSubscription::where('endpoint', $data['old_endpoint'])->first();
        if ($subscription && $data['endpoint'] !== $data['old_endpoint']) {
            PushSubscription::where('endpoint', $data['endpoint'])->whereKeyNot($subscription->id)->delete(); // the address is unique
            $subscription->update([
                'endpoint' => $data['endpoint'],
                'public_key' => $data['keys']['p256dh'],
                'auth_token' => $data['keys']['auth'],
                'content_encoding' => $data['content_encoding'] ?? $subscription->content_encoding,
            ]);
        }

        // The same answer whether or not the old address was known
        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|string|max:500']);

        $request->user()->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['ok' => true, 'message' => __('Order updates are off on this device.')]);
    }
}
