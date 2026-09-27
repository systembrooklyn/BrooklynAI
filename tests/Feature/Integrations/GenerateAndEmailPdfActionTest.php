<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;
use App\Modules\Integrations\Infrastructure\Google\Gmail\GmailEmailSender;
use Google\Client as GoogleClient;
use Google\Service\Docs as GoogleDocsService;
use Google\Service\Docs\BatchUpdateDocumentRequest;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile;
use Google\Service\Gmail as GoogleGmailService;
use Google\Service\Gmail\Message as GoogleGmailMessage;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GenerateAndEmailPdfActionTest extends TestCase
{
    use RefreshDatabase;

    private object $capturedDocs;

    private object $capturedGmail;

    protected function setUp(): void
    {
        parent::setUp();

        putenv('GOOGLE_DOCS_TEMPLATE_ID=template-1');
        $_ENV['GOOGLE_DOCS_TEMPLATE_ID'] = 'template-1';

        $this->capturedDocs = new class extends GoogleDocsClient
        {
            public array $usedCredentials = [];

            protected function createClient(ResolvedGoogleCredentials $credentials): GoogleClient
            {
                $this->usedCredentials[] = $credentials;

                return parent::createClient($credentials);
            }

            protected function dispatchDriveCopy(GoogleDriveService $service, string $fileId, DriveFile $metadata): mixed
            {
                $file = new DriveFile;
                $file->setId('temp-doc-1');
                $file->setName($metadata->getName());

                return $file;
            }

            protected function dispatchDocsBatchUpdate(GoogleDocsService $service, string $documentId, BatchUpdateDocumentRequest $request): void
            {
                // no-op
            }

            protected function dispatchDriveExport(GoogleDriveService $service, string $fileId, string $mimeType, array $optParams): mixed
            {
                return new Response(200, ['Content-Type' => 'application/pdf'], '%PDF-1.4 fake');
            }
        };

        $this->capturedGmail = new class extends GmailEmailSender
        {
            public array $sentRawMessages = [];

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleGmailService
            {
                return parent::createService($credentials);
            }

            protected function dispatch(GoogleGmailService $service, GoogleGmailMessage $message): ?GoogleGmailMessage
            {
                $this->sentRawMessages[] = (string) $message->getRaw();

                return null;
            }
        };

        $this->app->instance(GoogleDocsClient::class, $this->capturedDocs);
        $this->app->instance(GmailEmailSender::class, $this->capturedGmail);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/google/docs/generate-and-email', [
            'to' => ['x@example.com'], 'subject' => 'S', 'data' => ['name' => 'A'],
        ])->assertStatus(401);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google/docs/generate-and-email', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['to', 'subject', 'data']);
    }

    public function test_legacy_generate_and_email_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google/docs/generate-and-email', [
            'to' => ['bob@example.com'],
            'subject' => 'Here you go',
            'data' => ['name' => 'Bob'],
            'filename' => 'letter.pdf',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Document generated and emailed successfully!');
        $response->assertJsonPath('sent_to', ['bob@example.com']);
        $response->assertJsonPath('doc_id', 'temp-doc-1');
        $this->assertCount(1, $this->capturedGmail->sentRawMessages);
    }

    public function test_legacy_generate_and_email_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google/docs/generate-and-email', [
            'to' => ['bob@example.com'],
            'subject' => 'X',
            'data' => ['name' => 'A'],
        ]);

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Failed to generate and send document');
    }

    public function test_uses_explicit_connection_when_provided(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
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

        $response = $this->postJson('/api/google/docs/generate-and-email?connection_id='.$connection->id, [
            'to' => ['bob@example.com'],
            'subject' => 'X',
            'data' => ['name' => 'A'],
        ]);

        $response->assertStatus(200);
        $this->assertSame('conn-access', $this->capturedDocs->usedCredentials[0]->accessToken);
    }

    public function test_returns_404_when_connection_not_owned(): void
    {
        $owner = User::factory()->create();
        $attacker = User::factory()->create([
            'email' => 'atk@example.com',
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

        $this->postJson('/api/google/docs/generate-and-email?connection_id='.$connection->id, [
            'to' => ['x@example.com'], 'subject' => 'S', 'data' => ['name' => 'A'],
        ])->assertStatus(404);
    }

    public function test_with_connection_id_never_falls_back_to_legacy(): void
    {
        $user = User::factory()->create([
            'email' => 'alice@example.com',
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

        $response = $this->postJson('/api/google/docs/generate-and-email?connection_id='.$connection->id, [
            'to' => ['x@example.com'], 'subject' => 'S', 'data' => ['name' => 'A'],
        ]);

        $response->assertStatus(500);
        $this->assertCount(0, $this->capturedDocs->usedCredentials);
    }
}
