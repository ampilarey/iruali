<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\SellerPayout;
use App\Models\Setting;
use App\Services\FinanceService;
use App\Services\GstReportService;
use App\Services\GstService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Finance: iruali's monthly profit and loss, its expenses (with receipts, and monthly
 * ones copied automatically), and files for Xero: commission invoices as sales, expenses as bills.
 */
class FinanceController extends Controller
{
    public function index(Request $request, FinanceService $finance)
    {
        [, , $month] = GstReportService::month($request->query('month'));

        return view('admin.finance.index', ['pnl' => $finance->profitAndLoss($month), 'cardFeePercent' => (float) Setting::get('card_fee_percent', 0)]);
    }

    public function storeExpense(Request $request)
    {
        $data = $request->validate([
            'spent_on' => 'required|date',
            'category' => ['required', Rule::in(array_keys(Expense::CATEGORIES))],
            'description' => 'required|string|max:200',
            'supplier' => 'nullable|string|max:120',
            'amount' => 'required|numeric|min:0.01|max:10000000',
            'gst_amount' => 'nullable|numeric|min:0|lte:amount',
            'recurring' => 'nullable|boolean',
            'receipt' => 'nullable|file|mimes:pdf,jpg,jpeg,png,webp|max:8192',
        ]);
        $path = $request->file('receipt')?->store('expense-receipts', Expense::DISK);
        Expense::create(collect($data)->except('receipt')->all() + ['gst_amount' => $data['gst_amount'] ?? 0, 'recurring' => $request->boolean('recurring'), 'receipt_path' => $path, 'created_by' => $request->user()->id]);

        return redirect()->route('admin.finance', ['month' => substr($data['spent_on'], 0, 7)])->with('success', 'Expense added.');
    }

    public function destroyExpense(Expense $expense)
    {
        if ($expense->receipt_path) {
            Storage::disk(Expense::DISK)->delete($expense->receipt_path);
        }
        $expense->delete();

        return back()->with('success', 'Expense removed.');
    }

    public function receipt(Expense $expense)
    {
        abort_unless($expense->receipt_path && Storage::disk(Expense::DISK)->exists($expense->receipt_path), 404);

        return Storage::disk(Expense::DISK)->response($expense->receipt_path);
    }

    public function settings(Request $request)
    {
        abort_unless($request->user()->hasRole('admin'), 403); // finance staff see the numbers; the codes are for admins
        $data = $request->validate([
            'card_fee_percent' => 'required|numeric|min:0|max:10',
            'xero_sales_account' => 'required|string|max:20',
            'xero_expense_account' => 'required|string|max:20',
            'xero_tax_sales' => 'required|string|max:40',
            'xero_tax_purchases' => 'required|string|max:40',
        ]);
        Setting::set($data);

        return back()->with('success', 'Finance settings saved.');
    }

    public function exportPnl(Request $request, FinanceService $finance): StreamedResponse
    {
        [, , $month] = GstReportService::month($request->query('month'));
        $pnl = $finance->profitAndLoss($month);

        return $this->csv('iruali-profit-and-loss-'.$month.'.csv', function ($out) use ($pnl) {
            fputcsv($out, ['line', 'amount_mvr']);
            fputcsv($out, ['Commission (net of refunds, without GST)', $pnl['income']['commission']]);
            fputcsv($out, ['Delivery and small-order fees (net, without GST)', $pnl['income']['delivery']]);
            fputcsv($out, ['Income', $pnl['income_total']]);
            if ($pnl['card_fees'] > 0) {
                fputcsv($out, ['Card fees (estimated)', -$pnl['card_fees']]);
            }
            foreach ($pnl['costs'] as $category => $amount) {
                fputcsv($out, [Expense::CATEGORIES[$category] ?? $category, -$amount]);
            }
            fputcsv($out, ['Costs', -$pnl['cost_total']]);
            fputcsv($out, ['Profit', $pnl['profit']]);
        });
    }

    /**
     * Commission invoices of the month, in Xero's sales invoice import layout.
     */
    public function xeroInvoices(Request $request, GstService $gst): StreamedResponse
    {
        [$from, $to, $month] = GstReportService::month($request->query('month'));
        $payouts = SellerPayout::with('seller')->whereNotNull('invoice_number')->whereBetween('invoiced_at', [$from, $to])->orderBy('invoice_sequence')->get();

        return $this->csv('xero-commission-invoices-'.$month.'.csv', function ($out) use ($payouts, $gst) {
            fputcsv($out, ['*ContactName', 'EmailAddress', '*InvoiceNumber', '*InvoiceDate', '*DueDate', '*Description', '*Quantity', '*UnitAmount', '*AccountCode', '*TaxType', 'Currency']);
            foreach ($payouts as $payout) {
                $invoice = $gst->commissionInvoice($payout);
                $date = $payout->invoiced_at?->format('d/m/Y');
                fputcsv($out, \App\Support\Csv::row([
                    $payout->invoice_details['name'] ?? $payout->seller?->shopName() ?? 'Shop', $payout->seller?->email, $payout->invoice_number, $date, $date,
                    'iruali commission, '.$invoice['lines']->count().' order part(s)', 1, number_format($invoice['commission'], 2, '.', ''),
                    Setting::get('xero_sales_account', '200'), $invoice['gst'] > 0 ? Setting::get('xero_tax_sales', 'GST on Income') : 'No GST', 'MVR',
                ]));
            }
        });
    }

    /**
     * Expenses of the month, in Xero's bill import layout.
     */
    public function xeroBills(Request $request): StreamedResponse
    {
        [$from, $to, $month] = GstReportService::month($request->query('month'));
        $expenses = Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->orderBy('spent_on')->get();

        return $this->csv('xero-bills-'.$month.'.csv', function ($out) use ($expenses) {
            fputcsv($out, ['*ContactName', '*InvoiceNumber', '*InvoiceDate', '*DueDate', '*Description', '*Quantity', '*UnitAmount', '*AccountCode', '*TaxType', 'Currency']);
            foreach ($expenses as $expense) {
                $date = $expense->spent_on->format('d/m/Y');
                fputcsv($out, \App\Support\Csv::row([
                    $expense->supplier ?: $expense->categoryLabel(), 'EXP-'.$expense->id, $date, $date, $expense->description, 1,
                    number_format((float) $expense->amount, 2, '.', ''), Setting::get('xero_expense_account', '400'),
                    (float) $expense->gst_amount > 0 ? Setting::get('xero_tax_purchases', 'GST on Expenses') : 'No GST', 'MVR',
                ]));
            }
        });
    }

    protected function csv(string $name, \Closure $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            $rows($out);
            fclose($out);
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
