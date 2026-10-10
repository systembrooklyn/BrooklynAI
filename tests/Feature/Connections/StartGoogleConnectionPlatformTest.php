<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\OAuthStateModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class StartGoogleConnectionPlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
        config()->set('services.google.connections_redirect', 'https://example.test/callback');
        config()->set('connections.frontend_redirect', 'https://web.example.test');
        config()->set('connections.frontend_redirect_mobile', 'brooklynai://connections/google');
    }

    public function test_start_without_header_defaults_to_web(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/connections/google/start', ['capability' => 'drive'])
            ->assertOk()
            ->assertJsonStructure(['redirect_url']);

        $state = OAuthStateModel::query()->latest('id')->first();
        $this->assertNotNull($state);
        $this->assertSame('web', $state->platform);
    }

    public function test_start_with_mobile_header_stores_mobile_platform(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('X-Client-Platform', 'mobile')
            ->postJson('/api/connections/google/start', ['capability' => 'drive'])
            ->assertOk();

        $state = OAuthStateModel::query()->latest('id')->first();
        $this->assertNotNull($state);
        $this->assertSame('mobile', $state->platform);
    }

    public function test_header_value_is_case_insensitive(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('X-Client-Platform', 'MOBILE')
            ->postJson('/api/connections/google/start', ['capability' => 'drive'])
            ->assertOk();

        $state = OAuthStateModel::query()->latest('id')->first();
        $this->assertSame('mobile', $state->platform);
    }

    public function test_unknown_header_value_falls_back_to_web(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('X-Client-Platform', 'tablet')
            ->postJson('/api/connections/google/start', ['capability' => 'drive'])
            ->assertOk();

        $state = OAuthStateModel::query()->latest('id')->first();
        $this->assertSame('web', $state->platform);
    }

    public function test_header_does_not_affect_capability_validation(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->withHeader('X-Client-Platform', 'mobile')
            ->postJson('/api/connections/google/start', ['capability' => 'unknown_capability'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('capability');
    }
}
