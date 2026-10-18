<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BankStatementLine;
use App\Models\Order;
use App\Services\PaymentService;
use App\Services\SettlementService;
use Illuminate\Http\Request;

/**
 * Admin → Settlements: import the BML statement, see which money-in lines match orders, confirm
 * bank transfers they pay, match the rest by hand or set them aside. Admins and finance staff.
 */
class SettlementController extends Controller
{
    public function index(Request $request)
    {
        $status = in_array($request->query('status'), ['matched', 'ignored', 'all'], true) ? $request->query('status') : 'unmatched';

        return view('admin.settlements.index', [
            'lines' => BankStatementLine::with('order')->when($status !== 'all', fn ($q) => $q->where('status', $status))
                ->latest('posted_on')->paginate(50)->withQueryString(),
            'status' => $status,
            'counts' => BankStatementLine::query()->toBase()->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function import(Request $request, SettlementService $settlements)
    {
        $request->validate(['statement' => 'required|file|mimes:csv,txt|max:5120']);
        try {
            $result = $settlements->import($request->file('statement')->getRealPath());
        } catch (\RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }
        \App\Support\Audit::record('settlement.imported', null, $result);

        return back()->with('success', "Imported {$result['added']} line(s) of money in, {$result['matched']} matched to orders; {$result['skipped']} were already imported.");
    }

    /**
     * Mark the matched bank-transfer order paid.
     */
    public function confirm(BankStatementLine $line, PaymentService $payments)
    {
        $order = $line->order;
        if (! $order || $order->payment_status === 'paid' || $order->status === 'cancelled' || $order->payment_method === 'bml') {
            return back()->with('error', 'This order can\'t be confirmed from here.');
        }
        if (abs((float) $order->total_amount - (float) $line->amount) > 0.01) {
            return back()->with('error', 'The amount received ('.number_format((float) $line->amount, 2).') is not the order total ('.number_format((float) $order->total_amount, 2).'). Check it on the order page.');
        }
        $payments->confirm($order);

        return back()->with('success', 'Order '.$order->order_number.' marked paid.');
    }

    public function match(Request $request, BankStatementLine $line)
    {
        $data = $request->validate(['order_number' => 'required|string|max:40']);
        $order = Order::where('order_number', strtoupper(trim($data['order_number'])))->first();
        if (! $order) {
            return back()->with('error', 'No order has that number.');
        }
        $line->forceFill(['order_id' => $order->id, 'match_kind' => 'manual', 'status' => 'matched'])->save();

        return back()->with('success', 'Matched to '.$order->order_number.'.');
    }

    public function ignore(BankStatementLine $line)
    {
        $line->forceFill(['status' => $line->status === 'ignored' ? 'unmatched' : 'ignored'])->save();

        return back();
    }
}
