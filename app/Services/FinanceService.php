<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\PaymentTransaction;
use App\Models\Setting;
use Illuminate\Support\Carbon;

/**
 * iruali's own money for a month (Admin → Finance): income from commission, delivery and
 * small-order fees (net of refunds and without GST, from the GST report), card fees (an estimate
 * from the card payments and the BML rate in Settings, unless entered as expenses) and the
 * expenses entered, giving a profit or loss.
 */
class FinanceService
{
    /**
     * @return array<string, mixed>
     */
    public function profitAndLoss(string $month): array
    {
        $report = app(GstReportService::class)->build($month);
        [$from, $to] = [$report['from'], $report['to']];

        $income = [
            'commission' => round($report['iruali']['commission']['net'] - $report['iruali']['commission']['net_gst'], 2),
            'delivery' => round($report['iruali']['delivery']['net'] - $report['iruali']['delivery']['net_gst'], 2),
        ];

        $cardTotal = round((float) PaymentTransaction::where('state', 'CONFIRMED')->whereBetween('confirmed_at', [$from, $to])->sum('amount') / 100, 2);
        $expenses = Expense::whereBetween('spent_on', [$from->toDateString(), $to->toDateString()])->orderBy('spent_on')->get();
        $byCategory = $expenses->groupBy('category')->map(fn ($rows) => round((float) $rows->sum('amount') - (float) $rows->sum('gst_amount'), 2));
        $cardFees = $byCategory->has('card_fees') ? 0.0 : round($cardTotal * max(0, (float) Setting::get('card_fee_percent', 0)) / 100, 2);

        $incomeTotal = round(array_sum($income), 2);
        $costTotal = round((float) $byCategory->sum() + $cardFees, 2);

        return [
            'month' => $report['month'], 'from' => $from, 'to' => $to,
            'income' => $income, 'income_total' => $incomeTotal,
            'card_total' => $cardTotal, 'card_fees' => $cardFees,
            'costs' => $byCategory->all(), 'cost_total' => $costTotal,
            'profit' => round($incomeTotal - $costTotal, 2),
            'expenses' => $expenses,
        ];
    }

    /**
     * Copy last month's recurring expenses into this month (once). Returns how many.
     */
    public function copyRecurring(?Carbon $month = null): int
    {
        $month = ($month ?? now())->copy()->startOfMonth();
        $last = $month->copy()->subMonth();
        $count = 0;
        Expense::where('recurring', true)->whereBetween('spent_on', [$last->toDateString(), $last->copy()->endOfMonth()->toDateString()])->get()
            ->each(function (Expense $expense) use ($month, &$count) {
                $root = $expense->repeat_of ?? $expense->id;
                $exists = Expense::where(fn ($q) => $q->where('repeat_of', $root)->orWhere('id', $root))
                    ->whereBetween('spent_on', [$month->toDateString(), $month->copy()->endOfMonth()->toDateString()])->exists();
                if ($exists) {
                    return;
                }
                Expense::create($expense->only(['category', 'description', 'supplier', 'amount', 'gst_amount']) + [
                    'spent_on' => $month->copy()->day(min($expense->spent_on->day, $month->daysInMonth)), 'recurring' => true, 'repeat_of' => $root,
                ]);
                $count++;
            });

        return $count;
    }
}
