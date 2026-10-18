<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\OrderingBackRequest;
use Illuminate\Http\Request;

/**
 * "Tell me when it is back" on the banner while ordering is paused: one email when it reopens
 * (ordering:notify-back).
 */
class OrderingBackController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|email|max:255']);
        OrderingBackRequest::firstOrCreate(['email' => strtolower($data['email']), 'notified_at' => null], ['locale' => app()->getLocale()]);

        return back()->with('success', __('We will email you as soon as you can order again.'));
    }
}
