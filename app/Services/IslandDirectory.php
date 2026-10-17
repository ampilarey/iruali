<?php

namespace App\Services;

use App\Models\Island;
use App\Support\IslandList;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Looking islands up: the switched-on islands (once per request), an island by typed name, the
 * nearest island to a position ("Use my location") and the list the island picker loads.
 */
class IslandDirectory
{
    /** Farther than this from every island, the position is not in the Maldives. */
    public const MAX_DISTANCE_KM = 120;

    /** @return EloquentCollection<int, Island> */
    public function active(): EloquentCollection
    {
        $attributes = request()->attributes;
        if (! $attributes->has('islands.active')) {
            $attributes->set('islands.active', Island::query()->active()->orderBy('id')->get());
        }

        return $attributes->get('islands.active');
    }

    public function forget(): void
    {
        request()->attributes->remove('islands.active');
    }

    public function find(?int $id): ?Island
    {
        return $id ? $this->active()->firstWhere('id', $id) : null;
    }

    /**
     * The island a typed name means (accents, case and other names ignored; the atoll decides
     * between islands of the same name).
     */
    public function findByName(?string $name, ?string $atoll = null): ?Island
    {
        $match = IslandList::match($this->active(), $name, $atoll);

        return $match instanceof Island ? $match : null;
    }

    /**
     * The nearest switched-on island to a position, by great-circle (haversine) distance, with
     * the distance in km; null when every island is farther than MAX_DISTANCE_KM.
     *
     * @return array{island: Island, km: float}|null
     */
    public function nearest(float $lat, float $lng): ?array
    {
        $best = null;
        foreach ($this->active() as $island) {
            if (! $island->hasPosition()) {
                continue;
            }
            $km = self::distanceKm($lat, $lng, (float) $island->latitude, (float) $island->longitude);
            if ($best === null || $km < $best['km']) {
                $best = ['island' => $island, 'km' => $km];
            }
        }

        return $best && $best['km'] <= self::MAX_DISTANCE_KM ? $best : null;
    }

    public static function distanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $rad = M_PI / 180;
        $dLat = ($lat2 - $lat1) * $rad;
        $dLng = ($lng2 - $lng1) * $rad;
        $a = sin($dLat / 2) ** 2 + cos($lat1 * $rad) * cos($lat2 * $rad) * sin($dLng / 2) ** 2;

        return 6371.0 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * One island for JSON (the picker, the phone app).
     *
     * @return array<string, mixed>
     */
    public function toArray(Island $island): array
    {
        return [
            'id' => $island->id,
            'name' => $island->localized_name,
            'name_en' => $island->englishName(),
            'name_dv' => (string) $island->getTranslation('name', 'dv', false),
            'atoll' => (string) $island->atoll,
            'atoll_dv' => (string) $island->atoll_dv,
            'greater_male' => $island->isGreaterMale(),
        ];
    }

    /**
     * The picker's list: atolls north to south (the list's order), islands by name inside each.
     *
     * @return Collection<int, array{atoll: string, atoll_dv: string, islands: array<int, array<string, mixed>>}>
     */
    public function grouped(): Collection
    {
        return $this->active()
            ->groupBy(fn (Island $island) => (string) ($island->atoll ?: __('Other')))
            ->sortBy(fn (EloquentCollection $islands) => $islands->min(fn (Island $island) => $island->salat_id ?? 9999))
            ->map(fn (EloquentCollection $islands, string $atoll) => [
                'atoll' => $atoll,
                'atoll_dv' => (string) $islands->first()->atoll_dv,
                'islands' => $islands->sortBy(fn (Island $island) => $island->localized_name)->map(fn (Island $island) => $this->toArray($island))->values()->all(),
            ])->values();
    }
}
