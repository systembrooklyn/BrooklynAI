<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Analytics\GoogleAnalyticsClient;
use Google\Client as GoogleClient;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DimensionHeader;
use Google\Service\AnalyticsData\DimensionValue;
use Google\Service\AnalyticsData\MetricHeader;
use Google\Service\AnalyticsData\MetricValue;
use Google\Service\AnalyticsData\Row;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\AnalyticsData\RunReportResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetReportActionTest extends TestCase
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

            protected function dispatchRunReport(AnalyticsData $data, string $propertyResource, RunReportRequest $request): mixed
            {
                $dimValue = new DimensionValue;
                $dimValue->setValue('20260101');

                $metValue = new MetricValue;
                $metValue->setValue('100');

                $row = new Row;
                $row->setDimensionValues([$dimValue]);
                $row->setMetricValues([$metValue]);

                $dimHeader = new DimensionHeader;
                $dimHeader->setName('date');

                $metHeader = new MetricHeader;
                $metHeader->setName('sessions');

                $response = new RunReportResponse;
                $response->setDimensionHeaders([$dimHeader]);
                $response->setMetricHeaders([$metHeader]);
                $response->setRows([$row]);

                return $response;
            }
        };

        $this->app->instance(GoogleAnalyticsClient::class, $this->captured);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/api/google/analytics/properties/123')->assertStatus(401);
    }

    public function test_validates_date_fields(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google/analytics/properties/123', [
            'startDate' => '2026-01-10',
            'endDate' => '2026-01-01',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['endDate']);
    }

    public function test_legacy_report_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/google/analytics/properties/123');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Report retrieved successfully.');
        $response->assertJsonPath('data.dimensionHeaders.0', 'date');
        $response->assertJsonPath('data.metricHeaders.0', 'sessions');
        $response->assertJsonPath('data.rows.0.dimensions.0', '20260101');
        $response->assertJsonPath('data.rows.0.metrics.0', '100');

        $this->assertSame('legacy-access', $this->captured->usedCredentials[0]->accessToken);
    }

    public function test_legacy_report_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->postJson('/api/google/analytics/properties/123')->assertStatus(500);
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

        $response = $this->postJson('/api/google/analytics/properties/123?connection_id='.$connection->id);

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

        $this->postJson('/api/google/analytics/properties/123?connection_id='.$connection->id)->assertStatus(404);
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

        $response = $this->postJson('/api/google/analytics/properties/123?connection_id='.$connection->id);

        $response->assertStatus(500);
        $this->assertCount(0, $this->captured->usedCredentials);
    }
}
