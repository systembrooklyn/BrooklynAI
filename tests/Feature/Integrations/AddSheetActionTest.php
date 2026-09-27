<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsSheetManager;
use Google\Service\Sheets as GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AddSheetActionTest extends TestCase
{
    use RefreshDatabase;

    private object $capturedManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturedManager = new class extends GoogleSheetsSheetManager
        {
            public array $usedCredentials = [];

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleSheetsService
            {
                $this->usedCredentials[] = $credentials;

                return parent::createService($credentials);
            }

            protected function addSheet(GoogleSheetsService $service, string $spreadsheetId, string $title): array
            {
                return ['message' => "Sheet '{$title}' added"];
            }
        };

        $this->app->instance(GoogleSheetsSheetManager::class, $this->capturedManager);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/google-sheets/sheet-1', ['title' => 'New'])->assertStatus(401);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google-sheets/sheet-1', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['title']);
    }

    public function test_legacy_add_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google-sheets/sheet-1', ['title' => 'NewTab']);

        $response->assertStatus(200);
        $response->assertJsonPath('message', "Sheet 'NewTab' added");
        $this->assertSame('legacy-access', $this->capturedManager->usedCredentials[0]->accessToken);
    }

    public function test_legacy_add_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google-sheets/sheet-1', ['title' => 'NewTab']);

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Failed to add sheet');
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

        $response = $this->postJson('/api/google-sheets/sheet-1?connection_id='.$connection->id, ['title' => 'NewTab']);

        $response->assertStatus(200);
        $this->assertSame('conn-access', $this->capturedManager->usedCredentials[0]->accessToken);
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

        $response = $this->postJson('/api/google-sheets/sheet-1?connection_id='.$connection->id, ['title' => 'NewTab']);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Connection not found');
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

        $response = $this->postJson('/api/google-sheets/sheet-1?connection_id='.$connection->id, ['title' => 'NewTab']);

        $response->assertStatus(500);
        $this->assertCount(0, $this->capturedManager->usedCredentials);
    }
}
