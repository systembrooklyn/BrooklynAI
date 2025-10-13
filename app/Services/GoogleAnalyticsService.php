<?php

namespace App\Services;

use Google\Client as Google_Client;
use Google\Service\AnalyticsData;
use Google\Service\AnalyticsData\DateRange;
use Google\Service\AnalyticsData\Dimension;
use Google\Service\AnalyticsData\Metric;
use Google\Service\AnalyticsData\MetricOrderBy;
use Google\Service\AnalyticsData\OrderBy;
use Google\Service\AnalyticsData\RunRealtimeReportRequest;
use Google\Service\AnalyticsData\RunReportRequest;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use GuzzleHttp\Exception\RequestException;
use Google\Service\GoogleAnalyticsAdmin;


class GoogleAnalyticsService
{
    protected $client;
    protected $service;
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;

        if (!$user->google_access_token) {
            throw new \Exception('Missing Google access token for user.');
        }

        $this->client = new Google_Client();
        $this->client->setClientId(env('GOOGLE_CLIENT_ID'));
        $this->client->setClientSecret(env('GOOGLE_CLIENT_SECRET'));
        $this->client->setRedirectUri(env('GOOGLE_REDIRECT_URI'));
        $this->client->addScope('https://www.googleapis.com/auth/analytics.readonly');

        $expiresAt = strtotime($user->google_token_expires_at);
        $expiresIn = max(0, $expiresAt - time());

        $this->client->setAccessToken([
            'access_token' => $user->google_access_token,
            'refresh_token' => $user->google_refresh_token,
            'expires_in' => $expiresIn,
            'created' => time(),
        ]);

        // Refresh if expired
        if ($this->client->isAccessTokenExpired()) {
            if (!$user->google_refresh_token) {
                Log::error('No refresh token for Google Analytics', ['user_id' => $user->id]);
                throw new \Exception('Token expired and no refresh token. Re-login required.');
            }

            try {
                $newToken = $this->client->fetchAccessTokenWithRefreshToken($user->google_refresh_token);

                if (isset($newToken['access_token'])) {
                    $user->update([
                        'google_access_token' => $newToken['access_token'],
                        'google_token_expires_at' => now()->addSeconds($newToken['expires_in']),
                    ]);
                    $this->client->setAccessToken($newToken);
                } else {
                    Log::error('Invalid token response on refresh', ['user_id' => $user->id, 'response' => $newToken]);
                    throw new \Exception('Failed to refresh Google token.');
                }
            } catch (\Exception $e) {
                Log::error('Google Analytics token refresh failed: ' . $e->getMessage(), [
                    'user_id' => $user->id,
                    'trace' => $e->getTraceAsString(),
                ]);
                throw new \Exception('Could not authenticate with Google Analytics.', 0, $e);
            }
        }

