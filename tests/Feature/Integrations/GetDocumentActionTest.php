<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;
use Google\Client as GoogleClient;
use Google\Service\Docs as GoogleDocsService;
use Google\Service\Docs\Body;
use Google\Service\Docs\Document as GoogleDocsDocument;
use Google\Service\Docs\Paragraph;
use Google\Service\Docs\ParagraphElement;
use Google\Service\Docs\StructuralElement;
use Google\Service\Docs\TextRun;
use Google\Service\Drive as GoogleDriveService;
use Google\Service\Drive\DriveFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetDocumentActionTest extends TestCase
{
    use RefreshDatabase;

    private object $captured;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured = new class extends GoogleDocsClient
        {
            public array $usedCredentials = [];

            protected function createClient(ResolvedGoogleCredentials $credentials): GoogleClient
            {
                $this->usedCredentials[] = $credentials;

                return parent::createClient($credentials);
            }

            protected function dispatchDocsGet(GoogleDocsService $service, string $documentId): GoogleDocsDocument
            {
                $textRun = new TextRun;
                $textRun->setContent('Hello');
                $elem = new ParagraphElement;
                $elem->setTextRun($textRun);
                $para = new Paragraph;
                $para->setElements([$elem]);
                $struct = new StructuralElement;
                $struct->setParagraph($para);
                $body = new Body;
                $body->setContent([$struct]);

                $doc = new GoogleDocsDocument;
                $doc->setDocumentId($documentId);
                $doc->setTitle('Test');
                $doc->setBody($body);

                return $doc;
            }

            protected function dispatchDriveGetFile(GoogleDriveService $service, string $fileId, array $optParams): mixed
            {
                $file = new DriveFile;
                $file->setId($fileId);
                $file->setName('Test');
                $file->setOwners([]);
                $file->setModifiedTime('2026-01-01T00:00:00Z');
                $file->setWebViewLink('https://docs.google.com/document/d/'.$fileId.'/edit');

                return $file;
            }
        };

        $this->app->instance(GoogleDocsClient::class, $this->captured);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/google/docs/doc-1')->assertStatus(401);
    }

    public function test_legacy_get_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google/docs/doc-1');

        $response->assertStatus(200);
        $response->assertJsonPath('id', 'doc-1');
        $response->assertJsonPath('body', 'Hello');
        $this->assertSame('legacy-access', $this->captured->usedCredentials[0]->accessToken);
    }

    public function test_legacy_get_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/google/docs/doc-1')->assertStatus(500);
    }

    public function test_uses_explicit_connection_when_provided(): void
    {
        $user = User::factory()->create([
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

        $response = $this->getJson('/api/google/docs/doc-1?connection_id='.$connection->id);

        $response->assertStatus(200);
        $this->assertSame('conn-access', $this->captured->usedCredentials[0]->accessToken);
    }

    public function test_returns_404_when_connection_not_owned(): void
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

        $this->getJson('/api/google/docs/doc-1?connection_id='.$connection->id)->assertStatus(404);
    }

    public function test_with_connection_id_never_falls_back_to_legacy(): void
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

        $response = $this->getJson('/api/google/docs/doc-1?connection_id='.$connection->id);

        $response->assertStatus(500);
        $this->assertCount(0, $this->captured->usedCredentials);
    }
}
