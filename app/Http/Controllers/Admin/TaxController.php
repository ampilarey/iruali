<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\ShopTaxProfile;
use App\Services\GstReportService;
use App\Services\GstService;
use App\Support\Audit;
use App\Support\Company;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Tax: iruali's GST settings (registered, TIN, rate, invoice number prefix) and the
 * monthly GST report. Admins and finance staff (config/staff.php).
 */
class TaxController extends Controller
{
    public function edit(GstService $gst)
    {
        $settings = $gst->settings();
        $shops = ShopTaxProfile::with('user')->where('gst_registered', true)->get()
            ->sortBy(fn (ShopTaxProfile $profile) => mb_strtolower((string) $profile->user?->shopName()))->values();
        $company = ['legal_name' => Company::legalName(), 'trading_name' => Company::tradingName(), 'address' => Company::address(), 'registration_no' => Company::registrationNo()];

        return view('admin.tax.index', compact('settings', 'shops', 'company'));
    }

    public function update(Request $request, GstService $gst)
    {
        $request->merge([
            'gst_tin' => GstService::normaliseTin($request->input('gst_tin')) ?: null,
            'invoice_prefix' => strtoupper(trim((string) $request->input('invoice_prefix'))),
        ]);
        $data = $request->validate(GstService::settingsRules(), [], [
            'gst_registered' => __('GST registered'),
            'gst_tin' => __('TIN'),
            'gst_rate' => __('GST rate'),
            'invoice_prefix' => __('invoice number prefix'),
        ]);

        $before = $gst->settings();
        $after = [
            'gst_registered' => (bool) $data['gst_registered'],
            'gst_tin' => (string) ($data['gst_tin'] ?? ''),
            'gst_rate' => round((float) $data['gst_rate'], 2),
            'invoice_prefix' => $data['invoice_prefix'],
        ];

        Setting::set([
            'gst_registered' => $after['gst_registered'] ? 1 : 0,
            'gst_tin' => $after['gst_tin'],
            'gst_rate' => number_format($after['gst_rate'], 2, '.', ''),
            'invoice_prefix' => $after['invoice_prefix'],
        ]);

        $changed = array_keys(array_filter($after, fn ($value, $key) => $before[$key] != $value, ARRAY_FILTER_USE_BOTH));
        if ($changed !== []) {
            Audit::record('tax.settings_saved', null, [
                'before' => array_intersect_key($before, array_flip($changed)),
                'after' => array_intersect_key($after, array_flip($changed)),
            ]);
        }

        return redirect()->route('admin.tax')->with('success', __('Tax settings saved. They apply to orders placed from now on; earlier orders keep the GST they were placed with.'));
    }

    /**
     * /admin/tax/report?month=2026-10 (add &export=csv for the spreadsheet).
     */
    public function report(Request $request, GstReportService $reports)
    {
        $report = $reports->build($request->query('month'));

        if ($request->query('export') === 'csv') {
            return $this->csv($reports, $report);
        }
        if ($request->query('export') === 'ledger') {
            return $this->ledger($report);
        }

        return view('admin.tax.report', [
            'report' => $report, 'registeredNow' => app(GstService::class)->platformRegistered(),
            'period' => \App\Models\GstPeriod::where('month', $report['month'])->first(),
            'currentTotals' => self::totals($report),
        ]);
    }

    /**
     * The figures a filed return is compared on.
     *
     * @param  array<string, mixed>  $report
     * @return array<string, float>
     */
    public static function totals(array $report): array
    {
        return [
            'shop_sales' => (float) ($report['shop_totals']['net_sales'] ?? 0),
            'shop_gst' => (float) ($report['shop_totals']['net_gst'] ?? 0),
            'iruali_income' => (float) ($report['iruali_totals']['net'] ?? 0),
            'iruali_gst' => (float) ($report['iruali_totals']['net_gst'] ?? 0),
        ];
    }

