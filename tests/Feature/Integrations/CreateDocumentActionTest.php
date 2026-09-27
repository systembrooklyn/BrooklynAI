<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Docs\GoogleDocsClient;
use Google\Client as GoogleClient;
use Google\Service\Docs as GoogleDocsService;
use Google\Service\Docs\Document as GoogleDocsDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CreateDocumentActionTest extends TestCase
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

            protected function dispatchDocsCreate(GoogleDocsService $service, GoogleDocsDocument $document): GoogleDocsDocument
            {
                $doc = new GoogleDocsDocument;
                $doc->setDocumentId('doc-created');
                $doc->setTitle($document->getTitle() ?? 'Untitled Document');

                return $doc;
            }
        };

        $this->app->instance(GoogleDocsClient::class, $this->captured);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/google/docs', ['title' => 'X'])->assertStatus(401);
    }

    public function test_legacy_create_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google/docs', ['title' => 'My Doc']);

        $response->assertStatus(200);
        $response->assertJsonPath('id', 'doc-created');
        $this->assertSame('legacy-access', $this->captured->usedCredentials[0]->accessToken);
    }

    public function test_legacy_create_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/google/docs', ['title' => 'X'])->assertStatus(500);
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

        $response = $this->postJson('/api/google/docs?connection_id='.$connection->id, ['title' => 'X']);

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

        $this->postJson('/api/google/docs?connection_id='.$connection->id, ['title' => 'X'])->assertStatus(404);
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

        $response = $this->postJson('/api/google/docs?connection_id='.$connection->id, ['title' => 'X']);

        $response->assertStatus(500);
        $this->assertCount(0, $this->captured->usedCredentials);
    }
}
