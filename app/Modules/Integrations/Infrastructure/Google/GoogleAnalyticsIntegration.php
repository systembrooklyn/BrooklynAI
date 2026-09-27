<?php

namespace App\Modules\Integrations\Infrastructure\Google;

use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier;

final class GoogleAnalyticsIntegration implements IntegrationProvider
{
    public function definition(): IntegrationDefinition
    {
        $analyticsReadonly = ScopeIdentifier::fromString('https://www.googleapis.com/auth/analytics.readonly');

        return new IntegrationDefinition(
            key: IntegrationKey::fromString('google.analytics'),
            providerKey: ProviderKey::fromString('google'),
            name: 'Google Analytics',
            description: 'Access Google Analytics data and reports.',
            category: 'analytics',
            auth: new AuthDefinition(
                type: 'oauth2',
                providerKey: ProviderKey::fromString('google'),
            ),
            actions: [
                new ActionDefinition(
                    key: 'list_properties',
                    label: 'List Properties',
                    description: 'List GA4 properties accessible to the connected account.',
                    requiredScopes: [$analyticsReadonly],
                ),
                new ActionDefinition(
                    key: 'get_report',
                    label: 'Get Report',
                    description: 'Run a report for a GA4 property.',
                    requiredScopes: [$analyticsReadonly],
                ),
                new ActionDefinition(
                    key: 'get_realtime_overview',
                    label: 'Get Realtime Overview',
                    description: 'Fetch realtime metrics for a GA4 property.',
                    requiredScopes: [$analyticsReadonly],
                ),
                new ActionDefinition(
                    key: 'get_home_screen_metrics',
                    label: 'Get Home Screen Metrics',
                    description: 'Fetch summarized home-screen metrics for a GA4 property.',
                    requiredScopes: [$analyticsReadonly],
                ),
                new ActionDefinition(
                    key: 'get_top_pages_by_views',
                    label: 'Get Top Pages By Views',
                    description: 'Fetch top pages by page views for a GA4 property.',
                    requiredScopes: [$analyticsReadonly],
                ),
            ],
            triggers: [],
        );
    }
}
