<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Analytics\GoogleAnalyticsClient;
use Google\Client as GoogleClient;
use Google\Service\GoogleAnalyticsAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ListPropertiesActionTest extends TestCase
{
    use RefreshDatabase;

    private object $captured;

    protected function setUp(): void
    {
        parent::setUp();

        $this->captured = new class extends GoogleAnalyticsClient
        {
            public array $usedCredentials = [];

            protected function createClient(ResolvedGoogleCredentials $credentials): GoogleClient
            {
                $this->usedCredentials[] = $credentials;

                return parent::createClient($credentials);
            }

            protected function dispatchListAccountSummaries(GoogleAnalyticsAdmin $admin): mixed
            {
                $propertySummary = new class
                {
                    public function getProperty(): string
                    {
                        return 'properties/123';
                    }

                    public function getDisplayName(): string
                    {
                        return 'My Property';
                    }
                };

                $accountSummary = new class($propertySummary)
                {
                    public function __construct(private readonly object $propertySummary) {}

                    public function getName(): string
                    {
                        return 'accounts/456';
                    }

                    public function getDisplayName(): string
                    {
                        return 'My Account';
                    }

                    public function getPropertySummaries(): array
                    {
                        return [$this->propertySummary];
                    }
                };

                return new class($accountSummary)
                {
                    public function __construct(private readonly object $accountSummary) {}

                    public function getAccountSummaries(): array
                    {
                        return [$this->accountSummary];
                    }
                };
            }

            protected function dispatchGetProperty(GoogleAnalyticsAdmin $admin, string $resourceName): mixed
            {
                return new class
                {
                    public function getTimeZone(): string
                    {
                        return 'Africa/Cairo';
                    }

                    public function getCurrencyCode(): string
                    {
                        return 'EGP';
                    }
                };
            }
        };

        $this->app->instance(GoogleAnalyticsClient::class, $this->captured);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/google/analytics/properties')->assertStatus(401);
    }

    public function test_legacy_list_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google/analytics/properties');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Properties retrieved successfully.');
        $response->assertJsonPath('data.0.property_id', '123');
        $response->assertJsonPath('data.0.name', 'My Property');
        $response->assertJsonPath('data.0.time_zone', 'Africa/Cairo');
        $response->assertJsonPath('data.0.currency_code', 'EGP');
        $response->assertJsonPath('data.0.account_id', '456');
        $response->assertJsonPath('data.0.account_name', 'My Account');

        $this->assertSame('legacy-access', $this->captured->usedCredentials[0]->accessToken);
    }

    public function test_legacy_list_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/google/analytics/properties')->assertStatus(500);
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

        $response = $this->getJson('/api/google/analytics/properties?connection_id='.$connection->id);

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

        $this->getJson('/api/google/analytics/properties?connection_id='.$connection->id)->assertStatus(404);
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

        $response = $this->getJson('/api/google/analytics/properties?connection_id='.$connection->id);

        $response->assertStatus(500);
        $this->assertCount(0, $this->captured->usedCredentials);
    }
}
