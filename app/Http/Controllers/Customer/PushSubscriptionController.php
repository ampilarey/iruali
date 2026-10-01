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

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|string|max:500']);

        $request->user()->pushSubscriptions()->where('endpoint', $data['endpoint'])->delete();

        return response()->json(['ok' => true, 'message' => __('Order updates are off on this device.')]);
    }
}
