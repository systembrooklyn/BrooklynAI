<?php

namespace Tests\Feature\Connections;

use App\Models\User;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DisconnectConnectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_disconnect_requires_authentication(): void
    {
        $this->deleteJson('/api/connections/1')->assertStatus(401);
    }

    public function test_disconnect_deletes_owned_connection(): void
    {
        $user = User::factory()->create();
        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-1',
            'scopes' => ['openid', 'email', 'profile'],
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/connections/'.$connection->id);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Connection disconnected successfully');

        $this->assertDatabaseMissing('connections', ['id' => $connection->id]);
    }

    public function test_disconnect_cannot_delete_other_users_connection(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create();

        $connection = ConnectionModel::create([
            'user_id' => $owner->id,
            'provider' => 'google',
            'external_account_id' => 'sub-owner',
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        Sanctum::actingAs($attacker);

        $response = $this->deleteJson('/api/connections/'.$connection->id);

        $response->assertStatus(404);
        $this->assertDatabaseHas('connections', ['id' => $connection->id]);
    }

    public function test_disconnect_returns_404_for_nonexistent_connection(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->deleteJson('/api/connections/99999');

        $response->assertStatus(404);
    }
}
