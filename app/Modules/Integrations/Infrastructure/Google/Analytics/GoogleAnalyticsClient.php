<?php

namespace App\Modules\Integrations\Infrastructure\Google\Analytics;

use App\Modules\Connections\Core\ValueObjects\ResolvedGoogleCredentials;
use Google\Client as GoogleClient;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\MetricOrderBy;
use Google\Service\AnalyticsData\OrderBy;
use Google\Service\AnalyticsData\RunRealtimeReportRequest;
use Google\Service\AnalyticsData\RunReportRequest;
use Google\Service\GoogleAnalyticsAdmin;
use Illuminate\Support\Facades\Log;

class GoogleAnalyticsClient
{
    /**
     * @return array<int, array{property_id: string, name: string|null, time_zone: string, currency_code: string, account_id: string, account_name: string|null}>
     */
    public function listProperties(ResolvedGoogleCredentials $credentials): array
    {
        try {
            $admin = $this->createAnalyticsAdmin($credentials);

            $response = $this->dispatchListAccountSummaries($admin);
            $properties = [];

            foreach ($response->getAccountSummaries() as $accountSummary) {
                foreach ($accountSummary->getPropertySummaries() as $propertySummary) {
                    $propertyResourceName = $propertySummary->getProperty();

                    try {
                        $fullProperty = $this->dispatchGetProperty($admin, $propertyResourceName);

                        $properties[] = [
                            'property_id' => str_replace('properties/', '', $propertyResourceName),
                            'name' => $propertySummary->getDisplayName(),
                            'time_zone' => $fullProperty->getTimeZone() ?? 'UTC',
                            'currency_code' => $fullProperty->getCurrencyCode() ?? 'USD',
                            'account_id' => str_replace('accounts/', '', $accountSummary->getName()),
                            'account_name' => $accountSummary->getDisplayName(),
                        ];
                    } catch (\Exception $e) {
                        Log::warning('Could not fetch full property: '.$propertyResourceName, [
                            'error' => $e->getMessage(),
                        ]);

                        $properties[] = [
                            'property_id' => str_replace('properties/', '', $propertyResourceName),
                            'name' => $propertySummary->getDisplayName(),
                            'time_zone' => 'Unknown',
                            'currency_code' => 'Unknown',
                            'account_id' => str_replace('accounts/', '', $accountSummary->getName()),
                            'account_name' => $accountSummary->getDisplayName(),
                        ];
                    }
                }
            }

            return $properties;
        } catch (\Exception $e) {
            Log::error('GA4 Admin API Error: '.$e->getMessage(), []);

            throw $e;
        }
    }

    /**
     * @param  array<int, string>  $dimensions
     * @param  array<int, string>  $metrics
     * @return array{dimensionHeaders: array<int, string|null>, metricHeaders: array<int, string|null>, rows: array<int, array{dimensions: array<int, mixed>, metrics: array<int, mixed>}>}
     */
    public function getReport(
        ResolvedGoogleCredentials $credentials,
        string $propertyId,
        array $dimensions,
        array $metrics,
        string $startDate,
        string $endDate,
    ): array {
        try {
            $data = $this->createAnalyticsData($credentials);

            $request = new RunReportRequest([
                'dimensions' => array_map(fn ($d) => new Dimension(['name' => $d]), $dimensions),
                'metrics' => array_map(fn ($m) => new Metric(['name' => $m]), $metrics),
                'dateRanges' => [
                    new DateRange([
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                    ]),
                ],
                'limit' => 1000,
            ]);

            $response = $this->dispatchRunReport($data, "properties/{$propertyId}", $request);

            $rows = [];
            foreach ($response->getRows() as $row) {
                $rows[] = [
                    'dimensions' => collect($row->getDimensionValues())->pluck('value')->all(),
                    'metrics' => collect($row->getMetricValues())->pluck('value')->all(),
                ];
            }

            usort($rows, function ($a, $b) {
                return strcmp($a['dimensions'][0], $b['dimensions'][0]);
            });

            return [
                'dimensionHeaders' => collect($response->getDimensionHeaders())->pluck('name')->all(),
                'metricHeaders' => collect($response->getMetricHeaders())->pluck('name')->all(),
                'rows' => $rows,
            ];
        } catch (\Exception $e) {
            Log::error('GA4 report error: '.$e->getMessage(), [
                'property_id' => $propertyId,
            ]);

            throw $e;
        }
    }

