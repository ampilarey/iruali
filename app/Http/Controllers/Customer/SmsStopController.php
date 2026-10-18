<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Support\SmsOptOut;

/**
 * "Stop offers" from a campaign SMS: a page with one button (so link previews in chat apps don't
 * stop anything), then offers come by email only.
 */
class SmsStopController extends Controller
{
    public function show(string $code)
    {
        abort_unless(SmsOptOut::user($code) !== null, 404);

        return view('sms.stop', ['code' => $code, 'done' => false]);
    }

    public function store(string $code)
    {
        $user = SmsOptOut::user($code);
        abort_unless($user !== null, 404);
        SmsOptOut::stop($user);

        return view('sms.stop', ['code' => $code, 'done' => true]);
    }
}
