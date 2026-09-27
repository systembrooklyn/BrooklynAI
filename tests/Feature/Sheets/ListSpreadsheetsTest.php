<?php

namespace Tests\Feature\Sheets;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListSpreadsheetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_requires_authentication(): void
    {
        $response = $this->getJson('/api/google-sheets');
        $response->assertStatus(401);
    }

    public function test_list_returns_500_when_user_has_no_google_tokens(): void
    {
        $user = User::factory()->create([
            'has_bot_access' => true,
            'access_expiry' => now()->addMonth(),
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => now()->subDay(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google-sheets');

        $response->assertStatus(500);
        $response->assertJson(['message' => 'Failed to list spreadsheets']);
    }
}
