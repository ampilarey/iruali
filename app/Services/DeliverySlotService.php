<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Setting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Malé delivery time slots (Admin → Delivery). When they are on, a customer whose order is
 * delivered to Greater Malé may pick a slot at checkout ("Any time" is the default).
 *
 * Slots are offered from today up to "days ahead" days ahead. A slot starting within the cut-off
 * (hours from now) is too late to book, and a slot holding "max orders" non-cancelled orders is
 * full. Both are shown disabled and checked again when the order is placed.
 */
class DeliverySlotService
{
    public const DEFAULT_SLOTS = ['09:00-12:00', '12:00-15:00', '15:00-18:00', '18:00-21:00'];

    public const DEFAULT_DAYS_AHEAD = 3;

    public const DEFAULT_CUTOFF_HOURS = 3;

    public const DEFAULT_CAPACITY = 10;

    /** The posted value of a slot: its start, e.g. "2026-10-12 09:00". */
    public const KEY_FORMAT = 'Y-m-d H:i';

    public function enabled(): bool
    {
        return filter_var(Setting::get('delivery_slots_enabled', 0), FILTER_VALIDATE_BOOLEAN) && $this->definitions() !== [];
    }

    /**
     * The daily slots as [start, end] times, e.g. [["09:00", "12:00"], ...].
     *
     * @return list<array{0: string, 1: string}>
     */
    public function definitions(): array
    {
        return self::parse((string) Setting::get('delivery_slots', implode("\n", self::DEFAULT_SLOTS)));
    }

    public function daysAhead(): int
    {
        return max(0, min(14, (int) Setting::get('delivery_slot_days_ahead', self::DEFAULT_DAYS_AHEAD)));
    }

    public function cutoffHours(): int
    {
        return max(0, (int) Setting::get('delivery_slot_cutoff_hours', self::DEFAULT_CUTOFF_HOURS));
    }

    /** Most non-cancelled orders one slot takes; 0 = no limit. */
    public function capacity(): int
    {
        return max(0, (int) Setting::get('delivery_slot_capacity', self::DEFAULT_CAPACITY));
    }

    /**
     * Read the slot list as typed on the Delivery page: one "HH:MM-HH:MM" per line (commas work
     * too). Invalid lines and slots that end before they start are left out; sorted by start.
     *
     * @return list<array{0: string, 1: string}>
     */
    public static function parse(string $text): array
    {
        $slots = [];
        foreach (self::lines($text) as $line) {
            if ($slot = self::parseLine($line)) {
                $slots[$slot[0]] = $slot;
            }
        }
        ksort($slots);

        return array_values($slots);
    }

    /**
     * Lines of the slot list that are not a valid "HH:MM-HH:MM" slot.
     *
     * @return list<string>
     */
    public static function invalidLines(string $text): array
    {
        return array_values(array_filter(self::lines($text), fn (string $line) => self::parseLine($line) === null));
    }