        $this->service = new AnalyticsData($this->client);
    }

    public function getProperties()
    {
        try {
            $adminService = new GoogleAnalyticsAdmin($this->client);

            // Step 1: Get account + property summaries
            $response = $adminService->accountSummaries->listAccountSummaries();
            $properties = [];

            foreach ($response->getAccountSummaries() as $accountSummary) {
                foreach ($accountSummary->getPropertySummaries() as $propertySummary) {
                    $propertyResourceName = $propertySummary->getProperty(); // e.g., "properties/123456789"

                    // Step 2: Fetch full property details
                    try {
                        $fullProperty = $adminService->properties->get($propertyResourceName);

                        $properties[] = [
                            'property_id' => str_replace('properties/', '', $propertyResourceName),
                            'name' => $propertySummary->getDisplayName(),
                            'time_zone' => $fullProperty->getTimeZone() ?? 'UTC',
                            'currency_code' => $fullProperty->getCurrencyCode() ?? 'USD',
                            'account_id' => str_replace('accounts/', '', $accountSummary->getName()),
                            'account_name' => $accountSummary->getDisplayName(),
                        ];
                    } catch (\Exception $e) {
                        // Skip if no read access to full property
                        Log::warning('Could not fetch full property: ' . $propertyResourceName, [
                            'error' => $e->getMessage(),
                            'user_id' => $this->user->id,
                        ]);

                        // Fallback to summary-only data
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

            return collect($properties);
        } catch (\Exception $e) {
            Log::error('GA4 Admin API Error: ' . $e->getMessage(), [
                'user_id' => $this->user->id,
            ]);
            throw $e;
        }
    }

    public function getReport(
        string $propertyId,
        array $dimensions = ['date'],
        array $metrics = ['sessions'],
        string $startDate = '30daysAgo',
        string $endDate = 'today'
    ) {
        try {
            $request = new RunReportRequest([
                'dimensions' => array_map(fn($d) => new AnalyticsData\Dimension(['name' => $d]), $dimensions),
                'metrics' => array_map(fn($m) => new AnalyticsData\Metric(['name' => $m]), $metrics),
                'dateRanges' => [
                    new AnalyticsData\DateRange([
                        'startDate' => $startDate,
                        'endDate' => $endDate,
                    ])
                ],
                'limit' => 1000,
            ]);

            $response = $this->service->properties->runReport("properties/{$propertyId}", $request);

            $rows = [];
            foreach ($response->getRows() as $row) {
                $rows[] = [
                    'dimensions' => collect($row->getDimensionValues())->pluck('value')->all(),
                    'metrics' => collect($row->getMetricValues())->pluck('value')->all(),
                ];
            }
            usort($rows, function ($a, $b) {
                // Dates are in YYYYMMDD format (e.g., "20251011")
                return strcmp($a['dimensions'][0], $b['dimensions'][0]); // ascending
                // Use `return strcmp($b['dimensions'][0], $a['dimensions'][0]);` for descending
            });

            return [
                'dimensionHeaders' => collect($response->getDimensionHeaders())->pluck('name')->all(),
                'metricHeaders' => collect($response->getMetricHeaders())->pluck('name')->all(),
                'rows' => $rows,
            ];
        } catch (\Exception $e) {
            Log::error('GA4 report error: ' . $e->getMessage(), [
                'user_id' => $this->user->id,
                'property_id' => $propertyId,
            ]);
            throw $e;
        }
    }
    public function getRealtimeOverView(string $propertyId)
    {
        try {
            $realtimeService = new AnalyticsData($this->client);

            $request = new RunRealtimeReportRequest([
                'metrics' => [
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'eventCount']),
                    new Metric(['name' => 'screenPageViews']),
                    // new Metric(['name' => 'userEngagementDuration']),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'country']),
                    new Dimension(['name' => 'deviceCategory']),
                ],
            ]);

            $response = $realtimeService->properties->runRealtimeReport(
                "properties/{$propertyId}",
                $request
            );

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
            Log::error('Realtime GA4 API error: ' . $e->getMessage(), [
                'user_id' => $this->user->id,
                'property_id' => $propertyId,
            ]);
            throw $e;
        }
    }


    public function getHomeScreenMetrics(
        string $propertyId,
        string $startDate = '30daysAgo',
        string $endDate = 'today'
    ) {
        try {


            $dateRange = new DateRange([
                'startDate' => $startDate,
                'endDate' => $endDate,
            ]);


            // === 2. Get Event Count, Views, New Users (Today) ===

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

            $historicalResponse = $this->service->properties->runReport("properties/{$propertyId}", $historicalRequest);

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
            \Illuminate\Support\Facades\Log::error('GA4 Home Screen Metrics Error: ' . $e->getMessage(), [
                'user_id' => $this->user->id,
                'property_id' => $propertyId,
            ]);
            throw $e;
        }
    }
    public function getTopPagesByViews(string $propertyId, int $limit = 10)
    {
        try {
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
                    ])
                ],
                'orderBys' => [
                    new OrderBy([
                        'metric' => new MetricOrderBy([
                            'metricName' => 'screenPageViews'
                        ]),
                        'desc' => true,
                    ])
                ],
                'limit' => $limit,
            ]);

            $response = $this->service->properties->runReport("properties/{$propertyId}", $request);

            $rows = [];
            foreach ($response->getRows() as $row) {
                $pageTitle = $row->getDimensionValues()[0]->getValue();
                $views = (int) $row->getMetricValues()[0]->getValue();

                // Skip "(not set)" if needed
                if ($pageTitle === '(not set)') continue;

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
            Log::error('GA4 Top Pages Error: ' . $e->getMessage(), [
                'user_id' => $this->user->id,
                'property_id' => $propertyId,
            ]);
            throw $e;
        }
    }
}
