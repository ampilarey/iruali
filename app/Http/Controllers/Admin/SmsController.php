<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use App\Services\Sms\PhoneNumber;
use App\Services\Sms\SmsManager;
use Illuminate\Http\Request;

/**
 * Admin → SMS: what went out, whether the gateway accepted it, and a test send.
 */
class SmsController extends Controller
{
    public function index(SmsManager $sms)
    {
        $messages = SmsMessage::latest('id')->paginate(50);
        $counts = SmsMessage::where('created_at', '>=', now()->subDays(30))->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status');

        return view('admin.sms.index', ['messages' => $messages, 'counts' => $counts, 'driver' => $sms->driverName(), 'live' => $sms->isLive()]);
    }

    public function test(Request $request, SmsManager $sms)
    {
        $data = $request->validate([
            'to' => ['required', 'string', 'max:30', fn ($attr, $value, $fail) => PhoneNumber::isValid($value) ?: $fail('Enter a Maldivian mobile number (7 digits starting with 7 or 9).')],
            'message' => 'required|string|max:320',
        ]);

        $result = $sms->send($data['to'], $data['message']);

        return back()->with($result->success ? 'success' : 'error', match ($result->status) {
            'sent' => 'Message accepted by the gateway.',
            'logged' => 'SMS driver is "log": the message was written to the log and the list below, nothing was sent.',
            default => 'The gateway did not accept the message: '.($result->providerResponse ?: $result->status),
        });
    }
}
