<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Connections\Infrastructure\Eloquent\OAuthStateModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleCallbackTest extends TestCase
{
    use RefreshDatabase;

    protected string $frontend = 'https://frontend.test/connect';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.google.client_id' => 'test-client-id',
            'services.google.client_secret' => 'test-client-secret',
            'services.google.connections_redirect' => 'https://example.test/api/connections/google/callback',
            'connections.frontend_redirect' => $this->frontend,
        ]);
    }

    private function seedState(
        string $state,
        int $userId,
        ?string $expiresAt = null,
        ?string $consumedAt = null,
    ): OAuthStateModel {
        return OAuthStateModel::create([
            'state' => $state,
            'user_id' => $userId,
            'provider' => 'google',
            'scopes_requested' => ['openid', 'email', 'profile'],
            'expires_at' => $expiresAt ?? now()->addMinutes(10),
            'consumed_at' => $consumedAt,
        ]);
    }

    private function fakeGoogle(array $overrides = []): void
    {
        $token = $overrides['token'] ?? [
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ];
        $tokenInfo = $overrides['tokeninfo'] ?? [
            'sub' => 'google-sub-1',
            'email' => 'user@example.com',
            'scope' => 'openid email profile',
        ];
        $userInfo = $overrides['userinfo'] ?? [
            'sub' => 'google-sub-1',
            'email' => 'user@example.com',
            'name' => 'Test User',
        ];

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response($token, 200),
            'oauth2.googleapis.com/tokeninfo*' => Http::response($tokenInfo, 200),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::response($userInfo, 200),
        ]);
    }

    public function test_callback_with_missing_state_redirects_with_error(): void
    {
        $this->fakeGoogle();
        $response = $this->get('/api/connections/google/callback?code=x');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith($this->frontend, $location);
        $this->assertStringContainsString('error=oauth_state_invalid', $location);
    }

    public function test_callback_with_unknown_state_redirects_with_error(): void
    {
        $this->fakeGoogle();
        $response = $this->get('/api/connections/google/callback?code=x&state=unknown');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('error=oauth_state_invalid', $location);
    }

    public function test_callback_with_expired_state_redirects_with_error(): void
    {
        $user = User::factory()->create();
        $this->seedState('st-expired', $user->id, now()->subMinutes(1)->toDateTimeString());
        $this->fakeGoogle();

        $response = $this->get('/api/connections/google/callback?code=x&state=st-expired');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('error=oauth_state_expired', $location);
    }

    public function test_callback_with_replayed_state_redirects_with_error(): void
    {
        $user = User::factory()->create();
        $this->seedState('st-replayed', $user->id, now()->addMinutes(10)->toDateTimeString(), now()->toDateTimeString());
        $this->fakeGoogle();

        $response = $this->get('/api/connections/google/callback?code=x&state=st-replayed');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('error=oauth_state_replayed', $location);
    }

    public function test_callback_with_invalid_code_redirects_with_error(): void
    {
        $user = User::factory()->create();
        $this->seedState('st-code', $user->id);

        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant'], 400),
        ]);

        $response = $this->get('/api/connections/google/callback?code=bad&state=st-code');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('error=oauth_code_exchange_failed', $location);
    }

    public function test_callback_with_missing_scopes_redirects_with_error(): void
    {
        $user = User::factory()->create();
        $this->seedState('st-scope', $user->id);

        $this->fakeGoogle([
            'tokeninfo' => [
                'sub' => 'google-sub-1',
                'email' => 'user@example.com',
                'scope' => 'openid email', // missing "profile"
            ],
        ]);

        $response = $this->get('/api/connections/google/callback?code=x&state=st-scope');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('error=oauth_scope_invalid', $location);
    }

    public function test_callback_with_missing_external_account_id_redirects_with_error(): void
    {
        $user = User::factory()->create();
        $this->seedState('st-nosub', $user->id);

        $this->fakeGoogle([
            'tokeninfo' => ['email' => 'user@example.com', 'scope' => 'openid email profile'],
        ]);

        $response = $this->get('/api/connections/google/callback?code=x&state=st-nosub');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringContainsString('error=oauth_account_fetch_failed', $location);
    }

    public function test_callback_success_creates_connection_and_redirects_with_status(): void
    {
        $user = User::factory()->create();
        $this->seedState('st-success', $user->id);
        $this->fakeGoogle();

        $response = $this->get('/api/connections/google/callback?code=x&state=st-success');

        $response->assertStatus(302);
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith($this->frontend, $location);
        $this->assertStringContainsString('status=connected', $location);

        $connection = ConnectionModel::query()->where('user_id', $user->id)->first();
        $this->assertNotNull($connection);
        $this->assertSame('google', $connection->provider);
        $this->assertSame('google-sub-1', $connection->external_account_id);
        $this->assertSame('user@example.com', $connection->email);
        $this->assertSame('Test User', $connection->display_name);
        $this->assertSame('active', $connection->status);

        // State must be consumed
        $this->assertNotNull(OAuthStateModel::query()->where('state', 'st-success')->value('consumed_at'));
    }

    public function test_callback_success_stores_encrypted_credentials(): void
    {
        $user = User::factory()->create();
        $this->seedState('st-enc', $user->id);
        $this->fakeGoogle();

        $this->get('/api/connections/google/callback?code=x&state=st-enc')->assertStatus(302);

        $raw = DB::table('connections')->where('user_id', $user->id)->first();
        $this->assertNotNull($raw);
        $this->assertNotSame('access-1', $raw->access_token);
        $this->assertNotSame('refresh-1', $raw->refresh_token);

        $model = ConnectionModel::query()->where('user_id', $user->id)->first();
        $this->assertSame('access-1', $model->access_token);
        $this->assertSame('refresh-1', $model->refresh_token);
    }

    public function test_callback_upsert_updates_existing_connection(): void
    {
        $user = User::factory()->create();
        $existing = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'google-sub-1',
            'email' => 'old@example.com',
            'access_token' => 'old-access',
            'refresh_token' => 'old-refresh',
            'scopes' => ['openid'],
            'status' => 'active',
        ]);
        $this->seedState('st-upsert', $user->id);

        $this->fakeGoogle([
            'token' => ['access_token' => 'new-access', 'expires_in' => 3600],
        ]);

        $this->get('/api/connections/google/callback?code=x&state=st-upsert')->assertStatus(302);

        $this->assertSame(1, ConnectionModel::query()->where('user_id', $user->id)->count());
        $refreshed = $existing->fresh();
        $this->assertSame('new-access', $refreshed->access_token);
        // Refresh token preserved from existing since new response omitted it
        $this->assertSame('old-refresh', $refreshed->refresh_token);
        $this->assertSame('google-sub-1', $refreshed->external_account_id);
    }

    // public function test_callback_supports_multiple_google_accounts(): void
    // {
    //     $user = User::factory()->create();

    //     $this->seedState('st-a', $user->id);
    //     $this->fakeGoogle([
    //         'token' => ['access_token' => 'a-token', 'expires_in' => 3600],
    //         'tokeninfo' => ['sub' => 'google-sub-A', 'email' => 'a@example.com', 'scope' => 'openid email profile'],
    //         'userinfo' => ['sub' => 'google-sub-A', 'email' => 'a@example.com', 'name' => 'Account A'],
    //     ]);
    //     $this->get('/api/connections/google/callback?code=x&state=st-a')->assertStatus(302);

    //     $this->seedState('st-b', $user->id);
    //     $this->fakeGoogle([
    //         'token' => ['access_token' => 'b-token', 'expires_in' => 3600],
    //         'tokeninfo' => ['sub' => 'google-sub-B', 'email' => 'b@example.com', 'scope' => 'openid email profile'],
    //         'userinfo' => ['sub' => 'google-sub-B', 'email' => 'b@example.com', 'name' => 'Account B'],
    //     ]);
    //     $this->get('/api/connections/google/callback?code=x&state=st-b')->assertStatus(302);

    //     $this->assertSame(2, ConnectionModel::query()->where('user_id', $user->id)->count());
    //     $this->assertSame(1, ConnectionModel::query()->where('external_account_id', 'google-sub-A')->count());
    //     $this->assertSame(1, ConnectionModel::query()->where('external_account_id', 'google-sub-B')->count());
    // }

    // public function test_callback_replay_after_success_is_rejected(): void
    // {
    //     $user = User::factory()->create();
    //     $this->seedState('st-once', $user->id);
    //     $this->fakeGoogle();

    //     $this->get('/api/connections/google/callback?code=x&state=st-once')->assertStatus(302);
    //     $second = $this->get('/api/connections/google/callback?code=x&state=st-once');

    //     $second->assertStatus(302);
    //     $location = (string) $second->headers->get('Location');
    //     $this->assertStringContainsString('error=oauth_state_replayed', $location);
    // }
    public function test_callback_supports_multiple_google_accounts(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'oauth2.googleapis.com/token' => Http::sequence()
                ->push(['access_token' => 'a-token', 'expires_in' => 3600], 200)
                ->push(['access_token' => 'b-token', 'expires_in' => 3600], 200),
            'oauth2.googleapis.com/tokeninfo*' => Http::sequence()
                ->push(['sub' => 'google-sub-A', 'email' => 'a@example.com', 'scope' => 'openid email profile'], 200)
                ->push(['sub' => 'google-sub-B', 'email' => 'b@example.com', 'scope' => 'openid email profile'], 200),
            'www.googleapis.com/oauth2/v3/userinfo*' => Http::sequence()
                ->push(['sub' => 'google-sub-A', 'email' => 'a@example.com', 'name' => 'Account A'], 200)
                ->push(['sub' => 'google-sub-B', 'email' => 'b@example.com', 'name' => 'Account B'], 200),
        ]);

        $this->seedState('st-a', $user->id);
        $this->get('/api/connections/google/callback?code=x&state=st-a')->assertStatus(302);

        $this->seedState('st-b', $user->id);
        $this->get('/api/connections/google/callback?code=x&state=st-b')->assertStatus(302);

        $this->assertSame(2, ConnectionModel::query()->where('user_id', $user->id)->count());
        $this->assertSame(1, ConnectionModel::query()->where('external_account_id', 'google-sub-A')->count());
        $this->assertSame(1, ConnectionModel::query()->where('external_account_id', 'google-sub-B')->count());
    }

    public function test_callback_replay_after_success_is_rejected(): void
    {
        $user = User::factory()->create();
        $this->seedState('st-once', $user->id);
        $this->fakeGoogle();

        $this->get('/api/connections/google/callback?code=x&state=st-once')->assertStatus(302);
        $second = $this->get('/api/connections/google/callback?code=x&state=st-once');

        $second->assertStatus(302);
        $location = (string) $second->headers->get('Location');
        $this->assertStringContainsString('error=oauth_state_replayed', $location);
    }
}
