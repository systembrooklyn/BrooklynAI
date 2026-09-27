<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SendGmailEmailActionTest extends TestCase
{
    use RefreshDatabase;

    private object $capturedSender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturedSender = new class extends GmailEmailSender
        {
            /** @var array<int, ResolvedGoogleCredentials> */
            public array $usedCredentials = [];

            /** @var array<int, string> */
            public array $sentRawMessages = [];

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
            {
                $this->usedCredentials[] = $credentials;

                return parent::createService($credentials);
            }

            protected function dispatch(
                GoogleGmailService $service,
                GoogleGmailMessage $message,
            ): ?GoogleGmailMessage {
                $this->sentRawMessages[] = (string) $message->getRaw();

                return null;
            }
        };

        $this->app->instance(GmailEmailSender::class, $this->capturedSender);
    }

    public function test_send_still_requires_authentication(): void
    {
        $response = $this->postJson('/api/email/send', [
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
        ]);

        $response->assertStatus(401);
        $this->assertCount(0, $this->capturedSender->sentRawMessages);
    }

    public function test_send_still_validates_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/send', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to', 'subject', 'body']);
    }

    public function test_legacy_send_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'email' => 'sender@example.com',
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/send', [
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Email sent successfully!']);

        $this->assertCount(1, $this->capturedSender->usedCredentials);
        $this->assertSame('legacy-access', $this->capturedSender->usedCredentials[0]->accessToken);

        $this->assertCount(1, $this->capturedSender->sentRawMessages);
        $decoded = $this->decodeRawMessage($this->capturedSender->sentRawMessages[0]);
        $this->assertStringContainsString('From: sender@example.com', $decoded);
        $this->assertStringContainsString('To: recipient@example.com', $decoded);
        $this->assertStringContainsString('Subject: Hello', $decoded);
    }

    public function test_legacy_send_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/send', [
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
        ]);

        $response->assertStatus(500);
        $response->assertJson(['error' => 'Email send failed']);
        $this->assertCount(0, $this->capturedSender->usedCredentials);
        $this->assertCount(0, $this->capturedSender->sentRawMessages);
    }

    public function test_send_uses_explicit_connection_when_provided(): void
    {
        $user = User::factory()->create([
            'email' => 'sender@example.com',
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-a',
            'access_token' => 'conn-access',
            'refresh_token' => 'conn-refresh',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/send', [
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'connection_id' => $connection->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['message' => 'Email sent successfully!']);

        $this->assertCount(1, $this->capturedSender->usedCredentials);
        $this->assertSame('conn-access', $this->capturedSender->usedCredentials[0]->accessToken);
        $this->assertCount(1, $this->capturedSender->sentRawMessages);
    }

    public function test_send_returns_404_when_connection_not_owned(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create([
            'google_access_token' => 'attacker-legacy',
            'google_refresh_token' => 'attacker-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $owner->id,
            'provider' => 'google',
            'external_account_id' => 'sub-owner',
            'access_token' => 'owner-access',
            'refresh_token' => 'owner-refresh',
            'token_expires_at' => now()->addHour(),
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        Sanctum::actingAs($attacker);

        $response = $this->postJson('/api/email/send', [
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'connection_id' => $connection->id,
        ]);

        $response->assertStatus(404);
        $response->assertJson(['error' => 'Connection not found']);
        $this->assertCount(0, $this->capturedSender->usedCredentials);
        $this->assertCount(0, $this->capturedSender->sentRawMessages);
    }

    public function test_send_returns_500_when_connection_has_no_usable_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-empty',
            'access_token' => null,
            'refresh_token' => null,
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/send', [
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'connection_id' => $connection->id,
        ]);

        $response->assertStatus(500);
        $response->assertJson(['error' => 'Email send failed']);
        $this->assertCount(0, $this->capturedSender->usedCredentials);
        $this->assertCount(0, $this->capturedSender->sentRawMessages);
    }

    public function test_send_with_connection_id_never_falls_back_to_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);

        $connection = ConnectionModel::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'external_account_id' => 'sub-empty',
            'access_token' => null,
            'refresh_token' => null,
            'scopes' => ['openid'],
            'status' => 'active',
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/email/send', [
            'to' => 'recipient@example.com',
            'subject' => 'Hello',
            'body' => '<p>Hi</p>',
            'connection_id' => $connection->id,
        ]);

        $response->assertStatus(500);
        $this->assertCount(0, $this->capturedSender->usedCredentials);
        $this->assertCount(0, $this->capturedSender->sentRawMessages);
    }

    private function decodeRawMessage(string $raw): string
    {
        $base64 = str_replace(['-', '_'], ['+', '/'], $raw);
        $pad = strlen($base64) % 4;
        if ($pad > 0) {
            $base64 .= str_repeat('=', 4 - $pad);
        }

        return (string) base64_decode($base64);
    }
}
