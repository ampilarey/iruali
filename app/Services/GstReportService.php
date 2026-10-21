<?php

namespace App\Services;

use App\Models\Dispute;
use App\Models\GiftCard;
use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\SellerOrder;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The monthly GST report (Admin → Tax → Monthly report), from the GST frozen on each order when it
 * was placed: paid orders in the month, less the refunds made in it.
 *
 * - Each shop that was GST-registered when its orders were placed: sales and the GST in them,
 *   refunds and the GST in those.
 * - iruali: commission and delivery fees, and the GST in them for orders placed while iruali
 *   was GST-registered.
 *
 * A sale counts in the month it was paid. A refund counts in the month a return or dispute was
 * approved, or the paid order was cancelled. Gift-card orders are left out (GST applies when a card
 * is spent). A pre-order with a booking fee (BookingFeeService) has two invoices: the fee counts in
 * the month it was paid and the rest of the sale when the balance is paid; a fee refunded on
 * cancelling counts as a refund then, and iruali's commission on a fee kept counts when it is kept.
 *
 * @phpstan-type ShopLine array{seller_id: int|null, name: string, tin: mixed, invoices: int, sales: float, gst: float, refunds: float, refund_gst: float, net_sales: float, net_gst: float}
 */
class GstReportService
{
    public function __construct(protected GstService $gst) {}

    /**
     * "2026-10" → the first and last moment of that month (app time, Maldives); anything else is
     * this month.
     *
     * @return array{0: Carbon, 1: Carbon, 2: string}
     */
    public static function month(mixed $month): array
    {
        $month = is_string($month) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? $month : now()->format('Y-m');
        $from = Carbon::parse($month.'-01 00:00:00');

        return [$from, $from->copy()->endOfMonth(), $month];
    }

    /**
     * @return array{month: string, from: Carbon, to: Carbon, shops: Collection<int, ShopLine>, shop_totals: array<string, float>, iruali: array<string, array<string, float>>, iruali_totals: array<string, float>, registered_orders: int, refunds: Collection<int, array<string, mixed>>}
     */
    public function build(mixed $month): array
    {
        [$from, $to, $month] = self::month($month);
        $refunds = $this->refunds($from, $to);

        return [
            'month' => $month,
            'from' => $from,
            'to' => $to,
            ...$this->shops($from, $to, $refunds),
            ...$this->iruali($from, $to, $refunds),
            'refunds' => $refunds,
        ];
    }

