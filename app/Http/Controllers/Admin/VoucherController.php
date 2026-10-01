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

        $vouchers = Voucher::latest()->paginate(20);

        return view('admin.vouchers.index', compact('vouchers'));
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
}
