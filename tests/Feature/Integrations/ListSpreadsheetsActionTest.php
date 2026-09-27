<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsLister;
use Google\Service\Drive as GoogleDriveService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListSpreadsheetsActionTest extends TestCase
{
    use RefreshDatabase;

    private object $capturedLister;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturedLister = new class extends GoogleSheetsLister
        {
            /** @var array<int, ResolvedGoogleCredentials> */
            public array $usedCredentials = [];

            /** @var array<int, array<string, mixed>> */
            public array $returnedSpreadsheets = [];

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleDriveService
            {
                $this->usedCredentials[] = $credentials;

                return parent::createService($credentials);
            }

            protected function fetchSpreadsheets(GoogleDriveService $service): array
            {
                return $this->returnedSpreadsheets;
            }
        };

        $this->capturedLister->returnedSpreadsheets = [
            [
                'id' => 'sheet-1',
                'name' => 'Budget',
                'lastModified' => '2026-01-01T00:00:00Z',
                'ownerEmail' => 'a@example.com',
                'url' => 'https://docs.google.com/spreadsheets/d/sheet-1',
            ],
        ];

        $this->app->instance(GoogleSheetsLister::class, $this->capturedLister);
    }

    public function test_list_still_requires_authentication(): void
    {
        $response = $this->getJson('/api/google-sheets');

        $response->assertStatus(401);
        $this->assertCount(0, $this->capturedLister->usedCredentials);
    }

    public function test_legacy_list_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google-sheets');

        $response->assertStatus(200);
        $response->assertJsonStructure(['spreadsheets', 'count']);
        $response->assertJsonPath('count', 1);
        $response->assertJsonPath('spreadsheets.0.id', 'sheet-1');
        $response->assertJsonPath('spreadsheets.0.name', 'Budget');
        $response->assertJsonPath('spreadsheets.0.ownerEmail', 'a@example.com');

        $this->assertCount(1, $this->capturedLister->usedCredentials);
        $this->assertSame('legacy-access', $this->capturedLister->usedCredentials[0]->accessToken);
    }

    public function test_legacy_list_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google-sheets');

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Failed to list spreadsheets');
        $this->assertCount(0, $this->capturedLister->usedCredentials);
    }

    public function test_list_uses_explicit_connection_when_provided(): void
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

        $response = $this->getJson('/api/google-sheets?connection_id='.$connection->id);

        $response->assertStatus(200);
        $response->assertJsonPath('count', 1);

        $this->assertCount(1, $this->capturedLister->usedCredentials);
        $this->assertSame('conn-access', $this->capturedLister->usedCredentials[0]->accessToken);
        $this->assertNotSame('legacy-access', $this->capturedLister->usedCredentials[0]->accessToken);
    }

    public function test_list_returns_404_when_connection_not_owned(): void
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

        $response = $this->getJson('/api/google-sheets?connection_id='.$connection->id);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Connection not found');
        $this->assertCount(0, $this->capturedLister->usedCredentials);
    }

    public function test_list_returns_500_when_connection_has_no_usable_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
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

        $response = $this->getJson('/api/google-sheets?connection_id='.$connection->id);

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Failed to list spreadsheets');
        $this->assertCount(0, $this->capturedLister->usedCredentials);
    }

    public function test_list_with_connection_id_never_falls_back_to_legacy_credentials(): void
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

        $response = $this->getJson('/api/google-sheets?connection_id='.$connection->id);

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Failed to list spreadsheets');
        $this->assertCount(0, $this->capturedLister->usedCredentials);
    }
}
