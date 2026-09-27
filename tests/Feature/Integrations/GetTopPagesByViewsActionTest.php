<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use App\Modules\Connections\Infrastructure\Eloquent\ConnectionModel;
use App\Modules\Integrations\Infrastructure\Google\Analytics\GoogleAnalyticsClient;
use Google\Client as GoogleClient;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DimensionValue;
use Google\Service\AnalyticsData\MetricValue;
use Google\Service\AnalyticsData\Row;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\AnalyticsData\RunReportResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GetTopPagesByViewsActionTest extends TestCase
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
                $pageOne = new DimensionValue;
                $pageOne->setValue('/home');
                $viewsOne = new MetricValue;
                $viewsOne->setValue('120');

                $notSet = new DimensionValue;
                $notSet->setValue('(not set)');
                $viewsNotSet = new MetricValue;
                $viewsNotSet->setValue('50');

                $pageTwo = new DimensionValue;
                $pageTwo->setValue('/about');
                $viewsTwo = new MetricValue;
                $viewsTwo->setValue('80');

                $row1 = new Row;
                $row1->setDimensionValues([$pageOne]);
                $row1->setMetricValues([$viewsOne]);

                $row2 = new Row;
                $row2->setDimensionValues([$notSet]);
                $row2->setMetricValues([$viewsNotSet]);

                $row3 = new Row;
                $row3->setDimensionValues([$pageTwo]);
                $row3->setMetricValues([$viewsTwo]);

                $response = new RunReportResponse;
                $response->setRows([$row1, $row2, $row3]);

                return $response;
            }
        };

        $this->app->instance(GoogleAnalyticsClient::class, $this->captured);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/google/analytics/viewsbypage/123')->assertStatus(401);
    }

    public function test_legacy_top_pages_succeeds_without_connection_id_and_filters_not_set(): void
    {
        $user = User::factory()->create([
            'google_access_token' => 'legacy-access',
            'google_refresh_token' => 'legacy-refresh',
            'google_token_expires_at' => now()->addHour(),
        ]);
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/google/analytics/viewsbypage/123');

        $response->assertStatus(200);
        $response->assertJsonPath('message', 'Views by page title retrieved successfully.');
        $response->assertJsonPath('data.timeRange', 'today');
        $response->assertJsonCount(2, 'data.pages');
        $response->assertJsonPath('data.pages.0.pageTitle', '/home');
        $response->assertJsonPath('data.pages.0.views', 120);
        $response->assertJsonPath('data.pages.1.pageTitle', '/about');
        $response->assertJsonPath('data.pages.1.views', 80);

        $this->assertSame('legacy-access', $this->captured->usedCredentials[0]->accessToken);
    }

    public function test_legacy_top_pages_returns_500_when_no_legacy_credentials(): void
    {
        $user = User::factory()->create([
            'google_access_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_at' => null,
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/google/analytics/viewsbypage/123')->assertStatus(500);
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

        $response = $this->getJson('/api/google/analytics/viewsbypage/123?connection_id='.$connection->id);

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

        $this->getJson('/api/google/analytics/viewsbypage/123?connection_id='.$connection->id)->assertStatus(404);
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

        $response = $this->getJson('/api/google/analytics/viewsbypage/123?connection_id='.$connection->id);

        $response->assertStatus(500);
        $this->assertCount(0, $this->captured->usedCredentials);
    }
}
