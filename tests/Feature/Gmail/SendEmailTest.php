<?php

namespace Tests\Feature\Gmail;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_email_requires_authentication(): void
    {
        $response = $this->postJson('/api/email/send', [
            'to' => 'a@example.com',
            'subject' => 'Hi',
            'body' => 'Hello',
        ]);

        $response->assertStatus(401);
    }

    public function test_send_email_validates_required_fields(): void
    {
        $user = User::factory()->create([
            'has_bot_access' => true,
            'access_expiry' => now()->addMonth(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/send', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to', 'subject', 'body']);
    }

    public function test_send_email_returns_500_when_user_has_no_google_token(): void
    {
        $user = User::factory()->create([
            'has_bot_access' => true,
            'access_expiry' => now()->addMonth(),
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => now()->subDay(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/send', [
            'to' => 'recipient@example.com',
            'subject' => 'Test',
            'body' => 'Body content',
        ]);

        $response->assertStatus(500);
        $response->assertJson(['error' => 'Email send failed']);
    }
}
