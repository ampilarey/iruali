<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin → Customer report: what customers are worth over time (lifetime value), how many come
 * back (repeat rate), new against returning customers each month, monthly cohorts (of the people
 * whose first order was in a month, how many ordered again in the months after), the top
 * customers, and a CSV of every customer who has paid for an order. Paid, not cancelled orders only.
 */
class CustomerReportController extends Controller
{
    public const COHORT_MONTHS = 6;

    public function index()
    {
        $customers = $this->customers();
        $buyers = $customers->count();
        $repeat = $customers->where('orders', '>=', 2)->count();

        return view('admin.customers.report', [
            'totals' => [
                'buyers' => $buyers,
                'repeat' => $repeat,
                'repeat_rate' => $buyers ? round($repeat / $buyers * 100, 1) : 0,
                'lifetime_value' => $buyers ? round($customers->sum('spent') / $buyers, 2) : 0,
                'orders_per_customer' => $buyers ? round($customers->sum('orders') / $buyers, 2) : 0,
            ],
            'top' => $customers->sortByDesc('spent')->take(20)->values(),
            'months' => $this->months($customers),
            'cohorts' => $this->cohorts($customers),
        ]);
    }

    public function export(): StreamedResponse
    {
        $customers = $this->customers()->sortByDesc('spent');

        return response()->streamDownload(function () use ($customers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['name', 'email', 'phone', 'orders', 'spent_mvr', 'average_mvr', 'first_order', 'last_order', 'joined']);
            foreach ($customers as $row) {
                fputcsv($out, [
                    $row['name'], $row['email'], $row['phone'], $row['orders'], number_format($row['spent'], 2, '.', ''),
                    number_format($row['spent'] / max(1, $row['orders']), 2, '.', ''),
                    $row['first']->toDateString(), $row['last']->toDateString(), $row['joined']?->toDateString(),
                ]);
            }
            fclose($out);
        }, 'iruali-customers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * One row per customer with a paid order: orders, spend, first and last order, and the month
     * of each order (for the cohorts).
     *
     * @return Collection<int, array{id: int, name: string, email: string, phone: ?string, joined: ?\Carbon\Carbon, orders: int, spent: float, first: Carbon, last: Carbon, months: list<string>}>
     */
    protected function customers(): Collection
    {
        $orders = Order::query()->toBase()
            ->select('user_id', 'total_amount', 'created_at')
            ->whereNotNull('user_id')->where('payment_status', 'paid')->where('status', '!=', 'cancelled')
            ->orderBy('created_at')->get()->groupBy('user_id');
        if ($orders->isEmpty()) {
            return collect();
        }

        $users = User::query()->whereIn('id', $orders->keys())->whereNull('erased_at')->get(['id', 'name', 'email', 'phone', 'created_at'])->keyBy('id');

        return $orders->filter(fn ($rows, $id) => $users->has($id))->map(function (Collection $rows, $id) use ($users) {
            $user = $users[$id];
            $dates = $rows->map(fn ($row) => Carbon::parse($row->created_at));

            return [
                'id' => (int) $id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone, 'joined' => $user->created_at,
                'orders' => $rows->count(), 'spent' => (float) $rows->sum('total_amount'),
                'first' => Carbon::parse($rows->first()->created_at), 'last' => Carbon::parse($rows->last()->created_at),
                'months' => array_values(array_unique($dates->map(fn (Carbon $d) => $d->format('Y-m'))->all())),
            ];
        })->values();
    }

    /**
     * The last 12 months: customers ordering for the first time, customers coming back.
     *
     * @return list<array{month: string, new: int, returning: int}>
     */
    protected function months(Collection $customers): array
    {
        $rows = [];
        for ($i = 11; $i >= 0; $i--) {
            $month = now()->startOfMonth()->subMonths($i)->format('Y-m');
            $active = $customers->filter(fn ($c) => in_array($month, $c['months'], true));
            $new = $active->filter(fn ($c) => $c['first']->format('Y-m') === $month)->count();
            $rows[] = ['month' => Carbon::createFromFormat('Y-m', $month)->format('M Y'), 'new' => $new, 'returning' => $active->count() - $new];
        }

        return $rows;
    }

    /**
     * Cohorts by the month of the first order (last 12 months): the size, and the share who
     * ordered again 1..N months later.
     *
     * @return list<array{month: string, size: int, after: list<?float>}>
     */
    protected function cohorts(Collection $customers): array
    {
        $rows = [];
        for ($i = 11; $i >= 0; $i--) {
            $start = now()->startOfMonth()->subMonths($i);
            $cohort = $customers->filter(fn ($c) => $c['first']->format('Y-m') === $start->format('Y-m'));
            $after = [];
            for ($m = 1; $m <= self::COHORT_MONTHS; $m++) {
                $later = $start->copy()->addMonths($m);
                $after[] = $later->isFuture() || $cohort->isEmpty() ? null
                    : round($cohort->filter(fn ($c) => in_array($later->format('Y-m'), $c['months'], true))->count() / $cohort->count() * 100);
            }
            $rows[] = ['month' => $start->format('M Y'), 'size' => $cohort->count(), 'after' => $after];
        }

        return $rows;
    }
}
