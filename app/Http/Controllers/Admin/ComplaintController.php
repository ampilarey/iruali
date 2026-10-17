<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Models\OrderFeedback;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Admin → Complaints: complaints from the public form with their status and staff notes, and the
 * order ratings (average over 30 days, the latest low ones).
 */
class ComplaintController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status', 'open');
        $query = Complaint::query()->latest();
        match ($status) {
            'open' => $query->open(),
            'all' => null,
            default => $query->where('status', $status),
        };

        $recent = OrderFeedback::where('created_at', '>=', now()->subDays(30));

        return view('admin.complaints.index', [
            'complaints' => $query->paginate(25)->withQueryString(),
            'status' => $status,
            'counts' => Complaint::query()->toBase()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'ratings' => ['count' => (clone $recent)->count(), 'average' => round((float) (clone $recent)->avg('rating'), 1)],
            'lowRatings' => OrderFeedback::with('order')->where('rating', '<=', 2)->latest()->take(10)->get(),
        ]);
    }

    public function show(Complaint $complaint)
    {
        return view('admin.complaints.show', ['complaint' => $complaint->load('order', 'user')]);
    }

    public function update(Request $request, Complaint $complaint)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(Complaint::STATUSES))],
            'note' => 'nullable|string|max:2000',
        ]);

        if (filled($data['note'] ?? null)) {
            $complaint->notes = array_merge((array) $complaint->notes, [['by' => $request->user()->name, 'at' => now()->toDateTimeString(), 'body' => $data['note']]]);
        }
        if ($complaint->status !== $data['status']) {
            $complaint->resolved_at = in_array($data['status'], Complaint::OPEN, true) ? null : now();
        }
        $complaint->status = $data['status'];
        $complaint->save();

        return back()->with('success', 'Complaint updated.');
    }

    public function photo(Complaint $complaint, int $index)
    {
        $path = ((array) $complaint->photos)[$index] ?? null;
        abort_unless($path && Storage::disk(Complaint::DISK)->exists($path), 404);

        return Storage::disk(Complaint::DISK)->response($path);
    }
}
