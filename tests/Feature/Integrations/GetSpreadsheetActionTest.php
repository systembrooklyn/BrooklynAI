<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsGetter;
use Google\Service\Sheets as GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetSpreadsheetActionTest extends TestCase
{
    use RefreshDatabase;

    private object $capturedGetter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturedGetter = new class extends GoogleSheetsGetter
        {
            public array $usedCredentials = [];

            public array $returnedSpreadsheet = [];

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleSheetsService
            {
                $this->usedCredentials[] = $credentials;

                return parent::createService($credentials);
            }

            protected function fetchSpreadsheet(GoogleSheetsService $service, string $spreadsheetId): array
            {
                return $this->returnedSpreadsheet;
            }
        };

        $this->capturedGetter->returnedSpreadsheet = [
            'id' => 'sheet-1',
            'title' => 'Budget',
            'url' => 'https://docs.google.com/spreadsheets/d/sheet-1',
            'sheets' => [
                ['id' => 0, 'title' => 'Sheet1', 'index' => 0],
            ],
        ];

        $this->app->instance(GoogleSheetsGetter::class, $this->capturedGetter);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/google-sheets/sheet-1')->assertStatus(401);
    }

    public function test_legacy_get_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google-sheets/sheet-1');

        $response->assertStatus(200);
        $response->assertJsonPath('id', 'sheet-1');
        $response->assertJsonPath('title', 'Budget');
        $response->assertJsonPath('sheets.0.title', 'Sheet1');

        $this->assertCount(1, $this->capturedGetter->usedCredentials);
        $this->assertSame('legacy-access', $this->capturedGetter->usedCredentials[0]->accessToken);
    }

    public function test_legacy_get_returns_404_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google-sheets/sheet-1');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Failed to get spreadsheet');
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

        $response = $this->getJson('/api/google-sheets/sheet-1?connection_id='.$connection->id);

        $response->assertStatus(200);
        $this->assertCount(1, $this->capturedGetter->usedCredentials);
        $this->assertSame('conn-access', $this->capturedGetter->usedCredentials[0]->accessToken);
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

        $response = $this->getJson('/api/google-sheets/sheet-1?connection_id='.$connection->id);

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Connection not found');
        $this->assertCount(0, $this->capturedGetter->usedCredentials);
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

        $response = $this->getJson('/api/google-sheets/sheet-1?connection_id='.$connection->id);

        $response->assertStatus(404);
        $this->assertCount(0, $this->capturedGetter->usedCredentials);
    }
}