    /**
     * Paid orders in the period, gift cards left out.
     */
    protected function paidIn(Builder $query, Carbon $from, Carbon $to): Builder
    {
        return $query->where('orders.payment_status', 'paid')
            ->whereBetween('orders.paid_at', [$from, $to])
            ->whereNotIn('orders.id', GiftCard::query()->whereNotNull('order_id')->select('order_id'));
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $refunds
     * @return array{shops: Collection<int, ShopLine>, shop_totals: array<string, float>}
     */
    protected function shops(Carbon $from, Carbon $to, Collection $refunds): array
    {
        // A booking fee invoiced on its own is not counted again with the rest of the sale
        $sales = $this->paidIn(DB::table('seller_orders')->join('orders', 'orders.id', '=', 'seller_orders.order_id'), $from, $to)
            ->where('seller_orders.gst_registered', true)
            ->groupBy('seller_orders.seller_id')
            ->selectRaw('seller_orders.seller_id, COUNT(*) AS invoices, SUM(COALESCE(seller_orders.gst_taxable, seller_orders.subtotal) - COALESCE(seller_orders.deposit_amount, 0)) AS sales, SUM(seller_orders.gst_amount - COALESCE(seller_orders.deposit_gst, 0)) AS gst, MAX(seller_orders.gst_tin) AS tin, MAX(seller_orders.gst_business_name) AS name')
            ->get()
            ->keyBy(fn ($row) => (int) $row->seller_id);
        // Booking fees paid in the month, each with its own invoice
        $fees = DB::table('seller_orders')
            ->whereNotNull('seller_orders.deposit_invoice_number')
            ->whereBetween('seller_orders.deposit_invoiced_at', [$from, $to])
            ->where('seller_orders.gst_registered', true)
            ->groupBy('seller_orders.seller_id')
            ->selectRaw('seller_orders.seller_id, COUNT(*) AS invoices, SUM(seller_orders.deposit_amount) AS sales, SUM(seller_orders.deposit_gst) AS gst, MAX(seller_orders.gst_tin) AS tin, MAX(seller_orders.gst_business_name) AS name')
            ->get()
            ->keyBy(fn ($row) => (int) $row->seller_id);

        $refunded = $refunds->filter(fn (array $refund) => $refund['part']->gst_registered)->groupBy(fn (array $refund) => (int) $refund['part']->seller_id);
        $keys = $sales->keys()->merge($fees->keys())->merge($refunded->keys())->unique()->values();
        $sellers = User::withTrashed()->whereIn('id', $keys->filter()->all())->get()->keyBy('id');

        $shops = $keys->map(function (int $key) use ($sales, $fees, $refunded, $sellers) {
            $row = $sales->get($key) ?? $fees->get($key);
            $fee = $fees->get($key);
            $rows = $refunded->get($key, collect());
            $part = $rows->first()['part'] ?? null;
            $amount = round((float) ($sales->get($key)->sales ?? 0) + (float) ($fee->sales ?? 0), 2);
            $gst = round((float) ($sales->get($key)->gst ?? 0) + (float) ($fee->gst ?? 0), 2);
            $refunds = round((float) $rows->sum('goods'), 2);
            $refundGst = round((float) $rows->sum('goods_gst'), 2);

            return [
                'seller_id' => $key ?: null,
                'name' => (string) ($row->name ?? $part->gst_business_name ?? $sellers->get($key)?->shopName() ?? 'iruali'),
                'tin' => $row->tin ?? $part->gst_tin,
                'invoices' => (int) ($sales->get($key)->invoices ?? 0) + (int) ($fee->invoices ?? 0),
                'sales' => $amount,
                'gst' => $gst,
                'refunds' => $refunds,
                'refund_gst' => $refundGst,
                'net_sales' => round($amount - $refunds, 2),
                'net_gst' => round($gst - $refundGst, 2),
            ];
        })->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values();

        $totals = [];
        foreach (['sales', 'gst', 'refunds', 'refund_gst', 'net_sales', 'net_gst'] as $column) {
            $totals[$column] = round((float) $shops->sum($column), 2);
        }

        return ['shops' => $shops, 'shop_totals' => $totals];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $refunds
     * @return array{iruali: array<string, array<string, float>>, iruali_totals: array<string, float>, registered_orders: int}
     */
    protected function iruali(Carbon $from, Carbon $to, Collection $refunds): array
    {
        $orders = Order::withTrashed()->whereIn('id', $this->paidIn(DB::table('orders'), $from, $to)->select('orders.id'))
            ->get(['id', 'shipping_amount', 'delivery_gst', 'gst_platform_registered']);
        $commission = $this->paidIn(DB::table('seller_orders')->join('orders', 'orders.id', '=', 'seller_orders.order_id'), $from, $to)
            ->selectRaw('COALESCE(SUM(seller_orders.commission_amount), 0) AS amount, COALESCE(SUM(seller_orders.commission_gst), 0) AS gst')
            ->first();
        // Commission on booking fees kept for the shops when their orders were cancelled
        $kept = DB::table('seller_orders')->whereBetween('seller_orders.deposit_kept_at', [$from, $to])
            ->selectRaw('COALESCE(SUM(seller_orders.deposit_commission), 0) AS amount, COALESCE(SUM(seller_orders.deposit_commission_gst), 0) AS gst')
            ->first();

        $line = function (float $amount, float $gst, float $refunded, float $refundedGst): array {
            return [
                'amount' => round($amount, 2), 'gst' => round($gst, 2), 'refunded' => round($refunded, 2), 'refunded_gst' => round($refundedGst, 2),
                'net' => round($amount - $refunded, 2), 'net_gst' => round($gst - $refundedGst, 2),
            ];
        };

        $lines = [
            'commission' => $line((float) ($commission->amount ?? 0) + (float) ($kept->amount ?? 0), (float) ($commission->gst ?? 0) + (float) ($kept->gst ?? 0), (float) $refunds->sum('commission'), (float) $refunds->sum('commission_gst')),
            'delivery' => $line(
                (float) $orders->sum(fn (Order $order) => $this->gst->deliveryFee($order)),
                (float) $orders->sum('delivery_gst'),
                (float) $refunds->sum('delivery'),
                (float) $refunds->sum('delivery_gst'),
            ),
        ];

        $totals = [];
        foreach (['amount', 'gst', 'refunded', 'refunded_gst', 'net', 'net_gst'] as $column) {
            $totals[$column] = round($lines['commission'][$column] + $lines['delivery'][$column], 2);
        }

        return ['iruali' => $lines, 'iruali_totals' => $totals, 'registered_orders' => $orders->where('gst_platform_registered', true)->count()];
    }

    /**
     * Refunds in the period on paid orders, each split into the shop's goods and iruali's delivery
     * and commission, with the GST in them from the order's frozen details.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function refunds(Carbon $from, Carbon $to): Collection
    {
        $giftCardOrders = GiftCard::query()->whereNotNull('order_id')->pluck('order_id')->map(fn ($id) => (int) $id)->all();
        $events = collect();

        // Approved returns: the shop gives back its share of the items, any extra is the delivery fee
        $returns = ReturnRequest::whereIn('status', ['approved', 'refunded'])->whereBetween('resolved_at', [$from, $to])
            ->with(['order' => fn ($q) => $q->withTrashed(), 'sellerOrder'])->orderBy('resolved_at')->get();
        foreach ($returns as $return) {
            $events->push($this->refund('return', $return->resolved_at, $return->order, $return->sellerOrder, (float) $return->refund_amount, (float) $return->items_value));
        }

        // Disputes decided with a refund (capped at the part's items, like DisputeService)
        $disputes = Dispute::where('amount_resolved', '>', 0)->whereBetween('resolved_at', [$from, $to])
            ->with(['order' => fn ($q) => $q->withTrashed(), 'sellerOrder'])->orderBy('resolved_at')->get();
        foreach ($disputes as $dispute) {
            $events->push($this->refund('dispute', $dispute->resolved_at, $dispute->order, $dispute->sellerOrder, (float) $dispute->amount_resolved, (float) $dispute->sellerOrder?->subtotal));
        }

        // Booking fees refunded when their orders were cancelled: the fee's own invoice is reversed
        $fees = SellerOrder::whereNotNull('deposit_invoice_number')->whereBetween('deposit_reversed_at', [$from, $to])
            ->with(['order' => fn ($q) => $q->withTrashed()])->orderBy('id')->get();
        foreach ($fees as $part) {
            if ($part->order) {
                $events->push([
                    'kind' => 'booking_fee', 'date' => $part->deposit_reversed_at, 'order' => $part->order, 'part' => $part,
                    'goods' => round((float) $part->deposit_amount, 2), 'goods_gst' => round((float) $part->deposit_gst, 2),
                    'delivery' => 0.0, 'delivery_gst' => 0.0, 'commission' => 0.0, 'commission_gst' => 0.0,
                ]);
            }
        }

        // Paid orders cancelled: every part's sale is reversed (a booking fee invoiced on its own is
        // reversed above, or stays a sale when kept), and the delivery fee once per order
        $seen = [];
        $cancelled = SellerOrder::whereBetween('gst_reversed_at', [$from, $to])->with(['order' => fn ($q) => $q->withTrashed()])->orderBy('id')->get();
        foreach ($cancelled as $part) {
            $order = $part->order;
            if (! $order) {
                continue;
            }
            $first = ! isset($seen[$order->id]);
            $seen[$order->id] = true;
            $events->push([
                'kind' => 'cancellation',
                'date' => $part->gst_reversed_at,
                'order' => $order,
                'part' => $part,
                'goods' => round((float) ($part->gst_taxable ?? $part->subtotal) - (float) $part->deposit_amount, 2),
                'goods_gst' => round((float) $part->gst_amount - (float) $part->deposit_gst, 2),
                'delivery' => $first ? $this->gst->deliveryFee($order) : 0.0,
                'delivery_gst' => $first ? round((float) $order->delivery_gst, 2) : 0.0,
                'commission' => round((float) $part->commission_amount, 2),
                'commission_gst' => round((float) $part->commission_gst, 2),
            ]);
        }

        return $events->filter(fn (?array $event) => $event !== null
            && ($event['order']->payment_status === 'paid' || $event['kind'] === 'booking_fee')
            && ! in_array($event['order']->id, $giftCardOrders, true))->values();
    }

    /**
     * One return or dispute refund: the part of it up to the items' value is the shop's goods (iruali
     * gives up its commission on that), then the shop's own delivery charge when it delivered the
     * part itself (also the shop's sale), and the rest up to iruali's delivery fee is iruali's.
     *
     * @return array<string, mixed>|null
     */
    protected function refund(string $kind, ?CarbonInterface $date, ?Order $order, ?SellerOrder $part, float $refund, float $goodsCap): ?array
    {
        if (! $order || ! $part || $refund <= 0) {
            return null;
        }

        $base = round(min($refund, $goodsCap), 2);
        $shopDelivery = $part->deliveryRefundShare($refund, $goodsCap);
        $goods = round(min($base, (float) ($part->gst_taxable ?? $part->subtotal) - $part->shopDeliveryCharge()) + $shopDelivery, 2);
        $delivery = round(min(max(0, $refund - $goodsCap - $shopDelivery), $this->gst->deliveryFee($order)), 2);
        $commission = round($base * (float) $part->commission_rate / 100, 2);
        $platformRate = $order->gst_platform_registered ? $order->gst_rate : null;

        return [
            'kind' => $kind,
            'date' => $date,
            'order' => $order,
            'part' => $part,
            'goods' => $goods,
            'goods_gst' => $part->gst_registered ? GstService::gstIncluded($goods, $part->gst_rate) : 0.0,
            'delivery' => $delivery,
            'delivery_gst' => GstService::gstIncluded($delivery, $platformRate),
            'commission' => $commission,
            'commission_gst' => GstService::gstIncluded($commission, $platformRate),
        ];
    }

    /**
     * The report as CSV rows (header first): one row per shop, then iruali's commission and delivery.
     *
     * @param  array<string, mixed>  $report
     * @return array<int, array<int, string|int|float|null>>
     */
    public function csvRows(array $report): array
    {
        $money = fn ($value) => number_format((float) $value, 2, '.', '');
        // A shop name typed as a formula must not run in a spreadsheet
        $text = fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
        $rows = [['month', 'section', 'name', 'tin', 'amount_incl_gst', 'gst', 'refunds_incl_gst', 'gst_on_refunds', 'net_incl_gst', 'net_gst']];

        foreach ($report['shops'] as $shop) {
            $rows[] = [$report['month'], 'shop', $text($shop['name']), $shop['tin'], $money($shop['sales']), $money($shop['gst']), $money($shop['refunds']), $money($shop['refund_gst']), $money($shop['net_sales']), $money($shop['net_gst'])];
        }
        foreach (['commission' => 'iruali commission', 'delivery' => 'iruali delivery fees'] as $key => $label) {
            $line = $report['iruali'][$key];
            $rows[] = [$report['month'], 'iruali', $label, $this->gst->platformTin(), $money($line['amount']), $money($line['gst']), $money($line['refunded']), $money($line['refunded_gst']), $money($line['net']), $money($line['net_gst'])];
        }

        return $rows;
    }
}
