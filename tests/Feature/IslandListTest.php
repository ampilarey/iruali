<?php

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Island;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\IslandDirectory;
use App\Support\IslandList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The inhabited-islands list (database/data/islands.json) loaded by a migration: production's four
 * starter islands keep their ids, "Addu City" is switched off unless something points at it, the
 * load can run again, plus "Use my location" (nearest island) and existing shops' home islands.
 */
class IslandListTest extends TestCase
{
    use RefreshDatabase;

    protected const MIGRATION = '2026_10_18_100000_islands_add_location_and_load_island_list.php';

    /**
     * The islands table as production had it before the list: IslandSeeder's four starter rows.
     *
     * @return array<string, Island>
     */
    protected function starterIslands(): array
    {
        DB::table('islands')->delete();
        $rows = [];
        foreach ([['Malé', 'މާލެ', 'Kaafu'], ['Hulhumalé', 'ހުޅުމާލެ', 'Kaafu'], ['Addu City', 'އައްޑޫ', 'Seenu'], ['Fuvahmulah', 'ފުވައްމުލައް', 'Gnaviyani']] as [$en, $dv, $atoll]) {
            $rows[$en] = Island::create(['name' => ['en' => $en, 'dv' => $dv], 'atoll' => $atoll, 'is_active' => true]);
        }

        return $rows;
    }

    protected function runMigration(): void
    {
        (require database_path('migrations/'.self::MIGRATION))->up();
    }

    public function test_the_migration_loads_every_island_once(): void
    {
        $this->assertSame(207, Island::count());
        $this->assertSame(207, Island::whereNotNull('code')->count());
        $this->assertSame(190, Island::where('is_active', true)->count());

        $male = Island::where('code', 'kaafu-male')->sole();
        $this->assertSame('Malé', $male->getTranslation('name', 'en'));
        $this->assertSame('މާލެ', $male->getTranslation('name', 'dv'));
        $this->assertSame('ކ', $male->atoll_dv);
        $this->assertEqualsWithDelta(4.17503, $male->latitude, 0.0001);

        // Hand-added islands without a prayer-times id, and islands without a position
        $this->assertNull(Island::where('code', 'kaafu-hulhumale')->value('salat_id'));
        $this->assertNull(Island::where('code', 'haa-alif-berinmadhoo')->value('latitude'));
    }

    public function test_starter_islands_keep_their_ids_and_addu_city_is_switched_off(): void
    {
        $starter = $this->starterIslands();
        $this->runMigration();

        $this->assertSame(208, Island::count(), '207 listed islands plus the old Addu City row');
        $this->assertSame('kaafu-male', $starter['Malé']->fresh()->code);
        $this->assertSame('kaafu-hulhumale', $starter['Hulhumalé']->fresh()->code);
        $this->assertSame('gnaviyani-fuvahmulah', $starter['Fuvahmulah']->fresh()->code);
        $this->assertSame(1, Island::where('code', 'kaafu-male')->count());

        $addu = $starter['Addu City']->fresh();
        $this->assertNull($addu->code);
        $this->assertFalse($addu->is_active, 'nothing points at it, so it leaves the pickers');

        // Running it again changes nothing
        $this->runMigration();
        $this->assertSame(208, Island::count());
        $this->assertSame(['inserted' => 0, 'updated' => 207, 'deactivated' => [], 'kept' => []], IslandList::load());
    }

    public function test_addu_city_stays_on_while_an_address_points_at_it(): void
    {
        $starter = $this->starterIslands();
        $customer = User::factory()->create();
        Address::create(['user_id' => $customer->id, 'recipient_name' => 'Aisha', 'island_id' => $starter['Addu City']->id, 'island' => 'Addu City', 'atoll' => 'Seenu', 'house_name_or_street' => 'Blue House']);

        $result = IslandList::load();

        $this->assertSame(['Addu City'], $result['kept']);
        $this->assertTrue($starter['Addu City']->fresh()->is_active);
        $this->assertSame($starter['Addu City']->id, Address::sole()->island_id);
    }

    public function test_switched_off_islands_stay_off_when_the_list_loads_again(): void
    {
        Island::where('code', 'kaafu-gulhi')->update(['is_active' => false]);
        IslandList::load();

        $this->assertFalse((bool) Island::where('code', 'kaafu-gulhi')->value('is_active'));
    }

    public function test_the_nearest_island_to_a_position(): void
    {
        $islands = app(IslandDirectory::class);

        $this->assertSame('kaafu-hulhumale', $islands->nearest(4.2120, 73.5400)['island']->code);
        $this->assertSame('kaafu-male', $islands->nearest(4.1755, 73.5093)['island']->code);
        $this->assertSame('seenu-hithadhoo', $islands->nearest(-0.6080, 73.0880)['island']->code);
        $this->assertNull($islands->nearest(51.5072, -0.1276), 'London is not in the Maldives');
        $this->assertEqualsWithDelta(111.2, IslandDirectory::distanceKm(0, 73, 1, 73), 0.5);
    }

    public function test_islands_are_found_by_typed_names_and_greater_male_by_the_list(): void
    {
        $islands = app(IslandDirectory::class);
        $delivery = app(DeliveryService::class);

        $this->assertSame('kaafu-male', $islands->findByName('Male')?->code);
        $this->assertSame('kaafu-male', $islands->findByName(' MALÉ ')?->code);
        $this->assertSame('kaafu-villimale', $islands->findByName('Villingili')?->code);
        $this->assertSame('seenu-hithadhoo', $islands->findByName('Hithadhoo', 'Addu')?->code);
        $this->assertNull($islands->findByName('Hithadhoo'), 'two atolls have a Hithadhoo: the atoll decides');
        $this->assertSame('laamu-hithadhoo', $islands->findByName('Hithadhoo', 'Laamu')?->code);

        foreach (['kaafu-male', 'kaafu-hulhumale', 'kaafu-villimale'] as $code) {
            $this->assertTrue(Island::where('code', $code)->sole()->isGreaterMale(), $code);
        }
        $this->assertFalse(Island::where('code', 'kaafu-maafushi')->sole()->isGreaterMale());
        $this->assertSame('greater_male', $delivery->zoneFor(null, 'Male'));
    }

    public function test_existing_shops_get_a_home_island_and_keep_todays_coverage(): void
    {
        $everywhere = User::factory()->create(['is_seller' => true, 'city' => 'Male', 'state' => 'Kaafu', 'ships_to_islands' => true]);
        $islandOnly = User::factory()->create(['is_seller' => true, 'city' => 'Hithadhoo', 'state' => 'Addu', 'ships_to_islands' => false]);
        $unknown = User::factory()->create(['is_seller' => true, 'city' => 'Somewhere', 'ships_to_islands' => false]);
        $customer = User::factory()->create(['is_seller' => false, 'city' => 'Male']);

        $this->assertSame(2, IslandList::backfillShopHomes());

        $this->assertSame(Island::where('code', 'kaafu-male')->value('id'), $everywhere->fresh()->home_island_id);
        $this->assertSame('all', $everywhere->fresh()->delivery_coverage);
        $this->assertSame(Island::where('code', 'seenu-hithadhoo')->value('id'), $islandOnly->fresh()->home_island_id);
        $this->assertSame('home_and_male', $islandOnly->fresh()->delivery_coverage);
        $this->assertNull($unknown->fresh()->home_island_id);
        $this->assertSame('all', $unknown->fresh()->delivery_coverage, 'not found: today\'s behaviour');
        $this->assertNull($customer->fresh()->home_island_id);
    }
}
