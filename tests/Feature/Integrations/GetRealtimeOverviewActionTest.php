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
use Google\Service\AnalyticsData\RunRealtimeReportRequest;
use Google\Service\AnalyticsData\RunRealtimeReportResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetRealtimeOverviewActionTest extends TestCase
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

            protected function dispatchRunRealtimeReport(AnalyticsData $data, string $propertyResource, RunRealtimeReportRequest $request): mixed
            {
                $country = new DimensionValue;
                $country->setValue('Egypt');
                $device = new DimensionValue;
                $device->setValue('mobile');

                $activeUsers = new MetricValue;
                $activeUsers->setValue('5');
                $events = new MetricValue;
                $events->setValue('10');
                $views = new MetricValue;
                $views->setValue('20');

                $row = new Row;
                $row->setDimensionValues([$country, $device]);
                $row->setMetricValues([$activeUsers, $events, $views]);

                $dh1 = new DimensionHeader;
                $dh1->setName('country');
                $dh2 = new DimensionHeader;
                $dh2->setName('deviceCategory');

                $mh1 = new MetricHeader;
                $mh1->setName('activeUsers');
                $mh2 = new MetricHeader;
                $mh2->setName('eventCount');
                $mh3 = new MetricHeader;
                $mh3->setName('screenPageViews');

                $response = new RunRealtimeReportResponse;
                $response->setDimensionHeaders([$dh1, $dh2]);
                $response->setMetricHeaders([$mh1, $mh2, $mh3]);
                $response->setRows([$row]);

                return $response;
            }
        };

        $this->app->instance(GoogleAnalyticsClient::class, $this->captured);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/google/analytics/properties/123/realtime')->assertStatus(401);
    }

    public function test_legacy_realtime_succeeds_without_connection_id(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google/analytics/properties/123/realtime');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Realtime overview retrieved successfully.');
        $response->assertJsonPath('data.dimensionHeaders.0', 'country');
        $response->assertJsonPath('data.rows.0.dimensions.0', 'Egypt');
        $response->assertJsonPath('data.rows.0.metrics.0', '5');

        $this->assertSame('legacy-access', $this->captured->usedCredentials[0]->accessToken);
    }

    public function test_legacy_realtime_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/google/analytics/properties/123/realtime')->assertStatus(500);
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

        $response = $this->getJson('/api/google/analytics/properties/123/realtime?connection_id='.$connection->id);

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

        $this->getJson('/api/google/analytics/properties/123/realtime?connection_id='.$connection->id)->assertStatus(404);
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

        $response = $this->getJson('/api/google/analytics/properties/123/realtime?connection_id='.$connection->id);

        $response->assertStatus(500);
        $this->assertCount(0, $this->captured->usedCredentials);
    }
}
