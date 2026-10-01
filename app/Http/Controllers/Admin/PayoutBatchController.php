<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PayoutBatch;
use App\Services\PayoutService;
use App\Support\BankFileFormat;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Admin → Payouts → Batches: pay several shops in one bank bulk transfer.
 */
class PayoutBatchController extends Controller
{
    public function index()
    {
        $batches = PayoutBatch::with('creator')->latest()->paginate(20);

        return view('admin.payouts.batches.index', compact('batches'));
    }

    public function create(PayoutService $payouts)
    {
        $candidates = $payouts->batchCandidates();

        return view('admin.payouts.batches.create', compact('candidates'));
    }

    public function store(Request $request, PayoutService $payouts)
    {
        $data = $request->validate([
            'sellers' => 'required|array|min:1',
            'sellers.*' => 'integer',
            'notes' => 'nullable|string|max:1000',
        ]);

        $batch = $payouts->createBatch(array_map('intval', $data['sellers']), $request->user(), $data['notes'] ?? null);
        if (! $batch) {
            return back()->with('error', __('No batch was made: none of the chosen shops has both a bank account and something to pay.'));
        }

        return redirect()->route('admin.payout-batches.show', $batch)
            ->with('success', __('Batch :reference drafted with :count payouts.', ['reference' => $batch->reference, 'count' => $batch->count]));
    }

    public function show(PayoutBatch $batch)
    {
        $batch->load(['creator', 'payouts.seller.bankAccount']);
        $lines = $batch->payouts->sortBy(fn ($p) => mb_strtolower($p->seller?->shopName() ?? ''))->values();

        return view('admin.payouts.batches.show', compact('batch', 'lines'));
    }

    /**
     * The bank file. Downloading it marks a draft as exported.
     */
    public function download(PayoutBatch $batch, PayoutService $payouts)
    {
        if (! $batch->isOpen()) {
            return back()->with('error', __('This batch is :status, so there is no bank file to download.', ['status' => $batch->status]));
        }

        $content = $payouts->exportBatch($batch);

        return response($content, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.BankFileFormat::filename($batch).'"',
        ]);
    }

    public function markPaid(Request $request, PayoutBatch $batch, PayoutService $payouts)
    {
        $data = $request->validate([
            'bank_reference' => 'required|string|max:100',
            'paid_on' => 'required|date|before_or_equal:today',
        ]);

        if (! $payouts->markBatchPaid($batch, $data['bank_reference'], Carbon::parse($data['paid_on']), $request->user())) {
            return back()->with('error', __('This batch is :status and cannot be marked paid.', ['status' => $batch->fresh()->status]));
        }

        return back()->with('success', __('Batch :reference marked paid. Every shop in it has been told.', ['reference' => $batch->reference]));
    }

    public function cancel(PayoutBatch $batch, PayoutService $payouts)
    {
        if (! $payouts->cancelBatch($batch)) {
            return back()->with('error', __('Only a draft batch can be cancelled.'));
        }

        return redirect()->route('admin.payout-batches.index')->with('success', __('Batch :reference cancelled; its earnings are available again.', ['reference' => $batch->reference]));
    }
}