    /**
     * @return array{dimensionHeaders: array<int, string|null>, metricHeaders: array<int, string|null>, rows: array<int, array{dimensions: array<int, mixed>, metrics: array<int, mixed>}>}
     */
    public function getRealtimeOverview(ResolvedGoogleCredentials $credentials, string $propertyId): array
    {
        try {
            $data = $this->createAnalyticsData($credentials);

            $request = new RunRealtimeReportRequest([
                'metrics' => [
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'eventCount']),
                    new Metric(['name' => 'screenPageViews']),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'country']),
                    new Dimension(['name' => 'deviceCategory']),
                ],
            ]);

            $response = $this->dispatchRunRealtimeReport($data, "properties/{$propertyId}", $request);

            $rows = [];
            foreach ($response->getRows() ?? [] as $row) {
                $rows[] = [
                    'dimensions' => collect($row->getDimensionValues())->pluck('value')->all(),
                    'metrics' => collect($row->getMetricValues())->pluck('value')->all(),
                ];
            }

            return [
                'dimensionHeaders' => collect($response->getDimensionHeaders())->pluck('name')->all(),
                'metricHeaders' => collect($response->getMetricHeaders())->pluck('name')->all(),
                'rows' => $rows,
            ];
        } catch (\Exception $e) {
            Log::error('Realtime GA4 API error: '.$e->getMessage(), [
                'property_id' => $propertyId,
            ]);

            throw $e;
        }
    }

    /**
     * @return array{lastUpdated: string, metrics: array{activeUsers: int, eventCount: int, screenPageViews: int, newUsers: int}}
     */
    public function getHomeScreenMetrics(
        ResolvedGoogleCredentials $credentials,
        string $propertyId,
        string $startDate,
        string $endDate,
    ): array {
        try {
            $data = $this->createAnalyticsData($credentials);

            $dateRange = new DateRange([
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);

            $historicalRequest = new RunReportRequest([
                'metrics' => [
                    new Metric(['name' => 'eventCount']),
                    new Metric(['name' => 'screenPageViews']),
                    new Metric(['name' => 'newUsers']),
                    new Metric(['name' => 'activeUsers']),
                ],
                'dateRanges' => [$dateRange],
                'limit' => 1,
            ]);

            $historicalResponse = $this->dispatchRunReport($data, "properties/{$propertyId}", $historicalRequest);

            $eventCount = 0;
            $screenPageViews = 0;
            $newUsers = 0;
            $activeUsers = 0;

            if ($historicalResponse->getRows()) {
                $row = $historicalResponse->getRows()[0];
                $eventCount = (int) $row->getMetricValues()[0]->getValue();
                $screenPageViews = (int) $row->getMetricValues()[1]->getValue();
                $newUsers = (int) $row->getMetricValues()[2]->getValue();
                $activeUsers = (int) $row->getMetricValues()[3]->getValue();
            }

            return [
                'lastUpdated' => now()->toDateTimeString(),
                'metrics' => [
                    'activeUsers' => $activeUsers,
                    'eventCount' => $eventCount,
                    'screenPageViews' => $screenPageViews,
                    'newUsers' => $newUsers,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('GA4 Home Screen Metrics Error: '.$e->getMessage(), [
                'property_id' => $propertyId,
            ]);

            throw $e;
        }
    }

    /**
     * @return array{lastUpdated: string, timeRange: string, pages: array<int, array{pageTitle: string, views: int}>}
     */
    public function getTopPagesByViews(
        ResolvedGoogleCredentials $credentials,
        string $propertyId,
        int $limit = 10,
    ): array {
        try {
            $data = $this->createAnalyticsData($credentials);

            $request = new RunReportRequest([
                'dimensions' => [
                    new Dimension(['name' => 'unifiedScreenName']),
                ],
                'metrics' => [
                    new Metric(['name' => 'screenPageViews']),
                ],
                'dateRanges' => [
                    new DateRange([
                        'startDate' => 'today',
                        'endDate' => 'today',
                    ]),
                ],
                'orderBys' => [
                    new OrderBy([
                        'metric' => new MetricOrderBy([
                            'metricName' => 'screenPageViews',
                        ]),
                        'desc' => true,
                    ]),
                ],
                'limit' => $limit,
            ]);

            $response = $this->dispatchRunReport($data, "properties/{$propertyId}", $request);

            $rows = [];
            foreach ($response->getRows() as $row) {
                $pageTitle = $row->getDimensionValues()[0]->getValue();
                $views = (int) $row->getMetricValues()[0]->getValue();

                if ($pageTitle === '(not set)') {
                    continue;
                }

                $rows[] = [
                    'pageTitle' => $pageTitle,
                    'views' => $views,
                ];
            }

            return [
                'lastUpdated' => now()->toIso8601String(),
                'timeRange' => 'today',
                'pages' => $rows,
            ];
        } catch (\Exception $e) {
            Log::error('GA4 Top Pages Error: '.$e->getMessage(), [
                'property_id' => $propertyId,
            ]);

            throw $e;
        }
    }

    protected function createClient(ResolvedGoogleCredentials $credentials): GoogleClient
    {
        $client = new GoogleClient;
        $client->setClientId((string) env('GOOGLE_CLIENT_ID'));
        $client->setClientSecret((string) env('GOOGLE_CLIENT_SECRET'));
        $client->setRedirectUri((string) env('GOOGLE_REDIRECT_URI'));
        $client->addScope('https://www.googleapis.com/auth/analytics.readonly');

        $expiresAt = $credentials->expiresAt !== null ? $credentials->expiresAt->getTimestamp() : 0;
        $expiresIn = max(0, $expiresAt - time());

        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => $expiresIn,
            'created' => time(),
        ]);

        return $client;
    }

    protected function createAnalyticsData(ResolvedGoogleCredentials $credentials): AnalyticsData
    {
        return new AnalyticsData($this->createClient($credentials));
    }

    protected function createAnalyticsAdmin(ResolvedGoogleCredentials $credentials): GoogleAnalyticsAdmin
    {
        return new GoogleAnalyticsAdmin($this->createClient($credentials));
    }

    protected function dispatchListAccountSummaries(GoogleAnalyticsAdmin $admin): mixed
    {
        return $admin->accountSummaries->listAccountSummaries();
    }

    protected function dispatchGetProperty(GoogleAnalyticsAdmin $admin, string $resourceName): mixed
    {
        return $admin->properties->get($resourceName);
    }

    protected function dispatchRunReport(AnalyticsData $data, string $propertyResource, RunReportRequest $request): mixed
    {
        return $data->properties->runReport($propertyResource, $request);
    }

    protected function dispatchRunRealtimeReport(AnalyticsData $data, string $propertyResource, RunRealtimeReportRequest $request): mixed
    {
        return $data->properties->runRealtimeReport($propertyResource, $request);
    }
}
