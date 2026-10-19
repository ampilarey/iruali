<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Island;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin → Islands: adding an island the list is missing and correcting one (names, atoll, map
 * position). Only admins.
 */
class AdminIslandEditTest extends TestCase
{
    use RefreshDatabase;

    protected function admin(): User
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        return $admin;
    }

    public function test_an_admin_adds_a_missing_island_and_it_is_offered_straight_away(): void
    {
        $this->actingAs($this->admin())->get(route('admin.islands'))->assertOk()->assertSee('data-island-add', false);

        $this->post(route('admin.islands.store'), [
            'name_en' => 'Gulhifalhu', 'name_dv' => 'ގުޅީފަޅު', 'atoll' => 'Kaafu', 'latitude' => '4.1750', 'longitude' => '73.4380',
        ])->assertRedirect(route('admin.islands', ['q' => 'Gulhifalhu']))->assertSessionHas('success');

        $island = Island::where('code', 'kaafu-gulhifalhu')->sole();
        $this->assertTrue($island->is_active);
        $this->assertSame('ގުޅީފަޅު', $island->getTranslation('name', 'dv'));
        $this->assertSame(Island::where('atoll', 'Kaafu')->whereKeyNot($island->id)->value('atoll_dv'), $island->atoll_dv);
        $this->assertEqualsWithDelta(4.175, $island->latitude, 0.0001);
        $this->assertTrue(AuditLog::where('action', 'island.created')->exists());
        $this->assertTrue(app(\App\Services\IslandDirectory::class)->active()->contains('id', $island->id), 'in the pickers at once');

        // The same island again, or one outside the Maldives: refused
        $this->post(route('admin.islands.store'), ['name_en' => 'gulhifalhu', 'atoll' => 'Kaafu'])->assertSessionHasErrors('name_en');
        $this->post(route('admin.islands.store'), ['name_en' => 'Faraway', 'atoll' => 'Kaafu', 'latitude' => '20', 'longitude' => '73'])->assertSessionHasErrors('latitude');
        $this->post(route('admin.islands.store'), ['name_en' => 'Nowhere', 'atoll' => 'Not an atoll'])->assertSessionHasErrors('atoll');
        $this->assertSame(1, Island::where('name->en', 'Gulhifalhu')->count());
    }

    public function test_an_admin_corrects_an_island(): void
    {
        $island = Island::where('atoll', 'Kaafu')->orderBy('id')->firstOrFail();
        $other = Island::where('atoll', 'Kaafu')->whereKeyNot($island->id)->orderBy('id')->firstOrFail();

        $this->actingAs($this->admin())->get(route('admin.islands.edit', $island))->assertOk()->assertSee('data-island-edit', false)->assertSee($island->englishName());
        $this->put(route('admin.islands.update', $island), ['name_en' => 'Renamed Isle', 'name_dv' => '', 'atoll' => 'Kaafu', 'latitude' => '', 'longitude' => ''])
            ->assertRedirect()->assertSessionHas('success');

        $island->refresh();
        $this->assertSame('Renamed Isle', $island->englishName());
        $this->assertSame('', $island->getTranslation('name', 'dv', false));
        $this->assertNull($island->latitude);
        $this->assertTrue(AuditLog::where('action', 'island.updated')->exists());

        // Not to another island's name in the same atoll
        $this->put(route('admin.islands.update', $island), ['name_en' => $other->englishName(), 'atoll' => 'Kaafu'])->assertSessionHasErrors('name_en');
        $this->assertSame('Renamed Isle', $island->fresh()->englishName());
    }

    public function test_only_admins(): void
    {
        $island = Island::firstOrFail();
        $this->actingAs(User::factory()->create())->get(route('admin.islands.edit', $island))->assertForbidden();
        $this->post(route('admin.islands.store'), ['name_en' => 'X', 'atoll' => 'Kaafu'])->assertForbidden();
        $this->put(route('admin.islands.update', $island), ['name_en' => 'X', 'atoll' => 'Kaafu'])->assertForbidden();
    }
}
