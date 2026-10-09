<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryRate;
use App\Models\Setting;
use App\Services\DeliveryService;
use App\Services\DeliverySlotService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Admin → Delivery: the fee and transit days for each delivery area (Greater Malé, every atoll in
 * the islands table, other islands), the free-delivery threshold and the Malé time slots.
 *
 * Greater Malé's and other islands' fees are the same settings as on the Settings page; an atoll
 * left without a fee is charged the other islands' fee.
 */
class DeliveryController extends Controller
{
    public function index(DeliveryService $delivery, DeliverySlotService $slots)
    {
        return view('admin.delivery.index', [
            'rows' => $this->rows($delivery),
            'freeDeliveryOver' => Setting::get('free_delivery_over'),
            'slotsEnabled' => filter_var(Setting::get('delivery_slots_enabled', 0), FILTER_VALIDATE_BOOLEAN),
            'slotList' => implode("\n", array_map(fn ($slot) => $slot[0].'-'.$slot[1], $slots->definitions())),
            'daysAhead' => $slots->daysAhead(),
            'cutoffHours' => $slots->cutoffHours(),
            'capacity' => $slots->capacity(),
            'upcoming' => $slots->enabled() ? $slots->days() : collect(),
        ]);
    }

    public function update(Request $request, DeliveryService $delivery)
    {
        $areas = array_keys($delivery->areas());

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'rates' => 'required|array',
            'rates.*.area' => ['required', 'string', \Illuminate\Validation\Rule::in($areas)],
            'rates.*.fee' => 'nullable|numeric|min:0|max:100000',
            'rates.*.min' => 'nullable|integer|min:0|max:60',
            'rates.*.max' => 'nullable|integer|min:0|max:60',
            'free_delivery_over' => 'required|numeric|min:0|max:1000000',
            'delivery_slots_enabled' => 'nullable|boolean',
            'delivery_slots' => 'nullable|string|max:1000',
            'delivery_slot_days_ahead' => 'required|integer|min:0|max:14',
            'delivery_slot_cutoff_hours' => 'required|integer|min:0|max:48',
            'delivery_slot_capacity' => 'required|integer|min:0|max:10000',
        ], [], [
            'rates.*.fee' => __('fee'),
            'rates.*.min' => __('fewest days'),
            'rates.*.max' => __('most days'),
            'free_delivery_over' => __('free delivery over'),
        ]);

        $validator->after(function (Validator $validator) use ($request) {
            foreach ((array) $request->input('rates', []) as $i => $row) {
                $area = (string) ($row['area'] ?? '');
                if (in_array($area, [DeliveryService::AREA_GREATER_MALE, DeliveryService::AREA_ISLANDS], true) && blank($row['fee'] ?? null)) {
                    $validator->errors()->add("rates.$i.fee", __('Greater Malé and other islands need a fee.'));
                }
                if (filled($row['min'] ?? null) !== filled($row['max'] ?? null)) {
                    $validator->errors()->add("rates.$i.max", __('Give both the fewest and the most days, or leave both empty.'));
                } elseif (filled($row['min'] ?? null) && is_numeric($row['min']) && is_numeric($row['max']) && (int) $row['max'] < (int) $row['min']) {
                    $validator->errors()->add("rates.$i.max", __('The most days cannot be fewer than the fewest days.'));
                }
            }

            $text = (string) $request->input('delivery_slots', '');
            if ($bad = DeliverySlotService::invalidLines($text)) {
                $validator->errors()->add('delivery_slots', __('Write each time slot as HH:MM-HH:MM, ending after it starts. Not understood: :lines', ['lines' => implode(', ', $bad)]));
            }
            if ($request->boolean('delivery_slots_enabled') && DeliverySlotService::parse($text) === []) {
                $validator->errors()->add('delivery_slots', __('Add at least one time slot to turn time slots on.'));
            }
        });

        $data = $validator->validate();

        $before = $this->snapshot();

        DB::transaction(function () use ($data) {
            $settings = [
                'free_delivery_over' => $data['free_delivery_over'],
                'delivery_slots_enabled' => (int) ($data['delivery_slots_enabled'] ?? 0),
                'delivery_slots' => implode("\n", array_map(fn ($slot) => $slot[0].'-'.$slot[1], DeliverySlotService::parse((string) ($data['delivery_slots'] ?? '')))),
                'delivery_slot_days_ahead' => (int) $data['delivery_slot_days_ahead'],
                'delivery_slot_cutoff_hours' => (int) $data['delivery_slot_cutoff_hours'],
                'delivery_slot_capacity' => (int) $data['delivery_slot_capacity'],
            ];

            foreach ($data['rates'] as $row) {
                $area = $row['area'];
                $min = isset($row['min']) && $row['min'] !== '' ? (int) $row['min'] : null;
                $max = isset($row['max']) && $row['max'] !== '' ? (int) $row['max'] : null;
                $fee = isset($row['fee']) && $row['fee'] !== '' ? round((float) $row['fee'], 2) : null;

                // Greater Malé's and other islands' fees live in Settings (shared with the Settings page)
                if ($area === DeliveryService::AREA_GREATER_MALE) {
                    $settings['delivery_fee_greater_male'] = $fee;
                    $fee = null;
                } elseif ($area === DeliveryService::AREA_ISLANDS) {
                    $settings['delivery_fee_islands'] = $fee;
                    $fee = null;
                }

                if ($fee === null && $min === null && $max === null) {
                    DeliveryRate::where('area', $area)->get()->each->delete();

                    continue;
                }
                DeliveryRate::updateOrCreate(['area' => $area], ['fee' => $fee, 'transit_days_min' => $min, 'transit_days_max' => $max]);
            }

            Setting::set($settings);
        });

        $after = $this->snapshot();
        $changes = [];
        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            if (($before[$key] ?? null) !== ($after[$key] ?? null)) {
                $changes[$key] = ['from' => $before[$key] ?? null, 'to' => $after[$key] ?? null];
            }
        }
        if ($changes) {
            Audit::record('delivery.saved', null, $changes);
        }

        return redirect()->route('admin.delivery')->with('success', __('Delivery settings saved.'));
    }

    /**
     * One row per area for the table: its fee (blank for an atoll that uses the other islands'
     * fee) and its own transit days, with what applies now as the placeholder.
     *
     * @return list<array{area: string, label: string, fee: mixed, fee_required: bool, min: ?int, max: ?int, transit: array{0: int, 1: int}, islands: int}>
     */
    protected function rows(DeliveryService $delivery): array
    {
        $rates = $delivery->rateTable();
        $islandCounts = \App\Models\Island::where('is_active', true)->whereNotNull('atoll')->groupBy('atoll')
            ->selectRaw('atoll, count(*) as islands')->toBase()->pluck('islands', 'atoll')
            ->mapWithKeys(fn ($count, $atoll) => [mb_strtolower((string) $atoll) => (int) $count]);

        $rows = [];
        foreach ($delivery->areas() as $area => $label) {
            $area = (string) $area;
            $fee = match ($area) {
                DeliveryService::AREA_GREATER_MALE => Setting::get('delivery_fee_greater_male'),
                DeliveryService::AREA_ISLANDS => Setting::get('delivery_fee_islands'),
                default => $rates[$area]['fee'] ?? null,
            };
            $rows[] = [
                'area' => $area,
                'label' => $label,
                'fee' => $fee,
                'fee_required' => in_array($area, [DeliveryService::AREA_GREATER_MALE, DeliveryService::AREA_ISLANDS], true),
                'min' => $rates[$area]['min'] ?? null,
                'max' => $rates[$area]['max'] ?? null,
                'transit' => $delivery->transitDays($area),
                'islands' => (int) ($islandCounts[mb_strtolower($area)] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Everything this page sets, as it applies now, so the audit log records what changed.
     *
     * @return array<string, string>
     */
    protected function snapshot(): array
    {
        $slots = app(DeliverySlotService::class);
        $money = fn ($value) => number_format((float) $value, 2, '.', '');

        $values = [
            'free_delivery_over' => $money(Setting::get('free_delivery_over')),
            'fee:greater_male' => $money(Setting::get('delivery_fee_greater_male')),
            'fee:islands' => $money(Setting::get('delivery_fee_islands')),
            'slots_enabled' => filter_var(Setting::get('delivery_slots_enabled', 0), FILTER_VALIDATE_BOOLEAN) ? 'on' : 'off',
            'slots' => implode(', ', array_map(fn ($slot) => $slot[0].'-'.$slot[1], $slots->definitions())),
            'slot_days_ahead' => (string) $slots->daysAhead(),
            'slot_cutoff_hours' => (string) $slots->cutoffHours(),
            'slot_capacity' => (string) $slots->capacity(),
        ];
        foreach (DeliveryRate::orderBy('area')->get() as $rate) {
            if ($rate->fee !== null) {
                $values['fee:'.$rate->area] = $money($rate->fee);
            }
            if ($rate->transit_days_min !== null) {
                $values['days:'.$rate->area] = $rate->transit_days_min.'-'.$rate->transit_days_max;
            }
        }

        return $values;
    }
}