    /**
     * Lock a month once its GST return is filed: its figures are kept as they were.
     */
    public function lock(Request $request, GstReportService $reports)
    {
        $data = $request->validate(['month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'], 'filing_reference' => 'nullable|string|max:60']);
        abort_if(\App\Models\GstPeriod::where('month', $data['month'])->exists(), 422, 'This month is already locked.');
        \App\Models\GstPeriod::create([
            'month' => $data['month'], 'totals' => self::totals($reports->build($data['month'])), 'filing_reference' => $data['filing_reference'] ?? null,
            'locked_by' => $request->user()->id, 'locked_at' => now(),
        ]);
        Audit::record('tax.period_locked', null, ['month' => $data['month'], 'reference' => $data['filing_reference'] ?? null]);

        return back()->with('success', 'Locked: the figures for '.$data['month'].' are kept as filed.');
    }

    public function unlock(Request $request, string $month)
    {
        abort_unless($request->user()->hasRole('admin'), 403);
        \App\Models\GstPeriod::where('month', $month)->delete();
        Audit::record('tax.period_unlocked', null, ['month' => $month]);

        return back()->with('success', 'Unlocked '.$month.'.');
    }

    /**
     * Every taxable line of the month: each shop invoice, iruali's commission and delivery on it,
     * and each refund, with the GST recorded on the order.
     *
     * @param  array<string, mixed>  $report
     */
    protected function ledger(array $report): StreamedResponse
    {
        $parts = \App\Models\SellerOrder::query()->join('orders', 'orders.id', '=', 'seller_orders.order_id')
            ->leftJoin('users', 'users.id', '=', 'seller_orders.seller_id')
            ->where('orders.payment_status', 'paid')->whereBetween('orders.paid_at', [$report['from'], $report['to']])
            ->orderBy('orders.paid_at')->toBase()
            ->get(['orders.order_number', 'orders.paid_at', 'orders.delivery_gst', 'seller_orders.invoice_number', 'seller_orders.gst_tin', 'seller_orders.gst_registered',
                'seller_orders.gst_taxable', 'seller_orders.subtotal', 'seller_orders.gst_amount', 'seller_orders.commission_amount', 'seller_orders.commission_gst', 'users.business_name', 'users.name']);

        return response()->streamDownload(function () use ($parts, $report) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['date', 'kind', 'order', 'invoice', 'shop', 'tin', 'amount_incl_gst', 'gst']);
            foreach ($parts as $part) {
                $date = \Illuminate\Support\Carbon::parse($part->paid_at)->toDateString();
                $shop = $part->business_name ?: $part->name;
                fputcsv($out, \App\Support\Csv::row([$date, 'shop sale', $part->order_number, $part->invoice_number, $shop, $part->gst_registered ? $part->gst_tin : '', number_format((float) ($part->gst_taxable ?? $part->subtotal), 2, '.', ''), number_format((float) $part->gst_amount, 2, '.', '')]));
                fputcsv($out, \App\Support\Csv::row([$date, 'iruali commission', $part->order_number, '', $shop, '', number_format((float) $part->commission_amount, 2, '.', ''), number_format((float) $part->commission_gst, 2, '.', '')]));
            }
            foreach ($report['refunds'] as $refund) {
                fputcsv($out, \App\Support\Csv::row([
                    optional($refund['date'] ?? null)?->toDateString(), 'refund ('.($refund['kind'] ?? '').')', $refund['part']->order?->order_number, $refund['part']->invoice_number, '', '',
                    number_format(-(float) (($refund['goods'] ?? 0) + ($refund['delivery'] ?? 0)), 2, '.', ''), number_format(-(float) (($refund['goods_gst'] ?? 0) + ($refund['delivery_gst'] ?? 0)), 2, '.', ''),
                ]));
            }
            fclose($out);
        }, 'gst-ledger-'.$report['month'].'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    protected function csv(GstReportService $reports, array $report): StreamedResponse
    {
        $rows = $reports->csvRows($report);

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, 'gst-report-'.$report['month'].'.csv', ['Content-Type' => 'text/csv']);
    }
}
