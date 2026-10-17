<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HealthEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected string $stamp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stamp = storage_path('app/deploy-stamp.json');
        $this->backup = is_file($this->stamp) ? file_get_contents($this->stamp) : null;
    }

    protected function tearDown(): void
    {
        $this->backup === null ? @unlink($this->stamp) : file_put_contents($this->stamp, $this->backup);
        parent::tearDown();
    }

    protected ?string $backup = null;

    public function test_health_hides_the_commit_from_the_public(): void
    {
        @unlink($this->stamp);
        config(['app.health_detail_token' => 'operator-token']);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonMissingPath('commit')
            ->assertJsonMissingPath('branch');

        $this->getJson('/api/health', ['X-Health-Token' => 'wrong'])
            ->assertOk()
            ->assertJsonMissingPath('commit');
    }

    public function test_health_reports_unknown_commit_with_the_operator_token_and_no_stamp(): void
    {
        @unlink($this->stamp);
        config(['app.health_detail_token' => 'operator-token']);

        $this->getJson('/api/health', ['X-Health-Token' => 'operator-token'])
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('commit', 'unknown')
            ->assertJsonPath('branch', null)
            ->assertJsonPath('tag', null)
            ->assertJsonPath('deployed_at', null);
    }

    public function test_health_reports_deployed_commit_from_stamp(): void
    {
        config(['app.health_detail_token' => 'operator-token']);
        file_put_contents($this->stamp, json_encode([
            'commit' => str_repeat('a', 40),
            'commit_short' => 'aaaaaaa',
            'branch' => 'main',
            'deployed_at' => '2026-09-24T17:00:00Z',
        ]));

        $this->getJson('/api/health', ['X-Health-Token' => 'operator-token'])
            ->assertOk()
            ->assertJsonPath('commit', 'aaaaaaa')
            ->assertJsonPath('branch', 'main')
            ->assertJsonPath('tag', null)
            ->assertJsonPath('deployed_at', '2026-09-24T17:00:00Z');
    }

    public function test_health_reports_the_release_tag_on_production(): void
    {
        config(['app.health_detail_token' => 'operator-token']);
        file_put_contents($this->stamp, json_encode([
            'commit' => str_repeat('b', 40),
            'commit_short' => 'bbbbbbb',
            'branch' => 'HEAD',
            'tag' => 'v2026.10.01',
            'deployed_at' => '2026-10-01T09:00:00Z',
        ]));

        $this->getJson('/api/health', ['X-Health-Token' => 'operator-token'])
            ->assertOk()
            ->assertJsonPath('commit', 'bbbbbbb')
            ->assertJsonPath('tag', 'v2026.10.01');
    }
}
