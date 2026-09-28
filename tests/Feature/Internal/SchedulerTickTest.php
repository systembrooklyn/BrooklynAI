<?php

namespace Tests\Feature\Internal;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SchedulerTickTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('internal-scheduler-tick');
    }

    public function test_missing_token_returns_401(): void
    {
        config(['internal_scheduler.token' => 'test-token']);

        $this->postJson('/api/internal/scheduler/tick')
            ->assertStatus(401)
            ->assertJson(['error' => 'unauthorized']);
    }

    public function test_wrong_token_returns_401(): void
    {
        config(['internal_scheduler.token' => 'test-token']);

        $this->postJson('/api/internal/scheduler/tick', [], [
            'Authorization' => 'Bearer wrong-token',
        ])->assertStatus(401);
    }

    public function test_misconfigured_server_returns_503(): void
    {
        config(['internal_scheduler.token' => '']);

        $this->postJson('/api/internal/scheduler/tick', [], [
            'Authorization' => 'Bearer anything',
        ])->assertStatus(503);
    }

    public function test_valid_token_returns_ok(): void
    {
        config([
            'internal_scheduler.token' => 'test-token',
            'internal_scheduler.rate_limit_per_minute' => 20,
        ]);

        $this->postJson('/api/internal/scheduler/tick', [], [
            'Authorization' => 'Bearer test-token',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonStructure([
                'ok', 'processed', 'executed', 'skipped', 'failed', 'lock_held',
            ]);
    }

    public function test_get_method_returns_405(): void
    {
        config(['internal_scheduler.token' => 'test-token']);

        $this->getJson('/api/internal/scheduler/tick', [
            'Authorization' => 'Bearer test-token',
        ])->assertStatus(405);
    }

    public function test_rate_limiting_rejects_excess_calls(): void
    {
        config([
            'internal_scheduler.token' => 'test-token',
            'internal_scheduler.rate_limit_per_minute' => 2,
        ]);

        $headers = ['Authorization' => 'Bearer test-token'];

        $this->postJson('/api/internal/scheduler/tick', [], $headers)->assertStatus(200);
        $this->postJson('/api/internal/scheduler/tick', [], $headers)->assertStatus(200);
        $this->postJson('/api/internal/scheduler/tick', [], $headers)
            ->assertStatus(429)
            ->assertJson(['error' => 'rate_limited']);
    }
}
