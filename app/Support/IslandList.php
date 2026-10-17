<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The list of inhabited islands in database/data/islands.json (name and atoll in English and
 * Dhivehi, map position, the prayer-times dataset's id), loaded into the islands table.
 *
 * The load is run by a migration on every server (never by db:seed on production) and by
 * IslandSeeder locally, so it uses the query builder only: no models, whose columns may change
 * after the migration that calls it. Running it again changes nothing.
 */
class IslandList
{
    /** Atolls people type by their other names. */
    public const ATOLL_ALIASES = [
        'addu' => 'seenu', 'adducity' => 'seenu', 's' => 'seenu',
        'fuvahmulah' => 'gnaviyani', 'fuvammulah' => 'gnaviyani', 'gn' => 'gnaviyani',
        'male' => 'kaafu', 'k' => 'kaafu', 'northmale' => 'kaafu', 'southmale' => 'kaafu',
        'huvadhu' => 'gaafualif',
    ];

    /** Islands people type by another name (normalised name => normalised name in the list). */
    public const NAME_ALIASES = [
        'villingili' => 'villimale', 'vilimale' => 'villimale',
        'fuvammulah' => 'fuvahmulah', 'foammulah' => 'fuvahmulah',
        // Addu City is several islands joined by road; Hithadhoo is its main island
        'addu' => 'hithadhoo', 'adducity' => 'hithadhoo',
    ];

    /** Tables whose rows point at an island: such an island is never switched off by the load. */
    public const REFERENCES = [
        ['addresses', 'island_id'],
        ['quote_requests', 'island_id'],
        ['seller_delivery_settings', 'pickup_island_id'],
        ['product_island', 'island_id'],
        ['users', 'home_island_id'],
        ['users', 'shopping_island_id'],
        ['shop_delivery_islands', 'island_id'],
    ];

    /**
     * "Malé", "Male" and "MALE " all become "male": lower case, accents and anything that is not
     * a letter or digit removed.
     */
    public static function normalize(?string $name): string
    {
        $ascii = Str::ascii(mb_strtolower(trim((string) $name)));

        return (string) preg_replace('/[^a-z0-9]/', '', $ascii);
    }

    public static function normalizeAtoll(?string $atoll): string
    {
        $key = self::normalize(preg_replace('/\batoll\b/i', '', (string) $atoll));

        return self::ATOLL_ALIASES[$key] ?? $key;
    }

    /** A stable code for an island in the list: "kaafu-male". */
    public static function code(string $atoll, string $name): string
    {
        return Str::slug($atoll.' '.$name);
    }

    /**
     * @return list<array{salat_id: ?int, name_en: string, name_dv: string, atoll_en: string, atoll_dv: string, lat: float|int, lng: float|int, active: int|bool}>
     */
    public static function entries(?string $path = null): array
    {
        $json = file_get_contents($path ?? database_path('data/islands.json'));

        return json_decode((string) $json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Put the list into the islands table. Rows already there are matched by code (an earlier
     * load), else by English name (accents and case ignored, the atoll deciding when two islands
     * share a name), so their ids, and the addresses and orders pointing at them, are kept. A
     * matched row keeps its on/off switch (admins may have changed it); new rows take the list's.
     *
     * Rows that match nothing (e.g. the old "Addu City") are switched off unless something points
     * at them; those are kept on and named in the result.
     *
     * @return array{inserted: int, updated: int, deactivated: list<string>, kept: list<string>}
     */
    public static function load(?string $path = null): array
    {
        $entries = self::entries($path);
        $hasCode = Schema::hasColumn('islands', 'code');
        $existing = DB::table('islands')->get()->map(function ($row) {
            $name = json_decode((string) $row->name, true) ?: [];
            $row->name_en = (string) ($name['en'] ?? '');

            return $row;
        });

        // Names that appear in more than one atoll need the atoll to match
        $nameCounts = collect($entries)->countBy(fn ($entry) => self::normalize($entry['name_en']));

        $matched = [];
        $inserted = 0;
        $updated = 0;
        $now = now();

        foreach ($entries as $entry) {
            $code = self::code($entry['atoll_en'], $entry['name_en']);
            $normal = self::normalize($entry['name_en']);
            $atoll = self::normalizeAtoll($entry['atoll_en']);

            $row = $existing->first(fn ($row) => ! isset($matched[$row->id]) && ($row->code ?? null) === $code)
                ?? $existing->first(function ($row) use ($matched, $normal, $atoll, $nameCounts) {
                    if (isset($matched[$row->id]) || filled($row->code ?? null) || self::normalize($row->name_en) !== $normal) {
                        return false;
                    }

                    return $nameCounts[$normal] === 1 || self::normalizeAtoll($row->atoll) === $atoll;
                });

            $lat = (float) $entry['lat'];
            $lng = (float) $entry['lng'];
            $values = [
                'name' => json_encode(['en' => $entry['name_en'], 'dv' => $entry['name_dv']]),
                'atoll' => $entry['atoll_en'],
                'atoll_dv' => $entry['atoll_dv'],
                // 0,0 means "position unknown" in the dataset
                'latitude' => $lat == 0.0 && $lng == 0.0 ? null : $lat,
                'longitude' => $lat == 0.0 && $lng == 0.0 ? null : $lng,
                'salat_id' => $entry['salat_id'],
                'code' => $code,
                'updated_at' => $now,
            ];
            if (! $hasCode) {
                $values = array_intersect_key($values, array_flip(['name', 'atoll', 'updated_at']));
            }

            if ($row) {
                $matched[$row->id] = true;
                DB::table('islands')->where('id', $row->id)->update($values);
                $updated++;
            } else {
                DB::table('islands')->insert($values + ['is_active' => (bool) $entry['active'], 'created_at' => $now]);
                $inserted++;
            }
        }

        $deactivated = [];
        $kept = [];
        foreach ($existing as $row) {
            if (isset($matched[$row->id]) || filled($row->code ?? null)) {
                continue;
            }
            if (self::isReferenced((int) $row->id)) {
                $kept[] = $row->name_en;
            } elseif ($row->is_active) {
                DB::table('islands')->where('id', $row->id)->update(['is_active' => false, 'updated_at' => $now]);
                $deactivated[] = $row->name_en;
            }
        }

        return ['inserted' => $inserted, 'updated' => $updated, 'deactivated' => $deactivated, 'kept' => $kept];
    }

    /**
     * Existing shops without a home island: their typed island (city) and atoll (state) looked up
     * in the list. They keep "all islands" (today's behaviour), except shops that had unticked
     * "I ship to other islands", which become "home island and Greater Malé". Shops whose island
     * is not found are left as they are (all islands, no home island) and asked on their dashboard.
     *
     * @return int how many shops got a home island
     */
    public static function backfillShopHomes(): int
    {
        $islands = DB::table('islands')->get(['id', 'name', 'atoll', 'is_active']);
        $count = 0;
        DB::table('users')->where('is_seller', true)->whereNull('home_island_id')
            ->whereNotNull('city')->where('city', '!=', '')
            ->orderBy('id')->get(['id', 'city', 'state', 'ships_to_islands'])
            ->each(function ($shop) use ($islands, &$count) {
                $island = self::match($islands, $shop->city, $shop->state);
                if (! $island) {
                    return;
                }
                DB::table('users')->where('id', $shop->id)->update([
                    'home_island_id' => $island->id,
                    'delivery_coverage' => $shop->ships_to_islands ? 'all' : 'home_and_male',
                ]);
                $count++;
            });

        return $count;
    }

    public static function isReferenced(int $islandId): bool
    {
        foreach (self::REFERENCES as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column) && DB::table($table)->where($column, $islandId)->exists()) {
                return true;
            }
        }

        return false;
    }

    /**
     * The island a typed name (and atoll) means, from rows with id, name (JSON or array) and atoll:
     * accents, case and common other names ignored; the atoll decides between islands of the same
     * name, and a name in several atolls with no atoll given matches none.
     *
     * @param  Collection<int, covariant object>  $islands
     */
    public static function match(Collection $islands, ?string $name, ?string $atoll = null): ?object
    {
        $normal = self::normalize($name);
        if ($normal === '') {
            return null;
        }
        $normal = self::NAME_ALIASES[$normal] ?? $normal;
        $wantedAtoll = self::normalizeAtoll($atoll);

        $candidates = $islands->filter(function ($island) use ($normal) {
            if ($island instanceof \App\Models\Island) {
                return self::normalize($island->getTranslation('name', 'en', false)) === $normal;
            }
            $names = is_array($island->name) ? $island->name : (json_decode((string) $island->name, true) ?: []);

            return self::normalize($names['en'] ?? '') === $normal;
        });
        if ($candidates->count() > 1 && $wantedAtoll !== '') {
            $candidates = $candidates->filter(fn ($island) => self::normalizeAtoll($island->atoll) === $wantedAtoll);
        }
        if ($candidates->count() > 1) {
            // Prefer the switched-on one when an old row shares the name
            $active = $candidates->filter(fn ($island) => (bool) ($island->is_active ?? true));
            $candidates = $active->count() === 1 ? $active : $candidates;
        }

        return $candidates->count() === 1 ? $candidates->first() : null;
    }
}
