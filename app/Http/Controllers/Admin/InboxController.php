<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminInbox;
use Illuminate\Http\Request;

class InboxController extends Controller
{
    public function index(Request $request)
    {
        $items = AdminInbox::items($request->user());

        return view('admin.inbox.index', [
            'items' => $items,
            'total' => array_sum(array_column($items, 'count')),
        ]);
    }
}
