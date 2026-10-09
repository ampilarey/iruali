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

        return view('admin.tax.report', ['report' => $report, 'registeredNow' => app(GstService::class)->platformRegistered()]);
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
