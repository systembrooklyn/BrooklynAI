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
        $this->assertIsString($url);
        $this->assertStringContainsString('accounts.google.com', $url);
        $this->assertStringContainsString('state=', $url);

        $this->assertSame(1, OAuthStateModel::query()->count());
        $this->assertSame((int) $user->id, (int) OAuthStateModel::query()->first()->user_id);
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

        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        $scopes = explode(' ', $query['scope'] ?? '');
        sort($scopes);

        // PHP `sort()` uses byte comparison; URL strings start with 'h'
        // which sorts after 'email' (e) but before 'openid' (o).
        $this->assertSame([
            'email',
            'https://www.googleapis.com/auth/gmail.compose',
            'https://www.googleapis.com/auth/gmail.labels',
            'https://www.googleapis.com/auth/gmail.modify',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.send',
            'openid',
            'profile',
        ], $scopes);
    }

    public function test_start_with_unknown_capability_returns_422(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connections/google/start', [
            'capability' => 'unknown_capability',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['capability']);
    }

    public function test_start_ignores_client_provided_raw_scopes(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/connections/google/start', [
            'scopes' => ['https://www.googleapis.com/auth/gmail.modify'],
        ]);

        $response->assertStatus(200);

        $url = $response->json('redirect_url');

        $this->assertStringNotContainsString('gmail.modify', urldecode($url));
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
        parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

        $scopes = explode(' ', $query['scope'] ?? '');
        sort($scopes);

        $this->assertSame([
            'email',
            'openid',
            'profile',
        ], $scopes);
    }
}
