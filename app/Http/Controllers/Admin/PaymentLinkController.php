<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\PaymentLink;
use App\Services\Sms\PhoneNumber;
use App\Support\Audit;
use App\Support\PayLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/**
 * Admin → order → Payment → "Send payment link": texts and/or emails the customer a link to pay the
 * unpaid order by card or bank transfer (App\Support\PayLink).
 */
class PaymentLinkController extends Controller
{
    public function send(Request $request, Order $order)
    {
        $data = $request->validate(['by' => 'required|array|min:1', 'by.*' => 'in:sms,mail']);
        if (! PayLink::payable($order)) {
            return back()->with('error', 'This order is not waiting for payment.');
        }

        $phone = PhoneNumber::normalize($order->shipping_phone ?: $order->user?->phone);
        $email = $order->customerEmail();
        $routes = array_filter([
            'sms' => in_array('sms', $data['by'], true) ? $phone : null,
            'mail' => in_array('mail', $data['by'], true) ? $email : null,
        ]);
        if (! $routes) {
            return back()->with('error', 'This order has no mobile number or email to send the link to.');
        }

        $url = PayLink::url($order);
        $notifiable = Notification::route(array_key_first($routes), reset($routes));
        foreach (array_slice($routes, 1, null, true) as $channel => $to) {
            $notifiable->route($channel, $to);
        }
        $notifiable->notify(new PaymentLink($order, $url));

        $order->forceFill(['payment_link_sent_at' => now()])->save();
        Audit::record('payment.link_sent', $order, ['by' => array_keys($routes), 'order_number' => $order->order_number]);

        return back()->with('success', 'Payment link sent by '.implode(' and ', array_map(fn ($c) => $c === 'sms' ? 'SMS to '.$phone : 'email to '.$email, array_keys($routes))).'. It works for '.PayLink::HOURS.' hours.')
            ->with('payment_link', $url);
    }
}
