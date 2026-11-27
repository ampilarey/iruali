<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Audit 2, batch 6: what the deploy scripts, the service worker and the web server config must keep
 * doing. CI does not run the deploy scripts (they need the servers), so their safety rules are
 * checked here as text, and every script is syntax-checked (tests.yml, bash -n).
 */
class OpsScriptsTest extends TestCase
{
    protected function file(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }

    public function test_a_deploy_or_rollback_never_changes_code_under_live_traffic_and_survives_an_interruption(): void
    {
        $deploy = $this->file('scripts/deploy-production.sh');
        $this->assertStringContainsString("trap 'rollback \"interrupted\"' INT TERM HUP", $deploy);
        $this->assertStringContainsString('the site is already in maintenance mode (an earlier deploy did not finish); going on', $deploy);
        // The rollback goes back to the maintenance page before rebuilding the old code
        $this->assertMatchesRegularExpression('/rollback\(\) \{.*php artisan down --render="errors::503"/s', $deploy);
        // Re-deploying the same commit keeps the version before it as the rollback point
        $this->assertStringContainsString('LOG_PREV="$earlier"', $deploy);
        $this->assertStringContainsString('status=ok"', $deploy);
        $this->assertStringContainsString('previous=${LOG_PREV} previous_name=$LOG_PREV_NAME status=ok', $deploy);

        $rollback = $this->file('scripts/rollback-production.sh');
        $this->assertStringContainsString('php artisan down --render="errors::503" --retry=30', $rollback);
        $this->assertStringContainsString('the site already runs $TARGET_NAME', $rollback);
    }

    public function test_the_deploy_checks_the_built_stylesheet_and_every_copy_to_the_docroot(): void
    {
        $lib = $this->file('scripts/deploy-lib.sh');
        $this->assertStringContainsString("grep -oE '/build/assets/[A-Za-z0-9._-]+\\.css'", $lib);
        $this->assertStringContainsString('copying the built assets to $DOCROOT failed', $lib);
        // The test-site deploy job is not a check of the code
        $this->assertStringContainsString('str_starts_with((string) ($r["name"] ?? ""), "Webhook pull")', $lib);
        $this->assertStringContainsString('str_starts_with((string) ($r["name"] ?? ""), "Webhook pull")', $this->file('scripts/self-update-test.sh'));
        $this->assertStringContainsString('no CI checks yet — holding', $this->file('scripts/self-update-test.sh'));
    }

    public function test_the_test_site_deploy_clears_a_stale_lock_and_runs_the_smoke_test_on_old_bash(): void
    {
        $pull = $this->file('scripts/pull-deploy-test.sh');
        $this->assertStringContainsString('-mmin +30', $pull);
        $this->assertStringContainsString('${SMOKE_ARGS[@]+"${SMOKE_ARGS[@]}"}', $pull);
        $this->assertStringContainsString('|| code="000"', $this->file('.github/workflows/deploy-test-immediate.yml'));
        $this->assertStringContainsString('npm run build', $this->file('.github/workflows/release.yml'));
    }

    public function test_the_service_worker_stores_no_signed_in_page_and_trims_its_photos(): void
    {
        $sw = $this->file('public/sw.js');
        $this->assertStringContainsString("credentials: 'omit'", $sw);
        $this->assertStringContainsString('trim(cache, MAX_ASSETS)', $sw);
        $this->assertStringNotContainsString('cache.addAll(PRECACHE)', $sw);
    }

    public function test_www_goes_to_the_bare_domain_and_the_admin_seeder_reads_no_env(): void
    {
        $this->assertStringContainsString('RewriteCond %{HTTP_HOST} ^www\\.(.+)$ [NC]', $this->file('public/.htaccess'));
        $this->assertStringNotContainsString('env(', $this->file('database/seeders/UserSeeder.php'));
    }
}
