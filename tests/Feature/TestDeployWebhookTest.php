<?php

namespace Tests\Feature;

use App\Services\Deploy\TestDeployTrigger;
use Tests\TestCase;

/**
 * POST /api/deploy/test-pull: GitHub Actions starts the test site's pull-deploy script. Off without a
 * long secret, only on the test site, only with the secret; the script itself is never run here.
 */
class TestDeployWebhookTest extends TestCase
{
    protected const SECRET = 'a-long-test-deploy-secret-1234';

    protected function setUp(): void
    {
        parent::setUp();
        config(['deploy.test_webhook_secret' => self::SECRET, 'deploy.test_allowed_hosts' => ['test.iruali.mv'], 'app.url' => 'https://test.iruali.mv']);
        $this->mock(TestDeployTrigger::class, fn ($mock) => $mock->shouldReceive('triggerAsync')->andReturn(['ok' => true, 'message' => 'Deploy started']));
    }

    protected function hit(array $headers = [], string $host = 'test.iruali.mv', array $body = [])
    {
        return $this->withHeaders($headers)->postJson('https://'.$host.'/api/deploy/test-pull', $body);
    }

    public function test_it_is_off_without_a_long_secret(): void
    {
        config(['deploy.test_webhook_secret' => 'short']);
        $this->hit(['Authorization' => 'Bearer short'])->assertNotFound();
        config(['deploy.test_webhook_secret' => null]);
        $this->hit()->assertNotFound();
    }

    public function test_a_wrong_or_missing_secret_is_refused(): void
    {
        $this->hit()->assertUnauthorized();
        $this->hit(['Authorization' => 'Bearer wrong-secret-of-good-length'])->assertUnauthorized();
    }

    public function test_only_the_test_site_honours_it(): void
    {
        // Another host
        $this->hit(['Authorization' => 'Bearer '.self::SECRET], 'iruali.mv')->assertNotFound();
        // The live site (APP_URL iruali.mv), even when a request claims the test host
        config(['app.url' => 'https://iruali.mv']);
        $this->hit(['Authorization' => 'Bearer '.self::SECRET])->assertNotFound();
    }

    public function test_the_right_secret_on_the_test_site_starts_the_deploy(): void
    {
        $sha = str_repeat('a', 40);
        $this->hit(['X-Deploy-Secret' => self::SECRET], 'test.iruali.mv', ['sha' => $sha])->assertStatus(202)->assertJson(['sha' => $sha]);
    }
}
