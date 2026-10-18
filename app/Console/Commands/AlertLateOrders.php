<?php

namespace App\Console\Commands;

use App\Models\SellerOrder;
use App\Models\Setting;
use App\Notifications\ShopLateOrder;
use App\Support\TeamAlerts;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Hourly: shop parts of paid (or cash on delivery) orders that the shop has not started within
 * `late_start_hours`, and parts sent but past their expected delivery date. Each shop is emailed
 * about its own, the team gets one alert, and Admin → Inbox shows the counts. Once per part.
 */
class AlertLateOrders extends Command
{
    protected $signature = 'orders:alert-late';

    protected $description = 'Alert shops and the team about orders not started in time or past their delivery date';

    public static function startHours(): int
    {
        return max(1, (int) Setting::get('late_start_hours', 24));
    }

    /**
     * Parts the shop should have started by now (not counting pre-orders waiting for stock).
     *
     * @return Builder<SellerOrder>
     */
    public static function unstarted(): Builder
    {
        $since = now()->subHours(self::startHours());

        return SellerOrder::query()->where('status', 'pending')->where(fn ($q) => $q->where('awaiting_stock', false)->orWhereNull('awaiting_stock'))
            ->whereHas('order', fn ($q) => $q->where('status', '!=', 'cancelled')->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('payment_status', 'paid')->where('paid_at', '<=', $since))
                ->orWhere(fn ($q) => $q->where('payment_method', 'cod')->where('created_at', '<=', $since))));
    }

    /**
     * Parts sent but not delivered after their expected delivery date.
     *
     * @return Builder<SellerOrder>
     */
    public static function overdue(): Builder
    {
        return SellerOrder::query()->whereIn('status', ['shipped', 'out_for_delivery'])
            ->whereNotNull('expected_delivery_date')->whereDate('expected_delivery_date', '<', today());
    }

    public function handle(): int
    {
        $unstarted = self::unstarted()->whereNull('late_start_alerted_at')->with('order', 'seller')->get();
        $overdue = self::overdue()->whereNull('overdue_alerted_at')->with('order', 'seller')->get();

        $this->tellShops($unstarted, 'unstarted');
        $this->tellShops($overdue, 'overdue');
        SellerOrder::whereIn('id', $unstarted->pluck('id'))->toBase()->update(['late_start_alerted_at' => now()]);
        SellerOrder::whereIn('id', $overdue->pluck('id'))->toBase()->update(['overdue_alerted_at' => now()]);

        if ($unstarted->isNotEmpty() || $overdue->isNotEmpty()) {
            $lines = collect([
                $unstarted->isNotEmpty() ? $unstarted->count().' not started after '.self::startHours().' h ('.$unstarted->map(fn ($p) => $p->order?->order_number)->filter()->take(5)->implode(', ').')' : null,
                $overdue->isNotEmpty() ? $overdue->count().' past their delivery date ('.$overdue->map(fn ($p) => $p->order?->order_number)->filter()->take(5)->implode(', ').')' : null,
            ])->filter()->implode('; ');
            TeamAlerts::send('late_order', 'iruali: late orders: '.$lines.'.', route('admin.inbox'));
        }
        $this->info('Not started: '.$unstarted->count().'; past delivery date: '.$overdue->count().'.');

        return self::SUCCESS;
    }

    /**
     * @param  Collection<int, SellerOrder>  $parts
     */
    protected function tellShops(Collection $parts, string $kind): void
    {
        $parts->groupBy('seller_id')->each(function ($shopParts) use ($kind) {
            $seller = $shopParts->first()->seller;
            if ($seller) {
                $seller->notify(new ShopLateOrder($kind, $shopParts->map(fn (SellerOrder $p) => (string) $p->order?->order_number)->filter()->values()->all()));
            }
        });
    }
}
