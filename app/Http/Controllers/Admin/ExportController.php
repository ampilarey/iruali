<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Exports: CSV files for a date range: orders, order lines, sales by shop / category /
 * brand / island, vouchers used and refunds by reason. Paid, not cancelled orders unless said.
 */
class ExportController extends Controller
{
    public const KINDS = [
        'orders' => 'Orders (all, with status)',
        'order_lines' => 'Order lines (paid orders)',
        'sales_shop' => 'Sales by shop',
        'sales_category' => 'Sales by category',
        'sales_brand' => 'Sales by brand',
        'sales_island' => 'Sales by delivery island',
        'vouchers' => 'Vouchers and offers used',
        'refunds' => 'Refunds by reason',
    ];

    public function index()
    {
        return view('admin.exports.index', ['kinds' => self::KINDS]);
    }

    public function export(Request $request, string $kind): StreamedResponse
    {
        abort_unless(isset(self::KINDS[$kind]), 404);
        $data = $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = Carbon::parse($data['from'] ?? now()->startOfMonth())->startOfDay();
        $to = Carbon::parse($data['to'] ?? now())->endOfDay();

        [$header, $rows] = $this->{'rows'.str_replace('_', '', ucwords($kind, '_'))}($from, $to);

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, \App\Support\Csv::row(array_values((array) $row)));
            }
            fclose($out);
        }, 'iruali-'.str_replace('_', '-', $kind).'-'.$from->toDateString().'-to-'.$to->toDateString().'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    protected function paidOrders(Carbon $from, Carbon $to): \Illuminate\Database\Query\Builder
    {
        return DB::table('orders')->where('orders.payment_status', 'paid')->where('orders.status', '!=', 'cancelled')->whereBetween('orders.created_at', [$from, $to]);
    }

    /** @return array{0: list<string>, 1: iterable<mixed>} */
    protected function rowsOrders(Carbon $from, Carbon $to): array
    {
        return [
            ['order', 'date', 'status', 'payment_status', 'payment_method', 'island', 'atoll', 'goods_after_discounts', 'delivery', 'small_order_fee', 'voucher', 'voucher_discount', 'points_discount', 'total', 'refund_status', 'refund_amount'],
            DB::table('orders')->whereBetween('created_at', [$from, $to])->orderBy('created_at')
                ->selectRaw('order_number, created_at, status, payment_status, payment_method, shipping_city, shipping_state, total_amount - shipping_amount, shipping_amount - small_order_fee, small_order_fee, voucher_code, voucher_discount, points_redeemed_discount, total_amount, refund_status, refund_amount')
                ->cursor(),
        ];
    }

    /** @return array{0: list<string>, 1: iterable<mixed>} */
    protected function rowsOrderLines(Carbon $from, Carbon $to): array
    {
        return [
            ['order', 'date', 'shop', 'product_id', 'variant', 'sku', 'quantity', 'unit_price', 'line_total'],
            $this->paidOrders($from, $to)->join('order_items', 'order_items.order_id', '=', 'orders.id')
                ->leftJoin('products', 'products.id', '=', 'order_items.product_id')->leftJoin('users', 'users.id', '=', 'products.seller_id')
                ->orderBy('orders.created_at')
                ->selectRaw('orders.order_number, orders.created_at, COALESCE(users.business_name, users.name), order_items.product_id, order_items.variant_name, order_items.variant_sku, order_items.quantity, order_items.price, order_items.quantity * order_items.price')
                ->cursor(),
        ];
    }

    /**
     * @return array{0: list<string>, 1: iterable<mixed>}
     */
    protected function salesBy(Carbon $from, Carbon $to, string $label, string $groupExpression, ?\Closure $join = null): array
    {
        $query = $this->paidOrders($from, $to)->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id');
        if ($join) {
            $join($query);
        }

        return [
            [$label, 'orders', 'units', 'sales_mvr'],
            $query->groupByRaw($groupExpression)->orderByRaw('SUM(order_items.quantity * order_items.price) DESC')
                ->selectRaw($groupExpression.' AS label, COUNT(DISTINCT orders.id), SUM(order_items.quantity), ROUND(SUM(order_items.quantity * order_items.price), 2)')
                ->get(),
        ];
    }

    /** @return array{0: list<string>, 1: iterable<mixed>} */
    protected function rowsSalesShop(Carbon $from, Carbon $to): array
    {
        return $this->salesBy($from, $to, 'shop', "COALESCE(users.business_name, users.name, 'iruali')", fn ($q) => $q->leftJoin('users', 'users.id', '=', 'products.seller_id'));
    }

    /** @return array{0: list<string>, 1: iterable<mixed>} */
    protected function rowsSalesCategory(Carbon $from, Carbon $to): array
    {
        $rows = $this->salesBy($from, $to, 'category', 'COALESCE(products.category_id, 0)');
        $names = \App\Models\Category::all()->mapWithKeys(fn ($c) => [$c->id => $c->getTranslation('name', 'en')]);
        $rows[1] = collect($rows[1])->map(function ($row) use ($names) {
            $row = (array) $row;
            $row['label'] = $names[(int) $row['label']] ?? 'No category';

            return $row;
        });

        return $rows;
    }

    /** @return array{0: list<string>, 1: iterable<mixed>} */
    protected function rowsSalesBrand(Carbon $from, Carbon $to): array
    {
        return $this->salesBy($from, $to, 'brand', "COALESCE(brands.name, 'No brand')", fn ($q) => $q->leftJoin('brands', 'brands.id', '=', 'products.brand_id'));
    }

    /** @return array{0: list<string>, 1: iterable<mixed>} */
    protected function rowsSalesIsland(Carbon $from, Carbon $to): array
    {
        // Grouped on the island and atoll, joined here (CONCAT differs between MySQL and SQLite)
        $rows = $this->paidOrders($from, $to)->join('order_items', 'order_items.order_id', '=', 'orders.id')
            ->groupBy('orders.shipping_city', 'orders.shipping_state')->orderByRaw('SUM(order_items.quantity * order_items.price) DESC')
            ->selectRaw('orders.shipping_city AS city, orders.shipping_state AS atoll, COUNT(DISTINCT orders.id) AS orders, SUM(order_items.quantity) AS units, ROUND(SUM(order_items.quantity * order_items.price), 2) AS sales')
            ->get()->map(fn ($row) => [trim($row->city.', '.$row->atoll, ', '), $row->orders, $row->units, $row->sales]);

        return [['island', 'orders', 'units', 'sales_mvr'], $rows];
    }

    /** @return array{0: list<string>, 1: iterable<mixed>} */
    protected function rowsVouchers(Carbon $from, Carbon $to): array
    {
        return [
            ['code', 'orders', 'discount_mvr'],
            $this->paidOrders($from, $to)->whereNotNull('voucher_code')->groupBy('voucher_code')
                ->selectRaw('voucher_code, COUNT(*), ROUND(SUM(voucher_discount), 2)')->orderByRaw('COUNT(*) DESC')->get(),
        ];
    }

    /** @return array{0: list<string>, 1: iterable<mixed>} */
    protected function rowsRefunds(Carbon $from, Carbon $to): array
    {
        $orders = DB::table('orders')->whereNotNull('refund_status')->whereBetween('updated_at', [$from, $to])
            ->selectRaw("'Cancelled order' AS kind, COALESCE(refund_reason, 'Other') AS reason, refund_status AS status, COUNT(*) AS refunds, ROUND(SUM(refund_amount), 2) AS amount")
            ->groupBy('refund_reason', 'refund_status')->get();
        $returns = DB::table('return_requests')->whereIn('status', ['approved', 'refunded'])->whereBetween('resolved_at', [$from, $to])
            ->selectRaw("'Return' AS kind, reason, status, COUNT(*) AS refunds, ROUND(SUM(refund_amount), 2) AS amount")
            ->groupBy('reason', 'status')->get();

        return [['kind', 'reason', 'status', 'refunds', 'amount_mvr'], $orders->concat($returns)];
    }
}
