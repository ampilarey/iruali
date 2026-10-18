<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVoucherRequest;
use App\Http\Requests\UpdateVoucherRequest;
use App\Models\Voucher;

class VoucherController extends Controller
{
    public function index()
    {
        $this->authorize('viewAny', Voucher::class);

        // Card batches are listed once each, not code by code
        $vouchers = Voucher::whereNull('batch')->latest()->paginate(20);
        $batches = Voucher::whereNotNull('batch')->toBase()
            ->selectRaw('batch, count(*) as cards, sum(case when used_count > 0 then 1 else 0 end) as used, max(type) as type, max(amount) as amount, max(valid_until) as valid_until')
            ->groupBy('batch')->orderByDesc('batch')->get();

        return view('admin.vouchers.index', compact('vouchers', 'batches'));
    }

    public function create()
    {
        $this->authorize('create', Voucher::class);

        return view('admin.vouchers.create');
    }

    public function store(StoreVoucherRequest $request)
    {
        $this->authorize('create', Voucher::class);
        $voucher = Voucher::create($request->validated());
        \App\Support\Audit::record('voucher.created', $voucher, ['code' => $voucher->code]);

        return redirect()->route('admin.vouchers.index')->with('success', 'Voucher created successfully.');
    }

    public function edit(Voucher $voucher)
    {
        $this->authorize('update', $voucher);

        return view('admin.vouchers.edit', compact('voucher'));
    }

    public function update(UpdateVoucherRequest $request, Voucher $voucher)
    {
        $this->authorize('update', $voucher);
        $voucher->update($request->validated());
        \App\Support\Audit::record('voucher.updated', $voucher, ['code' => $voucher->code, 'changed' => array_keys($voucher->getChanges())]);

        return redirect()->route('admin.vouchers.index')->with('success', 'Voucher updated successfully.');
    }

    public function destroy(Voucher $voucher)
    {
        $this->authorize('delete', $voucher);
        $voucher->delete();
        \App\Support\Audit::record('voucher.deleted', $voucher, ['code' => $voucher->code]);

        return redirect()->route('admin.vouchers.index')->with('success', 'Voucher deleted successfully.');
    }

    /**
     * A batch of printed discount cards: N single-use codes with the same value and dates.
     */
    public function batch(\Illuminate\Http\Request $request)
    {
        $this->authorize('create', Voucher::class);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'count' => 'required|integer|min:1|max:1000',
            'type' => 'required|in:fixed,percent',
            'amount' => 'required|numeric|min:1|max:100000',
            'min_order' => 'nullable|numeric|min:0',
            'valid_until' => 'required|date|after:today',
        ], ['name.regex' => 'Use letters and numbers only, e.g. EID26.']);
        $batch = strtoupper($data['name']).'-'.now()->format('ymd');
        abort_if(Voucher::where('batch', $batch)->exists(), 422, 'A batch with this name was already made today.');

        for ($i = 0; $i < (int) $data['count']; $i++) {
            do {
                $code = strtoupper($data['name']).'-'.strtoupper(\Illuminate\Support\Str::random(6));
            } while (Voucher::where('code', $code)->exists());
            Voucher::create([
                'code' => $code, 'type' => $data['type'], 'amount' => $data['amount'], 'min_order' => $data['min_order'] ?? null,
                'max_uses' => 1, 'valid_until' => \Illuminate\Support\Carbon::parse($data['valid_until'])->endOfDay(), 'is_active' => true, 'batch' => $batch,
            ]);
        }
        \App\Support\Audit::record('voucher.batch', null, ['batch' => $batch, 'count' => (int) $data['count']]);

        return redirect()->route('admin.vouchers.index')->with('success', $data['count'].' cards made in batch '.$batch.'. Print them or download the codes below.');
    }

    public function printBatch(string $batch)
    {
        $this->authorize('viewAny', Voucher::class);
        $cards = Voucher::where('batch', $batch)->orderBy('id')->get();
        abort_if($cards->isEmpty(), 404);

        return view('admin.vouchers.cards', compact('cards', 'batch'));
    }

    public function exportBatch(string $batch)
    {
        $this->authorize('viewAny', Voucher::class);
        $cards = Voucher::where('batch', $batch)->orderBy('id')->get();
        abort_if($cards->isEmpty(), 404);

        return response()->streamDownload(function () use ($cards) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['code', 'type', 'amount', 'min_order', 'valid_until', 'used']);
            foreach ($cards as $card) {
                fputcsv($out, [$card->code, $card->type, $card->amount, $card->min_order, $card->valid_until?->toDateString(), $card->used_count > 0 ? 'yes' : 'no']);
            }
            fclose($out);
        }, 'iruali-cards-'.$batch.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
