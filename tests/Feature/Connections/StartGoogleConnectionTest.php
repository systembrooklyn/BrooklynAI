<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\OAuthStateModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StartGoogleConnectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.connections_redirect' => 'https://example.test/api/connections/google/callback',
            'connections.frontend_redirect' => 'https://frontend.test/connect',
        ]);
    }

    public function test_start_requires_authentication(): void
    {
        $this->postJson('/api/connections/google/start')->assertStatus(401);
    }

    public function test_start_returns_redirect_url_and_persists_state(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connections/google/start');

        $response->assertStatus(200);
        $response->assertJsonStructure(['redirect_url']);
        $url = $response->json('redirect_url');

        $this->assertStringStartsWith('https://accounts.google.com/o/oauth2/v2/auth?', $url);

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('test-client-id', $query['client_id'] ?? null);
        $this->assertSame('code', $query['response_type'] ?? null);
        $this->assertSame('offline', $query['access_type'] ?? null);
        $this->assertSame('select_account', $query['prompt'] ?? null);
        $this->assertSame('https://example.test/api/connections/google/callback', $query['redirect_uri'] ?? null);

        $scopes = explode(' ', (string) ($query['scope'] ?? ''));
        sort($scopes);
        $this->assertSame(['email', 'openid', 'profile'], $scopes);

        $this->assertIsString($query['state'] ?? null);
        $this->assertSame(64, strlen((string) $query['state']));

        $this->assertSame(1, OAuthStateModel::query()->where('state', $query['state'])->count());
        $this->assertSame($user->id, (int) OAuthStateModel::query()->where('state', $query['state'])->value('user_id'));
    }

    public function test_start_with_gmail_capability_includes_gmail_scopes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connections/google/start', [
            'capability' => 'gmail',
        ]);

        $response->assertStatus(200);
        $url = $response->json('redirect_url');

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $rawScope = (string) ($query['scope'] ?? '');

        $scopes = explode(' ', $rawScope);
        sort($scopes);

        // PHP `sort()` uses byte comparison; URL strings start with 'h'
        // which sorts after 'email' (e) but before 'openid' (o).
        $this->assertSame([
            'email',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
            'openid',
            'profile',
        ], $scopes);

        $this->assertStringNotContainsString('gmail.labels', $rawScope);
    }

    public function test_start_with_unknown_capability_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connections/google/start', [
            'capability' => 'not-a-real-capability',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['capability']);
    }

    public function test_start_ignores_client_provided_raw_scopes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connections/google/start', [
            'scopes' => ['https://www.googleapis.com/auth/drive'],
        ]);

        $response->assertStatus(200);
        $url = $response->json('redirect_url');

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $rawScope = (string) ($query['scope'] ?? '');

        $this->assertStringNotContainsString('drive', $rawScope);

        $scopes = explode(' ', $rawScope);
        sort($scopes);
        $this->assertSame(['email', 'openid', 'profile'], $scopes);
    }

    public function test_start_with_capability_persists_state_with_capability_scopes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connections/google/start', [
            'capability' => 'gmail',
        ]);

        $url = $response->json('redirect_url');
        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $state = OAuthStateModel::query()->where('state', $query['state'])->firstOrFail();

        $this->assertContains('openid', $state->scopes_requested);
        $this->assertContains('email', $state->scopes_requested);
        $this->assertContains('profile', $state->scopes_requested);
        $this->assertContains('https://www.googleapis.com/auth/gmail.readonly', $state->scopes_requested);
        $this->assertContains('https://www.googleapis.com/auth/gmail.send', $state->scopes_requested);
        $this->assertNotContains('https://www.googleapis.com/auth/gmail.labels', $state->scopes_requested);
    }

    public function test_start_with_empty_capability_uses_default_scopes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connections/google/start', [
            'capability' => '',
        ]);

        $response->assertStatus(200);
        $url = $response->json('redirect_url');

        $query = [];
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $scopes = explode(' ', (string) ($query['scope'] ?? ''));
        sort($scopes);

        $this->assertSame(['email', 'openid', 'profile'], $scopes);
    }
}
