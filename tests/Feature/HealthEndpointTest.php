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

    public function test_health_reports_unknown_commit_without_stamp(): void
    {
        @unlink($this->stamp);

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'healthy')
            ->assertJsonPath('commit', 'unknown');
    }

    public function test_health_reports_deployed_commit_from_stamp(): void
    {
        file_put_contents($this->stamp, json_encode([
            'commit' => str_repeat('a', 40),
            'commit_short' => 'aaaaaaa',
            'branch' => 'main',
            'deployed_at' => '2026-09-24T17:00:00Z',
        ]));

        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('commit', 'aaaaaaa')
            ->assertJsonPath('branch', 'main')
            ->assertJsonPath('deployed_at', '2026-09-24T17:00:00Z');
    }
}
