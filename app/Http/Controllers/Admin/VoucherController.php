<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Voucher;
use App\Http\Requests\StoreVoucherRequest;
use App\Http\Requests\UpdateVoucherRequest;

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
        Voucher::create($request->all());
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
        $voucher->update($request->all());
        return redirect()->route('admin.vouchers.index')->with('success', 'Voucher updated successfully.');
    }

    public function destroy(Voucher $voucher)
    {
        $this->authorize('delete', $voucher);
        $voucher->delete();
        return redirect()->route('admin.vouchers.index')->with('success', 'Voucher deleted successfully.');
    }
} 