    /**
     * @return list<string>
     */
    protected static function lines(string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $text) ?: []), fn ($line) => $line !== ''));
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    protected static function parseLine(string $line): ?array
    {
        if (! preg_match('/^([01]?\d|2[0-3])[:.]([0-5]\d)\s*[-–—]\s*([01]?\d|2[0-4])[:.]([0-5]\d)$/u', trim($line), $m)) {
            return null;
        }
        $start = sprintf('%02d:%02d', $m[1], $m[2]);
        $end = sprintf('%02d:%02d', $m[3], $m[4]);

        return $end > $start && $end <= '24:00' ? [$start, $end] : null;
    }

    /**
     * Every slot from today to "days ahead", grouped by day (key "Y-m-d"), with how many orders
     * it holds and whether it can still be booked.
     *
     * @return Collection<string, Collection<int, array{key: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, time: string, booked: int, full: bool, too_late: bool, available: bool}>>
     */
    public function days(?CarbonInterface $now = null): Collection
    {
        $now = CarbonImmutable::instance($now ?? now());
        $today = $now->startOfDay();
        $last = $today->addDays($this->daysAhead())->endOfDay();
        $booked = $this->bookingsBetween($today, $last);

        $days = collect();
        for ($date = $today; $date->lte($last); $date = $date->addDay()) {
            $days[$date->format('Y-m-d')] = collect($this->definitions())->map(fn (array $slot) => $this->describe($date, $slot, $now, $booked));
        }

        return $days;
    }

    /**
     * The slot with this key when it is offered and has room, else null.
     *
     * @param  bool  $lock  hold the slot's orders under a lock until the transaction ends (placing an order)
     * @return array{key: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, time: string, booked: int, full: bool, too_late: bool, available: bool}|null
     */
    public function find(string $key, ?CarbonInterface $now = null, bool $lock = false): ?array
    {
        $now = CarbonImmutable::instance($now ?? now());
        try {
            // "!" so the seconds are 0, not the current time's
            $start = CarbonImmutable::createFromFormat('!'.self::KEY_FORMAT, trim($key));
        } catch (\Throwable) {
            return null;
        }
        if (! $start || $start->format(self::KEY_FORMAT) !== trim($key)) {
            return null;
        }

        $date = $start->startOfDay();
        if ($date->lt($now->startOfDay()) || $date->gt($now->startOfDay()->addDays($this->daysAhead()))) {
            return null;
        }
        $definition = collect($this->definitions())->first(fn (array $slot) => $slot[0] === $start->format('H:i'));
        if (! $definition) {
            return null;
        }

        $query = Order::where('delivery_slot_starts_at', $start->format('Y-m-d H:i:s'))->where('status', '!=', 'cancelled');
        $count = $lock ? $query->lockForUpdate()->count() : $query->count();
        $slot = $this->describe($date, $definition, $now, [$start->format('Y-m-d H:i:s') => $count]);

        return $slot['available'] ? $slot : null;
    }

    /**
     * Book the slot for an order being placed (inside its transaction): checked again with the
     * slot's orders locked, so two customers can't both take the last place.
     *
     * @return array{key: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, time: string, booked: int, full: bool, too_late: bool, available: bool}
     */
    public function reserve(string $key): array
    {
        return $this->find($key, null, true)
            ?? throw new RuntimeException(__('That delivery time is no longer available. Please choose another time.'));
    }

    /**
     * "Sat 12 Oct, 09:00–12:00".
     */
    public static function label(CarbonInterface $start, ?CarbonInterface $end = null): string
    {
        return $start->translatedFormat('D j M').', '.$start->format('H:i').($end ? '–'.$end->format('H:i') : '');
    }

    /**
     * @param  array{0: string, 1: string}  $slot
     * @param  array<string, int>  $booked  non-cancelled orders keyed by slot start ("Y-m-d H:i:s")
     * @return array{key: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, time: string, booked: int, full: bool, too_late: bool, available: bool}
     */
    protected function describe(CarbonImmutable $date, array $slot, CarbonImmutable $now, array $booked): array
    {
        $startsAt = $date->setTimeFromTimeString($slot[0]);
        $endsAt = $slot[1] === '24:00' ? $date->addDay()->startOfDay() : $date->setTimeFromTimeString($slot[1]);
        $count = (int) ($booked[$startsAt->format('Y-m-d H:i:s')] ?? 0);
        $capacity = $this->capacity();
        $full = $capacity > 0 && $count >= $capacity;
        $tooLate = $startsAt->lte($now->addHours($this->cutoffHours()));

        return [
            'key' => $startsAt->format(self::KEY_FORMAT),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'time' => $slot[0].'–'.$slot[1],
            'booked' => $count,
            'full' => $full,
            'too_late' => $tooLate,
            'available' => ! $full && ! $tooLate,
        ];
    }

    /**
     * Non-cancelled orders per slot start between two times.
     *
     * @return array<string, int>
     */
    protected function bookingsBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        return Order::query()
            ->whereBetween('delivery_slot_starts_at', [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')])
            ->where('status', '!=', 'cancelled')
            ->selectRaw('delivery_slot_starts_at as slot, count(*) as orders')
            ->groupBy('delivery_slot_starts_at')
            ->toBase()
            ->get()
            ->mapWithKeys(fn ($row) => [CarbonImmutable::parse($row->slot)->format('Y-m-d H:i:s') => (int) $row->orders])
            ->all();
    }
}
