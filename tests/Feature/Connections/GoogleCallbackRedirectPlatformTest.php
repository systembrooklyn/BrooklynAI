<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\OAuthStateModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GoogleCallbackRedirectPlatformTest extends TestCase
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

    public function test_missing_state_redirects_to_web_with_error(): void
    {
        $response = $this->get('/api/connections/google/callback');

        $response->assertStatus(302);
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://web.example.test', $location);
        $this->assertStringContainsString('error=oauth_state_invalid', $location);
    }

    public function test_web_state_with_missing_code_redirects_to_web_with_error(): void
    {
        $user = User::factory()->create();
        $state = $this->seedState($user, 'web');

        $response = $this->get('/api/connections/google/callback?state='.$state->state);

        $response->assertStatus(302);
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('https://web.example.test', $location);
        $this->assertStringContainsString('error=oauth_code_exchange_failed', $location);
        $this->assertStringNotContainsString('status=', $location);
    }

    public function test_mobile_state_with_missing_code_redirects_to_deeplink_with_failed_status(): void
    {
        $user = User::factory()->create();
        $state = $this->seedState($user, 'mobile');

        $response = $this->get('/api/connections/google/callback?state='.$state->state);

        $response->assertStatus(302);
        $location = $response->headers->get('Location');

        $this->assertStringStartsWith('brooklynai://connections/google', $location);
        $this->assertStringContainsString('status=failed', $location);
        $this->assertStringContainsString('error=oauth_code_exchange_failed', $location);
    }

    private function seedState(User $user, string $platform): OAuthStateModel
    {
        return OAuthStateModel::create([
            'state' => bin2hex(random_bytes(16)),
            'user_id' => $user->id,
            'provider' => 'google',
            'platform' => $platform,
            'scopes_requested' => ['openid', 'email', 'profile'],
            'expires_at' => now()->addMinutes(10),
            'consumed_at' => null,
        ]);
    }
}
