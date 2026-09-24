<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class NewsletterController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['email' => 'required|email:rfc|max:255']);

        NewsletterSubscriber::firstOrCreate(['email' => mb_strtolower($data['email'])], ['locale' => app()->getLocale()]);

        NotificationService::success(__('You are subscribed. Watch your inbox for deals.'));

        return back();
    }
}
