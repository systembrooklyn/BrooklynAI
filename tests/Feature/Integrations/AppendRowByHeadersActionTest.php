<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Sheets\GoogleSheetsRowAppender;
use Google\Service\Sheets as GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AppendRowByHeadersActionTest extends TestCase
{
    use RefreshDatabase;

    private object $capturedAppender;

    protected function setUp(): void
    {
        parent::setUp();

        $this->capturedAppender = new class extends GoogleSheetsRowAppender
        {
            public array $usedCredentials = [];

            public array $returnedResult = [];

            protected function createService(ResolvedGoogleCredentials $credentials): GoogleSheetsService
            {
                $this->usedCredentials[] = $credentials;

                return parent::createService($credentials);
            }

            protected function writeRow(
                GoogleSheetsService $service,
                string $spreadsheetId,
                string $sheetName,
                array $data,
            ): array {
                return $this->returnedResult;
            }
        };

        $this->capturedAppender->returnedResult = [
            'message' => 'Row appended successfully',
            'row' => 5,
            'range' => 'Sheet1!A5:C5',
            'values' => ['x', 'y', 'z'],
        ];

        $this->app->instance(GoogleSheetsRowAppender::class, $this->capturedAppender);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/google-sheets/sheet-1/append-under-header', [
            'sheet_name' => 'Sheet1',
            'data' => ['a' => 'x'],
        ])->assertStatus(401);
    }

    public function test_validates_required_fields(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google-sheets/sheet-1/append-under-header', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['sheet_name', 'data']);
    }

    public function test_legacy_append_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google-sheets/sheet-1/append-under-header', [
            'sheet_name' => 'Sheet1',
            'data' => ['name' => 'x', 'email' => 'y'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Row appended successfully');
        $response->assertJsonPath('row', 5);
        $this->assertSame('legacy-access', $this->capturedAppender->usedCredentials[0]->accessToken);
    }

    public function test_legacy_append_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google-sheets/sheet-1/append-under-header', [
            'sheet_name' => 'Sheet1',
            'data' => ['name' => 'x'],
        ]);

        $response->assertStatus(500);
        $response->assertJsonPath('message', 'Failed to append row');
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

        $response = $this->postJson('/api/google-sheets/sheet-1/append-under-header?connection_id='.$connection->id, [
            'sheet_name' => 'Sheet1',
            'data' => ['name' => 'x'],
        ]);

        $response->assertStatus(200);
        $this->assertSame('conn-access', $this->capturedAppender->usedCredentials[0]->accessToken);
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

        $response = $this->postJson('/api/google-sheets/sheet-1/append-under-header?connection_id='.$connection->id, [
            'sheet_name' => 'Sheet1',
            'data' => ['name' => 'x'],
        ]);

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

        $response = $this->postJson('/api/google-sheets/sheet-1/append-under-header?connection_id='.$connection->id, [
            'sheet_name' => 'Sheet1',
            'data' => ['name' => 'x'],
        ]);

        $response->assertStatus(500);
        $this->assertCount(0, $this->capturedAppender->usedCredentials);
    }
}
