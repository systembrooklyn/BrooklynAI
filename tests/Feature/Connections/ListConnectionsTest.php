<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListConnectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_requires_authentication(): void
    {
        $this->getJson('/api/connections')->assertStatus(401);
    }

    public function test_list_returns_only_owned_connections_with_safe_fields(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-own',
            'email' => 'own@example.com',
            'display_name' => 'Own Account',
            'access_token' => 'top-secret-access',
            'refresh_token' => 'top-secret-refresh',
            'scopes' => ['openid', 'email', 'profile'],
            'status' => 'active',
        ]);

        ConnectionModel::create([
            'user_id' => $other->id,
            'provider' => 'google',
            'external_account_id' => 'sub-other',
            'email' => 'other@example.com',
            'access_token' => 'other-secret',
            'refresh_token' => 'other-secret',
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/connections');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Connections retrieved successfully');
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.provider', 'google');
        $response->assertJsonPath('data.0.external_account_id', 'sub-own');
        $response->assertJsonPath('data.0.email', 'own@example.com');
        $response->assertJsonPath('data.0.status', 'active');

        $body = $response->getContent();
        $this->assertStringNotContainsString('top-secret-access', $body);
        $this->assertStringNotContainsString('top-secret-refresh', $body);
        $this->assertStringNotContainsString('other-secret', $body);
        $this->assertStringNotContainsString('access_token', $body);
        $this->assertStringNotContainsString('refresh_token', $body);
        $this->assertStringNotContainsString('client_secret', $body);
    }
}
