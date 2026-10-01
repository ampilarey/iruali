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
        $this->assertNoMissingDhivehi(glob(resource_path('views/{,*/,*/*/}*.blade.php'), GLOB_BRACE));
    }

    public function test_every_translated_string_in_customer_code_has_a_dhivehi_entry(): void
    {
        $this->assertNoMissingDhivehi(array_merge(
            glob(app_path('Notifications/*.php')),
            glob(app_path('Services/*.php')),
            glob(app_path('Http/Controllers/Customer/*.php')),
            glob(app_path('Http/Requests/*.php')),
        ));
    }

    public function test_dhivehi_dates_and_validation_messages_are_translated(): void
    {
        $this->withSession(['locale' => 'dv'])->get('/')->assertOk();

        $this->assertSame('dv', \Carbon\Carbon::getLocale());
        $this->assertStringContainsString('މާރިޗު', \Carbon\Carbon::parse('2026-03-05')->translatedFormat('j F Y'));
        $this->assertSame('އީމެއިލް ލާޒިމު.', __('validation.required', ['attribute' => __('validation.attributes.email')]));
        $this->assertFileExists(lang_path('dv/pagination.php'));
    }

    public function test_dhivehi_mail_is_right_to_left(): void
    {
        app()->setLocale('dv');
        $html = (string) view('vendor.mail.html.layout', ['slot' => 'ސަލާމް'])->render();
        app()->setLocale('en');

        $this->assertStringContainsString('dir="rtl"', $html);
        $this->assertStringContainsString('direction: rtl', $html);
    }

    /**
     * Every __('...') string (without parameters) in the given files must have a dv.json entry.
     * Dotted keys ("products.name") live in the PHP translation files and are skipped.
     */
    private function assertNoMissingDhivehi(array $files): void
    {
        $dv = json_decode(file_get_contents(lang_path('dv.json')), true);
        $missing = [];

        foreach ($files as $file) {
            // __('...') and __('...', [...]) alike: the key is the first single-quoted argument.
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'\\s*[,)]/", file_get_contents($file), $m);
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
