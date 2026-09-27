<?php

namespace App\Modules\Integrations\Infrastructure\Google;

use App\Modules\Integrations\Core\Contracts\IntegrationProvider;
use App\Modules\Integrations\Core\Entities\ActionDefinition;
use App\Modules\Integrations\Core\Entities\AuthDefinition;
use App\Modules\Integrations\Core\Entities\IntegrationDefinition;
use App\Modules\Integrations\Core\ValueObjects\IntegrationKey;
use App\Modules\Integrations\Core\ValueObjects\ProviderKey;
use App\Modules\Integrations\Core\ValueObjects\ScopeIdentifier;

final class GoogleCalendarIntegration implements IntegrationProvider
{
    public function definition(): IntegrationDefinition
    {
        return new IntegrationDefinition(
            key: IntegrationKey::fromString('google.calendar'),
            providerKey: ProviderKey::fromString('google'),
            name: 'Google Calendar',
            description: 'Manage events, calendars, and attendees through Google Calendar.',
            category: 'scheduling',
            auth: new AuthDefinition(
                type: 'oauth2',
                providerKey: ProviderKey::fromString('google'),
            ),
            actions: [
                new ActionDefinition(
                    key: 'create_event',
                    label: 'Create Event',
                    description: 'Create a new event in the primary calendar.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/calendar.events'),
                    ],
                ),
                new ActionDefinition(
                    key: 'update_event',
                    label: 'Update Event',
                    description: 'Update an existing event in the primary calendar.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/calendar.events'),
                    ],
                ),
                new ActionDefinition(
                    key: 'delete_event',
                    label: 'Delete Event',
                    description: 'Delete an event from the primary calendar.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/calendar.events'),
                    ],
                ),
                new ActionDefinition(
                    key: 'list_events',
                    label: 'List Events',
                    description: 'List upcoming events from the primary calendar.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/calendar.readonly'),
                    ],
                ),
                new ActionDefinition(
                    key: 'get_event',
                    label: 'Get Event',
                    description: 'Retrieve a specific event from the primary calendar.',
                    requiredScopes: [
                        ScopeIdentifier::fromString('https://www.googleapis.com/auth/calendar.readonly'),
                    ],
                ),
            ],
            triggers: [],
        );
    }
}
