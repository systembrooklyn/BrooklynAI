<?php

namespace Tests\Feature\User;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/user');
        $response->assertStatus(401);
    }

    public function test_user_endpoint_returns_expected_fields_when_authenticated(): void
    {
        $user = User::factory()->create([
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'has_bot_access' => true,
            'access_expiry' => now()->addMonth(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/user');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'name',
                'avatar',
                'email',
                'has_bot_access',
                'access_expiry',
            ],
        ]);
        $response->assertJson([
            'message' => 'User Retrieved successfully',
            'data' => [
                'id' => $user->id,
                'name' => 'Jane',
                'email' => 'jane@example.com',
            ],
        ]);
    }
}
