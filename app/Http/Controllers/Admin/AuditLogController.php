<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'action' => 'nullable|string|max:60',
            'user' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $logs = AuditLog::with('user')
            ->when($filters['action'] ?? null, fn ($q, $a) => $q->where('action', $a))
            ->when($filters['user'] ?? null, fn ($q, $u) => $q->where('user_id', $u))
            ->when($filters['from'] ?? null, fn ($q, $d) => $q->where('created_at', '>=', \Carbon\Carbon::parse($d)->startOfDay()))
            ->when($filters['to'] ?? null, fn ($q, $d) => $q->where('created_at', '<=', \Carbon\Carbon::parse($d)->endOfDay()))
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString();

        $actors = User::whereIn('id', AuditLog::query()->select('user_id')->whereNotNull('user_id')->distinct())->orderBy('name')->get(['id', 'name']);

        return view('admin.audit.index', ['logs' => $logs, 'filters' => $filters, 'actors' => $actors, 'actions' => Audit::ACTIONS]);
    }
}
