<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ErrorEvent;
use Illuminate\Http\Request;

class ErrorEventController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'unresolved') === 'all' ? 'all' : 'unresolved';

        $events = ErrorEvent::with('user')
            ->when($status === 'unresolved', fn ($q) => $q->unresolved())
            ->orderByDesc('last_seen_at')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'unresolved' => ErrorEvent::unresolved()->count(),
            'all' => ErrorEvent::count(),
        ];

        return view('admin.errors.index', compact('events', 'status', 'counts'));
    }

    public function show(ErrorEvent $error)
    {
        $error->load('user');

        return view('admin.errors.show', ['event' => $error]);
    }

    public function resolve(ErrorEvent $error)
    {
        $error->forceFill(['resolved_at' => now()])->save();

        return redirect()->route('admin.errors')->with('success', 'Marked as resolved. It comes back if it happens again.');
    }
}
