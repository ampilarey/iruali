<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DhivehiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dhivehi_pages_are_right_to_left_and_translated(): void
    {
        $this->withSession(['locale' => 'dv'])
            ->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee('ކާޓް')          // Cart
            ->assertSee('ހުރިހާ ތަކެތި ބައްލަވާ'); // View All Products
    }

    public function test_english_pages_stay_left_to_right(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertOk()
            ->assertSee('dir="ltr"', false)
            ->assertSee('View All Products');
    }

    public function test_admin_pages_stay_left_to_right_in_dhivehi(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::firstOrCreate(['name' => 'admin'], ['display_name' => 'Admin'])->id);

        $this->actingAs($admin)
            ->withSession(['locale' => 'dv'])
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('dir="ltr"', false);
    }

    public function test_every_translated_view_string_has_a_dhivehi_entry(): void
    {
        $dv = json_decode(file_get_contents(lang_path('dv.json')), true);
        $missing = [];

        foreach (glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE) as $file) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'\\)/", file_get_contents($file), $m);
            foreach ($m[1] as $key) {
                $key = stripslashes($key);
                // Dotted keys ("products.name") live in the PHP translation files.
                if (preg_match('/^[a-z0-9_]+\\.[a-z0-9_.]+$/', $key)) {
                    continue;
                }
                if (! array_key_exists($key, $dv)) {
                    $missing[] = $key.'  ('.basename($file).')';
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)), 'Strings missing from resources/lang/dv.json');
    }
}
